<?php
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

ini_set("display_errors", 1);

// Set REQUEST_METHOD for CLI execution
if (!isset($_SERVER["REQUEST_METHOD"])) {
    $_SERVER["REQUEST_METHOD"] = "GET";
}

include_once "./library/utils.php";

// Test values
$terminals_id = 309;
$payment_uuid = "4a46598a-061c-4e4d-8336-281f604af5d8";
$order_uuid = "2a1a2359-f860-42bd-b513-55ddcd4f6554";

echo "=== Debug ordersPayments Query ===\n\n";

// Connect to database
echo "Connecting to database...\n";
try {
    $pdo = connect_db_and_set_http_method("GET"); // Use GET since we're not doing POST
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Database connected successfully.\n\n";
} catch (Exception $e) {
    echo "Error connecting to database: " . $e->getMessage() . "\n";
    exit(1);
}

// Display test parameters
echo "Test Parameters:\n";
echo "  terminals_id: " . $terminals_id . "\n";
echo "  payment_uuid: " . $payment_uuid . "\n";
echo "  order_uuid: " . $order_uuid . "\n\n";

// Test the query
echo "Executing query:\n";
echo "  SELECT * FROM ordersPayments WHERE terminals_id = ? AND paymentUuid = ? AND orderUuid = ?\n\n";

try {
    $stmt = $pdo->prepare("SELECT * FROM ordersPayments WHERE terminals_id = ? AND paymentUuid = ? AND orderUuid = ?");
    $stmt->execute([$terminals_id, $payment_uuid, $order_uuid]);
    
    $rowCount = $stmt->rowCount();
    echo "Query Results:\n";
    echo "  Row count: " . $rowCount . "\n\n";
    
    if ($rowCount != 0) {
        echo "✓ Payment already exists for orderUuid: " . $order_uuid . " and paymentUuid: " . $payment_uuid . "\n\n";
        
        // Fetch and display all matching rows
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Matching Records:\n";
        foreach ($rows as $index => $row) {
            echo "  Record #" . ($index + 1) . ":\n";
            foreach ($row as $key => $value) {
                echo "    " . $key . ": " . (is_null($value) ? "NULL" : $value) . "\n";
            }
            echo "\n";
        }
    } else {
        echo "✗ Payment does not exist for orderUuid: " . $order_uuid . " and paymentUuid: " . $payment_uuid . "\n\n";
    }
    
    // Additional debugging queries
    echo "\n=== Additional Debugging Queries ===\n\n";
    
    // Check if payment exists with just terminals_id and paymentUuid
    echo "1. Checking for payment with terminals_id and paymentUuid only:\n";
    $stmt2 = $pdo->prepare("SELECT * FROM ordersPayments WHERE terminals_id = ? AND paymentUuid = ?");
    $stmt2->execute([$terminals_id, $payment_uuid]);
    $rowCount2 = $stmt2->rowCount();
    echo "   Row count: " . $rowCount2 . "\n";
    if ($rowCount2 > 0) {
        $rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows2 as $row) {
            echo "   Found payment with orderUuid: " . ($row['orderUuid'] ?? 'NULL') . "\n";
        }
    }
    echo "\n";
    
    // Check if payment exists with just terminals_id and orderUuid
    echo "2. Checking for payment with terminals_id and orderUuid only:\n";
    $stmt3 = $pdo->prepare("SELECT * FROM ordersPayments WHERE terminals_id = ? AND orderUuid = ?");
    $stmt3->execute([$terminals_id, $order_uuid]);
    $rowCount3 = $stmt3->rowCount();
    echo "   Row count: " . $rowCount3 . "\n";
    if ($rowCount3 > 0) {
        $rows3 = $stmt3->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows3 as $row) {
            echo "   Found payment with paymentUuid: " . ($row['paymentUuid'] ?? 'NULL') . "\n";
        }
    }
    echo "\n";
    
    // Check all payments for this terminal
    echo "3. Checking all payments for terminals_id = " . $terminals_id . ":\n";
    $stmt4 = $pdo->prepare("SELECT id, paymentUuid, orderUuid, payDate, lastMod FROM ordersPayments WHERE terminals_id = ? ORDER BY id DESC LIMIT 10");
    $stmt4->execute([$terminals_id]);
    $rowCount4 = $stmt4->rowCount();
    echo "   Total recent payments (last 10): " . $rowCount4 . "\n";
    if ($rowCount4 > 0) {
        $rows4 = $stmt4->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows4 as $row) {
            echo "   ID: " . $row['id'] . " | paymentUuid: " . ($row['paymentUuid'] ?? 'NULL') . " | orderUuid: " . ($row['orderUuid'] ?? 'NULL') . "\n";
        }
    }
    echo "\n";
    
    // Check data types and exact values
    echo "4. Checking for similar UUIDs (case-insensitive, partial match):\n";
    $stmt5 = $pdo->prepare("SELECT id, paymentUuid, orderUuid FROM ordersPayments WHERE terminals_id = ? AND (LOWER(paymentUuid) LIKE ? OR LOWER(orderUuid) LIKE ?) LIMIT 5");
    $payment_uuid_part = strtolower(substr($payment_uuid, 0, 8)) . "%";
    $order_uuid_part = strtolower(substr($order_uuid, 0, 8)) . "%";
    $stmt5->execute([$terminals_id, $payment_uuid_part, $order_uuid_part]);
    $rowCount5 = $stmt5->rowCount();
    echo "   Similar UUIDs found: " . $rowCount5 . "\n";
    if ($rowCount5 > 0) {
        $rows5 = $stmt5->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows5 as $row) {
            echo "   ID: " . $row['id'] . " | paymentUuid: " . ($row['paymentUuid'] ?? 'NULL') . " | orderUuid: " . ($row['orderUuid'] ?? 'NULL') . "\n";
        }
    }
    echo "\n";
    
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "SQL State: " . $e->getCode() . "\n";
}

echo "\n=== Debug Complete ===\n";

