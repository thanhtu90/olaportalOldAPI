<?php
include_once "./library/utils.php";
enable_cors();
$pdo = connect_db_and_set_http_method( "GET,POST,DELETE" );
$tablename = "accounts";
$msgforinvalidjwt = "No permission";
$msgforinvalidid = "Invalid request";
$msgforsqlerror = "System error";
$msgforemailtaken = "Email taken";
$msgforsqlsuccess = "Operation completed";

require "vendor/autoload.php";
use \Firebase\JWT\JWT;

#check jwt validity
$jwt = getBearerToken();
try {
    $decoded = JWT::decode($jwt, "YOUR_SECRET_KEY", array('HS256'));
} catch ( Exception $e ){
    //var_dump( $e->getMessage() );
    send_http_status_and_exit("403",$msgforinvalidjwt);
}

#check admin priv
#$role = $decoded->{'data'}->{'role'};
#if ( $role != "admin" ) { send_http_status_and_exit("403",$msgforinvalidjwt); }

#remove data from database
if ( $_SERVER["REQUEST_METHOD"] == "DELETE") {
    $stmt = $pdo->prepare("select * from accounts where id = ?");
    $stmt->execute([ $_REQUEST["id"] ]);

    #no such item
    if ( $stmt->rowCount() == 0 ) { send_http_status_and_exit("404",$msgforinvalidid); }

    #remove item
    $stmt = $pdo->prepare("delete from accounts where id = ?");
    $rtn = $stmt->execute([ $_REQUEST["id"] ]);
    if ( $rtn ) {
        send_http_status_and_exit("200",$msgforsqlsuccess);
    } else {
        send_http_status_and_exit("400",$msgforsqlerror);
    }
}

#get data from database
if ( $_SERVER["REQUEST_METHOD"] == "GET") {
    $stmt = $pdo->prepare("select * from accounts where id = ?");
    $stmt->execute([ $_REQUEST["id"] ]);

    #no such item
    if ( $stmt->rowCount() == 0 ) { send_http_status_and_exit("404",$msgforinvalidid); }

    $res = array();
    while ( $row = $stmt->fetch() ) {
        $entry = array();
        $entry["processor"] = $row["processor"];
	$entry["cust_nbr"] = $row["cust_nbr"];
	$entry["merch_nbr"] = $row["merch_nbr"];
	$entry["dba_nbr"] = $row["dba_nbr"];
	$entry["terminal_nbr"] = $row["terminal_nbr"];
	$entry["mac"] = $row["mac"];
	$entry["fiserv_merch_id"] = $row["fiserv_merch_id"];
	$entry["merchid_ach"] = $row["merchid_ach"];
        #$stmt2 = $pdo->prepare("select * from accounts where id = ?");
        #$stmt2->execute([ $row["accounts_id"] ]);
        #$row2 = $stmt2->fetch();
        #$entry["agent_companyname"] = $row2["companyname"];
    
        array_push( $res, $entry );
    }
    send_http_status_and_exit("200",json_encode($res));
}

#write data to database
if ( $_SERVER["REQUEST_METHOD"] == "POST") {
    #check json validity and parameter existence
    $params = get_params_from_http_body([
      "id",
      "processor",
      "cust_nbr",
      "merch_nbr",
      "dba_nbr",
      "terminal_nbr",
      "mac"
    ]);
    // Get fiserv_merch_id and merchid_ach from body if present, else set to null
    $jsonObj = json_decode(file_get_contents("php://input"), true);
    $fiserv_merch_id = isset($jsonObj['fiserv_merch_id']) ? htmlspecialchars($jsonObj['fiserv_merch_id']) : null;
    $merchid_ach = isset($jsonObj['merchid_ach']) ? htmlspecialchars($jsonObj['merchid_ach']) : null;
    $stmt = $pdo->prepare("update accounts set
      processor = ?,
      cust_nbr = ?,
      merch_nbr = ?,
      dba_nbr = ?,
      terminal_nbr = ?,
      mac = ?,
      fiserv_merch_id = ?,
      merchid_ach = ?
    where id = ?
    ");
    $rtn = $stmt->execute([
        $params["processor"],
        $params["cust_nbr"],
	$params["merch_nbr"],
	$params["dba_nbr"],
	$params["terminal_nbr"],
	$params["mac"],
	$fiserv_merch_id,
	$merchid_ach,
        $params["id"]
    ]);
    if ( $rtn ) {
        send_http_status_and_exit("201",$msgforsqlsuccess);
    } else {
        send_http_status_and_exit("400",$msgforsqlerror);
    }
}
?>
