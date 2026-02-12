<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include_once "./library/utils.php";
enable_cors();
require_once __DIR__ . '/vendor/autoload.php';
use Spatie\Async\Pool;

$LOG_PREFIX = '[ORDERS2-API] ';

error_log(sprintf("%sScript started - Type: %s, DateType: %s", $LOG_PREFIX, $_REQUEST["type"], $_REQUEST["datetype"]));

###
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

error_log(sprintf("%sDate range - Start: %d, End: %d", $LOG_PREFIX, $starttime, $endtime));

$msgfornoterminal = "No permission";
$msgforsqlerror = "Unable to get data";
$pdo = connect_db_and_set_http_method("GET");

$where = "";
$params = [];
if ($_REQUEST["type"] == "agent") { 
    $where = 'and o.agents_id = ?'; 
    $params[] = $_REQUEST["id"];
}
if ($_REQUEST["type"] == "merchant") { 
    $where = 'and o.vendors_id = ?'; 
    $params[] = $_REQUEST["id"];
}
if ($_REQUEST["type"] == "terminal") { 
    $where = 'and o.terminals_id = ?'; 
    $params[] = $_REQUEST["id"];
}

// Step 1: Fetch all orders with deduplication by UUID (keep latest)
// Optimized: Fetch orders first, then terminals in a separate query to avoid JOIN overhead
$ordersQuery = "
SELECT 
    o.id,
    o.lastMod,
    o.orderReference,
    o.subTotal,
    o.tax,
    o.terminals_id,
    o.delivery_type,
    o.onlineorder_id,
    o.onlinetrans_id,
    o.uuid,
    o.store_uuid,
    o.secondary_tax_list
FROM orders o
WHERE o.lastMod > ? AND o.lastMod < ? $where
ORDER BY o.uuid, o.lastMod DESC
";

error_log(sprintf("%sExecuting orders query - Type: %s, Where: %s", $LOG_PREFIX, $_REQUEST["type"], $where));

try {
    $queryStartTime = microtime(true);
    $stmt = $pdo->prepare($ordersQuery);
    $stmt->execute(array_merge([$starttime, $endtime], $params));
    
    // Deduplicate orders by UUID in PHP (keep the latest one)
    $orders = [];
    $orderIds = [];
    $orderUuids = [];
    $terminalIds = [];
    $seenUuids = [];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $uuid = $row["uuid"];
        // If UUID exists, skip older orders (since we sorted DESC)
        if (!empty($uuid)) {
            if (isset($seenUuids[$uuid])) {
                continue;
            }
            $seenUuids[$uuid] = true;
        }
        
        $orders[$row["id"]] = $row;
        $orderIds[] = $row["id"];
        if (!empty($row["uuid"])) {
            $orderUuids[] = $row["uuid"];
        }
        if (!empty($row["terminals_id"])) {
            $terminalIds[$row["terminals_id"]] = true;
        }
    }
    
    $queryTime = microtime(true) - $queryStartTime;
    $orderCount = count($orders);
    error_log(sprintf("%sFound %d unique orders in %.2f seconds", $LOG_PREFIX, $orderCount, $queryTime));
    
    if ($orderCount == 0) {
        send_http_status_and_exit("200", json_encode([]));
    }
    
    // Fetch terminal descriptions in a single query (more efficient than JOIN)
    $terminalDescriptions = [];
    if (!empty($terminalIds)) {
        $terminalIdsList = array_keys($terminalIds);
        $terminalPlaceholders = implode(',', array_fill(0, count($terminalIdsList), '?'));
        $terminalQuery = "SELECT id, description FROM terminals WHERE id IN ($terminalPlaceholders)";
        $terminalStmt = $pdo->prepare($terminalQuery);
        $terminalStmt->execute($terminalIdsList);
        while ($termRow = $terminalStmt->fetch(PDO::FETCH_ASSOC)) {
            $terminalDescriptions[$termRow["id"]] = $termRow["description"];
        }
    }
    
    // Helper function to batch large IN clauses (MySQL has limits)
    $batchSize = 1000; // MySQL typically handles up to 1000-2000 items in IN clause efficiently
    
    // Step 2, 3 & 4: Fetch discounts, payments, and techFeeRate in parallel using async
    $pool = Pool::create()->concurrency(3);
    
    // Prepare discount query function
    $discountsTask = async(function () use ($orderIds, $batchSize) {
        include_once __DIR__ . "/library/utils.php";
        $pdo = connect_db_and_set_http_method("GET");
        $discounts = [];
        
        // Batch the orderIds if there are too many
        $batches = array_chunk($orderIds, $batchSize);
        foreach ($batches as $batch) {
            $placeholders = implode(',', array_fill(0, count($batch), '?'));
            $discountsQuery = "
                SELECT 
                    orders_id,
                    SUM(discount * qty) as total_discount
                FROM orderItems
                WHERE orders_id IN ($placeholders)
                GROUP BY orders_id
            ";
            $stmt = $pdo->prepare($discountsQuery);
            $stmt->execute($batch);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $discounts[$row["orders_id"]] = (float)$row["total_discount"];
            }
        }
        return $discounts;
    });
    
    // Prepare payments query function
    // Build a mapping from order id to uuid for fallback query
    $orderIdToUuid = [];
    foreach ($orders as $order) {
        if (!empty($order["uuid"])) {
            $orderIdToUuid[$order["id"]] = $order["uuid"];
        }
    }
    
    $paymentsTask = async(function () use ($orderIds, $orderIdToUuid, $batchSize) {
        include_once __DIR__ . "/library/utils.php";
        $pdo = connect_db_and_set_http_method("GET");
        $paymentsByOrder = [];
        
        // Batch the orderIds if there are too many
        $batches = array_chunk($orderIds, $batchSize);
        foreach ($batches as $batch) {
            $placeholders = implode(',', array_fill(0, count($batch), '?'));
            $paymentsQuery = "
                SELECT 
                    orderReference,
                    orderUuid,
                    refNumber,
                    total,
                    refund,
                    techFee,
                    orderId,
                    tips,
                    olapayApprovalId,
                    lastMod as paymentLastMod,
                    paymentUuid,
                    amtPaid,
                    payment_type_code,
                    payment_type_name
                FROM ordersPayments
                WHERE orderReference IN ($placeholders)
                ORDER BY orderReference, paymentUuid, paymentLastMod DESC
            ";
            $stmt = $pdo->prepare($paymentsQuery);
            $stmt->execute($batch);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $orderRef = $row["orderReference"];
                if (!isset($paymentsByOrder[$orderRef])) {
                    $paymentsByOrder[$orderRef] = [];
                }
                $paymentsByOrder[$orderRef][] = $row;
            }
        }
        
        // Fallback: For orders without payments, try querying by orderUuid
        $missingOrderUuids = [];
        $uuidToOrderId = []; // Reverse mapping to assign payments back to order id
        foreach ($orderIds as $orderId) {
            if (!isset($paymentsByOrder[$orderId]) && isset($orderIdToUuid[$orderId])) {
                $uuid = $orderIdToUuid[$orderId];
                $missingOrderUuids[] = $uuid;
                $uuidToOrderId[$uuid] = $orderId;
            }
        }
        
        if (!empty($missingOrderUuids)) {
            $uuidBatches = array_chunk($missingOrderUuids, $batchSize);
            foreach ($uuidBatches as $uuidBatch) {
                $placeholders = implode(',', array_fill(0, count($uuidBatch), '?'));
                $fallbackQuery = "
                    SELECT 
                        orderReference,
                        orderUuid,
                        refNumber,
                        total,
                        refund,
                        techFee,
                        orderId,
                        tips,
                        olapayApprovalId,
                        lastMod as paymentLastMod,
                        paymentUuid,
                        amtPaid,
                        payment_type_code,
                        payment_type_name
                    FROM ordersPayments
                    WHERE orderUuid IN ($placeholders)
                    ORDER BY orderUuid, paymentUuid, paymentLastMod DESC
                ";
                $stmt = $pdo->prepare($fallbackQuery);
                $stmt->execute($uuidBatch);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $orderUuid = $row["orderUuid"];
                    // Map back to the original order id
                    if (isset($uuidToOrderId[$orderUuid])) {
                        $orderId = $uuidToOrderId[$orderUuid];
                        if (!isset($paymentsByOrder[$orderId])) {
                            $paymentsByOrder[$orderId] = [];
                        }
                        $paymentsByOrder[$orderId][] = $row;
                    }
                }
            }
        }
        
        return $paymentsByOrder;
    });
    
    // Prepare techFeeRate query function
    $techFeeRateTask = async(function () use ($orderUuids, $batchSize) {
        include_once __DIR__ . "/library/utils.php";
        $pdo = connect_db_and_set_http_method("GET");
        $techFeeRates = [];
        
        if (empty($orderUuids)) {
            return $techFeeRates;
        }
        
        // Batch the orderUuids if there are too many
        $batches = array_chunk($orderUuids, $batchSize);
        foreach ($batches as $batch) {
            $placeholders = implode(',', array_fill(0, count($batch), '?'));
            $techFeeRateQuery = "
                SELECT 
                    orderUuid,
                    tech_fee_rate
                FROM orderItems
                WHERE orderUuid IN ($placeholders)
                    AND tech_fee_rate > 0
                ORDER BY orderUuid, id ASC
            ";
            $stmt = $pdo->prepare($techFeeRateQuery);
            $stmt->execute($batch);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $orderUuid = $row["orderUuid"];
                // Get the first tech_fee_rate > 0 for each order
                if (!isset($techFeeRates[$orderUuid])) {
                    $techFeeRates[$orderUuid] = (float)$row["tech_fee_rate"];
                }
            }
        }
        return $techFeeRates;
    });
    
    // Execute all three queries in parallel
    $pool[] = $discountsTask;
    $pool[] = $paymentsTask;
    $pool[] = $techFeeRateTask;
    
    $startTime = microtime(true);
    $results = $pool->wait();
    $parallelTime = microtime(true) - $startTime;
    error_log(sprintf("%sParallel queries completed in %.2f seconds", $LOG_PREFIX, $parallelTime));
    
    $discounts = $results[0];
    $paymentsByOrderRaw = $results[1];
    $techFeeRates = $results[2];
    
    // Process payments: deduplicate by paymentUuid and convert to final format
    $paymentsByOrder = [];
    $paymentCounts = [];
    
    foreach ($paymentsByOrderRaw as $orderRef => $paymentRows) {
        $paymentsByOrder[$orderRef] = [];
        $paymentMap = []; // For UUID-based deduplication
        
        foreach ($paymentRows as $row) {
            $paymentUuid = $row["paymentUuid"];
            
            $paymentData = [
                "refNumber" => $row["refNumber"],
                "total" => $row["total"],
                "refund" => $row["refund"],
                "techFee" => $row["techFee"],
                "orderId" => $row["orderId"],
                "tips" => (float)$row["tips"],
                "olapayApprovalId" => $row["olapayApprovalId"],
                "paymentLastMod" => $row["paymentLastMod"],
                "paymentUuid" => $row["paymentUuid"],
                "amtPaid" => $row["amtPaid"],
                "payment_type_code" => $row["payment_type_code"] ?? null,
                "payment_type_name" => $row["payment_type_name"] ?? null
            ];
            
            // Deduplicate by paymentUuid - keep the one with greatest paymentLastMod
            if (!empty($paymentUuid)) {
                if (!isset($paymentMap[$paymentUuid]) || 
                    $row["paymentLastMod"] > $paymentMap[$paymentUuid]["paymentLastMod"]) {
                    $paymentMap[$paymentUuid] = $paymentData;
                }
            } else {
                // If no paymentUuid, add directly (for backward compatibility)
                $paymentsByOrder[$orderRef][] = $paymentData;
            }
        }
        
        // Add deduplicated payments from map
        foreach ($paymentMap as $payment) {
            $paymentsByOrder[$orderRef][] = $payment;
        }
        
        $paymentCounts[$orderRef] = count($paymentsByOrder[$orderRef]);
    }
    
    // Step 4: Build final result array
    $allResults = [];
    
    // Sort orders by lastMod DESC
    uasort($orders, function($a, $b) {
        return $b["lastMod"] - $a["lastMod"];
    });
    
    foreach ($orders as $order) {
        $entry = array();
        $entry["orderTime"] = (int)$order["lastMod"];
        $entry["orderId"] = $order["orderReference"];
        $entry["tax"] = $order["tax"];
        $entry["discount"] = isset($discounts[$order["id"]]) ? $discounts[$order["id"]] : 0;
        $entry["id"] = $order["id"];
        $entry["subtotal"] = $order["subTotal"];
        $entry["cnt"] = isset($paymentCounts[$order["id"]]) ? $paymentCounts[$order["id"]] : 0;
        $entry["terminals_id"] = $order["terminals_id"];
        $entry["delivery_type"] = $order["delivery_type"];
        $entry["onlineorder_id"] = $order["onlineorder_id"];
        $entry["onlinetrans_id"] = $order["onlinetrans_id"];
        $entry["uuid"] = $order["uuid"];
        $entry["store_uuid"] = $order["store_uuid"];
        $entry["orderReference"] = $order["orderReference"];
        $entry["terminalID"] = isset($terminalDescriptions[$order["terminals_id"]]) ? $terminalDescriptions[$order["terminals_id"]] : null;
        
        // Get payments for this order
        $entry["payments"] = isset($paymentsByOrder[$order["id"]]) ? $paymentsByOrder[$order["id"]] : [];
        
        // Get techFeeRate for this order (first tech_fee_rate > 0 from orderItems)
        $entry["techFeeRate"] = isset($techFeeRates[$order["uuid"]]) ? $techFeeRates[$order["uuid"]] : null;
        
        // Secondary tax list
        $entry["secondaryTaxList"] = $order["secondary_tax_list"];
        
        array_push($allResults, $entry);
    }
    
    error_log(sprintf("%sQuery completed - Total orders: %d", $LOG_PREFIX, count($allResults)));
    
} catch (Exception $e) {
    error_log(sprintf("%sQuery error - %s", $LOG_PREFIX, $e->getMessage()));
    send_http_status_and_exit("500", json_encode(["error" => "Query failed: " . $e->getMessage()]));
}

error_log(sprintf("%sSending response - Status: 200, Results: %d", $LOG_PREFIX, count($allResults)));

send_http_status_and_exit("200", json_encode($allResults));

?>
