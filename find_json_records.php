<?php
/**
 * Helper script to find JSON record IDs for use with reconcile_orders.php --json_id parameter
 * 
 * Usage:
 * php find_json_records.php --date=2024-01-15                    # Find by date
 * php find_json_records.php --serial=ABC123                     # Find by terminal serial
 * php find_json_records.php --vendors_id=123                    # Find by vendor
 * php find_json_records.php --date=2024-01-15 --limit=10        # Limit results
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

include_once __DIR__ . "/config/database.php";

// Get command line parameters
if (php_sapi_name() === 'cli') {
    $options = getopt("", ["date:", "serial:", "vendors_id:", "limit:", "help"]);
    $date = $options['date'] ?? null;
    $serial = $options['serial'] ?? null;
    $vendors_id = $options['vendors_id'] ?? null;
    $limit = intval($options['limit'] ?? 20);
    $help = isset($options['help']);
} else {
    $date = $_GET['date'] ?? null;
    $serial = $_GET['serial'] ?? null;
    $vendors_id = $_GET['vendors_id'] ?? null;
    $limit = intval($_GET['limit'] ?? 20);
    $help = isset($_GET['help']);
}

if ($help || (!$date && !$serial && !$vendors_id)) {
    echo "Find JSON record IDs for reconciliation\n";
    echo "=====================================\n\n";
    echo "Usage:\n";
    echo "  php find_json_records.php --date=YYYY-MM-DD [--limit=N]\n";
    echo "  php find_json_records.php --serial=TERMINAL_SERIAL [--limit=N]\n";
    echo "  php find_json_records.php --vendors_id=VENDOR_ID [--limit=N]\n";
    echo "  php find_json_records.php --date=YYYY-MM-DD --vendors_id=VENDOR_ID\n\n";
    echo "Examples:\n";
    echo "  php find_json_records.php --date=2024-01-15\n";
    echo "  php find_json_records.php --serial=ABC123 --limit=5\n";
    echo "  php find_json_records.php --vendors_id=123\n\n";
    echo "Output can be used with reconcile_orders.php:\n";
    echo "  php reconcile_orders.php --json_id=12345\n\n";
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

// Build query based on provided parameters
$where_conditions = [];
$params = [];

if ($date) {
    if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $date)) {
        die("Date must be in YYYY-MM-DD format\n");
    }
    $start_datetime = $date . ' 00:00:00';
    $end_datetime = $date . ' 23:59:59';
    $where_conditions[] = "j.lastmod BETWEEN ? AND ?";
    $params[] = $start_datetime;
    $params[] = $end_datetime;
}

if ($serial) {
    $where_conditions[] = "j.serial = ?";
    $params[] = $serial;
}

if ($vendors_id) {
    if (!is_numeric($vendors_id)) {
        die("vendors_id must be a number\n");
    }
    $where_conditions[] = "t.vendors_id = ?";
    $params[] = $vendors_id;
}

// Build the SQL query
$sql = "
    SELECT 
        j.id as json_id,
        j.serial,
        j.lastmod,
        t.vendors_id,
        a.name as vendor_name,
        CHAR_LENGTH(j.content) as content_size
    FROM json j
    LEFT JOIN terminals t ON j.serial = t.serial
    LEFT JOIN accounts a ON t.vendors_id = a.id
";

if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(" AND ", $where_conditions);
}

$sql .= " ORDER BY j.id DESC LIMIT ?";
$params[] = $limit;

echo "Searching for JSON records...\n";
if ($date) echo "Date: $date\n";
if ($serial) echo "Serial: $serial\n";
if ($vendors_id) echo "Vendor ID: $vendors_id\n";
echo "Limit: $limit\n\n";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

if (empty($records)) {
    echo "No JSON records found matching your criteria.\n";
    exit;
}

echo "Found " . count($records) . " JSON records:\n";
echo str_repeat("=", 100) . "\n";
printf("%-8s %-15s %-10s %-20s %-20s %-8s\n", 
    "JSON ID", "Serial", "Vendor ID", "Vendor Name", "Last Modified", "Size");
echo str_repeat("-", 100) . "\n";

foreach ($records as $record) {
    printf("%-8s %-15s %-10s %-20s %-20s %-8s\n",
        $record['json_id'],
        $record['serial'] ?? 'N/A',
        $record['vendors_id'] ?? 'N/A',
        substr($record['vendor_name'] ?? 'N/A', 0, 19),
        $record['lastmod'],
        number_format($record['content_size']) . 'B'
    );
}

echo str_repeat("=", 100) . "\n";
echo "\nTo reconcile a specific record, use:\n";
echo "php reconcile_orders.php --json_id=<JSON_ID>\n\n";

echo "Examples based on your results:\n";
$count = 0;
foreach ($records as $record) {
    if ($count >= 3) break; // Show max 3 examples
    echo "php reconcile_orders.php --json_id=" . $record['json_id'] . "\n";
    $count++;
}
echo "\n";