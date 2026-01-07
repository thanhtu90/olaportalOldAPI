<?php
include_once "./library/utils.php";
enable_cors();

$msgfornoterminal = "No permission";
$msgforsqlerror = "Unable to get data";
$pdo = connect_db_and_set_http_method( "GET" );

$entry = array();
$res = array();
$stmt = $pdo->prepare("select * from `ordersPayments` where id = ?");
$stmt->execute([$_REQUEST["id"] ]);
$row = $stmt->fetch();
if ( $row["refNumber"] == "CASH" ) {
    $entry["payMethod"] = "Cash";
} else {
    $entry["payMethod"] = "Card";
}
$entry["tip"] = $row["tips"];
$entry["techFee"] = $row["techFee"];
$entry["refund"] = $row["refund"];
$stmt = $pdo->prepare("select * from `orders` where id = ?");
$stmt->execute([ $row["orderReference"] ]);
$row = $stmt->fetch();
$entry["subTotal"] = $row["subTotal"];
$entry["tax"] = $row["tax"];
//$entry["discount"] = $row["total"] - $row["tax"] - $row["subTotal"];
$entry["total"] = $row["total"];
$entry["orderDetail"] = array();

$entry["discount"] = 0;
$stmt2 = $pdo->prepare("select * from `orderItems` where orders_id = ? and items_id = 0");
$stmt2->execute([ $row["id"] ]);
while ( $row2 = $stmt2->fetch() ) {
    $item = array();
    $item["name"] = $row2["description"];
    $item["amount"] = $row2["price"];
    $item["quantity"] = $row2["qty"];
    $entry["discount"] = $entry["discount"] + $row2["discount"] * $row2["qty"];

    $item["modifiers"] = array();
    $stmt3 = $pdo->prepare("select * from `orderItems` where orders_id = ? and items_id = ?");
    $stmt3->execute([$row["id"],$row2["id"]]);
    while ( $row3 = $stmt3->fetch() ) {
      $modifier = array();
      $modifier["name"] = $row3["description"];
      $modifier["amount"] = $row3["price"];
      array_push($item["modifiers"],$modifier);
    }
    array_push( $entry["orderDetail"], $item);
}

array_push( $res, $entry );

send_http_status_and_exit("200",json_encode($res));
?>
