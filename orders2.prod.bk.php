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
if ($_REQUEST["type"] == "agent") { $where = 'and o.agents_id = ?'; }
if ($_REQUEST["type"] == "merchant") { $where = 'and o.vendors_id = ?'; }
if ($_REQUEST["type"] == "terminal") { $where = 'and o.terminals_id = ?'; }

// Optimized query using CTE (Common Table Expression) - maintaining backward compatibility
$optimizedQuery = "
WITH filtered_orders AS (
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
        t.description as terminalID,
        COUNT(DISTINCT op.id) as payment_count,
        SUM(op.total) as total_payments,
        SUM(oi.discount * oi.qty) as total_discount,
        op.refNumber,
        op.total as ptotal,
        op.orderId,
        op.refund,
        op.techFee,
        op.tips,
        op.olapayApprovalId,
        ROW_NUMBER() OVER (PARTITION BY o.uuid ORDER BY o.lastMod DESC) as rn
    FROM orders o
    LEFT JOIN ordersPayments op ON op.orderReference = o.id
    LEFT JOIN orderItems oi ON oi.orders_id = o.id
    LEFT JOIN terminals t ON t.id = o.terminals_id
    WHERE o.lastMod > ? AND o.lastMod < ? $where
    GROUP BY o.id, o.lastMod, o.orderReference, o.subTotal, o.tax, o.terminals_id, 
             o.delivery_type, o.onlineorder_id, o.onlinetrans_id, o.uuid, o.store_uuid, 
             t.description, op.refNumber, op.total, op.orderId, op.refund, op.techFee, op.tips, op.olapayApprovalId
)
SELECT * FROM filtered_orders
WHERE rn = 1
ORDER BY lastMod DESC
";

error_log(sprintf("%sExecuting optimized query - Type: %s, Where: %s", $LOG_PREFIX, $_REQUEST["type"], $where));

try {
    if ($_REQUEST["type"] == "site") {
      $stmt = $pdo->prepare($optimizedQuery);
      $stmt->execute([$starttime, $endtime]);
    } else {
      $stmt = $pdo->prepare($optimizedQuery);
      $stmt->execute([$starttime, $endtime, $_REQUEST["id"]]);
    }
    
    $allResults = [];
    $rowCount = 0;
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $rowCount++;
        $entry = array();
        $entry["orderTime"] = (int)$row["lastMod"];
        $entry["orderId"] = $row["orderReference"];
        $entry["tax"] = $row["tax"];
        $entry["discount"] = $row["total_discount"] ?? 0;
        $entry["id"] = $row["id"];
        $entry["subtotal"] = $row["subTotal"];
        $entry["cnt"] = $row["payment_count"] ?? 0;
        $entry["terminals_id"] = $row["terminals_id"];
        $entry["delivery_type"] = $row["delivery_type"];
        $entry["onlineorder_id"] = $row["onlineorder_id"];
        $entry["onlinetrans_id"] = $row["onlinetrans_id"];
        $entry["uuid"] = $row["uuid"];
        $entry["store_uuid"] = $row["store_uuid"];
        $entry["orderReference"] = $row["orderReference"];
        $entry["olapayApprovalId"] = $row["olapayApprovalId"];
        $entry["terminalID"] = $row["terminalID"];
        
        // Payment handling - matching original logic from orders2_v1.php
        $payments = array();
        if ($row["payment_count"] > 0) {
            // For multiple payments, fetch details (this is rare)
            $stmt2 = $pdo->prepare("SELECT refNumber, total, refund, techFee, orderId, tips, olapayApprovalId, lastMod as paymentLastMod, paymentUuid, amtPaid FROM ordersPayments WHERE orderReference = ?");
            $stmt2->execute([$row["id"]]);
            
            // Temporary array to store payments by UUID for deduplication
            $paymentsByUuid = array();
            
            while ($row2 = $stmt2->fetch()) {
                $entry2 = array();
                $entry2["refNumber"] = $row2["refNumber"];
                $entry2["total"] = $row2["total"];
                $entry2["refund"] = $row2["refund"];
                $entry2["techFee"] = $row2["techFee"];
                $entry2["orderId"] = $row2["orderId"];
                $entry2["tips"] = (float)$row2["tips"];
                $entry2["olapayApprovalId"] = $row2["olapayApprovalId"];
                $entry2["paymentLastMod"] = isset($row2["paymentLastMod"]) ? $row2["paymentLastMod"] : null;
                $entry2["paymentUuid"] = isset($row2["paymentUuid"]) ? $row2["paymentUuid"] : null;
                $entry2["amtPaid"] = isset($row2["amtPaid"]) ? $row2["amtPaid"] : null;
                
                // Deduplicate by paymentUuid - keep the one with greatest paymentLastMod
                if (!empty($entry2["paymentUuid"])) {
                    if (!isset($paymentsByUuid[$entry2["paymentUuid"]]) || 
                        $entry2["paymentLastMod"] > $paymentsByUuid[$entry2["paymentUuid"]]["paymentLastMod"]) {
                        $paymentsByUuid[$entry2["paymentUuid"]] = $entry2;
                    }
                } else {
                    // If no paymentUuid, add directly (for backward compatibility)
                    array_push($payments, $entry2);
                }
            }
            
            // Add deduplicated payments to the final array
            foreach ($paymentsByUuid as $payment) {
                array_push($payments, $payment);
            }
        }
        $entry["payments"] = $payments;
        
        array_push($allResults, $entry);
    }
    
    error_log(sprintf("%sQuery completed - Total rows: %d", $LOG_PREFIX, $rowCount));
    
} catch (Exception $e) {
    error_log(sprintf("%sQuery error - %s", $LOG_PREFIX, $e->getMessage()));
    send_http_status_and_exit("500", json_encode(["error" => "Query failed: " . $e->getMessage()]));
}

error_log(sprintf("%sSending response - Status: 200, Results: %d", $LOG_PREFIX, count($allResults)));

send_http_status_and_exit("200", json_encode($allResults));

?>