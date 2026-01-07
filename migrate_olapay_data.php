<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once "./library/utils.php";

echo "Starting OlaPay data migration...\n";

$pdo = connect_db_and_set_http_method("GET");

// Checkpoint file location
$checkpoint_file = '/tmp/migrate_olapay_checkpoint';
$last_processed_id = 0;

// Check if checkpoint exists and read the last processed ID
if (file_exists($checkpoint_file)) {
    $checkpoint_data = file_get_contents($checkpoint_file);
    if ($checkpoint_data !== false) {
        $last_processed_id = (int)trim($checkpoint_data);
        echo "Found checkpoint file. Resuming from ID: {$last_processed_id}\n";
    }
}

// First, let's check if the new table exists
$stmt = $pdo->query("SHOW TABLES LIKE 'unique_olapay_transactions'");
if ($stmt->rowCount() == 0) {
    echo "Error: unique_olapay_transactions table does not exist. Please run the migration first.\n";
    exit(1);
}

// Check if we already have data in the new table (only if starting fresh)
if ($last_processed_id == 0) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM unique_olapay_transactions");
    $result = $stmt->fetch();
    if ($result['count'] > 0) {
        echo "Warning: unique_olapay_transactions table already contains data. Skipping migration.\n";
        exit(0);
    }
}

echo "Fetching data from jsonOlaPay table...\n";

// Get all records from jsonOlaPay, starting from the last processed ID
$stmt = $pdo->prepare("SELECT id, serial, content, lastmod FROM jsonOlaPay WHERE id > ? ORDER BY id");
$stmt->execute([$last_processed_id]);
$total_records = $stmt->rowCount();
echo "Found {$total_records} records to process (starting from ID > {$last_processed_id})...\n";

$processed = 0;
$inserted = 0;
$skipped = 0;
$errors = 0;
$last_processed_id_in_batch = $last_processed_id;

// Prepare insert statement
$insert_stmt = $pdo->prepare("
    INSERT IGNORE INTO unique_olapay_transactions 
    (serial, content, lastmod, order_id, trans_date, trans_id) 
    VALUES (?, ?, ?, ?, ?, ?)
");

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $processed++;
    
    if ($processed % 1000 == 0) {
        echo "Processed {$processed}/{$total_records} records...\n";
    }
    
    try {
        $content = $row['content'];
        $serial = $row['serial'];
        $lastmod = $row['lastmod'];
        
        // Parse JSON content
        $json_data = json_decode($content, true);
        
        if ($json_data === null) {
            echo "Warning: Invalid JSON in record ID {$row['id']}\n";
            $errors++;
            continue;
        }
        
        // Extract the required fields for uniqueness
        $order_id = isset($json_data['orderID']) ? $json_data['orderID'] : null;
        $trans_date = isset($json_data['trans_date']) ? $json_data['trans_date'] : null;
        $trans_id = isset($json_data['trans_id']) ? $json_data['trans_id'] : null;
        
        // Insert into new table (INSERT IGNORE will skip duplicates)
        $result = $insert_stmt->execute([
            $serial,
            $content,
            $lastmod,
            $order_id,
            $trans_date,
            $trans_id
        ]);
        
        if ($result) {
            $inserted++;
        } else {
            $skipped++;
        }
        
        // Save checkpoint every 100 records
        if ($processed % 100 == 0) {
            $last_processed_id_in_batch = $row['id'];
            file_put_contents($checkpoint_file, $last_processed_id_in_batch);
        }
        
    } catch (Exception $e) {
        echo "Error processing record ID {$row['id']}: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\nMigration completed!\n";
echo "Total records processed: {$processed}\n";
echo "Records inserted: {$inserted}\n";
echo "Records skipped (duplicates): {$skipped}\n";
echo "Errors: {$errors}\n";

// Save final checkpoint with the last processed ID
if ($processed > 0) {
    // Update the last processed ID from the batch
    $last_processed_id_in_batch = $row['id'];
    file_put_contents($checkpoint_file, $last_processed_id_in_batch);
    echo "Final checkpoint saved: {$last_processed_id_in_batch}\n";
}

// Show final counts
$stmt = $pdo->query("SELECT COUNT(*) as count FROM unique_olapay_transactions");
$result = $stmt->fetch();
echo "Total records in unique_olapay_transactions: {$result['count']}\n";

$stmt = $pdo->query("SELECT COUNT(*) as count FROM jsonOlaPay");
$result = $stmt->fetch();
echo "Total records in jsonOlaPay: {$result['count']}\n";

echo "Migration script completed successfully!\n";
?> 