<?php
include_once "./library/utils.php";
//enable_cors();

$msgfornoterminal = "No permission";
$msgforsqlerror = "Unable to get data";
$pdo = connect_db_and_set_http_method( "GET" );

$where = "";
//if ( $_REQUEST["type"] == "site" ) {}
//if ( $_REQUEST["type"] == "agent" ) { $where = 'and ordersPayments.agents_id = ?'; }
//if ( $_REQUEST["type"] == "merchant" ) { $where = 'and ordersPayments.vendors_id = ?'; }
if ( $_REQUEST["type"] == "terminal" ) { $where = 'and ordersPayments.terminals_id = ?'; }

$res = array();
$stmt = $pdo->prepare("select * from `items` where terminals_id = ?");
$stmt->execute([ $_REQUEST["id"] ]);

while ( $row = $stmt->fetch() ) {
  $entry = array();
  $entry["desc"] = $row["desc"];
  $entry["amount_on_hand"] = $row["amount_on_hand"];
  array_push( $res, $entry );
}

send_http_status_and_exit("200",json_encode($res));
?>
