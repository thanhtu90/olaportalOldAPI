<?php
/**
 * Sync jsonOlaPay records to unique_olapay_transactions
 * 
 * This script reads from jsonOlaPay table and inserts missing records into unique_olapay_transactions.
 * Similar to reconcile_orders.php structure but for OlaPay transaction sync.
 * 
 * Usage:
 *   CLI:
 *     php sync_jsonOlaPay_to_unique.php --serial=WPYB002428000461 --from-date=2025-11-10 [--to-date=2025-11-10] [--debug]
 *     php sync_jsonOlaPay_to_unique.php --id=12345 [--debug]
 *     php sync_jsonOlaPay_to_unique.php --from-id=12345 --to-id=12400 [--debug]
 *     php sync_jsonOlaPay_to_unique.php --serial=WPYB002428000461 --from-lastmod=1731200000 [--to-lastmod=1731300000] [--debug]
 * 
 *   Web:
 *     ?serial=WPYB002428000461&from-date=2025-11-10&debug=true
 *     ?id=12345&debug=true
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(0); // Allow script to run for a long time

include_once __DIR__ . "/library/utils.php";

// Determine execution context and get parameters
if (php_sapi_name() === 'cli') {
    // Running from command line
    $options = getopt("", [
        "id:",
        "from-id:",
        "to-id:",
        "serial:",
        "from-date:",
        "to-date:",
        "from-lastmod:",
        "to-lastmod:",
        "debug",
        "dry-run"
    ]);
    $id = $options['id'] ?? null;
    $from_id = $options['from-id'] ?? null;
    $to_id = $options['to-id'] ?? null;
    $serial = $options['serial'] ?? null;
    $from_date = $options['from-date'] ?? null;
    $to_date = $options['to-date'] ?? null;
    $from_lastmod = $options['from-lastmod'] ?? null;
    $to_lastmod = $options['to-lastmod'] ?? null;
    $debug_mode = isset($options['debug']);
    $dry_run = isset($options['dry-run']);
} else {
    // Running from web browser
    $id = $_GET['id'] ?? null;
    $from_id = $_GET['from-id'] ?? null;
    $to_id = $_GET['to-id'] ?? null;
    $serial = $_GET['serial'] ?? null;
    $from_date = $_GET['from-date'] ?? null;
    $to_date = $_GET['to-date'] ?? null;
    $from_lastmod = $_GET['from-lastmod'] ?? null;
    $to_lastmod = $_GET['to-lastmod'] ?? null;
    $debug_mode = ($_GET['debug'] ?? 'false') === 'true';
    $dry_run = ($_GET['dry-run'] ?? 'false') === 'true';
}

// Helper function to parse date/timestamp
function parseTimestamp($value) {
    if ($value === null) {
        return null;
    }
    
    // If it's numeric, assume it's a Unix timestamp
    if (is_numeric($value)) {
        return (int)$value;
    }
    
    // Otherwise, try to parse it as a date string
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return false;
    }
    
    return $timestamp;
}

// Validate parameters
$has_id_filter = $id || $from_id;
$has_date_filter = $from_date || $from_lastmod;

if (!$has_id_filter && !$has_date_filter) {
    http_response_code(400);
    $error_message = "Please provide a filter parameter.\n\n";
    if (php_sapi_name() === 'cli') {
        $error_message .= "Usage:\n";
        $error_message .= "  php sync_jsonOlaPay_to_unique.php --id=12345 [--debug] [--dry-run]\n";
        $error_message .= "  php sync_jsonOlaPay_to_unique.php --from-id=12345 [--to-id=12400] [--debug] [--dry-run]\n";
        $error_message .= "  php sync_jsonOlaPay_to_unique.php --serial=SERIAL --from-date=YYYY-MM-DD [--to-date=YYYY-MM-DD] [--debug] [--dry-run]\n";
        $error_message .= "  php sync_jsonOlaPay_to_unique.php --serial=SERIAL --from-lastmod=TIMESTAMP [--to-lastmod=TIMESTAMP] [--debug] [--dry-run]\n";
    } else {
        $error_message .= "Examples:\n";
        $error_message .= "  ?id=12345&debug=true\n";
        $error_message .= "  ?from-id=12345&to-id=12400&debug=true\n";
        $error_message .= "  ?serial=WPYB002428000461&from-date=2025-11-10&debug=true\n";
    }
    die($error_message);
}

// Validate date format if provided
if ($from_date && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $from_date)) {
    http_response_code(400);
    die("from-date must be in YYYY-MM-DD format.\n");
}

if ($to_date && !preg_match("/^\d{4}-\d{2}-\d{2}$/", $to_date)) {
    http_response_code(400);
    die("to-date must be in YYYY-MM-DD format.\n");
}

// Parse timestamps
$from_timestamp = null;
$to_timestamp = null;

if ($from_lastmod) {
    $from_timestamp = parseTimestamp($from_lastmod);
    if ($from_timestamp === false) {
        die("Invalid from-lastmod format.\n");
    }
} elseif ($from_date) {
    $from_timestamp = strtotime($from_date . ' 00:00:00');
}

if ($to_lastmod) {
    $to_timestamp = parseTimestamp($to_lastmod);
    if ($to_timestamp === false) {
        die("Invalid to-lastmod format.\n");
    }
} elseif ($to_date) {
    $to_timestamp = strtotime($to_date . ' 23:59:59');
}

// Connect to database
include_once __DIR__ . "/config/database.php";

try {
    $databaseService = new DatabaseService();
    $pdo = $databaseService->getConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Optimize database settings for bulk operations
    try {
        $pdo->exec("SET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO'");
        $pdo->exec("SET SESSION autocommit = 1");
        $pdo->exec("SET SESSION wait_timeout = 28800");
        $pdo->exec("SET SESSION interactive_timeout = 28800");
        echo "Database connection established.\n";
    } catch (PDOException $opt_e) {
        echo "Note: Some database optimizations may not be available.\n";
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed.");
}

echo "============================================\n";
echo "Sync jsonOlaPay to unique_olapay_transactions\n";
echo "============================================\n\n";

// Display filter info
echo "Filters:\n";
if ($id) echo "  ID: $id\n";
if ($from_id) echo "  From ID: $from_id\n";
if ($to_id) echo "  To ID: $to_id\n";
if ($serial) echo "  Serial: $serial\n";
if ($from_date) echo "  From Date: $from_date\n";
if ($to_date) echo "  To Date: $to_date\n";
if ($from_timestamp) echo "  From Timestamp: $from_timestamp (" . date('Y-m-d H:i:s', $from_timestamp) . ")\n";
if ($to_timestamp) echo "  To Timestamp: $to_timestamp (" . date('Y-m-d H:i:s', $to_timestamp) . ")\n";
echo "  Debug Mode: " . ($debug_mode ? "ON" : "OFF") . "\n";
echo "  Dry Run: " . ($dry_run ? "ON (no changes will be made)" : "OFF") . "\n";
echo "\n";

// Build query to fetch from jsonOlaPay
$sql = "SELECT id, serial, content, lastmod FROM jsonOlaPay WHERE 1=1";
$params = [];

if ($id) {
    $sql .= " AND id = ?";
    $params[] = $id;
} else {
    if ($from_id) {
        $sql .= " AND id >= ?";
        $params[] = $from_id;
    }
    if ($to_id) {
        $sql .= " AND id <= ?";
        $params[] = $to_id;
    }
}

if ($serial) {
    $sql .= " AND serial = ?";
    $params[] = $serial;
}

if ($from_timestamp) {
    $sql .= " AND lastmod >= ?";
    $params[] = $from_timestamp;
}

if ($to_timestamp) {
    $sql .= " AND lastmod <= ?";
    $params[] = $to_timestamp;
}

$sql .= " ORDER BY id ASC";

if ($debug_mode) {
    echo "Query: $sql\n";
    echo "Params: " . implode(', ', $params) . "\n\n";
}

// Execute query
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

$total_records = count($records);

if ($total_records === 0) {
    echo "No records found in jsonOlaPay matching the criteria.\n";
    exit(0);
}

echo "Found $total_records records in jsonOlaPay to process.\n\n";

// Prepare statements for checking and inserting
$checkStmt = $pdo->prepare("
    SELECT id FROM unique_olapay_transactions 
    WHERE serial = ? AND order_id = ? AND trans_date = ? AND trans_id = ?
");

$insertStmt = $pdo->prepare("
    INSERT IGNORE INTO unique_olapay_transactions 
    (serial, content, lastmod, order_id, trans_date, trans_id, created_at) 
    VALUES (?, ?, ?, ?, ?, ?, NOW())
");

$updateStmt = $pdo->prepare("
    UPDATE unique_olapay_transactions 
    SET content = ?, lastmod = ? 
    WHERE serial = ? AND order_id = ? AND trans_date = ? AND trans_id = ?
");

// Statistics
$start_time = time();
$processed = 0;
$inserted = 0;
$updated = 0;
$skipped = 0;
$errors = [];
$already_exists = 0;

// Process each record
foreach ($records as $record) {
    $processed++;
    
    // Progress indicator
    if ($processed % 100 === 0 || $debug_mode) {
        $elapsed = time() - $start_time;
        echo "Processing $processed/$total_records (elapsed: {$elapsed}s)\n";
    }
    
    $jsonOlaPay_id = $record['id'];
    $serial_val = $record['serial'];
    $content = $record['content'];
    $lastmod = $record['lastmod'];
    
    // Parse JSON content
    $json_data = json_decode($content, true);
    
    if ($json_data === null) {
        $errors[] = "Invalid JSON in jsonOlaPay ID: $jsonOlaPay_id";
        if ($debug_mode) {
            echo "  ❌ Invalid JSON in ID: $jsonOlaPay_id\n";
        }
        continue;
    }
    
    // Extract fields for unique_olapay_transactions
    $order_id = $json_data['orderID'] ?? null;
    $trans_date = $json_data['trans_date'] ?? null;
    $trans_id = $json_data['trans_id'] ?? null;
    $trans_type = $json_data['trans_type'] ?? 'UNKNOWN';
    $amount = $json_data['amount'] ?? '0';
    $status = $json_data['Status'] ?? 'UNKNOWN';
    $auth_code = $json_data['auth_code'] ?? null;
    
    if ($debug_mode) {
        echo "\n--- jsonOlaPay ID: $jsonOlaPay_id ---\n";
        echo "  Serial: $serial_val\n";
        echo "  Order ID: " . ($order_id ?? 'N/A') . "\n";
        echo "  Trans ID: " . ($trans_id ?? 'N/A') . "\n";
        echo "  Trans Date: " . ($trans_date ?? 'N/A') . "\n";
        echo "  Trans Type: $trans_type\n";
        echo "  Amount: $amount\n";
        echo "  Status: $status\n";
        echo "  Auth Code: " . ($auth_code ?? 'N/A') . "\n";
        echo "  Lastmod: " . date('Y-m-d H:i:s', $lastmod) . "\n";
    }
    
    // Check if record already exists in unique_olapay_transactions
    // Using the full unique key: (serial, order_id, trans_date, trans_id)
    // Note: trans_id is shared across Sale/TipAdjustment/Void/Refund for the same order
    $exists = false;
    if ($trans_id && $trans_date) {
        $checkByUniqueKey = $pdo->prepare("
            SELECT id, lastmod FROM unique_olapay_transactions 
            WHERE serial = ? AND order_id = ? AND trans_date = ? AND trans_id = ?
        ");
        $checkByUniqueKey->execute([$serial_val, $order_id, $trans_date, $trans_id]);
        $existing = $checkByUniqueKey->fetch();
        
        if ($existing) {
            $exists = true;
            
            // Check if we should update (if jsonOlaPay has newer data)
            if ($lastmod > $existing['lastmod']) {
                if (!$dry_run) {
                    $updateStmt->execute([$content, $lastmod, $serial_val, $order_id, $trans_date, $trans_id]);
                }
                $updated++;
                if ($debug_mode) {
                    echo "  ✏️  Updated existing record (newer lastmod)\n";
                }
            } else {
                $already_exists++;
                if ($debug_mode) {
                    echo "  ⏭️  Already exists (skipped)\n";
                }
            }
        }
    }
    
    // Insert if doesn't exist
    if (!$exists) {
        try {
            if (!$dry_run) {
                $result = $insertStmt->execute([
                    $serial_val,
                    $content,
                    $lastmod,
                    $order_id,
                    $trans_date,
                    $trans_id
                ]);
                
                if ($result && $insertStmt->rowCount() > 0) {
                    $inserted++;
                    if ($debug_mode) {
                        echo "  ✅ Inserted into unique_olapay_transactions\n";
                    }
                } else {
                    $skipped++;
                    if ($debug_mode) {
                        echo "  ⏭️  Skipped (INSERT IGNORE - duplicate)\n";
                    }
                }
            } else {
                $inserted++;
                if ($debug_mode) {
                    echo "  🔵 Would insert (dry run)\n";
                }
            }
        } catch (Exception $e) {
            $errors[] = "Error inserting jsonOlaPay ID $jsonOlaPay_id: " . $e->getMessage();
            if ($debug_mode) {
                echo "  ❌ Error: " . $e->getMessage() . "\n";
            }
        }
    }
}

// Summary
$elapsed = time() - $start_time;
$elapsed_str = sprintf("%d:%02d", floor($elapsed / 60), $elapsed % 60);

echo "\n";
echo "============================================\n";
echo "SYNC SUMMARY\n";
echo "============================================\n";
echo "Total Records Processed: $processed/$total_records\n";
echo "Elapsed Time: $elapsed_str\n";
echo "\n";
echo "Results:\n";
echo "  ✅ Inserted: $inserted\n";
echo "  ✏️  Updated: $updated\n";
echo "  ⏭️  Already Exists: $already_exists\n";
echo "  ⏭️  Skipped (duplicates): $skipped\n";
echo "  ❌ Errors: " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}

if ($dry_run) {
    echo "\n⚠️  This was a DRY RUN - no changes were made to the database.\n";
    echo "Remove --dry-run flag to apply changes.\n";
}

echo "\nSync completed.\n";
?>






