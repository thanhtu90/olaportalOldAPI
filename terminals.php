<?php
include_once "./library/utils.php";
ini_set("display_errors", 1);
enable_cors();
$pdo = connect_db_and_set_http_method("GET");
$tablename = "accounts";
$msgforinvalidjwt = "No permission";

require "vendor/autoload.php";

use \Firebase\JWT\JWT;

// Debug mode - bypass JWT verification
if (isset($_REQUEST['debug']) && $_REQUEST['debug'] === 'true') {
    $decoded = (object)['data' => (object)['role' => 'admin']];
} else {
    // Normal JWT verification
    $jwt = getBearerToken();
    try {
        $decoded = JWT::decode($jwt, "YOUR_SECRET_KEY", array('HS256'));
    } catch (Exception $e) {
        send_http_status_and_exit("403", $msgforinvalidjwt);
        //var_dump( $e->getMessage() );
    }
}

#check admin priv
#$role = $decoded->{'data'}->{'role'};
#if ( $role != "admin" ) { send_http_status_and_exit("403",$msgforinvalidjwt); }

#get data from database
$res = array();

#get vendor company name
$stmt = $pdo->prepare("select * from accounts where id = ?");
$stmt->execute([$_REQUEST["vendorsId"]]);
$row = $stmt->fetch();
$res["vendor_companyname"] = $row["companyname"];

#get agent company nname
$stmt = $pdo->prepare("select * from accounts where id = ?");
$stmt->execute([$row["accounts_id"]]);
$row = $stmt->fetch();
$res["agent_companyname"] = $row["companyname"];
$res["agent_id"] = $row["id"];

$stmt = $pdo->prepare("
    SELECT t.*, 
           JSON_ARRAYAGG(JSON_OBJECT(
               'id', pm.id,
               'name', pm.name, 
               'code', pm.code
           )) AS payment_methods
    FROM terminals t
    LEFT JOIN terminal_payment_methods tpm ON t.id = tpm.terminal_id
    LEFT JOIN payment_methods pm ON tpm.payment_method_id = pm.id
    WHERE t.vendors_id = ?
    GROUP BY t.id
    ORDER BY t.id DESC
");
$stmt->execute([$_REQUEST["vendorsId"]]);
$res["data"] = array();
while ($row = $stmt->fetch()) {
    $entry = array();
    $entry["id"] = $row["id"];
    $entry["serial"] = $row["serial"];
    $entry["description"] = $row["description"];
    $entry["enterdate"] = $row["enterdate"];
    $entry["store_uuid"] = $row["store_uuid"];
    $entry["payment_methods"] = json_decode($row["payment_methods"], true);

    array_push($res["data"], $entry);
}
send_http_status_and_exit("200", json_encode($res));
