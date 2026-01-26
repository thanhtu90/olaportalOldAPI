<?php
include_once "./library/utils.php";
enable_cors();
$pdo = connect_db_and_set_http_method("GET,POST,DELETE");
$tablename = "payment_methods";
$msgforinvalidjwt = "No permission";
$msgforinvalidid = "Invalid request";
$msgforsqlerror = "System error";
$msgforemailtaken = "Email taken";
$msgforsqlsuccess = "Operation completed";

require "vendor/autoload.php";

use \Firebase\JWT\JWT;

#check jwt validity
// $jwt = getBearerToken();
// try {
//     $decoded = JWT::decode($jwt, "YOUR_SECRET_KEY", array('HS256'));
// } catch (Exception $e) {
//     send_http_status_and_exit("403", $msgforinvalidjwt);
// }

#get payment methods for vendor
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_REQUEST["vendors_id"])) {
    // Optimized query: Using GROUP BY instead of DISTINCT for better performance
    // Original join order is optimal for MySQL query planner
    $stmt = $pdo->prepare("
        SELECT pm.name, pm.code 
        FROM payment_methods pm
        INNER JOIN terminal_payment_methods tpm ON pm.id = tpm.payment_method_id
        INNER JOIN terminals t ON tpm.terminal_id = t.id
        WHERE t.vendors_id = ?
        GROUP BY pm.id, pm.name, pm.code
        ORDER BY pm.name
    ");

    $stmt->execute([$_REQUEST["vendors_id"]]);

    // Use fetchAll() instead of while loop for better performance
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $res = array();
    foreach ($rows as $row) {
        $res[] = array(
            "name" => $row["name"],
            "code" => $row["code"]
        );
    }

    send_http_status_and_exit("200", json_encode($res));
}

#get single payment method
if ($_SERVER["REQUEST_METHOD"] == "GET" && isset($_REQUEST["id"])) {
    $stmt = $pdo->prepare("SELECT * FROM payment_methods WHERE id = ?");
    $stmt->execute([$_REQUEST["id"]]);

    if ($stmt->rowCount() == 0) {
        send_http_status_and_exit("404", $msgforinvalidid);
    }

    $res = array();
    while ($row = $stmt->fetch()) {
        $entry = array(
            "name" => $row["name"],
            "code" => $row["code"],
            "description" => $row["description"]
        );
        array_push($res, $entry);
    }
    send_http_status_and_exit("200", json_encode($res));
}
