<?php
include_once "./library/utils.php";
enable_cors();
$pdo = connect_db_and_set_http_method( "GET" );
$tablename = "accounts";
$msgforinvalidjwt = "No permission";

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
$stmt = $pdo->prepare("select * from accounts where email = ?");
$stmt->execute([ $_REQUEST["email"] ]);
$res = array();
if ( $stmt->rowCount() != 0 ) {
	$row = $stmt->fetch();
	$res["message"] = "sent";
	$res["email"] = $row["email"];
	$newPassword = rand(1000,9999);

	$stmt = $pdo->prepare("update accounts set
		password = CONCAT('*', UPPER(SHA1(UNHEX(SHA1(?)))))
		where
		email = ?
	");
	$rtn = $stmt->execute([
		$newPassword,
		$_REQUEST["email"]
	]);

	mail($_REQUEST["email"],"OlaPortal Password Reset","Your new password is " . $newPassword. ". Please use it to login to the portal, and reset the password in account detail section.","From: <noreply@olapay.us>\r\n");
} else {
  $res["message"] = "does not exist";
}
send_http_status_and_exit("200",json_encode($res));
?>
