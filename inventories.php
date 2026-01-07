<?php
include_once "./library/utils.php";
enable_cors();
$msgfornoterminal = "No permission";
$msgforsqlerror = "Unable to get data";

#authentication

#handle if method is not get or delete
if ( !$_REQUEST["act"] ) {
    $_REQUEST = (array)json_decode(file_get_contents("php://input"));
}

#selcet
if ( $_REQUEST["act"] == "select" ) {
    $res = array();
    $pdo = connect_db_and_set_http_method( "GET" );
    $stmt = $pdo->prepare("select *, sum(quantity) as quantity from inventories, inventoryLogs where inventories.id = inventoryLogs.inventoryId and vendors_id = ? group by inventories.sku order by inventories.id desc");
    $stmt->execute([ $_REQUEST["vendors_id"] ]);
    while ( $row = $stmt->fetch() ) {
	#sold quantity
        $stmt2 = $pdo->prepare("select sum(qty) as sold from orderItems where vendors_id = ? and notes = ? group by notes");
        $stmt2->execute([ $_REQUEST["vendors_id"], $row["sku"] ]);
        $row2 = $stmt2->fetch();
        if ( !$row2["sold"] ) { $row2["sold"] = 0; }

        $entry = array();
        $entry["id"] = $row["id"];
        $entry["sku"] = $row["sku"];
	$entry["name"] = $row["name"];
	$entry["quantity"] = intval($row["quantity"]) - $row2["sold"];
        array_push( $res, $entry );
    }
    send_http_status_and_exit("200",json_encode($res));
}

#insert
if ( $_REQUEST["act"] == "insert" ) {
    $pdo = connect_db_and_set_http_method( "POST" );
    $stmt = $pdo->prepare("insert into inventories set vendors_id = ?, sku = ?, name = ?");
    $stmt->execute([ $_REQUEST["vendors_id"], $_REQUEST["sku"], $_REQUEST["name"] ]);
    $res = Array();
    $res["status"] = "success";
    send_http_status_and_exit("200",json_encode($res));
}

#update
#if ( $_REQUEST["act"] == "update" ) {
#    $pdo = connect_db_and_set_http_method( "PUT" );
#    $stmt = $pdo->prepare("update inventories set sku = ?, name = ?");
#    $stmt->execute([ $_REQUEST["sku"], $_REQUEST["name"] ]);
#}

#delete
#if ( $_REQUEST["act"] == "delete" ) {
#    $pdo = connect_db_and_set_http_method( "DELETE" );
#    $sql = "";
#
#}

?>
