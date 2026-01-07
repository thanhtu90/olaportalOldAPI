<?php
/**
 * Debug helper to look up orders by UUID and understand why reconciliation might fail
 * 
 * Usage:
 * php debug_order_lookup.php --uuid=c003bfe1-ea66-415d-a320-1d5acc5377b1
 * php debug_order_lookup.php --uuid=e7d1d2b2-5a95-49a9-a9a3-483b34b90f98
 * php debug_order_lookup.php --terminal_id=123
 * php debug_order_lookup.php --vendors_id=456
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

include_once __DIR__ . "/config/database.php";

// Get command line parameters
if (php_sapi_name() === 'cli') {
    $options = getopt("", ["uuid:", "terminal_id:", "vendors_id:", "order_id:", "help"]);
    $uuid = $options['uuid'] ?? null;
    $terminal_id = $options['terminal_id'] ?? null;
    $vendors_id = $options['vendors_id'] ?? null;
    $order_id = $options['order_id'] ?? null;
    $help = isset($options['help']);
} else {
    $uuid = $_GET['uuid'] ?? null;
    $terminal_id = $_GET['terminal_id'] ?? null;
    $vendors_id = $_GET['vendors_id'] ?? null;
    $order_id = $_GET['order_id'] ?? null;
    $help = isset($_GET['help']);
}

if ($help || (!$uuid && !$terminal_id && !$vendors_id && !$order_id)) {
    echo "Debug Order Lookup Tool\n";
    echo "======================\n\n";
    echo "Usage:\n";
    echo "  php debug_order_lookup.php --uuid=ORDER_UUID\n";
    echo "  php debug_order_lookup.php --terminal_id=TERMINAL_ID\n";
    echo "  php debug_order_lookup.php --vendors_id=VENDOR_ID\n";
    echo "  php debug_order_lookup.php --order_id=ORDER_ID\n\n";
    echo "Examples:\n";
    echo "  php debug_order_lookup.php --uuid=c003bfe1-ea66-415d-a320-1d5acc5377b1\n";
    echo "  php debug_order_lookup.php --terminal_id=123\n";
    echo "  php debug_order_lookup.php --vendors_id=456\n\n";
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

echo "Debug Order Lookup\n";
echo "==================\n";

if ($uuid) {
    echo "Searching for UUID: $uuid\n\n";
    
    // Search in orders table
    $stmt = $pdo->prepare("
        SELECT o.*, t.serial, a.name as vendor_name 
        FROM orders o 
        LEFT JOIN terminals t ON o.terminals_id = t.id 
        LEFT JOIN accounts a ON o.vendors_id = a.id 
        WHERE o.uuid = ?
    ");
    $stmt->execute([$uuid]);
    $orders = $stmt->fetchAll();
    
    if (empty($orders)) {
        echo "❌ No orders found with UUID: $uuid\n\n";
        
        // Check if similar UUIDs exist
        $similar_stmt = $pdo->prepare("SELECT uuid, id, orderReference, total FROM orders WHERE uuid LIKE ? LIMIT 10");
        $similar_stmt->execute(['%' . substr($uuid, 0, 8) . '%']);
        $similar = $similar_stmt->fetchAll();
        
        if (!empty($similar)) {
            echo "🔍 Similar UUIDs found:\n";
            foreach ($similar as $sim) {
                echo "  UUID: {$sim['uuid']}, ID: {$sim['id']}, Ref: {$sim['orderReference']}, Total: {$sim['total']}\n";
            }
        } else {
            echo "🔍 No similar UUIDs found\n";
        }
    } else {
        echo "✅ Found " . count($orders) . " order(s):\n";
        foreach ($orders as $order) {
            echo "\nOrder Details:\n";
            echo "  ID: {$order['id']}\n";
            echo "  UUID: {$order['uuid']}\n";
            echo "  Order Reference: {$order['orderReference']}\n";
            echo "  Terminal ID: {$order['terminals_id']}\n";
            echo "  Terminal Serial: " . ($order['serial'] ?? 'N/A') . "\n";
            echo "  Vendor ID: {$order['vendors_id']}\n";
            echo "  Vendor Name: " . ($order['vendor_name'] ?? 'N/A') . "\n";
            echo "  Total: {$order['total']}\n";
            echo "  Status: {$order['status']}\n";
            echo "  Order Date: {$order['orderDate']}\n";
            echo "  Last Modified: {$order['lastMod']}\n";
            
            // Check for order items
            $items_stmt = $pdo->prepare("SELECT COUNT(*) as item_count FROM orderItems WHERE orderUuid = ?");
            $items_stmt->execute([$uuid]);
            $item_count = $items_stmt->fetchColumn();
            echo "  Order Items: $item_count\n";
            
            // Check for payments
            $payments_stmt = $pdo->prepare("SELECT COUNT(*) as payment_count FROM ordersPayments WHERE orderUuid = ?");
            $payments_stmt->execute([$uuid]);
            $payment_count = $payments_stmt->fetchColumn();
            echo "  Payments: $payment_count\n";
        }
    }
}

if ($terminal_id) {
    echo "\nSearching for Terminal ID: $terminal_id\n";
    echo str_repeat("-", 40) . "\n";
    
    $stmt = $pdo->prepare("
        SELECT o.uuid, o.id, o.orderReference, o.total, o.orderDate, o.lastMod
        FROM orders o 
        WHERE o.terminals_id = ? 
        ORDER BY o.id DESC 
        LIMIT 10
    ");
    $stmt->execute([$terminal_id]);
    $orders = $stmt->fetchAll();
    
    if (empty($orders)) {
        echo "❌ No orders found for terminal ID: $terminal_id\n";
    } else {
        echo "✅ Recent orders for terminal $terminal_id:\n";
        printf("%-8s %-38s %-8s %-8s %-20s\n", "ID", "UUID", "Ref", "Total", "Date");
        echo str_repeat("-", 85) . "\n";
        foreach ($orders as $order) {
            printf("%-8s %-38s %-8s %-8s %-20s\n", 
                $order['id'], 
                $order['uuid'], 
                $order['orderReference'], 
                $order['total'], 
                $order['orderDate']
            );
        }
    }
}

if ($vendors_id) {
    echo "\nSearching for Vendor ID: $vendors_id\n";
    echo str_repeat("-", 40) . "\n";
    
    // Get vendor info
    $vendor_stmt = $pdo->prepare("SELECT name FROM accounts WHERE id = ?");
    $vendor_stmt->execute([$vendors_id]);
    $vendor = $vendor_stmt->fetch();
    
    if (!$vendor) {
        echo "❌ Vendor ID $vendors_id not found\n";
    } else {
        echo "Vendor: {$vendor['name']}\n\n";
        
        // Get terminals for this vendor
        $terminals_stmt = $pdo->prepare("SELECT id, serial FROM terminals WHERE vendors_id = ?");
        $terminals_stmt->execute([$vendors_id]);
        $terminals = $terminals_stmt->fetchAll();
        
        echo "Terminals (" . count($terminals) . "):\n";
        foreach ($terminals as $terminal) {
            echo "  Terminal ID: {$terminal['id']}, Serial: {$terminal['serial']}\n";
            
            // Get recent orders for this terminal
            $orders_stmt = $pdo->prepare("
                SELECT COUNT(*) as order_count, MAX(orderDate) as latest_order 
                FROM orders 
                WHERE terminals_id = ?
            ");
            $orders_stmt->execute([$terminal['id']]);
            $order_info = $orders_stmt->fetch();
            echo "    Orders: {$order_info['order_count']}, Latest: " . ($order_info['latest_order'] ?? 'None') . "\n";
        }
    }
}

if ($order_id) {
    echo "\nSearching for Order ID: $order_id\n";
    echo str_repeat("-", 40) . "\n";
    
    $stmt = $pdo->prepare("
        SELECT o.*, t.serial, a.name as vendor_name 
        FROM orders o 
        LEFT JOIN terminals t ON o.terminals_id = t.id 
        LEFT JOIN accounts a ON o.vendors_id = a.id 
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo "❌ No order found with ID: $order_id\n";
    } else {
        echo "✅ Order found:\n";
        echo "  ID: {$order['id']}\n";
        echo "  UUID: {$order['uuid']}\n";
        echo "  Order Reference: {$order['orderReference']}\n";
        echo "  Terminal: {$order['terminals_id']} ({$order['serial']})\n";
        echo "  Vendor: {$order['vendors_id']} ({$order['vendor_name']})\n";
        echo "  Total: {$order['total']}\n";
        echo "  Date: {$order['orderDate']}\n";
    }
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "Debug completed\n";