<?php
// Manual retry endpoint: drain any pending `quickbooks_export_queue` rows for
// the given vendor. Normal flow pushes to QBO inside `quickbook.php`; this
// endpoint is kept for retrying rows that stayed in the queue because the
// merchant re-authenticated after a failure or the token was expired.
require_once('vendor/autoload.php');
include_once "./library/utils.php";
include_once "./library/qb_export_lib.php";
$config = require './config/qb_config.php';

ini_set('max_execution_time', 1800);

header('Content-Type: application/json');

enable_cors();

$vendorId = $_GET['id'] ?? null;
if (!$vendorId) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'error'  => 'Vendor ID is required'
    ]);
    exit;
}

$pdo = connect_db_and_set_http_method("GET", "DELETE");

error_log("qb_export.php manual flush for vendor_id={$vendorId}");

$summary = flushQuickbooksQueue($pdo, (int)$vendorId, $config);

echo json_encode([
    'status'    => 'success',
    'qb_export' => $summary,
]);
