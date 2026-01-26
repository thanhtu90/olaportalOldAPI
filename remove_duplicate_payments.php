<?php
/**
 * Script to check and remove duplicate payments by order ID
 * 
 * Usage:
 *   php remove_duplicate_payments.php --order-id=681126 [--dry-run] [--delete]
 *   php remove_duplicate_payments.php --scan-all [--dry-run] [--delete]
 * 
 * Options:
 *   --order-id=N      Process specific order ID
 *   --scan-all        Scan all orders for duplicates
 *   --dry-run         Show what would be deleted without actually deleting (default)
 *   --delete          Actually delete duplicates (requires explicit flag)
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/database.php';

use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Parse command line arguments
$options = getopt('', ['order-id:', 'scan-all', 'dry-run', 'delete', 'help']);
$orderId = $options['order-id'] ?? null;
$scanAll = isset($options['scan-all']);
$dryRun = !isset($options['delete']); // Default to dry-run unless --delete is specified
$showHelp = isset($options['help']);

if ($showHelp) {
    echo "Usage:\n";
    echo "  php remove_duplicate_payments.php --order-id=681126 [--dry-run] [--delete]\n";
    echo "  php remove_duplicate_payments.php --scan-all [--dry-run] [--delete]\n\n";
    echo "Options:\n";
    echo "  --order-id=N      Process specific order ID\n";
    echo "  --scan-all        Scan all orders for duplicates\n";
    echo "  --dry-run         Show what would be deleted without actually deleting (default)\n";
    echo "  --delete          Actually delete duplicates (requires explicit flag)\n";
    echo "  --help            Show this help message\n";
    exit(0);
}

if (!$orderId && !$scanAll) {
    echo "Error: Either --order-id or --scan-all must be specified\n";
    echo "Use --help for usage information\n";
    exit(1);
}

// Connect to database
try {
    $databaseService = new DatabaseService();
    $pdo = $databaseService->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    echo "Connected to database successfully.\n\n";
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

if ($dryRun) {
    echo "=== DRY RUN MODE - No changes will be made ===\n\n";
} else {
    echo "=== DELETE MODE - Duplicates will be permanently deleted ===\n\n";
}

/**
 * Find duplicate payments for a specific order
 */
function findDuplicatePayments($pdo, $orderId) {
    // First, verify the order exists
    $stmt = $pdo->prepare("SELECT id, uuid, orderReference, total FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo "Error: Order ID $orderId not found.\n";
        return null;
    }
    
    echo "Order ID: {$order['id']}\n";
    echo "Order UUID: {$order['uuid']}\n";
    echo "Order Reference: {$order['orderReference']}\n";
    echo "Order Total: {$order['total']}\n\n";
    
    // Get all payments for this order
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            paymentUuid, 
            orderUuid, 
            orderReference, 
            payDate, 
            amtPaid, 
            total, 
            tips, 
            refund, 
            lastMod,
            terminals_id
        FROM ordersPayments 
        WHERE orderReference = ? 
        ORDER BY id ASC
    ");
    $stmt->execute([$orderId]);
    $allPayments = $stmt->fetchAll();
    
    if (count($allPayments) <= 1) {
        echo "No duplicates found. Total payments: " . count($allPayments) . "\n";
        return ['order' => $order, 'duplicates' => [], 'to_keep' => [], 'to_delete' => []];
    }
    
    echo "Total payments found: " . count($allPayments) . "\n\n";
    
    // Group payments by deduplication key
    $paymentGroups = [];
    $toKeep = [];
    $toDelete = [];
    
    foreach ($allPayments as $payment) {
        // Create deduplication key
        if (!empty($payment['paymentUuid'])) {
            // Use paymentUuid + orderUuid for deduplication
            $key = 'uuid_' . $payment['paymentUuid'] . '_' . $payment['orderUuid'];
        } else {
            // Use orderUuid + payDate + orderReference for deduplication when paymentUuid is null
            $key = 'null_' . $payment['orderUuid'] . '_' . $payment['payDate'] . '_' . $payment['orderReference'];
        }
        
        if (!isset($paymentGroups[$key])) {
            $paymentGroups[$key] = [];
        }
        $paymentGroups[$key][] = $payment;
    }
    
    // Identify duplicates
    foreach ($paymentGroups as $key => $payments) {
        if (count($payments) > 1) {
            // Sort by lastMod descending, then by id ascending (keep the one with latest lastMod, or oldest id if same)
            usort($payments, function($a, $b) {
                if ($a['lastMod'] != $b['lastMod']) {
                    return $b['lastMod'] - $a['lastMod']; // Descending
                }
                return $a['id'] - $b['id']; // Ascending
            });
            
            // Keep the first one (latest lastMod or oldest id)
            $toKeep[] = $payments[0];
            
            // Mark the rest for deletion
            for ($i = 1; $i < count($payments); $i++) {
                $toDelete[] = $payments[$i];
            }
        } else {
            // No duplicates in this group, keep it
            $toKeep[] = $payments[0];
        }
    }
    
    return [
        'order' => $order,
        'all_payments' => $allPayments,
        'duplicates' => $paymentGroups,
        'to_keep' => $toKeep,
        'to_delete' => $toDelete
    ];
}

/**
 * Delete duplicate payments
 */
function deleteDuplicatePayments($pdo, $paymentIds, $dryRun) {
    if (empty($paymentIds)) {
        return 0;
    }
    
    $placeholders = implode(',', array_fill(0, count($paymentIds), '?'));
    $sql = "DELETE FROM ordersPayments WHERE id IN ($placeholders)";
    
    if ($dryRun) {
        echo "  [DRY RUN] Would delete payment IDs: " . implode(', ', $paymentIds) . "\n";
        return count($paymentIds);
    } else {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($paymentIds);
        $deleted = $stmt->rowCount();
        echo "  [DELETED] Removed $deleted payment(s): " . implode(', ', $paymentIds) . "\n";
        return $deleted;
    }
}

/**
 * Scan all orders for duplicates
 */
function scanAllOrders($pdo) {
    echo "Scanning all orders for duplicate payments...\n\n";
    
    // Find orders with multiple payments
    $stmt = $pdo->query("
        SELECT 
            orderReference,
            COUNT(*) as payment_count,
            COUNT(DISTINCT paymentUuid) as unique_payment_uuids,
            COUNT(DISTINCT CONCAT(orderUuid, '_', payDate)) as unique_payment_keys
        FROM ordersPayments 
        WHERE orderReference IS NOT NULL
        GROUP BY orderReference
        HAVING payment_count > 1
        ORDER BY payment_count DESC
        LIMIT 100
    ");
    
    $ordersWithDuplicates = $stmt->fetchAll();
    
    if (empty($ordersWithDuplicates)) {
        echo "No orders with duplicate payments found.\n";
        return;
    }
    
    echo "Found " . count($ordersWithDuplicates) . " orders with potential duplicates:\n\n";
    
    foreach ($ordersWithDuplicates as $row) {
        echo "Order ID: {$row['orderReference']} - {$row['payment_count']} payments, ";
        echo "{$row['unique_payment_uuids']} unique paymentUuids, ";
        echo "{$row['unique_payment_keys']} unique payment keys\n";
    }
}

// Main execution
if ($orderId) {
    echo "Processing order ID: $orderId\n";
    echo str_repeat('=', 60) . "\n\n";
    
    $result = findDuplicatePayments($pdo, $orderId);
    
    if ($result === null) {
        exit(1);
    }
    
    if (empty($result['to_delete'])) {
        echo "\nNo duplicates found. All payments are unique.\n";
        exit(0);
    }
    
    echo "\n=== DUPLICATE ANALYSIS ===\n\n";
    echo "Total payments: " . count($result['all_payments']) . "\n";
    echo "Unique payment groups: " . count($result['duplicates']) . "\n";
    echo "Payments to keep: " . count($result['to_keep']) . "\n";
    echo "Payments to delete: " . count($result['to_delete']) . "\n\n";
    
    // Show details of duplicates
    echo "=== DUPLICATE GROUPS ===\n\n";
    foreach ($result['duplicates'] as $key => $payments) {
        if (count($payments) > 1) {
            $firstPayment = $payments[0];
            $dedupType = !empty($firstPayment['paymentUuid']) ? 'paymentUuid' : 'orderUuid+payDate+orderReference';
            
            echo "Group Key: $key\n";
            echo "  Deduplication method: $dedupType\n";
            echo "  Payments in group: " . count($payments) . "\n";
            
            // Sort by lastMod descending
            usort($payments, function($a, $b) {
                if ($a['lastMod'] != $b['lastMod']) {
                    return $b['lastMod'] - $a['lastMod'];
                }
                return $a['id'] - $b['id'];
            });
            
            $keep = $payments[0];
            echo "  KEEP: Payment ID {$keep['id']}\n";
            echo "        - paymentUuid: " . ($keep['paymentUuid'] ?? 'NULL') . "\n";
            echo "        - orderUuid: {$keep['orderUuid']}\n";
            echo "        - amtPaid: $" . number_format($keep['amtPaid'], 2) . "\n";
            echo "        - lastMod: {$keep['lastMod']}\n";
            
            for ($i = 1; $i < count($payments); $i++) {
                $p = $payments[$i];
                echo "  DELETE: Payment ID {$p['id']}\n";
                echo "          - paymentUuid: " . ($p['paymentUuid'] ?? 'NULL') . "\n";
                echo "          - orderUuid: {$p['orderUuid']}\n";
                echo "          - amtPaid: $" . number_format($p['amtPaid'], 2) . "\n";
                echo "          - lastMod: {$p['lastMod']}\n";
            }
            echo "\n";
        }
    }
    
    // Safety check: Ensure we're not deleting all payments
    if (count($result['to_keep']) == 0) {
        echo "\n[ERROR] Safety check failed: Would delete all payments for this order!\n";
        echo "This should never happen. Aborting.\n";
        exit(1);
    }
    
    // Delete duplicates
    $paymentIdsToDelete = array_column($result['to_delete'], 'id');
    
    echo "=== DELETION SUMMARY ===\n\n";
    echo "Payment IDs to delete: " . implode(', ', $paymentIdsToDelete) . "\n";
    echo "Total amount to delete: $" . number_format(array_sum(array_column($result['to_delete'], 'amtPaid')), 2) . "\n\n";
    
    $deletedCount = deleteDuplicatePayments($pdo, $paymentIdsToDelete, $dryRun);
    
    if ($dryRun) {
        echo "\n[DRY RUN] Would delete $deletedCount duplicate payment(s).\n";
        echo "Run with --delete flag to actually delete them.\n";
    } else {
        echo "\n[SUCCESS] Deleted $deletedCount duplicate payment(s).\n";
        
        // Verify remaining payments
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM ordersPayments WHERE orderReference = ?");
        $stmt->execute([$orderId]);
        $remaining = $stmt->fetch()['count'];
        echo "Remaining payments for order $orderId: $remaining\n";
        
        // Show remaining payment details
        $stmt = $pdo->prepare("
            SELECT id, paymentUuid, amtPaid, total, tips, lastMod 
            FROM ordersPayments 
            WHERE orderReference = ? 
            ORDER BY id ASC
        ");
        $stmt->execute([$orderId]);
        $remainingPayments = $stmt->fetchAll();
        
        if (!empty($remainingPayments)) {
            echo "\nRemaining payment details:\n";
            foreach ($remainingPayments as $p) {
                echo "  - Payment ID {$p['id']}: $" . number_format($p['amtPaid'], 2) . 
                     " (paymentUuid: " . ($p['paymentUuid'] ?? 'NULL') . ", lastMod: {$p['lastMod']})\n";
            }
        }
    }
    
} elseif ($scanAll) {
    scanAllOrders($pdo);
    echo "\nUse --order-id=N to process a specific order.\n";
}

echo "\nDone.\n";
