<?php
namespace Tests\Mock;

// Mock functions
function enable_cors() {}
function connect_db_and_set_http_method($http_method) {
    global $pdo;
    return $pdo;
}
function send_http_status_and_exit($code, $message) {
    throw new \Exception("HTTP $code: $message");
}

// Include the real json.php
include __DIR__ . '/../json.php';