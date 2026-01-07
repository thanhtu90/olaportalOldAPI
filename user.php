<?php
ini_set("display_errors",1);
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
        $entry["firstname"] = $row["firstname"];
        $entry["lastname"] = $row["lastname"];
        $entry["email"] = $row["email"];
        $entry["enterdate"] = $row["enterdate"];
        $entry["role"] = $row["role"];
        $entry["companyname"] = $row["companyname"];
        $entry["address"] = $row["address"];
        $entry["landline"] = $row["landline"];
	$entry["mobile"] = $row["mobile"];
	$entry["title"] = $row["title"];
    
        array_push( $res, $entry );
    }
    send_http_status_and_exit("200",json_encode($res));
}

#write data to database
if ( $_SERVER["REQUEST_METHOD"] == "POST") {
    #check json validity and parameter existence
    $params = get_params_from_http_body([
        "id",
        "firstname",
        "lastname",
        "email",
        "password",
        "role",
        "companyname",
        "address",
        "landline",
	"mobile",
	"title"
    ]);
    $default_processor = "nab";
    $processor = $params["processor"] ?? $default_processor;
    $default_cust_nbr = "9001";
    $cust_nbr = $params["cust_nbr"] ?? $default_cust_nbr;
    $default_merch_nbr = "900300";
    $merch_nbr = $params["merch_nbr"] ?? $default_merch_nbr;
    $default_dba_nbr = "1";
    $dba_nbr = $params["dba_nbr"] ?? $default_dba_nbr;
    $default_terminal_nbr = "50";
    $terminal_nbr = $params["terminal_nbr"] ?? $default_terminal_nbr;
    $default_mac = "2ifP9bBSu9TrjMt8EPh1rI51AR35sfus";
    $mac = $params["mac"] ?? $default_mac;

    if ( $params["id"] == "0" ) {
        #need to check email here
        $stmt = $pdo->prepare("select * from accounts where email = ?");        
        $rtn = $stmt->execute([ $params["email"] ]);
        if ( $stmt->rowCount() != 0 ) {
            send_http_status_and_exit("400",$msgforemailtaken);
        }

        $stmt = $pdo->prepare("insert into accounts set
            firstname = ?,
            lastname = ?,
            email = ?,
            password = CONCAT('*', UPPER(SHA1(UNHEX(SHA1(?))))),
            role = ?,
            companyname = ?,
            address = ?,
            landline = ?,
	    mobile = ?,
            title = ?,
            processor = ?,
            cust_nbr = ?,
            merch_nbr = ?,
            dba_nbr = ?,
            terminal_nbr = ?,
            mac = ?,
            accounts_id = 0,
            enterdate = now()
        ");
        $rtn = $stmt->execute([
            $params["firstname"],
            $params["lastname"],
            $params["email"],
            $params["password"],
            $params["role"],
            $params["companyname"],
            $params["address"],
            $params["landline"],
	    $params["mobile"],
	    $params["title"],
	    $processor,
            $cust_nbr,
            $merch_nbr,
            $dba_nbr,
            $terminal_nbr,
            $mac,
        ]);
    } else {
        #need to check email here
        $stmt = $pdo->prepare("select * from accounts where email = ? and id != ?");        
        $rtn = $stmt->execute([ $params["email"], $params["id"] ]);
        if ( $stmt->rowCount() != 0 ) {
            send_http_status_and_exit("400",$msgforemailtaken);
        }
        
        $stmt = $pdo->prepare("update accounts set
            firstname = ?,
            lastname = ?,
            email = ?,
            role = ?,
            companyname = ?,
            address = ?,
            landline = ?,
	    mobile = ?,
	    title = ?,
	    processor = ?,
            cust_nbr = ?,
            merch_nbr = ?,
            dba_nbr = ?,
            terminal_nbr = ?,
            mac = ?
            where
            id = ?
        ");
        $rtn = $stmt->execute([
            $params["firstname"],
            $params["lastname"],
            $params["email"],
            $params["role"],
            $params["companyname"],
            $params["address"],
            $params["landline"],
	    $params["mobile"],
	    $params["title"],
	    $processor,
            $cust_nbr,
            $merch_nbr,
            $dba_nbr,
            $terminal_nbr,
            $mac,
            $params["id"]
        ]);

        #renew password
        if ( $params["password"] != "" ) {
            $stmt = $pdo->prepare("update accounts set
                password = CONCAT('*', UPPER(SHA1(UNHEX(SHA1(?)))))
                where
                id = ?
            ");
            $rtn = $stmt->execute([
                $params["password"],
                $params["id"]
            ]);
        }
    }
    $res = array();
    if ( $rtn ) {
        send_http_status_and_exit("201",$msgforsqlsuccess);
    } else {
        send_http_status_and_exit("400",$msgforsqlerror);
    }
}
?>
