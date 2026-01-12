<?php
// V3 - OPTIMIZED (fixes slow response > 1 minute issue)
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

// OPTIMIZED V3 - Performance improvements:
// 1. Uses generated columns (trans_type, status, amount) instead of JSON_EXTRACT
// 2. Uses trans_id column (indexed) instead of JSON_EXTRACT
// 3. Uses EXISTS instead of NOT IN subquery for better performance
// 4. Single query instead of two separate queries
// 5. Removed redundant DISTINCT on SELECT

// Main optimized query
$query = "
SELECT 
    accounts.id,
    accounts.companyname AS business,
    COUNT(DISTINCT uot.trans_id) AS transactions,
    SUM(
        CASE 
            WHEN uot.trans_type IN ('Refund', 'Return')
            THEN uot.amount
            ELSE 0 
        END
    ) AS refund,
    SUM(
        CASE
            WHEN uot.trans_id IS NOT NULL AND uot.trans_id != ''
            THEN uot.amount
            ELSE 0
        END
    ) - SUM(
        CASE 
            WHEN uot.trans_type IN ('Refund', 'Return')
            THEN uot.amount
            ELSE 0 
        END
    ) AS amount
FROM unique_olapay_transactions uot
INNER JOIN terminals ON terminals.serial = uot.serial
INNER JOIN accounts ON terminals.vendors_id = accounts.id
WHERE uot.lastmod > :starttime 
AND uot.lastmod < :endtime
-- Use generated column 'status' instead of JSON_EXTRACT
AND uot.status NOT IN ('', 'FAIL', 'REFUNDED')
-- Use generated column 'trans_type' instead of JSON_EXTRACT
AND uot.trans_type NOT IN ('Return Cash', '', 'Auth')
-- Use trans_id column instead of JSON_EXTRACT
AND uot.trans_id IS NOT NULL
AND uot.trans_id != ''
-- Filter for olapay-only merchants using EXISTS (more efficient than NOT IN)
AND EXISTS (
    SELECT 1 
    FROM terminal_payment_methods tpm
    INNER JOIN payment_methods pm ON pm.id = tpm.payment_method_id
    WHERE tpm.terminal_id = terminals.id AND pm.code = 'olapay'
)
AND NOT EXISTS (
    SELECT 1 
    FROM terminal_payment_methods tpm
    INNER JOIN payment_methods pm ON pm.id = tpm.payment_method_id
    WHERE tpm.terminal_id = terminals.id AND pm.code = 'olapos'
)
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