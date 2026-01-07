<?php
include_once "./library/utils.php";
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
//if ( $_REQUEST["type"] == "site" ) {}
if ( $_REQUEST["type"] == "agent" ) { $where = 'and ordersPayments.agents_id = ?'; }
if ( $_REQUEST["type"] == "merchant" ) { $where = 'and ordersPayments.vendors_id = ?'; }
if ( $_REQUEST["type"] == "terminal" ) { $where = 'and ordersPayments.terminals_id = ?'; }

$res = array();

#$stmt = $pdo->prepare("select *, ordersPayments.total, ordersPayments.lastmod as lastMod, ordersPayments.id as id, orders.id as orders_id, sum(discount*qty) as discount, count(distinct ordersPayments.id) as cnt from ordersPayments left join orders on ordersPayments.orderReference = orders.id left join orderItems on orderItems.orders_id = orders.id where ordersPayments.lastMod > '" . $starttime . "' and ordersPayments.lastMod < '" . $endtime . "'" . $where . " group by orders.id order by ordersPayments.lastMod desc");

$stmt = $pdo->prepare("select *, orders.orderReference, sum(ordersPayments.total) as total, ordersPayments.total as ptotal, ordersPayments.orderId as orderId, orders.lastmod as lastMod, orders.id as id, orders.id as orders_id, sum(discount*qty) as discount, count(distinct ordersPayments.id) as cnt, terminals.description as terminalID from orders left join ordersPayments on ordersPayments.orderReference = orders.id left join orderItems on orderItems.orders_id = orders.id left join terminals on terminals.id = orders.terminals_id where orders.lastMod > '" . $starttime . "' and orders.lastMod < '" . $endtime . "'" . $where . " group by orders.id order by orders.lastMod desc");

if ( $_REQUEST["type"] == "site" ) {
  $stmt->execute([ ]);
} else {
  $stmt->execute([ $_REQUEST["id"] ]);
}
while ( $row = $stmt->fetch() ) {
  $entry = array();
  //$entry["orderTime"] = date("Y-m-d h:i:s",$row["lastMod"]);
  $entry["orderTime"] = (int)$row["lastMod"];
  $entry["orderId"] = $row["orderId"];
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
  $entry["terminals_id"] = $row["terminals_id"];
  $entry["delivery_type"] = $row["delivery_type"];
  $entry["onlineorder_id"] = $row["onlineorder_id"];
  $entry["onlinetrans_id"] = $row["onlinetrans_id"];
  $entry["uuid"] = $row["uuid"];
  $entry["store_uuid"] = $row["store_uuid"];
  $entry["orderReference"] = $row["orderReference"];
  $entry["olapayApprovalId"] = $row["olapayApprovalId"];
  $entry["terminalID"] = $row["terminalID"];
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
    $entry2["orderId"] = $row["orderId"]; //payment
    $entry2["tips"] = (float)$row["tip"];
    $entry2["olapayApprovalId"] = $row["olapayApprovalId"];
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
      $entry2["orderId"] = $row2["orderId"];
      $entry2["tips"] = (float)$row2["tips"];
      $entry2["olapayApprovalId"] = $row2["olapayApprovalId"];
      array_push($payments,$entry2);
    }
  }
  $entry["payments"] = $payments;
  array_push( $res, $entry );
}

send_http_status_and_exit("200",json_encode($res));
?>
