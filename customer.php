<?php
include_once "./library/utils.php";
enable_cors();
ini_set("display_errors", 1);
$pdo = connect_db_and_set_http_method( "GET,POST,DELETE" );
$tablename = "customer";
$msgforinvalidjwt = "No permission";
$msgforinvalidid = "Invalid request";
$msgforsqlerror = "System error";
$msgforphonetaken = "Phone taken";
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
    $stmt = $pdo->prepare("select * from customer where id = ?");
    $stmt->execute([ $_REQUEST["id"] ]);

    #no such item
    if ( $stmt->rowCount() == 0 ) { send_http_status_and_exit("404",$msgforinvalidid); }

    #remove item
    $stmt = $pdo->prepare("delete from customer where id = ?");
    $rtn = $stmt->execute([ $_REQUEST["id"] ]);
    if ( $rtn ) {
        send_http_status_and_exit("200",$msgforsqlsuccess);
    } else {
        send_http_status_and_exit("400",$msgforsqlerror);
    }
}

#get data from database
if ( $_SERVER["REQUEST_METHOD"] == "GET") {
    $stmt = $pdo->prepare("select * from customer where id = ?");
    $stmt->execute([ $_REQUEST["id"] ]);

    #no such item
    if ( $stmt->rowCount() == 0 ) { send_http_status_and_exit("404",$msgforinvalidid); }

    $res = array();
    while ( $row = $stmt->fetch() ) {
        $entry = array();
        $entry["id"] = $row["id"];
        $entry["first_name"] = $row["first_name"];
        $entry["last_name"] = $row["last_name"];
        $entry["phone"] = $row["phone"];
        $entry["email"] = $row["email"];
        $entry["street"] = $row["street"];
        $entry["city"] = $row["city"];
        $entry["zip"] = $row["zip"];
        $entry["dob"] = $row["dob"];
        $entry["point"] = $row["point"];
        $entry["membership"] = $row["membership"];
        $entry["status"] = $row["status"];
        $entry["gender"] = $row["gender"];
        $entry["timestamp"] = $row["timestamp"];
        $entry["lastmod"] = $row["lastmod"];
        $entry["note"] = $row["note"];
    
        array_push( $res, $entry );
    }
    send_http_status_and_exit("200",json_encode($res));
}

#write data to database
if ( $_SERVER["REQUEST_METHOD"] == "POST") {
    #check json validity and parameter existence
    $params = get_params_from_http_body([
        "id",
        "city",
        "dob",
        "email",
        "first_name",
        "gender",
        "last_name",
        "membership",
        "note",
	"phone",
	"point",
	"status",
	"street",
	"zip",
	"vendors_id"
    ]);

    if ( $params["id"] == "0" ) {

        $stmt = $pdo->prepare("select * from customer where phone = ? and vendors_id = ?");
        $rtn = $stmt->execute([ $params["phone"], $params["vendors_id"] ]);
        if ( $stmt->rowCount() != 0 ) {
            send_http_status_and_exit("400",$msgforphonetaken);
        }

        $stmt = $pdo->prepare("insert into customer set
            vendors_id = ?,
            city = ?,
            dob = ?,
            email = ?,
            first_name = ?,
            gender = ?,
            last_name = ?,
            membership = ?,
	    note = ?,
            phone = ?,
	    point = ?,
	    status = ?,
            street = ?,
            zip = ?,
	    timestamp = now(),
            lastmod = now()      
        ");
	$rtn = $stmt->execute([
            $params["vendors_id"],
            $params["city"],
            $params["dob"],
            $params["email"],
            $params["first_name"],
            $params["gender"],
            $params["last_name"],
            $params["membership"],
	    $params["note"],
	    $params["phone"],
	    $params["point"],
	    $params["status"],
	    $params["street"],
	    $params["zip"]
        ]);
    } else {
        #need to check email here
        $stmt = $pdo->prepare("select * from customer where phone = ? and id != ? and vendors_id = ?");        
        $rtn = $stmt->execute([ $params["phone"], $params["id"], $params["vendors_id"] ]);
        if ( $stmt->rowCount() != 0 ) {
            send_http_status_and_exit("400",$msgforphonetaken);
        }
        
	$stmt = $pdo->prepare("update customer set
            city = ?,	    
            dob = ?,
            email = ?,
            first_name = ?,
            gender = ?,
            last_name = ?,
            membership = ?,
            note = ?,
            phone = ?,
            point = ?,
	    status = ?,
            street = ?,
            zip = ?
	    where
            id = ?
        ");
	$rtn = $stmt->execute([
            $params["city"],
            $params["dob"],
            $params["email"],
            $params["first_name"],
            $params["gender"],
            $params["last_name"],
            $params["membership"],
            $params["note"],
            $params["phone"],
            $params["point"],
	    $params["status"],
	    $params["street"],
            $params["zip"],
            $params["id"]
	]);
    }
    $res = array();
    if ( $rtn ) {
        send_http_status_and_exit("201",$msgforsqlsuccess);
    } else {
        send_http_status_and_exit("400",$msgforsqlerror);
    }
}
?>
