<?php
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

// Main query modified to use olapay merchant IDs
// Group by serial and lastmod to exclude
$query = "WITH
    unique_transactions AS(
    SELECT DISTINCT
        jsonOlaPay.*
    FROM
        jsonOlaPay
    JOIN terminals ON terminals.serial = jsonOlaPay.serial
    JOIN accounts ON terminals.vendors_id = accounts.id
    WHERE jsonOlaPay.lastmod > :starttime 
        AND jsonOlaPay.lastmod < :endtime
    GROUP BY
        accounts.id,
        jsonOlaPay.serial,
        jsonOlaPay.lastmod
)
SELECT DISTINCT
    accounts.id,
    accounts.companyname AS business,
    COUNT(DISTINCT JSON_EXTRACT(unique_transactions.content, '$.trans_id')) AS transactions,
    SUM(
        CASE 
            WHEN JSON_UNQUOTE(JSON_EXTRACT(unique_transactions.content, '$.trans_type')) IN ('Refund', 'Return')
            THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(unique_transactions.content, '$.amount')) AS DECIMAL(10,2))
            ELSE 0 
        END
    ) AS refund,
    SUM(
        CASE
            WHEN JSON_EXTRACT(unique_transactions.content, '$.trans_id')
            THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(unique_transactions.content, '$.amount')) AS DECIMAL(10,2))
            ELSE 0
        END
    ) - SUM(
        CASE 
            WHEN JSON_UNQUOTE(JSON_EXTRACT(unique_transactions.content, '$.trans_type')) IN ('Refund', 'Return')
            THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(unique_transactions.content, '$.amount')) AS DECIMAL(10,2))
            ELSE 0 
        END
    ) AS amount
FROM unique_transactions
JOIN terminals ON terminals.serial = unique_transactions.serial
JOIN accounts ON terminals.vendors_id = accounts.id
WHERE CAST(unique_transactions.lastmod AS UNSIGNED) > :starttime 
AND CAST(unique_transactions.lastmod AS UNSIGNED) < :endtime
AND accounts.id IN (" . implode(',', $olapay_merchant_ids ?: [0]) . ")
AND JSON_UNQUOTE(JSON_EXTRACT(unique_transactions.content, '$.Status')) NOT IN ('', 'FAIL', 'REFUNDED')
AND JSON_UNQUOTE(JSON_EXTRACT(unique_transactions.content, '$.trans_type')) NOT IN ('Return Cash', '', 'Auth')
AND JSON_EXTRACT(unique_transactions.content, '$.trans_id') IS NOT NULL
AND JSON_EXTRACT(unique_transactions.content, '$.trans_id') != ''
{$where}
GROUP BY accounts.id
ORDER BY amount DESC
LIMIT 10";

$stmt = $pdo->prepare($query);
$params = [
    ':starttime' => $starttime,
    ':endtime' => $endtime
];

error_log("Final query: " . str_replace(
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
