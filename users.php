<?php
include_once "./library/utils.php";
enable_cors();
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
$role = $decoded->{'data'}->{'role'};
if ( $role != "admin" ) { send_http_status_and_exit("403",$msgforinvalidjwt); }

#get data from database
$stmt = $pdo->prepare("select * from accounts where role = 'admin' or role = 'agent' order by id desc");
$stmt->execute([]);
$res = array();
while ( $row = $stmt->fetch() ) {
    $entry = array();
    $entry["id"] = $row["id"];
    $entry["name"] = $row["firstname"] . " " . $row["lastname"];
    $entry["email"] = $row["email"];
    $entry["enterdate"] = $row["enterdate"];
    $entry["role"] = $row["role"];
    $entry["title"] = $row["title"];
    $entry["companyname"] = $row["companyname"];
    
    array_push( $res, $entry );
}
send_http_status_and_exit("200",json_encode($res));
?>
