<?php
include_once "./library/utils.php";
enable_cors();
ini_set('display_errors', 1);
error_reporting(E_ALL);

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

if ( $_REQUEST["type"] == "merchant" ) {
  $stmt = $pdo->prepare("SELECT * FROM `terminals` WHERE vendors_id = ?");
  $stmt->execute([ $_REQUEST["id"] ]);
  if ( !$stmt->fetch() ) {
    $res = array();
    $res["count_items"] = array();
    $res["amount_items"] = array();
    $res["max_count"] = 0;
    send_http_status_and_exit("200",json_encode($res));
  }
}

$where = "";
if ( $_REQUEST["type"] == "site" ) {}
if ( $_REQUEST["type"] == "agent" ) { $where = 'and ordersPayments.agents_id = ?'; }
if ( $_REQUEST["type"] == "merchant" ) { $where = 'and ordersPayments.vendors_id = ?'; }
if ( $_REQUEST["type"] == "terminal" ) { $where = 'and ordersPayments.terminals_id = ?'; }

$res = array();
$res["count_items"] = array();
$res["amount_items"] = array();

$stmt = $pdo->prepare("SELECT group_name, sum(qty) as qty, sum(price*qty) as amt, price, sum(orders.tax) as tax FROM `orderItems` INNER JOIN `ordersPayments` ON orderItems.orders_id = ordersPayments.orderReference INNER JOIN `orders` ON orderItems.orderUuid = orders.uuid WHERE items_id = '0' " . $where . " and ordersPayments.lastMod > '" . $starttime . "' and ordersPayments.lastMod < '" . $endtime . "' and group_name != '' group by `group_name` order by amt desc");
if ( $_REQUEST["type"] == "site" ) {
  $stmt->execute([ ]);
} else {
  $stmt->execute([ $_REQUEST["id"] ]);
}
$res["max_count"] = 0;
while ( $row = $stmt->fetch() ) {
    if ( $row["qty"] > $res["max_count"] ){ $res["max_count"] = $row["qty"]; }
    $entry["name"] = $row["group_name"];
    $entry["quantity"] = (int)$row["qty"];
    $entry["amount"] = (float)$row["amt"];
    $entry["price"] = (float)$row["price"];
    $entry["tax"] = (float)$row["tax"];
    array_push( $res["count_items"], $entry );
}
/*
$stmt = $pdo->prepare("SELECT *, sum(price*qty) as amt FROM `orderItems`, `ordersPayments` where orderItems.orders_id = ordersPayments.orderReference and items_id = '0' " . $where . " and ordersPayments.lastMod > '" . $starttime . "' and ordersPayments.lastMod < '" . $endtime . "' group by `group_name` order by amt desc");
$stmt->execute([ $_REQUEST["id"] ]);
$res["max_amount"] = 0;
while ( $row = $stmt->fetch() ) {
    if ( $row["amt"] > $res["max_amount"] ){ $res["max_amount"] = $row["amt"]; }
    $entry["name"] = $row["group_name"] . "($" . number_format($row["amt"],2) . ")";
    $entry["value"] = (float)$row["amt"];
    array_push( $res["amount_items"], $entry );
}
*/
send_http_status_and_exit("200",json_encode($res));
?>
