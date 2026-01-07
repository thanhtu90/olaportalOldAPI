<?php
/**
 * Script to extract order items from JSON and generate SQL INSERT statements
 */

// The JSON string provided by the user
$jsonString = '{"hasInventory":false,"payments":"[{\"amtPaid\":49.630001068115234,\"editEmployeeId\":0,\"editEmployeePIN\":\"NONE\",\"editTermSerial\":\"A157FN230400099\",\"employeeId\":0,\"employeePIN\":\"NONE\",\"id\":3384,\"lastMod\":1762137669,\"oUUID\":\"7bfbd9d3-e87a-47ab-8f03-5bab56d28911\",\"olapayApprovalId\":\"5294642977\",\"orderID\":\"SA1.00099-CRD-35-4053\",\"orderReference\":\"4053\",\"pUUID\":\"3877e923-b9e5-4b08-acd5-9498dafcc08e\",\"payDate\":\"Nov 2, 2025 18:41:08\",\"processTermSerial\":\"A157FN230400099\",\"refNumber\":\"4400\",\"refund\":0.0,\"status\":\"PAID\",\"techfee\":0.0,\"tips\":0.0,\"total\":49.63}]","items":"[{\"item\":{\"amountOnHand\":99999,\"cost\":0.0,\"crv\":0.0,\"crv_taxable\":0,\"description\":\"can soda\",\"ebt\":0,\"group\":110,\"iUUID\":\"043ba71d-8942-4c0c-ae88-d545f7c25d33\",\"id\":1197,\"isInventory\":false,\"kitchenPrint\":0,\"labelPrint\":0,\"largeImage\":\"#009cef\",\"lastMod\":1732317773,\"manualPrice\":0,\"notes\":\"\",\"portalInventoryId\":-1,\"price\":2.5,\"smallImage\":\"\",\"status\":0,\"taxRate\":10.75,\"taxable\":1,\"upc\":\"\",\"weighted\":0},\"mods\":[],\"orderItem\":{\"cost\":2.5,\"crv\":0.0,\"crv_taxable\":0,\"description\":\"can soda\",\"discount\":0.0,\"ebt\":0,\"group\":110,\"iUUID\":\"043ba71d-8942-4c0c-ae88-d545f7c25d33\",\"id\":18345,\"itemAddedDateTime\":\"Nov 2, 2025 18:10:17\",\"itemDiscount\":0.0,\"itemId\":1197,\"kitchenPrint\":0,\"labelPrint\":0,\"lastMod\":1762135817,\"notes\":\"\",\"oUUID\":\"7bfbd9d3-e87a-47ab-8f03-5bab56d28911\",\"orderReference\":4053,\"price\":2.58,\"qty\":1,\"status\":\"READY_TO_PAY\",\"taxAmount\":10.75,\"taxable\":1,\"weight\":1.0},\"orderModsItems\":[]},{\"item\":{\"amountOnHand\":99999,\"cost\":0.0,\"crv\":0.0,\"crv_taxable\":0,\"description\":\"Horchata Grande\",\"ebt\":0,\"group\":111,\"iUUID\":\"89681118-629d-4d76-bbe8-7bad350f2a92\",\"id\":1217,\"isInventory\":false,\"kitchenPrint\":0,\"labelPrint\":0,\"largeImage\":\"#709dc6\",\"lastMod\":1732317773,\"manualPrice\":0,\"notes\":\"\",\"portalInventoryId\":-1,\"price\":5.5,\"smallImage\":\"\",\"status\":0,\"taxRate\":10.75,\"taxable\":1,\"upc\":\"\",\"weighted\":0},\"mods\":[],\"orderItem\":{\"cost\":5.5,\"crv\":0.0,\"crv_taxable\":0,\"description\":\"Horchata Grande\",\"discount\":0.0,\"ebt\":0,\"group\":111,\"iUUID\":\"89681118-629d-4d76-bbe8-7bad350f2a92\",\"id\":18346,\"itemAddedDateTime\":\"Nov 2, 2025 18:10:20\",\"itemDiscount\":0.0,\"itemId\":1217,\"kitchenPrint\":0,\"labelPrint\":0,\"lastMod\":1762135820,\"notes\":\"\",\"oUUID\":\"7bfbd9d3-e87a-47ab-8f03-5bab56d28911\",\"orderReference\":4053,\"price\":5.67,\"qty\":1,\"status\":\"READY_TO_PAY\",\"taxAmount\":10.75,\"taxable\":1,\"weight\":1.0},\"orderModsItems\":[]},{\"item\":{\"amountOnHand\":99999,\"cost\":0.0,\"crv\":0.0,\"crv_taxable\":0,\"description\":\"torta reg\",\"ebt\":0,\"group\":118,\"iUUID\":\"36b1d359-10c3-4cc1-848c-d5d5a1d7e6d8\",\"id\":1341,\"isInventory\":false,\"kitchenPrint\":0,\"labelPrint\":0,\"largeImage\":\"#008c92\",\"lastMod\":1732317774,\"manualPrice\":0,\"notes\":\"\",\"portalInventoryId\":-1,\"price\":12.5,\"smallImage\":\"\",\"status\":0,\"taxRate\":10.75,\"taxable\":1,\"upc\":\"\",\"weighted\":0},\"mods\":[{\"amountOnHand\":999999,\"cost\":0.0,\"crv\":0.0,\"crv_taxable\":0,\"description\":\"Pollo Asado\",\"ebt\":0,\"group\":141,\"iUUID\":\"d056048e-c17c-4d77-967a-cef434666b7a\",\"id\":1667,\"isInventory\":false,\"kitchenPrint\":0,\"labelPrint\":0,\"largeImage\":\"\",\"lastMod\":1732317775,\"manualPrice\":0,\"notes\":\"\",\"portalInventoryId\":-1,\"price\":0.0,\"smallImage\":\"\",\"status\":0,\"taxRate\":0.0,\"taxable\":1,\"upc\":\"\",\"weighted\":0}],\"orderItem\":{\"cost\":12.5,\"crv\":0.0,\"crv_taxable\":0,\"description\":\"torta reg\",\"discount\":0.0,\"ebt\":0,\"group\":118,\"iUUID\":\"36b1d359-10c3-4cc1-848c-d5d5a1d7e6d8\",\"id\":18347,\"itemAddedDateTime\":\"Nov 2, 2025 18:10:33\",\"itemDiscount\":0.0,\"itemId\":1341,\"kitchenPrint\":0,\"labelPrint\":0,\"lastMod\":1762135833,\"notes\":\"\",\"oUUID\":\"7bfbd9d3-e87a-47ab-8f03-5bab56d28911\",\"orderReference\":4053,\"price\":12.88,\"qty\":1,\"status\":\"READY_TO_PAY\",\"taxAmount\":10.75,\"taxable\":1,\"weight\":1.0},\"orderModsItems\":[{\"description\":\"Pollo Asado\",\"group\":141,\"iUUID\":\"d056048e-c17c-4d77-967a-cef434666b7a\",\"id\":4506,\"itemAddedDateTime\":\"Nov 2, 2025 18:10:33\",\"itemId\":1667,\"lastMod\":1762135833,\"oUUID\":\"7bfbd9d3-e87a-47ab-8f03-5bab56d28911\",\"orderReference\":4053,\"price\":0.0,\"status\":\"READY_TO_PAY\",\"taxExempt\":1}]},{\"item\":{\"amountOnHand\":99999,\"cost\":0.0,\"crv\":0.0,\"crv_taxable\":0,\"description\":\"Consome\",\"ebt\":0,\"group\":119,\"iUUID\":\"06df2bd4-7861-40ac-bf04-6d07c691889d\",\"id\":1345,\"isInventory\":false,\"kitchenPrint\":0,\"labelPrint\":0,\"largeImage\":\"#b768a2\",\"lastMod\":1732317774,\"manualPrice\":0,\"notes\":\"\",\"portalInventoryId\":-1,\"price\":3.0,\"smallImage\":\"\",\"status\":0,\"taxRate\":10.75,\"taxable\":1,\"upc\":\"\",\"weighted\":0},\"mods\":[],\"orderItem\":{\"cost\":3.0,\"crv\":0.0,\"crv_taxable\":0,\"description\":\"Consome\",\"discount\":0.0,\"ebt\":0,\"group\":119,\"iUUID\":\"06df2bd4-7861-40ac-bf04-6d07c691889d\",\"id\":18348,\"itemAddedDateTime\":\"Nov 2, 2025 18:10:39\",\"itemDiscount\":0.0,\"itemId\":1345,\"kitchenPrint\":0,\"labelPrint\":0,\"lastMod\":1762135839,\"notes\":\"\",\"oUUID\":\"7bfbd9d3-e87a-47ab-8f03-5bab56d28911\",\"orderReference\":4053,\"price\":3.09,\"qty\":1,\"status\":\"READY_TO_PAY\",\"taxAmount\":10.75,\"taxable\":1,\"weight\":1.0},\"orderModsItems\":[]},{\"item\":{\"amountOnHand\":99999,\"cost\":0.0,\"crv\":0.0,\"crv_taxable\":0,\"description\":\"quesabirria \",\"ebt\":0,\"group\":119,\"iUUID\":\"819262ce-5a1b-489d-82b6-4d27c532f479\",\"id\":1347,\"isInventory\":false,\"kitchenPrint\":0,\"labelPrint\":0,\"largeImage\":\"#00aeef\",\"lastMod\":1761075999,\"manualPrice\":0,\"notes\":\"\",\"portalInventoryId\":-1,\"price\":5.0,\"smallImage\":\"\",\"status\":0,\"taxRate\":10.75,\"taxable\":1,\"upc\":\"\",\"weighted\":0},\"mods\":[],\"orderItem\":{\"cost\":5.0,\"crv\":0.0,\"crv_taxable\":0,\"description\":\"quesabirria \",\"discount\":0.0,\"ebt\":0,\"group\":119,\"iUUID\":\"819262ce-5a1b-489d-82b6-4d27c532f479\",\"id\":18349,\"itemAddedDateTime\":\"Nov 2, 2025 18:10:41\",\"itemDiscount\":0.0,\"itemId\":1347,\"kitchenPrint\":0,\"labelPrint\":0,\"lastMod\":1762135841,\"notes\":\"\",\"oUUID\":\"7bfbd9d3-e87a-47ab-8f03-5bab56d28911\",\"orderReference\":4053,\"price\":5.15,\"qty\":4,\"status\":\"READY_TO_PAY\",\"taxAmount\":10.75,\"taxable\":1,\"weight\":1.0},\"orderModsItems\":[]}]","orders":"[{\"employeeId\":0,\"employeePIN\":\"NONE\",\"id\":4053,\"lastMod\":1762137669,\"notes\":\"\",\"oUUID\":\"7bfbd9d3-e87a-47ab-8f03-5bab56d28911\",\"orderDate\":\"Nov 2, 2025 18:10:17\",\"orderName\":\"5\",\"status\":\"PAID\",\"subTotal\":44.80501174926758,\"tax\":4.820409774780273,\"total\":49.630001068115234}]","groups":"[{\"description\":\"Soft Drinks\",\"gUUID\":\"4cc95b6c-9447-4387-b8b9-142e9a4b82c6\",\"groupType\":\"PARENT_GROUP\",\"id\":110,\"lastMod\":1732317773,\"notes\":\"\"},{\"description\":\"Aguas Frescas\",\"gUUID\":\"47472175-f534-47b7-8469-c6e0bec8a4fe\",\"groupType\":\"PARENT_GROUP\",\"id\":111,\"lastMod\":1732317773,\"notes\":\"\"},{\"description\":\"Burritos Y Tortas\",\"gUUID\":\"db717263-6fff-4a18-87d1-91dc33f085cd\",\"groupType\":\"PARENT_GROUP\",\"id\":118,\"lastMod\":1732317773,\"notes\":\"\"},{\"description\":\"Tacos Y Quesadilla\",\"gUUID\":\"1289b2d0-f95e-4d7f-94bc-dfbb6d6b86c0\",\"groupType\":\"PARENT_GROUP\",\"id\":119,\"lastMod\":1732317773,\"notes\":\"\"},{\"description\":\"Tacos Y Quesadilla\",\"gUUID\":\"1289b2d0-f95e-4d7f-94bc-dfbb6d6b86c0\",\"groupType\":\"PARENT_GROUP\",\"id\":119,\"lastMod\":1732317773,\"notes\":\"\"}]","termId":"\"A1.00099\"","store_uuid":"\"f575017e-4fc6-4511-9a55-1e38cde1a83e\""}';

// Convert status string to integer
function getStatusCode($status) {
    if (is_numeric($status)) {
        return (int)$status;
    }
    
    if (empty($status)) {
        return 0; // Default to READY_TO_PAY
    }
    
    $statusMap = [
        "READY_TO_PAY" => 0,
        "PAID" => 1,
        "CANCELLED" => 2,
        "PENDING" => 3
    ];
    return $statusMap[strtoupper($status)] ?? 0;
}

// Escape SQL string
function escapeSql($value) {
    if ($value === null) {
        return 'NULL';
    }
    if (is_numeric($value)) {
        return $value;
    }
    return "'" . addslashes($value) . "'";
}

// Parse the JSON
$data = json_decode($jsonString);
$items_json = json_decode($data->items);
$orders_json = json_decode($data->orders);
$groups_json = json_decode($data->groups);

// Build group names map
$group_names = [];
foreach ($groups_json as $group) {
    $group_names[$group->id] = $group->description;
}

// Get order UUID
$order_uuid = $orders_json[0]->oUUID;
$orderReference = $orders_json[0]->id; // Using id as orderReference

// Try to connect to database and get order information
$orders_id = null;
$agents_id = null;
$vendors_id = null;
$terminals_id = null;

// Check if values are provided as command line arguments
if (isset($argv[1]) && isset($argv[2]) && isset($argv[3]) && isset($argv[4])) {
    $orders_id = intval($argv[1]);
    $agents_id = intval($argv[2]);
    $vendors_id = intval($argv[3]);
    $terminals_id = intval($argv[4]);
} else {
    // Try to get from database
    try {
        include_once './config/database.php';
        $databaseService = new DatabaseService();
        $pdo = $databaseService->getConnection();
        
        // Query for order details
        $stmt = $pdo->prepare("SELECT id, agents_id, vendors_id, terminals_id FROM orders WHERE uuid = ? LIMIT 1");
        $stmt->execute([$order_uuid]);
        $order_row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($order_row) {
            $orders_id = $order_row['id'];
            $agents_id = $order_row['agents_id'];
            $vendors_id = $order_row['vendors_id'];
            $terminals_id = $order_row['terminals_id'];
        }
    } catch (Exception $e) {
        // Database connection failed, will use placeholders
    }
}

// If still not found, use numeric placeholders (0) that are easy to find/replace
if ($orders_id === null || $agents_id === null || $vendors_id === null || $terminals_id === null) {
    echo "-- NOTE: Order not found in database. Using placeholder values (0).\n";
    echo "-- Usage: php generate_order_items_sql.php <orders_id> <agents_id> <vendors_id> <terminals_id>\n";
    echo "-- Or replace all occurrences of 0 for agents_id, vendors_id, terminals_id, orders_id with actual values\n\n";
    $orders_id = 0;
    $agents_id = 0;
    $vendors_id = 0;
    $terminals_id = 0;
}

echo "-- ============================================================\n";
echo "-- SQL INSERT statements for order items\n";
echo "-- Order UUID: $order_uuid\n";
echo "-- Order Reference: $orderReference\n";
echo "-- Orders ID: $orders_id\n";
echo "-- Agents ID: $agents_id\n";
echo "-- Vendors ID: $vendors_id\n";
echo "-- Terminals ID: $terminals_id\n";
echo "-- ============================================================\n\n";

// Process each item
$item_counter = 0;
foreach ($items_json as $index => $item_data) {
    $orderItem = $item_data->orderItem;
    $item = $item_data->item;
    
    // Convert itemAddedDateTime to timestamp
    $itemAddedDateTime = strtotime($orderItem->itemAddedDateTime);
    
    // Get group name
    $group_name = $group_names[$orderItem->group] ?? '';
    
    $item_counter++;
    
    // Build main item INSERT
    echo "-- ============================================================\n";
    echo "-- Main Item $item_counter: " . $orderItem->description . "\n";
    echo "-- Item UUID: " . $orderItem->iUUID . "\n";
    echo "-- ============================================================\n";
    
    $sql = "INSERT INTO orderItems (
    itemUuid, orderUuid, agents_id, vendors_id, terminals_id,
    group_name, orders_id, cost, description, group_id,
    notes, price, taxable, qty, items_id, discount, orderReference,
    taxamount, itemid, ebt, crv, crv_taxable, itemDiscount,
    status, itemsAddedDateTime, lastMod, kitchenPrint, labelPrint, weight
) VALUES (
    " . escapeSql($orderItem->iUUID) . ",
    " . escapeSql($order_uuid) . ",
    " . intval($agents_id) . ",
    " . intval($vendors_id) . ",
    " . intval($terminals_id) . ",
    " . escapeSql($group_name) . ",
    " . intval($orders_id) . ",
    " . $orderItem->cost . ",
    " . escapeSql($orderItem->description) . ",
    " . $orderItem->group . ",
    " . escapeSql($item->notes ?? '') . ",
    " . $orderItem->price . ",
    " . $orderItem->taxable . ",
    " . $orderItem->qty . ",
    0,
    " . ($orderItem->discount ?? 0) . ",
    " . $orderReference . ",
    " . ($orderItem->taxAmount ?? 0) . ",
    " . ($orderItem->itemId ?? 0) . ",
    " . ($orderItem->ebt ?? 0) . ",
    " . ($orderItem->crv ?? 0) . ",
    " . ($orderItem->crv_taxable ?? 0) . ",
    " . ($orderItem->itemDiscount ?? 0) . ",
    " . getStatusCode($orderItem->status) . ",
    " . $itemAddedDateTime . ",
    " . $orderItem->lastMod . ",
    " . ($orderItem->kitchenPrint ?? 0) . ",
    " . ($orderItem->labelPrint ?? 0) . ",
    " . ($orderItem->weight ?? 1.0) . "
);\n\n";
    
    echo $sql;
    
    // Process modifiers (orderModsItems)
    if (isset($item_data->orderModsItems) && is_array($item_data->orderModsItems) && count($item_data->orderModsItems) > 0) {
        echo "-- NOTE: For the modifier below, replace LAST_INSERT_ID() with the actual ID\n";
        echo "--       returned after inserting the parent item above\n\n";
        
        foreach ($item_data->orderModsItems as $mod_index => $mod) {
            $modItemAddedDateTime = isset($mod->itemAddedDateTime) ? strtotime($mod->itemAddedDateTime) : time();
            
            echo "-- Modifier for Item $item_counter: " . $mod->description . "\n";
            $mod_sql = "INSERT INTO orderItems (
    itemUuid, orderUuid, agents_id, vendors_id, terminals_id,
    group_name, orders_id, cost, description, group_id,
    notes, price, taxable, qty, items_id, discount, orderReference,
    taxamount, itemid, ebt, crv, crv_taxable, itemDiscount,
    status, itemsAddedDateTime, lastMod, kitchenPrint, labelPrint, weight
) VALUES (
    " . escapeSql($mod->iUUID ?? null) . ",
    " . escapeSql($order_uuid) . ",
    " . intval($agents_id) . ",
    " . intval($vendors_id) . ",
    " . intval($terminals_id) . ",
    NULL,
    " . intval($orders_id) . ",
    " . ($mod->cost ?? 0) . ",
    " . escapeSql($mod->description) . ",
    " . ($mod->group ?? 0) . ",
    '',
    " . ($mod->price ?? 0) . ",
    " . ($mod->taxable ?? 1) . ",
    1,
    LAST_INSERT_ID(),
    0,
    " . $orderReference . ",
    0,
    " . ($mod->itemId ?? 0) . ",
    0,
    0,
    0,
    0,
    0,
    " . $modItemAddedDateTime . ",
    " . ($mod->lastMod ?? time()) . ",
    0,
    0,
    1.0
);\n\n";
            
            echo $mod_sql;
        }
    }
}

echo "-- ============================================================\n";
echo "-- Summary:\n";
echo "-- Total main items: " . count($items_json) . "\n";
$total_mods = 0;
foreach ($items_json as $item) {
    if (isset($item->orderModsItems) && is_array($item->orderModsItems)) {
        $total_mods += count($item->orderModsItems);
    }
}
echo "-- Total modifiers: $total_mods\n";
echo "-- Total INSERT statements: " . (count($items_json) + $total_mods) . "\n";
echo "-- ============================================================\n";
