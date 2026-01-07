<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(0); // Allow script to run for a long time

include_once __DIR__ . "/config/database.php";

// Parse command-line arguments
$options = getopt("", ["from-date:", "to-date:", "dry-run", "verbose"]);

$from_date = $options['from-date'] ?? null;
$to_date = $options['to-date'] ?? null;
$dry_run = isset($options['dry-run']);
$verbose = isset($options['verbose']);

// Validate parameters
if (!$from_date || !$to_date) {
    echo "Usage: php sync_crv.php --from-date=YYYY-MM-DD --to-date=YYYY-MM-DD [--dry-run] [--verbose]\n";
    echo "Example: php sync_crv.php --from-date=2025-11-01 --to-date=2025-11-24\n";
    exit(1);
}

// Validate date format
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $from_date) || !preg_match("/^\d{4}-\d{2}-\d{2}$/", $to_date)) {
    echo "Error: Dates must be in YYYY-MM-DD format.\n";
    exit(1);
}

// Convert dates to timestamps (start of day for from_date, end of day for to_date)
$from_timestamp = strtotime($from_date . " 00:00:00");
$to_timestamp = strtotime($to_date . " 23:59:59");

if ($from_timestamp === false || $to_timestamp === false) {
    echo "Error: Invalid date format.\n";
    exit(1);
}

if ($from_timestamp > $to_timestamp) {
    echo "Error: from-date must be before or equal to to-date.\n";
    exit(1);
}

// Connect to database
try {
    $databaseService = new DatabaseService();
    $pdo = $databaseService->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "=== CRV Sync Script ===\n";
echo "Date range: {$from_date} to {$to_date}\n";
echo "From timestamp: {$from_timestamp} (" . date('Y-m-d H:i:s', $from_timestamp) . ")\n";
echo "To timestamp: {$to_timestamp} (" . date('Y-m-d H:i:s', $to_timestamp) . ")\n";
if ($dry_run) {
    echo "Mode: DRY RUN (no updates will be made)\n";
}
echo "\n";

// Query orderItems that need crv update
// We'll look for records where crv is NULL or 0, and have a valid itemUuid
// Join with accounts to get vendor info
$sql = "SELECT oi.id, oi.itemUuid, oi.crv, oi.crv_taxable, oi.description, oi.itemsAddedDateTime,
               oi.vendors_id, a.companyname
        FROM orderItems oi
        LEFT JOIN accounts a ON oi.vendors_id = a.id
        WHERE oi.itemsAddedDateTime >= ? 
        AND oi.itemsAddedDateTime <= ?
        AND oi.itemUuid IS NOT NULL
        AND (oi.crv IS NULL OR oi.crv = 0)
        ORDER BY oi.vendors_id, oi.id";

$stmt = $pdo->prepare($sql);
$stmt->execute([$from_timestamp, $to_timestamp]);
$orderItems = $stmt->fetchAll();

$total_count = count($orderItems);
echo "Found {$total_count} orderItems records that need CRV update.\n\n";

if ($total_count == 0) {
    echo "No records to update. Exiting.\n";
    exit(0);
}

// Prepare statement to get crv from items table
$items_stmt = $pdo->prepare("SELECT crv FROM items WHERE uuid = ? LIMIT 1");

// Prepare statement to update orderItems
$update_stmt = $pdo->prepare("UPDATE orderItems SET crv = ?, crv_taxable = ? WHERE id = ?");

$updated_count = 0;
$not_found_count = 0;
$error_count = 0;
$skipped_count = 0;

// Store update details for analytics
$update_details = [];
$vendor_stats = [];

// Process each orderItem
foreach ($orderItems as $orderItem) {
    $orderItemId = $orderItem['id'];
    $itemUuid = $orderItem['itemUuid'];
    $description = $orderItem['description'];
    $vendors_id = $orderItem['vendors_id'] ?? 'N/A';
    $companyname = $orderItem['companyname'] ?? 'N/A';
    
    echo "Processing orderItem ID: {$orderItemId} | Item: {$description} | Vendor ID: {$vendors_id} | Company: {$companyname}\n";
    
    // Query items table for crv
    try {
        $items_stmt->execute([$itemUuid]);
        $itemRow = $items_stmt->fetch();
        
        if (!$itemRow || !isset($itemRow['crv']) || $itemRow['crv'] === null) {
            $not_found_count++;
            echo "  ❌ Item not found or crv is NULL in items table (itemUuid: {$itemUuid})\n";
            continue;
        }
        
        // Parse crv from items table
        $itemsCrv = $itemRow['crv'];
        $crv = 0;
        $crv_taxable = 0;
        
        // Handle different formats of crv
        if (is_string($itemsCrv)) {
            $itemsCrvDecoded = json_decode($itemsCrv, true);
            if (is_array($itemsCrvDecoded)) {
                // Format: {"val": "0.05", "is_taxable": false}
                if (isset($itemsCrvDecoded["val"])) {
                    $crv = floatval($itemsCrvDecoded["val"]);
                    if (isset($itemsCrvDecoded["is_taxable"])) {
                        $crv_taxable = $itemsCrvDecoded["is_taxable"] ? 1 : 0;
                    }
                }
            } elseif (is_numeric($itemsCrvDecoded)) {
                $crv = floatval($itemsCrvDecoded);
            }
        } elseif (is_numeric($itemsCrv)) {
            $crv = floatval($itemsCrv);
        } elseif (is_array($itemsCrv)) {
            // PDO might return JSON as array directly
            if (isset($itemsCrv["val"])) {
                $crv = floatval($itemsCrv["val"]);
                if (isset($itemsCrv["is_taxable"])) {
                    $crv_taxable = $itemsCrv["is_taxable"] ? 1 : 0;
                }
            }
        }
        
        // Skip if crv is still 0 after parsing
        if ($crv == 0) {
            $skipped_count++;
            echo "  ⏭️  CRV value is 0, skipping update\n";
            continue;
        }
        
        // Update orderItems
        if (!$dry_run) {
            try {
                $update_stmt->execute([$crv, $crv_taxable, $orderItemId]);
                $updated_count++;
                echo "  ✅ UPDATED: crv={$crv}, crv_taxable={$crv_taxable}\n";
                
                // Store update details for analytics
                $update_details[] = [
                    'orderItemId' => $orderItemId,
                    'itemName' => $description,
                    'vendors_id' => $vendors_id,
                    'companyname' => $companyname,
                    'crv' => $crv,
                    'crv_taxable' => $crv_taxable,
                    'itemUuid' => $itemUuid
                ];
                
                // Update vendor stats
                if (!isset($vendor_stats[$vendors_id])) {
                    $vendor_stats[$vendors_id] = [
                        'companyname' => $companyname,
                        'count' => 0,
                        'items' => []
                    ];
                }
                $vendor_stats[$vendors_id]['count']++;
                $vendor_stats[$vendors_id]['items'][] = $description;
                
            } catch (PDOException $e) {
                $error_count++;
                echo "  ❌ ERROR updating orderItem ID {$orderItemId}: " . $e->getMessage() . "\n";
            }
        } else {
            $updated_count++;
            echo "  🔍 WOULD UPDATE: crv={$crv}, crv_taxable={$crv_taxable}\n";
            
            // Store update details for analytics (dry run)
            $update_details[] = [
                'orderItemId' => $orderItemId,
                'itemName' => $description,
                'vendors_id' => $vendors_id,
                'companyname' => $companyname,
                'crv' => $crv,
                'crv_taxable' => $crv_taxable,
                'itemUuid' => $itemUuid
            ];
            
            // Update vendor stats
            if (!isset($vendor_stats[$vendors_id])) {
                $vendor_stats[$vendors_id] = [
                    'companyname' => $companyname,
                    'count' => 0,
                    'items' => []
                ];
            }
            $vendor_stats[$vendors_id]['count']++;
            $vendor_stats[$vendors_id]['items'][] = $description;
        }
        
    } catch (PDOException $e) {
        $error_count++;
        echo "  ❌ ERROR querying items table for itemUuid {$itemUuid}: " . $e->getMessage() . "\n";
    }
    
    echo "\n"; // Add spacing between items
}

echo "\n" . str_repeat("=", 80) . "\n";
echo "=== SUMMARY ===\n";
echo str_repeat("=", 80) . "\n";
echo "Total records processed: {$total_count}\n";
echo "Successfully updated: {$updated_count}\n";
echo "Items not found: {$not_found_count}\n";
echo "Skipped (crv = 0): {$skipped_count}\n";
echo "Errors: {$error_count}\n";

if ($dry_run) {
    echo "\n⚠️  DRY RUN mode - no actual updates were made.\n";
    echo "Run without --dry-run to apply updates.\n";
} else {
    echo "\n✅ Updates completed successfully.\n";
}

// Analytics by Vendor
if (!empty($vendor_stats)) {
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "=== ANALYTICS BY VENDOR ===\n";
    echo str_repeat("=", 80) . "\n";
    
    // Sort by count (descending)
    uasort($vendor_stats, function($a, $b) {
        return $b['count'] - $a['count'];
    });
    
    foreach ($vendor_stats as $vendors_id => $stats) {
        $companyname = $stats['companyname'] ?: 'N/A';
        $count = $stats['count'];
        $unique_items = array_unique($stats['items']);
        $unique_count = count($unique_items);
        
        echo "\nVendor ID: {$vendors_id} | Company: {$companyname}\n";
        echo "  Total items updated: {$count}\n";
        echo "  Unique items: {$unique_count}\n";
        
        if ($verbose) {
            echo "  Items updated:\n";
            $item_counts = array_count_values($stats['items']);
            foreach ($item_counts as $item => $item_count) {
                echo "    - {$item} (x{$item_count})\n";
            }
        } else {
            // Show top 5 items
            $item_counts = array_count_values($stats['items']);
            arsort($item_counts);
            $top_items = array_slice($item_counts, 0, 5, true);
            if (!empty($top_items)) {
                echo "  Top items:\n";
                foreach ($top_items as $item => $item_count) {
                    echo "    - {$item} (x{$item_count})\n";
                }
                if (count($item_counts) > 5) {
                    echo "    ... and " . (count($item_counts) - 5) . " more\n";
                }
            }
        }
    }
}

// Detailed update log
if (!empty($update_details) && $verbose) {
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "=== DETAILED UPDATE LOG ===\n";
    echo str_repeat("=", 80) . "\n";
    
    foreach ($update_details as $detail) {
        echo sprintf(
            "ID: %-8s | Vendor: %-6s | Company: %-30s | Item: %-40s | CRV: %-6s | Taxable: %s\n",
            $detail['orderItemId'],
            $detail['vendors_id'],
            substr($detail['companyname'], 0, 30),
            substr($detail['itemName'], 0, 40),
            $detail['crv'],
            $detail['crv_taxable']
        );
    }
}

echo "\n" . str_repeat("=", 80) . "\n";

exit(0);

