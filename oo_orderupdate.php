<?php
include_once "./library/utils.php";
enable_cors();
$pdo = connect_db_and_set_http_method( "GET,PATCH" );
$tablename = "accounts";
$msgforinvalidjwt = "No permission";
$msgforinvalidid = "Invalid request";
$msgforsqlerror = "System error";
$msgforemailtaken = "Email taken";
$msgforsqlsuccess = "Operation completed";

require "vendor/autoload.php";
use \Firebase\JWT\JWT;

#check jwt validity
#$jwt = getBearerToken();
#try {
#    $decoded = JWT::decode($jwt, "YOUR_SECRET_KEY", array('HS256'));
#} catch ( Exception $e ){
#    //var_dump( $e->getMessage() );
#    send_http_status_and_exit("403",$msgforinvalidjwt);
#}

#check admin priv
#$role = $decoded->{'data'}->{'role'};
#if ( $role != "admin" ) { send_http_status_and_exit("403",$msgforinvalidjwt); }

#get data from database
if ( $_SERVER["REQUEST_METHOD"] == "GET") {
    #check terminal availability
    $stmt = $pdo->prepare("SELECT * FROM `terminals` WHERE `serial` = ?");
    $stmt->execute([ $_REQUEST["serial"] ]);

    #no such item
    if ( $stmt->rowCount() == 0 ) {
        http_response_code("404");
        $entry = array();
        $entry["errorMsg"] = "no such terminal";
    }
    $row = $stmt->fetch();
    
    #check order availability
    $stmt = $pdo->prepare("SELECT * FROM `orders` WHERE `terminals_id` = ? and `orderReference` = ?");
    $stmt->execute([ $row["id"], $_REQUEST["orderRef"]]);
    $res = array();

    if ( $stmt->rowCount() == 0 ) {
        http_response_code("404");
	$entry = array();
	$entry["errorMsg"] = "no such order";
    } else {
        $row = $stmt->fetch(); 
        http_response_code("200");
        $entry = array();
        $entry["orderStat"] = $row["status"];
    }
    echo json_encode($entry);
}

#write data to database
if ( $_SERVER["REQUEST_METHOD"] == "PATCH") {
    #check json validity and parameter existence
    $params = get_params_from_http_body([
        "serial",
        "orderRef",
	"status"
    ]);

    #check terminal availability
    $stmt = $pdo->prepare("SELECT * FROM `terminals` WHERE `serial` = ?");
    $stmt->execute([ $params["serial"] ]);

    #no such item
    if ( $stmt->rowCount() == 0 ) {
        http_response_code("404");
        $entry = array();
        $entry["errorMsg"] = "no such terminal";
    }
    $row = $stmt->fetch();
    
    #check order availability
    $stmt = $pdo->prepare("SELECT * FROM `orders` WHERE `terminals_id` = ? and `orderReference` = ?");
    $stmt->execute([ $row["id"], $params["orderRef"]]);
    $res = array();

    if ( $stmt->rowCount() == 0 ) {
        http_response_code("404");
        $entry = array();
        $entry["errorMsg"] = "no such order";
    } else {
        #$row = $stmt->fetch(); 
        $stmt = $pdo->prepare("update `orders` set status = ? WHERE `terminals_id` = ? and `orderReference` = ?");
	$stmt->execute([ $params["status"], $row["id"], $params["orderRef"] ]);
        http_response_code("200");
        $entry = array();
	$entry["orderStat"] = $params["status"];
       # $entry["lastMod"] = $params["lastMod"];
    }
    echo json_encode($entry);

/*
    $stmt = $pdo->prepare("update orders set
        firstname = ?,
        lastname = ?,
        email = ?,
        companyname = ?,
        address = ?,
        landline = ?,
        mobile = ?,
            where
            id = ?
    ");
    $rtn = $stmt->execute([
        $params["firstname"],
        $params["lastname"],
        $params["email"],
        $params["companyname"],
        $params["address"],
        $params["landline"],
        $params["mobile"],
        $params["id"]
    ]);
    $res = array();
    if ( $rtn ) {
        send_http_status_and_exit("201",$msgforsqlsuccess);
    } else {
        send_http_status_and_exit("400",$msgforsqlerror);
    }
 */
}
?>
