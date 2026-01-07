<?php
include_once "./library/utils.php";
enable_cors();
$pdo = connect_db_and_set_http_method( "POST" );
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

#get params
$params = get_params_from_http_body([
    "serial",
    "description",
]);
	
#basic information from jwt
$idFromJwt = $decoded->{"data"}->{"id"};
$roleFromJwt = $decoded->{"data"}->{"role"};
$companynameFromJwt = $decoded->{"data"}->{"companyname"};

#vendor only
#if ( $roleFromJwt != 'vendor' ) {
#  send_http_status_and_exit("403",$msgforinvalidjwt);
#}


#check for duplicated entries
$stmt = $pdo->prepare("select * from terminals where serial = ?");
$stmt->execute([ $params["serial"] ]);
if ( $stmt->rowCount() != 0 ) {
  #generate reponse
  $res = array();
  $res["message"] = "duplicated serial";
  http_response_code("400");
  echo json_encode($res);
  exit();
}

#current online order terminals
$terminals = array();
#$idFromJwt = "64";
$stmt = $pdo->prepare("insert into terminals ( `vendors_id`, `serial`, `description`, enterdate, lastmod ) values ( ?, ?, ?, now(), now() )");
$stmt->execute([ $idFromJwt, $params["serial"], $params["description"] ]);

#generate reponse
$res = array();
$res["message"] = "success";
$res["serial"] = $params["serial"];
http_response_code("200");
echo json_encode($res);

exit();

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
        $entry["companyname"] = $row["companyname"];
        $entry["address"] = $row["address"];
        $entry["landline"] = $row["landline"];
	$entry["mobile"] = $row["mobile"];
	$entry["title"] = $row["title"];
	$entry["accounts_id"] = $row["accounts_id"];

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
        "firstname",
        "lastname",
        "email",
        "password",
        "companyname",
        "address",
        "landline",
        "mobile",
	"accountsId",
	"title"
    ]);

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
            accounts_id = ?,
            enterdate = now()
        ");
        $rtn = $stmt->execute([
            $params["firstname"],
            $params["lastname"],
            $params["email"],
            $params["password"],
            'vendor',
            $params["companyname"],
            $params["address"],
            $params["landline"],
	    $params["mobile"],
	    $params["title"],
            $params["accountsId"]
        ]);
    } else {
        #need to check email here
        $stmt = $pdo->prepare("select * from accounts where email = ? and id != ?");        
        $rtn = $stmt->execute([ $params["email"], $params["id"] ]);
        if ( $stmt->rowCount() != 0 ) {
            send_http_status_and_exit("400",$msgforemailtaken);
        }
        
	$stmt = $pdo->prepare("update accounts set
            accounts_id = ?,
            firstname = ?,
            lastname = ?,
            email = ?,
            companyname = ?,
            address = ?,
            landline = ?,
	    mobile = ?,
            title = ?
            where
            id = ?
        ");
	$rtn = $stmt->execute([
            $params["accountsId"],
            $params["firstname"],
            $params["lastname"],
            $params["email"],
            $params["companyname"],
            $params["address"],
            $params["landline"],
	    $params["mobile"],
	    $params["title"],
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

	#update other records
	#orders
	$stmt = $pdo->prepare("update orders set agents_id = ? where vendors_id = ?");
	$rtn = $stmt->execute([ $params["accountsId"], $params["id"] ]);
	#orderItems
        $stmt = $pdo->prepare("update orderItems set agents_id = ? where vendors_id = ?");
        $rtn = $stmt->execute([ $params["accountsId"], $params["id"] ]);
	#orderPayments
	$stmt = $pdo->prepare("update ordersPayments set agents_id = ? where vendors_id = ?");
        $rtn = $stmt->execute([ $params["accountsId"], $params["id"] ]);
	#items
	$stmt = $pdo->prepare("update items set agents_id = ? where vendors_id = ?");
        $rtn = $stmt->execute([ $params["accountsId"], $params["id"] ]);
    }
    $res = array();
    if ( $rtn ) {
        send_http_status_and_exit("201",$msgforsqlsuccess);
    } else {
        send_http_status_and_exit("400",$msgforsqlerror);
    }
}
?>
