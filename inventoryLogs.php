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
    $stmt = $pdo->prepare("select * from inventoryLogs where inventoryId = ? order by id desc");
    $stmt->execute([ $_REQUEST["inventories_id"] ]);
    while ( $row = $stmt->fetch() ) {
        $entry = array();
        $entry["id"] = $row["id"];
        $entry["quantity"] = $row["quantity"];
	$entry["reason"] = $row["reason"];
        $entry["enterdate"] = $row["enterdate"];
        array_push( $res, $entry );
    }
    send_http_status_and_exit("200",json_encode($res));
}

#insert
if ( $_REQUEST["act"] == "insert" ) {
    $pdo = connect_db_and_set_http_method( "POST" );
    $stmt = $pdo->prepare("insert into inventoryLogs set inventoryId = ?, quantity = ?, reason = ?, enterdate = now()");
    $stmt->execute([ $_REQUEST["inventories_id"], $_REQUEST["quantity"], $_REQUEST["reason"] ]);
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
