<?php
include_once "./library/utils.php";
enable_cors();
$pdo = connect_db_and_set_http_method( "POST" );
$msgforinvalidjwt = "No permission";
$params = get_params_from_http_body([
    "email",
    "body"
]);
#require "vendor/autoload.php";
#use \Firebase\JWT\JWT;

#check jwt validity
#$jwt = getBearerToken();
#try {
#    $decoded = JWT::decode($jwt, "YOUR_SECRET_KEY", array('HS256'));
#} catch ( Exception $e ){
#    send_http_status_and_exit("403",$msgforinvalidjwt);
    //var_dump( $e->getMessage() );
#}

#check admin priv
#$role = $decoded->{'data'}->{'role'};
#if ( $role != "admin" ) { send_http_status_and_exit("403",$msgforinvalidjwt); }

#get data from database
$res = array();
$res["message"] = "success";

mail($params["email"],"OlaPortal Receipt",$params["body"],"From: <noreply@olapay.us>\r\n");
send_http_status_and_exit("200",json_encode($res));
?>
