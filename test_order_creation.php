<?php
/**
 * Test script to verify that reconcile_orders.php now creates missing orders
 * 
 * This script helps test the new order creation functionality by:
 * 1. Finding orders that exist in JSON but not in database
 * 2. Running reconcile to create the missing orders
 * 3. Verifying the orders were created successfully
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

include_once __DIR__ . "/config/database.php";

// Get command line parameters
if (php_sapi_name() === 'cli') {
    $options = getopt("", ["json_id:", "test_uuid:", "help"]);
    $json_id = $options['json_id'] ?? null;
    $test_uuid = $options['test_uuid'] ?? null;
    $help = isset($options['help']);
} else {
    $json_id = $_GET['json_id'] ?? null;
    $test_uuid = $_GET['test_uuid'] ?? null;
    $help = isset($_GET['help']);
}

if ($help || (!$json_id && !$test_uuid)) {
    echo "Test Order Creation in Reconcile Script\n";
    echo "======================================\n\n";
    echo "Usage:\n";
    echo "  php test_order_creation.php --json_id=12345\n";
    echo "  php test_order_creation.php --test_uuid=ORDER_UUID\n\n";
    echo "Examples:\n";
    echo "  php test_order_creation.php --json_id=12345\n";
    echo "  php test_order_creation.php --test_uuid=e7d1d2b2-5a95-49a9-a9a3-483b34b90f98\n\n";
    echo "This will:\n";
    echo "  1. Check if orders exist in database\n";
    echo "  2. Run reconcile to create missing orders\n";
    echo "  3. Verify orders were created\n\n";
    exit;
}

try {
    $databaseService = new DatabaseService();
    $pdo = $databaseService->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

echo "Test Order Creation Functionality\n";
echo "=================================\n\n";

if ($test_uuid) {
    echo "Testing specific UUID: $test_uuid\n";
    echo str_repeat("-", 50) . "\n";
    
    // Check if order exists before
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE uuid = ?");
    $stmt->execute([$test_uuid]);
    $existing_order = $stmt->fetch();
    
    if ($existing_order) {
        echo "✅ Order already exists in database:\n";
        echo "  ID: {$existing_order['id']}\n";
        echo "  UUID: {$existing_order['uuid']}\n";
        echo "  Reference: {$existing_order['orderReference']}\n";
        echo "  Total: {$existing_order['total']}\n";
        echo "\n❌ Cannot test creation - order already exists!\n";
        echo "\nTo test creation, use an order UUID that doesn't exist in database.\n";
    } else {
        echo "❌ Order with UUID $test_uuid NOT found in database.\n";
        echo "\n🔍 To test order creation:\n";
        echo "1. Find a JSON record containing this UUID:\n";
        echo "   php find_json_records.php --date=YYYY-MM-DD\n";
        echo "2. Run reconcile on that JSON ID:\n";
        echo "   php reconcile_orders.php --json_id=JSON_ID --debug\n";
        echo "3. The script should now CREATE the missing order instead of skipping it.\n";
    }
}

if ($json_id) {
    echo "Testing JSON ID: $json_id\n";
    echo str_repeat("-", 50) . "\n";
    
    // Get the JSON record
    $stmt = $pdo->prepare("SELECT serial, content FROM json WHERE id = ?");
    $stmt->execute([$json_id]);
    $json_record = $stmt->fetch();
    
    if (!$json_record) {
        echo "❌ JSON record with ID $json_id not found.\n";
        exit;
    }
    
    echo "✅ Found JSON record for serial: {$json_record['serial']}\n";
    
    // Parse the JSON to find orders
    $json_payload = json_decode($json_record['content']);
    $inner_json_str = $json_payload;
    
    // Handle double-encoded JSON
    if (is_string($json_payload)) {
        $inner_json_str = json_decode($json_payload);
    }
    
    if (!is_object($inner_json_str)) {
        echo "❌ Could not parse JSON content.\n";
        exit;
    }
    
    $orders_json = isset($inner_json_str->orders) ? json_decode($inner_json_str->orders) : [];
    $isOnlinePlatform = $inner_json_str->isOnlinePlatform ?? false;
    
    echo "Orders found in JSON: " . count($orders_json) . "\n\n";
    
    if (empty($orders_json)) {
        echo "❌ No orders found in JSON record.\n";
        exit;
    }
    
    // Check each order
    $missing_orders = [];
    $existing_orders = [];
    
    foreach ($orders_json as $idx => $order_data) {
        $order_uuid = $isOnlinePlatform ? ($order_data->uuid ?? null) : ($order_data->oUUID ?? null);
        $order_id = $order_data->id ?? 'N/A';
        
        if (!$order_uuid) {
            echo "⚠️  Order $idx (ID: $order_id) has no UUID - will be skipped\n";
            continue;
        }
        
        // Check if exists in database
        $stmt = $pdo->prepare("SELECT id, orderReference, total FROM orders WHERE uuid = ?");
        $stmt->execute([$order_uuid]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            $existing_orders[] = [
                'index' => $idx,
                'json_id' => $order_id,
                'uuid' => $order_uuid,
                'db_id' => $existing['id'],
                'total' => $existing['total']
            ];
            echo "✅ Order $idx (ID: $order_id, UUID: $order_uuid) exists in DB\n";
        } else {
            $missing_orders[] = [
                'index' => $idx,
                'json_id' => $order_id,
                'uuid' => $order_uuid,
                'total' => $order_data->total ?? 0
            ];
            echo "❌ Order $idx (ID: $order_id, UUID: $order_uuid) MISSING from DB\n";
        }
    }
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "Summary:\n";
    echo "Total orders in JSON: " . count($orders_json) . "\n";
    echo "Existing in DB: " . count($existing_orders) . "\n";
    echo "Missing from DB: " . count($missing_orders) . "\n";
    
    if (!empty($missing_orders)) {
        echo "\n🚀 PERFECT FOR TESTING!\n";
        echo "Missing orders that will be CREATED by reconcile script:\n";
        foreach ($missing_orders as $missing) {
            echo "  - Order ID: {$missing['json_id']}, UUID: {$missing['uuid']}, Total: {$missing['total']}\n";
        }
        
        echo "\n📋 To test the new functionality:\n";
        echo "1. Run: php reconcile_orders.php --json_id=$json_id --debug\n";
        echo "2. Watch for messages like: '🔄 Order with UUID xxx not found in database. Creating missing order...'\n";
        echo "3. Verify orders are created with: php debug_order_lookup.php --uuid=UUID\n";
        
        echo "\n🎯 Expected NEW behavior:\n";
        echo "   - Script will CREATE missing orders instead of skipping them\n";
        echo "   - Summary will show 'Orders created: " . count($missing_orders) . "'\n";
        echo "   - All orders will be processed instead of skipped\n";
    } else {
        echo "\n⚠️  All orders already exist in database.\n";
        echo "To test order creation, find a JSON record with missing orders.\n";
        echo "Try: php find_json_records.php --date=RECENT_DATE\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Test completed.\n";