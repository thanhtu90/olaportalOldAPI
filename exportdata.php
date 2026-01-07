<?php
header("content-type: application/vnd.ms-excel");
header("content-disposition: attachment; filename=export.csv");
include_once "./library/utils.php";

//enable_cors();
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
//if ( $_REQUEST["type"] == "site" ) {}
//if ( $_REQUEST["type"] == "agent" ) { $where = 'and ordersPayments.agents_id = ?'; }
if ( $_REQUEST["type"] == "merchant" ) { $where = 'and ordersPayments.vendors_id = ?'; }
if ( $_REQUEST["type"] == "terminal" ) { $where = 'and ordersPayments.terminals_id = ?'; }

$res = array();
$stmt = $pdo->prepare("select * from `ordersPayments` where 1 = 1 " . $where . " and ordersPayments.lastMod > '" . $starttime . "' and ordersPayments.lastMod < '" . $endtime . "' order by lastmod");
if ( $_REQUEST["type"] == "site" ) {
  $stmt->execute([ ]);
} else {
  $stmt->execute([ $_REQUEST["id"] ]);
}
echo "orderId,payMethod,orderTime,amount\n";
while ( $row = $stmt->fetch() ) {
  $entry = array();
  //$entry["orderTime"] = date("Y-m-d h:i:s",$row["lastMod"]);
  $entry["orderTime"] = (int)$row["lastMod"];
  $entry["orderId"] = $row["orderId"];
  
  //$stmt2 = $pdo->prepare("select * from `orderItems` where orders_id = ? and items_id = 0 order by lastMod limit 0,1");
  //$stmt2->execute([ $row["id"] ]);
  //$row2 = $stmt2->fetch();

  //$entry["firstItem"] = $row2["description"];
  $entry["id"] = $row["id"];
  $entry["amount"] = (float)$row["amtPaid"];
  if ( $row["refNumber"] == "0" ) {
    $entry["payMethod"] = "Cash";
  } else {
    $entry["payMethod"] = "Card";
  }
  echo $entry["orderId"] . ",";
  echo $entry["payMethod"] . ",";
  echo date("Y-m-d h:i:s",$entry["orderTime"]) . ",";
  echo $entry["amount"];
  echo "\n";
}
?>