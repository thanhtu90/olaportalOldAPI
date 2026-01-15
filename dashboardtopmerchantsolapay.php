<?php
// V2
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once "./library/utils.php";
enable_cors();

// Date range calculation
$tomorrow = strtotime('tomorrow');
$starttime = null;
$endtime = null;

switch ($_REQUEST["datetype"]) {
    case "Last 30 Days":
        $starttime = $tomorrow - 86400 * 30;
        $endtime = strtotime('now');
        break;
    case "Last 24 Hours":
        $tomorrow = strtotime('next hour');
        $starttime = $tomorrow - 86400;
        $endtime = strtotime('now');
        break;
    case "Last 52 Weeks":
        $starttime = $tomorrow - 86400 * 52 * 7;
        $endtime = strtotime('now');
        break;
    case "Custom":
        $starttime = strtotime($_REQUEST["fromDate"]);
        $endtime = strtotime($_REQUEST["toDate"]) + 86400;
        break;
}

// Database connection and error messages
$msgfornoterminal = "No permission";
$msgforsqlerror = "Unable to get data";
$pdo = connect_db_and_set_http_method("GET");

// Build WHERE clause based on type
$where = "";
switch ($_REQUEST["type"]) {
    case "agent":
        $where = 'AND terminals.agents_id = :id';
        break;
    case "merchant":
        $where = 'AND accounts.id = :id';
        break;
    case "terminal":
        $where = 'AND terminals.id = :id';
        break;
}

// Initialize response array
$res = [
    "count_items" => [],
    "amount_items" => [],
    "max_count" => 0
];

// The main change is in the merchant selection query
$olapay_merchants_query = "
    SELECT DISTINCT accounts.id
    FROM accounts
    JOIN terminals ON terminals.vendors_id = accounts.id
    JOIN terminal_payment_methods ON terminal_payment_methods.terminal_id = terminals.id
    JOIN payment_methods ON payment_methods.id = terminal_payment_methods.payment_method_id
    WHERE payment_methods.code = 'olapay'
    AND accounts.id NOT IN (
        SELECT DISTINCT accounts.id
        FROM accounts
        JOIN terminals ON terminals.vendors_id = accounts.id
        JOIN terminal_payment_methods ON terminal_payment_methods.terminal_id = terminals.id
        JOIN payment_methods ON payment_methods.id = terminal_payment_methods.payment_method_id
        WHERE payment_methods.code = 'olapos'
    )";  // Exclude merchants that have olapos payment method

$stmt_merchants = $pdo->query($olapay_merchants_query);
$olapay_merchant_ids = $stmt_merchants->fetchAll(PDO::FETCH_COLUMN);

// Main query using CTE to filter out pollution (CREATED status, empty trans_id, etc.)
// Uses generated columns (status, trans_type, amount) for performance - no JSON extraction needed
$query = "
WITH valid_transactions AS (
    SELECT 
        uot.serial,
        uot.trans_id,
        uot.trans_type,
        uot.amount,
        uot.status,
        uot.lastmod
    FROM unique_olapay_transactions uot
    WHERE uot.lastmod > :starttime 
      AND uot.lastmod < :endtime
      -- Filter out pollution: CREATED status = incomplete/pending transactions
      AND uot.status NOT IN ('CREATED', '', 'FAIL', 'REFUNDED')
      -- Filter out records without valid trans_id
      AND uot.trans_id IS NOT NULL 
      AND uot.trans_id != ''
      -- Filter out invalid transaction types
      AND uot.trans_type IS NOT NULL
      AND uot.trans_type NOT IN ('Return Cash', '', 'Auth')
)
SELECT 
    accounts.id,
    accounts.companyname AS business,
    COUNT(DISTINCT vt.trans_id) AS transactions,
    SUM(
        CASE 
            WHEN vt.trans_type IN ('Refund', 'Return')
            THEN vt.amount
            ELSE 0 
        END
    ) AS refund,
    SUM(vt.amount) - SUM(
        CASE 
            WHEN vt.trans_type IN ('Refund', 'Return')
            THEN vt.amount
            ELSE 0 
        END
    ) AS amount
FROM valid_transactions vt
JOIN terminals ON terminals.serial = vt.serial
JOIN accounts ON terminals.vendors_id = accounts.id
WHERE accounts.id IN (" . implode(',', $olapay_merchant_ids ?: [0]) . ")
{$where}
GROUP BY accounts.id, accounts.companyname
ORDER BY amount DESC
LIMIT 10";

$stmt = $pdo->prepare($query);
$params = [
    ':starttime' => $starttime,
    ':endtime' => $endtime
];

error_log("Final query (v2): " . str_replace(
    [':starttime', ':endtime'],
    [$starttime, $endtime],
    str_replace(
        ["\n", "\r", "\t", "  "],
        [' ', '', '', ' '],
        $query
    )
));

if ($_REQUEST["type"] !== "site") {
    $params[':id'] = $_REQUEST["id"];
}

$stmt->execute($params);

// Process results
while ($row = $stmt->fetch()) {
    $entry = [
        "id" => $row["id"],
        "business" => $row["business"],
        "transactions" => $row["transactions"],
        "refund" => $row["refund"],
        "amount" => (float)$row["amount"]
    ];

    if (isset($row["qty"]) && $row["qty"] > $res["max_count"]) {
        $res["max_count"] = $row["qty"];
    }

    $res["count_items"][] = $entry;
}

send_http_status_and_exit("200", json_encode($res));
?> 