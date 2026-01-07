<?php
// qb_token_poll.php
require_once('vendor/autoload.php');
include_once "./library/utils.php";

header('Content-Type: application/json');

enable_cors();

// Simply use $_GET
$vendorId = $_GET['id'] ?? null;
if (!$vendorId) {
    http_response_code(400);
    echo json_encode(['error' => 'Vendor ID is required']);
    exit;
}

$pdo = connect_db_and_set_http_method("GET");

error_log("Vendor ID: " . $vendorId);

$current_ts = (int)time();

$stmt = $pdo->prepare("
    SELECT id 
    FROM quickbooks_token_cred 
    WHERE vendor_id = :vendor_id
    AND token_expire > :current_ts
    ORDER BY lastmod DESC 
    LIMIT 1
");

$stmt->execute([
   ':vendor_id' => $vendorId,
   ':current_ts' => $current_ts
]);

$result = $stmt->fetch();

// Add this line to debug
error_log("Query result: " . print_r($result, true));

if ($result) {
    echo json_encode([
        'status' => 'success',
        'message' => 'Token acquired'
    ]);
} else {
    echo json_encode([
        'status' => 'pending',
        'message' => 'Token not yet acquired or expired'
    ]);
}