<?php
# v4 - optimized for unique_olapay_transactions table with deduplication
# Key features:
# - Groups all transactions by trans_id (Sale + related TipAdjustment/Void/Refund)
# - Deduplicates: keeps only the LATEST of each trans_type per trans_id
# - Orders: Sale first, then related transactions by lastmod
# - No duplicate transactions (especially tip, refund, void)
error_reporting(E_ALL);
ini_set('display_errors', 1);
include_once "./library/utils.php";
require_once __DIR__ . '/vendor/autoload.php';
enable_cors();

use Spatie\Async\Pool;

$tomorrow = strtotime('tomorrow');
switch ($_REQUEST["datetype"]) {
  case "Last 30 Days":
    $starttime = $tomorrow - 86400*30;
    $endtime = strtotime('now');
    break;
  case "Last 24 Hours":
    $tomorrow = strtotime('next hour');
    $starttime = $tomorrow - 86400;
    $endtime = strtotime('now');
    break;
  case "Last 52 Weeks":
    $starttime = $tomorrow - 86400*52*7;
    $endtime = strtotime('now');
    break;
  case "Custom":
    $starttime = strtotime($_REQUEST["fromDate"]);
    $endtime = strtotime($_REQUEST["toDate"]) + 86400;
    break;
}

$msgfornoterminal = "No permission";
$msgforsqlerror = "Unable to get data";
$pdo = connect_db_and_set_http_method("GET");

$type = $_REQUEST["type"];
$id = isset($_REQUEST["id"]) ? $_REQUEST["id"] : null;

// 1. Fetch all terminals matching the criteria
if ($type == "merchant") {
    $terminalsStmt = $pdo->prepare(
        "SELECT serial, description FROM terminals WHERE vendors_id = ? AND onlinestorename = ''"
    );
    $terminalsStmt->execute([$id]);
} else {
    $terminalsStmt = $pdo->prepare(
        "SELECT t.serial, t.description FROM terminals t INNER JOIN accounts a ON t.vendors_id = a.id WHERE a.id != 172 AND a.id != 183 AND t.onlinestorename = ''"
    );
    $terminalsStmt->execute();
}
$terminals = $terminalsStmt->fetchAll(PDO::FETCH_ASSOC);

// 2. For each terminal, fetch its transactions in the date range and linked transactions
$pool = Pool::create()->concurrency(4);
foreach ($terminals as $terminal) {
    $pool[] = async(function () use ($terminal, $starttime, $endtime) {
        include_once __DIR__ . "/library/utils.php";
        $pdo = connect_db_and_set_http_method("GET");
        
        // Step 1: Get transactions in the date range with trans_type for deduplication
        $stmt = $pdo->prepare("
SELECT u.id, u.lastmod, u.content, u.trans_id, u.trans_type,
       JSON_UNQUOTE(JSON_EXTRACT(u.content, '$.original_trans_id')) as original_trans_id,
       JSON_UNQUOTE(JSON_EXTRACT(u.content, '$.requested_amount')) as requested_amount
FROM unique_olapay_transactions u
WHERE u.serial = ?
  AND u.lastmod > ?
  AND u.lastmod < ?
  AND u.status NOT IN ('', 'FAIL', 'REFUNDED')
  AND u.trans_type NOT IN ('Return Cash', '', 'Auth')
  -- Condition 1: Match by trans_id with serial check
  AND NOT EXISTS (
    SELECT 1
    FROM ordersPayments op
    INNER JOIN terminals t ON t.id = op.terminals_id
    WHERE t.serial = u.serial
      AND op.olapayApprovalId = u.trans_id
      AND op.olapayApprovalId IS NOT NULL
      AND op.olapayApprovalId != ''
  )
  -- Condition 2: Match by order_id prefix with date range
  AND NOT EXISTS (
    SELECT 1
    FROM ordersPayments op
    WHERE op.olapayApprovalId = SUBSTRING_INDEX(u.order_id, '-', 1)
      AND op.lastMod > ?
      AND op.lastMod < ?
  )
ORDER BY u.lastmod DESC
        ");
        $stmt->execute([$terminal['serial'], $starttime, $endtime, $starttime, $endtime]);
        $initialRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($initialRecords)) {
            return [
                'serial' => $terminal['serial'],
                'description' => $terminal['description'],
                'records' => []
            ];
        }
        
        // Step 2: Collect all trans_ids and original_trans_ids from initial results
        $transIds = [];
        $originalTransIds = [];
        $existingIds = []; // Track IDs already in results to avoid duplicates
        
        foreach ($initialRecords as $record) {
            $existingIds[$record['id']] = true;
            if (!empty($record['trans_id'])) {
                $transIds[] = $record['trans_id'];
            }
            if (!empty($record['original_trans_id']) && $record['original_trans_id'] !== 'null') {
                $originalTransIds[] = $record['original_trans_id'];
            }
        }
        
        // Step 3: Find linked transactions outside the date range
        // Split into two optimized queries:
        // Query A: Refunds that happened AFTER our date range (lastmod >= endtime) referencing our sales
        // Query B: Original sales that happened BEFORE our date range (lastmod < starttime) referenced by our refunds
        $linkedRecords = [];
        
        // Query A: Find ALL related transactions with same trans_id (including outside date range)
        // This ensures we get the complete picture for deduplication
        if (!empty($transIds)) {
            $uniqueTransIds = array_unique($transIds);
            $placeholders = implode(',', array_fill(0, count($uniqueTransIds), '?'));
            
            $relatedStmt = $pdo->prepare("
SELECT u.id, u.lastmod, u.content, u.trans_id, u.trans_type,
       JSON_UNQUOTE(JSON_EXTRACT(u.content, '$.original_trans_id')) as original_trans_id,
       JSON_UNQUOTE(JSON_EXTRACT(u.content, '$.requested_amount')) as requested_amount
FROM unique_olapay_transactions u
WHERE u.serial = ?
  AND u.status NOT IN ('', 'FAIL', 'REFUNDED')
  AND u.trans_type NOT IN ('Return Cash', '', 'Auth')
  -- Find all transactions with the same trans_id (Sale, TipAdjustment, Void, Return share trans_id)
  AND u.trans_id IN ($placeholders)
  -- Condition: Exclude if already matched in ordersPayments
  AND NOT EXISTS (
    SELECT 1
    FROM ordersPayments op
    INNER JOIN terminals t ON t.id = op.terminals_id
    WHERE t.serial = u.serial
      AND op.olapayApprovalId = u.trans_id
      AND op.olapayApprovalId IS NOT NULL
      AND op.olapayApprovalId != ''
  )
ORDER BY u.lastmod ASC
            ");
            
            $params = array_merge([$terminal['serial']], $uniqueTransIds);
            $relatedStmt->execute($params);
            $linkedRecords = array_merge($linkedRecords, $relatedStmt->fetchAll(PDO::FETCH_ASSOC));
        }
        
        // Query B: Find original sales BEFORE starttime that our refunds reference
        // Original sales always happen before their refunds, so we only look backward in time
        if (!empty($originalTransIds)) {
            $uniqueOriginalTransIds = array_unique($originalTransIds);
            $placeholders = implode(',', array_fill(0, count($uniqueOriginalTransIds), '?'));
            
            $originalsStmt = $pdo->prepare("
SELECT u.id, u.lastmod, u.content, u.trans_id, u.trans_type,
       JSON_UNQUOTE(JSON_EXTRACT(u.content, '$.original_trans_id')) as original_trans_id,
       JSON_UNQUOTE(JSON_EXTRACT(u.content, '$.requested_amount')) as requested_amount
FROM unique_olapay_transactions u
WHERE u.serial = ?
  AND u.lastmod < ?
  AND u.status NOT IN ('', 'FAIL', 'REFUNDED')
  AND u.trans_type NOT IN ('Return Cash', '', 'Auth')
  -- Find original sales by their trans_id (uses indexed column)
  AND u.trans_id IN ($placeholders)
  -- Condition: Exclude if already matched in ordersPayments
  AND NOT EXISTS (
    SELECT 1
    FROM ordersPayments op
    INNER JOIN terminals t ON t.id = op.terminals_id
    WHERE t.serial = u.serial
      AND op.olapayApprovalId = u.trans_id
      AND op.olapayApprovalId IS NOT NULL
      AND op.olapayApprovalId != ''
  )
ORDER BY u.lastmod DESC
            ");
            
            $params = array_merge([$terminal['serial'], $starttime], $uniqueOriginalTransIds);
            $originalsStmt->execute($params);
            $linkedRecords = array_merge($linkedRecords, $originalsStmt->fetchAll(PDO::FETCH_ASSOC));
        }
        
        // Step 4: Merge records, avoiding duplicates by id
        $allRecords = $initialRecords;
        foreach ($linkedRecords as $linkedRecord) {
            if (!isset($existingIds[$linkedRecord['id']])) {
                $existingIds[$linkedRecord['id']] = true;
                $allRecords[] = $linkedRecord;
            }
        }
        
        // Step 5: Group by trans_id and DEDUPLICATE by trans_type
        // For each trans_id, keep only the LATEST of each trans_type
        // This eliminates duplicate TipAdjustments, Voids, Returns, etc.
        $transIdGroups = [];
        
        foreach ($allRecords as $record) {
            $transId = $record['trans_id'];
            $transType = $record['trans_type'] ?? 'Unknown';
            $lastmod = (int)$record['lastmod'];
            
            if (!isset($transIdGroups[$transId])) {
                $transIdGroups[$transId] = [];
            }
            
            // For each trans_type within a trans_id, keep only the latest by lastmod
            if (!isset($transIdGroups[$transId][$transType])) {
                $transIdGroups[$transId][$transType] = $record;
            } else {
                // Keep the one with higher lastmod (more recent)
                $existingLastmod = (int)$transIdGroups[$transId][$transType]['lastmod'];
                if ($lastmod > $existingLastmod) {
                    $transIdGroups[$transId][$transType] = $record;
                }
            }
        }
        
        // Step 6: Build final output - group related transactions together
        // Order: Sale first, then other trans_types sorted by lastmod
        $groupedRecords = [];
        $processedTransIds = [];
        
        // Sort trans_ids by the earliest transaction (usually the Sale)
        $transIdOrder = [];
        foreach ($transIdGroups as $transId => $typeRecords) {
            $minLastmod = PHP_INT_MAX;
            foreach ($typeRecords as $record) {
                $minLastmod = min($minLastmod, (int)$record['lastmod']);
            }
            $transIdOrder[$transId] = $minLastmod;
        }
        arsort($transIdOrder); // Most recent first
        
        foreach ($transIdOrder as $transId => $minLastmod) {
            if (isset($processedTransIds[$transId])) {
                continue;
            }
            $processedTransIds[$transId] = true;
            
            $typeRecords = $transIdGroups[$transId];
            
            // Separate Sale from other types
            $saleRecord = null;
            $otherRecords = [];
            
            foreach ($typeRecords as $transType => $record) {
                if ($transType === 'Sale') {
                    $saleRecord = $record;
                } else {
                    $otherRecords[] = $record;
                }
            }
            
            // Sort other records by lastmod ascending (chronological order)
            usort($otherRecords, function($a, $b) {
                return (int)$a['lastmod'] - (int)$b['lastmod'];
            });
            
            // Add Sale first if exists
            if ($saleRecord) {
                $groupedRecords[] = [
                    'lastmod' => $saleRecord['lastmod'],
                    'content' => $saleRecord['content']
                ];
            }
            
            // Add other related transactions (TipAdjustment, Void, Return, etc.)
            foreach ($otherRecords as $record) {
                $groupedRecords[] = [
                    'lastmod' => $record['lastmod'],
                    'content' => $record['content']
                ];
            }
        }
        
        return [
            'serial' => $terminal['serial'],
            'description' => $terminal['description'],
            'records' => $groupedRecords
        ];
    });
}
$results = $pool->wait();

send_http_status_and_exit("200", json_encode($results));
?> 