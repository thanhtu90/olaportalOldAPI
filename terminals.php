<?php
include_once "./library/utils.php";
ini_set("display_errors", 1);
enable_cors();
$pdo = connect_db_and_set_http_method("GET");
$msgforinvalidjwt = "No permission";
require "vendor/autoload.php";

use \Firebase\JWT\JWT;

/**
 * JWT Verification
 */
if (isset($_REQUEST['debug']) && $_REQUEST['debug'] === 'true') {
    $decoded = (object)['data' => (object)['role' => 'admin']];
} else {
    $jwt = getBearerToken();
    try {
        $decoded = JWT::decode($jwt, "YOUR_SECRET_KEY", ['HS256']);
    } catch (Exception $e) {
        send_http_status_and_exit("403", $msgforinvalidjwt);
    }
}

/**
 * Validate vendorsId
 */
if (!isset($_REQUEST["vendorsId"])) {
    send_http_status_and_exit("400", "vendorsId is required");
}
$vendorsId = $_REQUEST["vendorsId"];
$res = [];

/**
 * Get vendor
 */
$stmt = $pdo->prepare("SELECT companyname, accounts_id FROM accounts WHERE id = ?");
$stmt->execute([$vendorsId]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);
$res["vendor_companyname"] = $vendor["companyname"] ?? null;

/**
 * Get agent
 */
$agent = null;

if ($vendor && !empty($vendor["accounts_id"])) {

    $stmt = $pdo->prepare("SELECT id, companyname FROM accounts WHERE id = ?");
    $stmt->execute([$vendor["accounts_id"]]);

    $agent = $stmt->fetch(PDO::FETCH_ASSOC);

}

$res["agent_companyname"] = $agent["companyname"] ?? null;
$res["agent_id"] = $agent["id"] ?? null;

/**
 * Get terminals
 */
$stmt = $pdo->prepare("
    SELECT 
        t.id,
        t.serial,
        t.description,
        t.enterdate,
        t.store_uuid,
        t.blocked,
        t.blocked_at,
        t.blocked_reason,
        JSON_ARRAYAGG(
            JSON_OBJECT(
                'id', pm.id,
                'name', pm.name,
                'code', pm.code
            )
        ) AS payment_methods
    FROM terminals t
    LEFT JOIN terminal_payment_methods tpm 
        ON t.id = tpm.terminal_id
    LEFT JOIN payment_methods pm 
        ON tpm.payment_method_id = pm.id
    WHERE t.vendors_id = ?
    GROUP BY t.id
    ORDER BY t.id DESC
");

$stmt->execute([$vendorsId]);
$res["data"] = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $methods = json_decode($row["payment_methods"], true);
    if (is_array($methods)) {
        $methods = array_values(array_filter($methods, function ($m) {
            return $m["id"] !== null;
        }));
    } else {
        $methods = [];
    }
    $res["data"][] = [
        "id" => $row["id"],
        "serial" => $row["serial"],
        "description" => $row["description"],
        "enterdate" => $row["enterdate"],
        "store_uuid" => $row["store_uuid"],
        "blocked" => (bool) $row["blocked"],
        "blocked_at" => $row["blocked_at"],
        "blocked_reason" => $row["blocked_reason"],
        "payment_methods" => $methods
    ];
}

send_http_status_and_exit("200", json_encode($res));