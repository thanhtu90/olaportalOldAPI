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

$stmt_batch_count = $pdo->prepare("
                    SELECT batchCount
                    FROM quickbooks_export_queue 
                    WHERE vendor_id = :vendor_id
                    ORDER BY id DESC
                    LIMIT 1
                ");

$stmt_batch_count->execute(['vendor_id' => $vendorId]);
$batchCount = $stmt_batch_count->fetchColumn();

//Handle the case where no rows are found:
if ($batchCount === false) {
    $batchCount = 0; // or whatever default value you want
}

$stmt_count = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM quickbooks_export_queue 
                WHERE vendor_id = :vendor_id
                ");
$stmt_count->execute([
                   ':vendor_id' => $vendorId,
                ]); 
$count = $stmt_count->fetchColumn();

// Add this line to debug
error_log("Query result: " . print_r($count, true));

if ($count == 0) {
    echo json_encode([
        'status' => 'Q Finished',
        'pending_count' => (int)$count,
        'batch_count' => (int)$batchCount
    ]);
} else {
    echo json_encode([
        'status' => 'Q NOT Finished',
        'pending_count' => (int)$count,
        'batch_count' => (int)$batchCount
    ]);
}