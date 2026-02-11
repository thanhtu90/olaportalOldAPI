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

// Main query using CTE to filter out pollution and aggregate per transaction
// Optimized to calculate "Doanh thu thuần" (Net Sales) by excluding tips, taxes, tech fees and subtracting refunds
$query = "
WITH valid_transactions AS (
    SELECT 
        uot.serial,
        uot.trans_id,
        uot.trans_type,
        uot.amount,
        uot.status,
        uot.content
    FROM unique_olapay_transactions uot
    WHERE uot.lastmod >= :starttime 
      AND uot.lastmod <= :endtime
      -- Filter out pollution: CREATED status = incomplete/pending transactions
      -- Include 'REFUNDED' to ensure we capture the original sale base even if fully refunded
      AND uot.status NOT IN ('CREATED', '', 'FAIL')
      -- Filter out records without valid trans_id
      AND uot.trans_id IS NOT NULL 
      AND uot.trans_id != ''
      -- Filter out invalid transaction types
      AND uot.trans_type IS NOT NULL
      AND uot.trans_type NOT IN ('Return Cash', '', 'Auth')
),
transaction_aggregates AS (
    SELECT 
        vt.serial,
        vt.trans_id,
        -- Get base subtotal from the original SALE/SALE CASH record. Fallback to amount if subtotal is missing/zero.
        MAX(CASE 
            WHEN vt.trans_type IN ('Sale', 'Sale Cash') 
            THEN COALESCE(NULLIF(CAST(JSON_UNQUOTE(JSON_EXTRACT(vt.content, '$.subtotal')) AS DECIMAL(10,2)), 0), vt.amount, 0)
            ELSE 0 
        END) AS base_subtotal,
        -- Extract tax and tech fee to subtract if the transaction was TIPPED (synced after tip adjustment)
        MAX(CASE WHEN vt.trans_type IN ('Sale', 'Sale Cash') THEN COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(vt.content, '$.tax')) AS DECIMAL(10,2)), 0) ELSE 0 END) AS sale_tax,
        MAX(CASE WHEN vt.trans_type IN ('Sale', 'Sale Cash') THEN COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(vt.content, '$.tech_fee_amount')) AS DECIMAL(10,2)), 0) ELSE 0 END) AS sale_tech_fee,
        -- Check if any record in this chain is TIPPED or a TipAdjustment
        MAX(CASE WHEN vt.status = 'TIPPED' OR JSON_UNQUOTE(JSON_EXTRACT(vt.content, '$.command')) = 'TipAdjustment' THEN 1 ELSE 0 END) AS is_tipped,
        -- Check if the whole transaction was VOIDED
        MAX(CASE WHEN vt.trans_type IN ('Void', 'Voided') THEN 1 ELSE 0 END) AS is_voided,
        -- Total refund amount for this transaction
        SUM(CASE WHEN vt.trans_type IN ('Refund', 'Return', 'Refunded', 'Returned') THEN COALESCE(vt.amount, 0) ELSE 0 END) AS refund_total
    FROM valid_transactions vt
    GROUP BY vt.serial, vt.trans_id
),
net_sales_per_transaction AS (
    SELECT 
        serial,
        trans_id,
        refund_total,
        (CASE 
            WHEN is_voided = 1 THEN 0
            WHEN is_tipped = 1 THEN (base_subtotal - sale_tax - sale_tech_fee)
            ELSE base_subtotal
        END) AS net_revenue
    FROM transaction_aggregates
)
SELECT 
    accounts.id,
    COALESCE(accounts.companyname, '') AS business,
    COUNT(trans_id) AS transactions,
    COALESCE(SUM(refund_total), 0) AS refund,
    COALESCE(SUM(net_revenue), 0) AS amount
FROM net_sales_per_transaction vt
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

error_log("Final query (v2-fixed): " . str_replace(
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
        "business" => $row["business"] ?? "",
        "transactions" => $row["transactions"],
        "refund" => $row["refund"],
        "amount" => (float)$row["amount"]
    ];

    if (isset($row["transactions"]) && (int)$row["transactions"] > $res["max_count"]) {
        $res["max_count"] = (int)$row["transactions"];
    }

    $res["count_items"][] = $entry;
}

send_http_status_and_exit("200", json_encode($res));
?> 