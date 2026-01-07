<?php
include_once "./library/utils.php";
//enable_cors();
$pdo = connect_db_and_set_http_method( "GET" );
$tablename = "accounts";
$msgforinvalidjwt = "No permission";

require "vendor/autoload.php";
use \Firebase\JWT\JWT;

#check jwt validity
$jwt = getBearerToken();
try {
    $decoded = JWT::decode($jwt, "YOUR_SECRET_KEY", array('HS256'));
} catch ( Exception $e ){
    send_http_status_and_exit("403",$msgforinvalidjwt);
    //var_dump( $e->getMessage() );
}

#check admin priv
#$role = $decoded->{'data'}->{'role'};
#if ( $role != "admin" ) { send_http_status_and_exit("403",$msgforinvalidjwt); }

#get data from database
$res = array();

#get vendor company name
$stmt = $pdo->prepare("select * from accounts where id = ?");
$stmt->execute([ $_REQUEST["vendorsId"] ]);
$row = $stmt->fetch();
$res["vendor_companyname"] = $row["companyname"];

#get agent company nname
$stmt = $pdo->prepare("select * from accounts where id = ?");
$stmt->execute([ $row["accounts_id"] ]);
$row = $stmt->fetch();
$res["agent_companyname"] = $row["companyname"];
$res["agent_id"] = $row["id"];

$stmt = $pdo->prepare("select * from terminals where vendors_id = ? order by id desc");
$stmt->execute([ $_REQUEST["vendorsId"] ]);
$res["data"] = array();
while ( $row = $stmt->fetch() ) {
    $entry = array();
    $entry["id"] = $row["id"];
    $entry["serial"] = $row["serial"];
    $entry["description"] = $row["description"];
    $entry["enterdate"] = $row["enterdate"];
    
    array_push( $res["data"], $entry );
}
send_http_status_and_exit("200",json_encode($res));
?>