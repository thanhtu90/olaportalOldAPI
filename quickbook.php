<?php
require_once('vendor/autoload.php');
include_once "./library/utils.php";
use QuickBooksOnline\API\DataService\DataService;
use QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2AccessToken;

$config = require './config/qb_config.php';

ini_set('max_execution_time', 1800);

header('Content-Type: application/json');

enable_cors();

$pdo = connect_db_and_set_http_method( "GET,POST,DELETE" );
$tablename = "accounts";
$msgforinvalidjwt = "No permission";
$msgforinvalidid = "Invalid request";
$msgforsqlerror = "System error";
$msgforemailtaken = "Email taken";
$msgforsqlsuccess = "Operation completed";

$vendors_id = $_REQUEST["id"];
$startTime = strtotime($_REQUEST["fromDate"]);
$endTime = strtotime($_REQUEST["toDate"]) + 86400;

// Optional browser-supplied IANA timezone (e.g. "America/Chicago"). The
// portal sends this with getQuickBook() so the export memo formats
// `orders.lastMod` in the SAME zone the bookkeeper is viewing in the
// portal sidenav. Validated in buildAllOrdersJson() before use.
$requestTz = isset($_REQUEST["tz"]) ? (string)$_REQUEST["tz"] : '';

error_log(print_r($vendors_id,true));

error_log(print_r($startTime,true));

error_log(print_r($endTime,true));

// Build one QuickBooks SalesItemLine. Amount/UnitPrice are rounded to 2dp so
// the receipt total in QBO matches the $X.XX subtotals shown in the portal.
function qb_build_line($description, $amount, $qty = 1, $taxable = false) {
    $amt = round((float)$amount, 2);
    $qty = max(1, (int)$qty);
    $unit = round($amt / $qty, 2);
    return [
        'Description' => (string)$description,
        'Amount'      => $amt,
        'DetailType'  => 'SalesItemLineDetail',
        'SalesItemLineDetail' => [
            'UnitPrice'  => $unit,
            'Qty'        => $qty,
            'TaxCodeRef' => ['value' => $taxable ? 'TAX' : 'NON'],
        ],
    ];
}

// Build the QuickBooks SalesReceipt batch array for all orders in the window.
//
// Historically this ran a single cross-joined query over orders +
// orderItems (parent) + orderItems (modifier) + ordersPayments. That caused
// two distinct bugs:
//   1. Modifier rows were ALSO returned as their own `oi` parent rows because
//      `oi` was not filtered to `items_id = 0`, so every modifier shipped
//      twice: once merged into the parent line ("Brewed Coffee; L") and once
//      as a standalone line ("L  $0.50").
//   2. The payment cross-join inflated tech-fee/tip sums when an order had
//      multiple items or multiple payments.
// The payload now matches the transaction-detail sidenav (one parent line
// per item at its BASE price, plus one line per modifier at the modifier's
// own price) and sends primary tax via TxnTaxDetail with secondary taxes as
// additional line items. See CLAUDE.md for format decisions.
function buildAllOrdersJson($pdo, $vendors_id, $startTime, $endTime, $requestTz = '') {
    // Pick the timezone used to format `orders.lastMod` (UTC unix epoch)
    // in the export memo. Priority:
    //   1. Browser tz from the portal request (`?tz=...`) — matches exactly
    //      what the bookkeeper sees in the sidenav.
    //   2. `stores.timezone` for the vendor — best guess when the portal
    //      client didn't send one (e.g. legacy cron/manual hits).
    //   3. PHP default (America/Los_Angeles per README).
    // Without this layering the memo drifts whenever the bookkeeper views
    // the portal from outside the store's local timezone — which was the
    // "02:13 PM portal vs 12:13 PM QB" bug.
    $tzObj   = null;
    $pickedTz = '';

    if ($requestTz !== '') {
        try {
            $tzObj    = new DateTimeZone($requestTz);
            $pickedTz = $requestTz;
        } catch (Exception $e) {
            error_log('QB export: invalid request timezone "' . $requestTz . '", falling through to stores.timezone');
        }
    }

    if ($tzObj === null) {
        $stmt_tz = $pdo->prepare("
            SELECT timezone
            FROM stores
            WHERE vendor_id = :vendors_id
              AND timezone IS NOT NULL
              AND timezone <> ''
            ORDER BY id ASC
            LIMIT 1
        ");
        $stmt_tz->execute([':vendors_id' => $vendors_id]);
        $storeTz = $stmt_tz->fetchColumn();
        if ($storeTz) {
            try {
                $tzObj    = new DateTimeZone($storeTz);
                $pickedTz = $storeTz;
            } catch (Exception $e) {
                error_log('QB export: invalid store timezone "' . $storeTz . '", falling through to PHP default');
            }
        }
    }

    if ($tzObj === null) {
        $fallback = date_default_timezone_get() ?: 'America/Los_Angeles';
        try {
            $tzObj    = new DateTimeZone($fallback);
            $pickedTz = $fallback;
        } catch (Exception $e) {
            $tzObj    = new DateTimeZone('America/Los_Angeles');
            $pickedTz = 'America/Los_Angeles';
        }
    }
    error_log('QB export: formatting timestamps in timezone ' . $pickedTz);

    // Keep `lastMod` as a raw unix int and format it in PHP — that's the
    // same timestamp the portal sidenav shows (the frontend does
    // `new Date(lastMod * 1000)` which renders in browser-local time;
    // bookkeepers reconcile against the store's local clock).
    $stmt_orders = $pdo->prepare("
        SELECT id, uuid, orderReference, lastMod,
               subTotal, tax, total, secondary_tax_list
        FROM orders
        WHERE lastMod >= :startTime
          AND lastMod < :endTime
          AND vendors_id = :vendors_id
        ORDER BY lastMod DESC
    ");
    $stmt_orders->execute([
        ':startTime'  => $startTime,
        ':endTime'    => $endTime,
        ':vendors_id' => $vendors_id,
    ]);

    // Parent items only — items_id = 0 is the flag for top-level order items
    // (same convention used by orderDetail2.php for the portal sidenav).
    $stmt_items = $pdo->prepare("
        SELECT id, description, price, qty, taxable, discount
        FROM orderItems
        WHERE orderUuid = :uuid AND items_id = 0
        ORDER BY id ASC
    ");

    $stmt_mods = $pdo->prepare("
        SELECT description, price, taxable
        FROM orderItems
        WHERE orderUuid = :uuid AND items_id = :parent_id
        ORDER BY id ASC
    ");

    // One row per order — prevents the cross-join inflation when an order
    // has multiple items or multiple payment rows.
    $stmt_pays = $pdo->prepare("
        SELECT COALESCE(SUM(techFee), 0) AS totalTechFee,
               COALESCE(SUM(tips),    0) AS totalTips
        FROM ordersPayments
        WHERE orderUuid = :uuid
    ");

    $batchArray       = [];
    $batchItemRequest = [];
    $orderCount       = 0;

    while ($order = $stmt_orders->fetch(PDO::FETCH_ASSOC)) {
        $uuid = $order['uuid'];
        if ($uuid === null || $uuid === '') {
            continue;
        }

        $line = [];
        $lineDiscountTotal = 0.0;

        // ── Parent items + modifiers ────────────────────────────────────
        $stmt_items->execute([':uuid' => $uuid]);
        while ($item = $stmt_items->fetch(PDO::FETCH_ASSOC)) {
            $itemQty     = max(1, (int)$item['qty']);
            $itemPrice   = (float)$item['price'];
            $itemTaxable = ((int)$item['taxable'] === 1);

            // Parent line at BASE price × qty (modifiers are NOT rolled in).
            $line[] = qb_build_line(
                $item['description'],
                $itemPrice * $itemQty,
                $itemQty,
                $itemTaxable
            );

            // Item-level discount (e.g. "Item Discount" shown in the sidenav).
            $lineDiscountTotal += (float)$item['discount'] * $itemQty;

            // One QB line per modifier, at the modifier's own price.
            // Multiply by the PARENT qty so `sum(parent + mods)` still equals
            // the order subTotal when qty > 1.
            $stmt_mods->execute([':uuid' => $uuid, ':parent_id' => (int)$item['id']]);
            while ($mod = $stmt_mods->fetch(PDO::FETCH_ASSOC)) {
                $modPrice   = (float)$mod['price'];
                $modTaxable = ((int)$mod['taxable'] === 1);
                if (abs($modPrice) < 0.00001) {
                    // Skip zero-priced modifiers (e.g. "Small" sizing with no
                    // upcharge). They'd show as "$0.00 S" rows in QBO which
                    // the user explicitly flagged as noise.
                    continue;
                }
                $line[] = qb_build_line(
                    $mod['description'],
                    $modPrice * $itemQty,
                    1,
                    $modTaxable
                );
            }
        }

        // ── Discount (order-level, negative line) ───────────────────────
        if ($lineDiscountTotal > 0.00001) {
            $discLine = qb_build_line('Discount', -$lineDiscountTotal, 1, false);
            // qb_build_line clamps qty >= 1 but UnitPrice must stay negative.
            $discLine['SalesItemLineDetail']['UnitPrice'] = round(-$lineDiscountTotal, 2);
            $line[] = $discLine;
        }

        // ── Secondary taxes as additional plain line items ──────────────
        // secondary_tax_list is stored as a JSON object like
        //   {"0":{"name":"CRV","taxTotalAmount":0.25}, ...}
        // Each non-zero entry becomes its own NON-tax service line so the
        // QBO receipt total picks them up (TxnTaxDetail below only covers
        // the primary tax).
        if (!empty($order['secondary_tax_list'])) {
            $parsed = json_decode($order['secondary_tax_list'], true);
            if (is_array($parsed)) {
                foreach ($parsed as $t) {
                    if (!is_array($t)) {
                        continue;
                    }
                    $amt = isset($t['taxTotalAmount']) ? (float)$t['taxTotalAmount'] : 0.0;
                    if ($amt <= 0.00001) {
                        continue;
                    }
                    $name = isset($t['name']) && $t['name'] !== '' ? (string)$t['name'] : 'Secondary Tax';
                    $line[] = qb_build_line($name, $amt, 1, false);
                }
            }
        }

        // ── Primary tax as a plain line item ────────────────────────────
        // Why a line item instead of TxnTaxDetail.TotalTax: QBO Automated
        // Sales Tax (AST) silently ignores TotalTax unless a valid
        // TxnTaxCodeRef is also supplied, and we don't map vendor tax codes
        // here. A NON-tax line item works on both AST and manual-tax
        // accounts and guarantees the receipt total absorbs the tax (this
        // is the same pattern we use for Tech Fee / Tips / secondary tax).
        $primaryTax = (float)$order['tax'];
        if ($primaryTax > 0.00001) {
            $line[] = qb_build_line('Tax', $primaryTax, 1, false);
        }

        // ── Tech fee + tips (summed per order, not per joined row) ──────
        $stmt_pays->execute([':uuid' => $uuid]);
        $payRow  = $stmt_pays->fetch(PDO::FETCH_ASSOC) ?: [];
        $techFee = (float)($payRow['totalTechFee'] ?? 0);
        $tipsAmt = (float)($payRow['totalTips']    ?? 0);

        if ($techFee > 0.00001) {
            $line[] = qb_build_line('Tech Fee', $techFee, 1, false);
        }
        if ($tipsAmt > 0.00001) {
            $line[] = qb_build_line('Tips', $tipsAmt, 1, false);
        }

        // ── Date + private note ─────────────────────────────────────────
        // QBO SalesReceipt.TxnDate is date-only. Stash the full datetime
        // and the portal's full order reference in PrivateNote so
        // bookkeepers can reconcile QBO entries against the portal
        // (matches the "Ref #<id> / <orderReference>" shown in the
        // order-detail sidenav).
        $lastModTs = (int)$order['lastMod'];
        if ($lastModTs > 0) {
            $dt = (new DateTime('@' . $lastModTs))->setTimezone($tzObj);
            $orderDate     = $dt->format('Y-m-d');
            $orderDateTime = $dt->format('Y-m-d h:i A');
        } else {
            $orderDate     = date('Y-m-d');
            $orderDateTime = '';
        }

        $fullRef = '#' . (string)$order['id'] . ' / ' . (string)$order['orderReference'];

        $noteParts = [];
        if ($orderDateTime !== '') {
            $noteParts[] = 'Order time: ' . $orderDateTime;
        }
        $noteParts[] = 'Order reference: ' . $fullRef;

        $salesReceipt = [
            'TxnDate'     => $orderDate,
            'DocNumber'   => (string)$order['orderReference'],
            'PrivateNote' => implode(' | ', $noteParts),
            'Line'        => $line,
        ];

        // ── Batch (30 receipts max per QBO batch request) ───────────────
        $orderCount++;
        $batchItemRequest[] = [
            'bId'          => 'bid' . (string)$orderCount,
            'operation'    => 'create',
            'SalesReceipt' => $salesReceipt,
        ];

        if ($orderCount >= 30) {
            $batchArray[]     = ['BatchItemRequest' => $batchItemRequest];
            $batchItemRequest = [];
            $orderCount       = 0;
        }
    }

    if (!empty($batchItemRequest)) {
        $batchArray[] = ['BatchItemRequest' => $batchItemRequest];
    }

    error_log('QB batch count: ' . count($batchArray));
    return $batchArray;
}

$payloadArray = buildAllOrdersJson($pdo,$vendors_id,$startTime,$endTime,$requestTz);

$batchCount = count($payloadArray);

error_log('Batch count: ' . print_r($batchCount, true));

foreach ($payloadArray as $payload) {
    $payload_json = json_encode($payload);
    $stmt_qb_q = $pdo->prepare("
        INSERT INTO quickbooks_export_queue (
        vendor_id,
        batchCount,
        payload,
        status,
        lastmod
        ) VALUES (
        :vendor_id,
        :batch_count,
        :payload,
        :status,
        CURRENT_TIMESTAMP
        )
    ");
    $stmt_qb_q->execute([
        ':vendor_id' => (int)$vendors_id,
        ':batch_count' => (int)$batchCount,
        ':payload' => $payload_json,
        ':status' => 1
    ]);
}

// Drain the queue to QuickBooks synchronously so a single `quickbook.php`
// call both builds the batches and pushes them to QBO. Previously this file
// only enqueued, which left rows stuck in `quickbooks_export_queue` until
// `qb_export.php` was called separately — the reason merchants saw no sales
// in QBO even after a 200 response here.
include_once './library/qb_export_lib.php';
$exportResult = flushQuickbooksQueue($pdo, (int)$vendors_id, $config);
error_log('QB export summary: ' . json_encode($exportResult));

echo json_encode([
    'status'      => 'success',
    'batch_count' => count($payloadArray),
    'qb_export'   => $exportResult,
]);
?>
