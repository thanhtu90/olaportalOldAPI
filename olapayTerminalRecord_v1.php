<?php
include_once "./library/utils.php";
require_once __DIR__ . '/vendor/autoload.php';
use Spatie\Async\Pool;

enable_cors();
###
$tomorrow = strtotime('tomorrow');
switch ( $_REQUEST["datetype"] ) {
  case "Last 30 Days":
    $starttime = $tomorrow - 86400*30;
    $endtime = strtotime('now');
    #$interval = 86400;
    break;
  case "Last 24 Hours":
    $tomorrow = strtotime('next hour');
    $starttime = $tomorrow - 86400;
    $endtime = strtotime('now');
    #$interval = 3600;
    break;
  case "Last 52 Weeks":
    $starttime = $tomorrow - 86400*52*7;
    $endtime = strtotime('now');
    $interval = 86400*7;
    break;
  case "Custom":
    $starttime = strtotime($_REQUEST["fromDate"]);
    $endtime = strtotime($_REQUEST["toDate"]) + 86400;
    #$interval = 86400;
    break;
}
###

$msgfornoterminal = "No permission";
$msgforsqlerror = "Unable to get data";
$pdo = connect_db_and_set_http_method( "GET" );

$where = "";
if ( $_REQUEST["type"] == "site" ) { $where = 'and serial = ?'; }
if ( $_REQUEST["type"] == "agent" ) { $where = 'and serial = ?'; }
if ( $_REQUEST["type"] == "merchant" ) { $where = 'and serial = ?'; }
//if ( $_REQUEST["type"] == "terminal" ) { $where = 'and ordersPayments.terminals_id = ?'; }

$res = array();
$terminals = array();

// --- Pagination and Limit ---
$limit = isset($_REQUEST['limit']) ? intval($_REQUEST['limit']) : 1000;
$limit = min($limit, 2000); // Prevent abuse
$offset = 0;

// --- Determine total count (estimate max batches) ---
$type = $_REQUEST["type"];
$id = isset($_REQUEST["id"]) ? $_REQUEST["id"] : null;

if ($type == "merchant") {
    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) as cnt FROM terminals t LEFT JOIN jsonOlaPay j ON t.serial = j.serial AND j.lastmod > ? AND j.lastmod < ? WHERE t.vendors_id = ? AND t.onlinestorename = ''"
    );
    $countStmt->execute([ $starttime, $endtime, $id ]);
} else {
    $countStmt = $pdo->prepare(
        "SELECT COUNT(*) as cnt FROM terminals t INNER JOIN accounts a ON t.vendors_id = a.id LEFT JOIN jsonOlaPay j ON t.serial = j.serial AND j.lastmod > ? AND j.lastmod < ? WHERE a.id != 172 AND a.id != 183 AND t.onlinestorename = ''"
    );
    $countStmt->execute([ $starttime, $endtime ]);
}
$totalRows = $countStmt->fetch()["cnt"];
$maxBatches = ceil($totalRows / $limit);

$pool = Pool::create()->concurrency(8);
$batchOffsets = [];
for ($i = 0; $i < $maxBatches; $i++) {
    $batchOffsets[] = $i * $limit;
}

$type = $_REQUEST["type"];
$id = isset($_REQUEST["id"]) ? $_REQUEST["id"] : null;
$starttime = $starttime;
$endtime = $endtime;

$results = [];
$terminals = [];

foreach ($batchOffsets as $offset) {
    $pool[] = async(function () use ($type, $id, $starttime, $endtime, $limit, $offset) {
        // Each async task must create its own PDO connection
        include_once __DIR__ . "/library/utils.php";
        $pdo = connect_db_and_set_http_method( "GET" );
        $rows = [];
        if ($type == "merchant") {
            $stmt = $pdo->prepare("
                SELECT 
                    t.serial, 
                    t.description,
                    j.lastmod,
                    j.content
                FROM terminals t
                LEFT JOIN jsonOlaPay j ON t.serial = j.serial 
                    AND j.lastmod > ? 
                    AND j.lastmod < ?
                WHERE t.vendors_id = ? 
                    AND t.onlinestorename = ''
                ORDER BY t.serial, j.lastmod DESC
                LIMIT $limit OFFSET $offset
            ");
            $stmt->execute([ $starttime, $endtime, $id ]);
        } else {
            $stmt = $pdo->prepare("
                SELECT 
                    t.serial, 
                    t.description,
                    j.lastmod,
                    j.content
                FROM terminals t
                INNER JOIN accounts a ON t.vendors_id = a.id
                LEFT JOIN jsonOlaPay j ON t.serial = j.serial 
                    AND j.lastmod > ? 
                    AND j.lastmod < ?
                WHERE a.id != 172 
                    AND a.id != 183 
                    AND t.onlinestorename = ''
                ORDER BY t.serial, j.lastmod DESC
                LIMIT $limit OFFSET $offset
            ");
            $stmt->execute([ $starttime, $endtime ]);
        }
        while ($row = $stmt->fetch()) {
            $rows[] = $row;
        }
        return $rows;
    })->then(function ($rows) use (&$terminals) {
        foreach ($rows as $row) {
            $serial = $row["serial"];
            if (!isset($terminals[$serial])) {
                $terminals[$serial] = array(
                    "serial" => $row["serial"],
                    "description" => $row["description"],
                    "records" => array()
                );
            }
            if ($row["lastmod"] !== null) {
                $entry2 = array(
                    "lastmod" => $row["lastmod"],
                    "content" => $row["content"]
                );
                array_push($terminals[$serial]["records"], $entry2);
            }
        }
    });
}

await($pool);

$res = array_values($terminals);
send_http_status_and_exit("200",json_encode($res));
exit(0);

$stmt = $pdo->prepare("select *, sum(ordersPayments.total) as total, ordersPayments.total as ptotal, orders.lastmod as lastMod, orders.id as id, orders.id as orders_id, sum(discount*qty) as discount, count(distinct ordersPayments.id) as cnt from orders left join ordersPayments on ordersPayments.orderReference = orders.id left join orderItems on orderItems.orders_id = orders.id where orders.lastMod > '" . $starttime . "' and orders.lastMod < '" . $endtime . "'" . $where . " group by orders.id order by orders.lastMod desc");

if ( $_REQUEST["type"] == "site" ) {
  $stmt->execute([ ]);
} else {
  $stmt->execute([ $_REQUEST["id"] ]);
}
while ( $row = $stmt->fetch() ) {
  $entry = array();
  //$entry["orderTime"] = date("Y-m-d h:i:s",$row["lastMod"]);
  $entry["orderTime"] = (int)$row["lastMod"];
  $entry["orderId"] = $row["orders_id"];
  $entry["tax"] = $row["tax"];
  #$entry["refnumber"] = $row["refNumber"]; #payment
  
  #$stmt2 = $pdo->prepare("select sum(discount*qty) as discount from orderItems where orders_id = ?");
  #$stmt2->execute([ $row["orderReference"] ]);
  #$row2 = $stmt2->fetch();
  
  $entry["discount"] = $row["discount"];
  //$stmt2 = $pdo->prepare("select * from `orderItems` where orders_id = ? and items_id = 0 order by lastMod limit 0,1");
  //$stmt2->execute([ $row["id"] ]);
  //$row2 = $stmt2->fetch();

  //$entry["firstItem"] = $row2["description"];
  $entry["id"] = $row["id"];
  #$entry["amount"] = "$" . number_format((float)$row["total"],2);
  #$entry["amount"] = $row["total"]; #payment
  $entry["subtotal"] = $row["subTotal"];
  $entry["cnt"] = $row["cnt"];

  #$entry["payMethod"] = "Card";
  #if ( $row["refNumber"] == "CASH" ) { $entry["payMethod"] = "Cash"; }
  #if ( strpos($row["refNumber"],"GIFT") ) { $entry["payMethod"] = "Gift"; }
  #$entry["refund"] = "$" . number_format((float)$row["refund"],2);
  $payments = array();
  if ( $entry["cnt"] == 1 ) {
    $entry2 = array();
    $entry2["refNumber"] = $row["refNumber"];
    $entry2["total"] = $row["ptotal"];
    $entry2["refund"] = $row["refund"];
    $entry2["techFee"] = $row["techFee"];
    array_push($payments,$entry2);
  } else if ( $entry["cnt"] != 0 )  {
  #$entry["refund"] = $row["refund"]; #payment
  #$entry["techFee"] = $row["techFee"]; #payment
    $stmt2 = $pdo->prepare("select * from ordersPayments where orderReference = ?");
    $stmt2->execute([ $row["orders_id"] ]);
    while ( $row2 = $stmt2->fetch() ) {
      $entry2 = array();
      $entry2["refNumber"] = $row2["refNumber"];
      $entry2["total"] = $row2["total"];
      $entry2["refund"] = $row2["refund"];
      $entry2["techFee"] = $row2["techFee"];
      array_push($payments,$entry2);
    }
  }
  $entry["payments"] = $payments;
  array_push( $res, $entry );
}

send_http_status_and_exit("200",json_encode($res));
?>
