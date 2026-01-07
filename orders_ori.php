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
//if ( $_REQUEST["type"] == "agent" ) { $where = 'and ordersPayments.agents_id = ?'; }
if ( $_REQUEST["type"] == "merchant" ) { $where = 'and ordersPayments.vendors_id = ?'; }
if ( $_REQUEST["type"] == "terminal" ) { $where = 'and ordersPayments.terminals_id = ?'; }

$res = array();
#$stmt = $pdo->prepare("select *, ordersPayments.id as id, sum(discount*qty) as discount from `ordersPayments`, `orders`, `orderItems` where ordersPayments.orderReference = orders.id and orderItems.orders_id = orders.id " . $where . " and ordersPayments.lastMod > '" . $starttime . "' and ordersPayments.lastMod < '" . $endtime . "' group by orders.id order by ordersPayments.lastMod desc");


#$stmt = $pdo->prepare("select *, ordersPayments.id as id, sum(discount*qty) as discount from ordersPayments left join orders on ordersPayments.orderReference = orders.id left join orderItems on orderItems.orders_id = orders.id where ordersPayments.vendors_id = 35 group by orders.id";


$stmt = $pdo->prepare("select *, ordersPayments.orderReference as orderReference, ordersPayments.id as id from `ordersPayments`, `orders` where ordersPayments.orderReference = orders.id " . $where . " and ordersPayments.lastMod > '" . $starttime . "' and ordersPayments.lastMod < '" . $endtime . "' order by ordersPayments.lastMod desc");
#echo "select * from `ordersPayments`, `orders` where ordersPayments.orderId = orders.id " . $where . " and ordersPayments.lastMod > '" . $starttime . "' and ordersPayments.lastMod < '" . $endtime . "' order by ordersPayments.lastMod desc";
#        exit(0);


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
  $entry["refnumber"] = $row["refNumber"];
  
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
  $entry["amount"] = $row["total"];
  $entry["subtotal"] = $row["subTotal"];

  #$entry["payMethod"] = "Card";
  #if ( $row["refNumber"] == "CASH" ) { $entry["payMethod"] = "Cash"; }
  #if ( strpos($row["refNumber"],"GIFT") ) { $entry["payMethod"] = "Gift"; }
  #$entry["refund"] = "$" . number_format((float)$row["refund"],2);
  $entry["refund"] = $row["refund"];
  array_push( $res, $entry );
}

send_http_status_and_exit("200",json_encode($res));
?>
