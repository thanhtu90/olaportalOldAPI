<?php
require_once __DIR__ . '/vendor/autoload.php'; // Adjust path as needed

use Dotenv\Dotenv;
print_r("__DIR__: " . __DIR__ . " \n");

$dotenv = Dotenv::createImmutable(__DIR__); // __DIR__ is the directory where .env is located
$dotenv->load();

function getDeliveryServiceCode($service)
{
  $services = [
    "SELF-PICKUP" => 0,
    "DOORDASH" => 1,
    "UBER" => 2,
    "GRUBHUB" => 3
  ];

  return $services[strtoupper($service)] ?? 0;
}

// Convert status string to integer
function getStatusCode($status) {
    if (is_numeric($status)) {
        return (int)$status;
    }
    
    // Handle null or empty status
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

/**
 * Batch insert order items using SQL native batch insert with VALUES clause
 * 
 * @param PDO $pdo Database connection
 * @param array $items Array of order item data
 * @return array Array of inserted item IDs
 */
function batchInsertOrderItems($pdo, $items) {
    if (empty($items)) {
        return [];
    }
    
    try {
        // Define column names
        $columns = [
            'itemUuid', 'orderUuid', 'agents_id', 'vendors_id', 'terminals_id',
            'group_name', 'orders_id', 'cost', 'description', 'group_id',
            'notes', 'price', 'taxable', 'qty', 'items_id', 'discount', 'orderReference',
            'taxamount', 'itemid', 'ebt', 'crv', 'crv_taxable', 'itemDiscount',
            'status', 'itemsAddedDateTime', 'lastMod', 'tech_fee_rate', 'secondary_tax_list'
        ];
        
        // Build VALUES clause with placeholders
        $placeholders = [];
        $values = [];
        
        foreach ($items as $item) {
            $placeholders[] = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
            foreach ($columns as $column) {
                $values[] = $item[$column] ?? null;
            }
        }
        
        // Build the complete SQL
        $sql = "INSERT INTO orderItems (" . implode(', ', $columns) . ") VALUES " . implode(', ', $placeholders);
        
        // Execute batch insert
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        if ($stmt->rowCount() != count($items)) {
            print_r("SQL batch insert failed: " . $stmt->rowCount() . " != " . count($items) . " \n");
            print_r("SQL: " . $sql . " \n");
            print_r("Values: " . json_encode($values) . " \n");
            throw new Exception("SQL batch insert failed: " . $stmt->rowCount() . " != " . count($items) . " \n");
        }
        $inserted_ids = [];
        $first_id = $pdo->lastInsertId();
        for ($i = 0; $i < count($items); $i++) {
            $inserted_ids[] = $first_id + $i;
        }
        
        return $inserted_ids;
        
    } catch (PDOException $e) {
        print_r("SQL batch insert failed: " . $e->getMessage() . " \n");
        print_r("SQL: " . ($sql ?? 'SQL not built') . " \n");
        print_r("Values count: " . count($values ?? []) . " \n");
        throw $e;
    }
}

/**
 * Normalize item data by ensuring required fields have default values
 * 
 * @param array $itemData Raw item data array
 * @return array Normalized item data
 */
function normalizeItemData($itemData) {
    $normalized = $itemData;
    
    // Ensure notes is a string
    $normalized["notes"] = $itemData["notes"] ?? "";
    if (empty($normalized["notes"])) {
        $normalized["notes"] = "";
    }
    
    // Ensure taxAmount has a default value
    if (!isset($normalized["taxAmount"])) {
        $normalized["taxAmount"] = "0";
    }
    
    // Ensure itemId has a default value
    if (!isset($normalized["itemId"])) {
        $normalized["itemId"] = "0";
    }
    
    return $normalized;
}

/**
 * Get group name based on platform type
 * 
 * @param PDO $pdo Database connection
 * @param bool $isOnlinePlatform Whether this is an online platform order
 * @param int $groupId Group ID
 * @param array $groupNames Array of group names for non-online platforms
 * @return string Group name
 */
function getGroupName($pdo, $isOnlinePlatform, $groupId, $groupNames = []) {
    if ($isOnlinePlatform) {
        $stmt = $pdo->prepare("SELECT name FROM online_order_groups WHERE id = ?");
        $stmt->execute([$groupId]);
        $row = $stmt->fetch();
        return $row["name"] ?? '';
    } else {
        return $groupNames[$groupId] ?? '';
    }
}

/**
 * Calculate tech_fee_rate from JSON item data if payment was by card
 * 
 * @param object $item_json_item The item JSON object containing both "item" and "orderItem"
 * @param array $payments_json Payments JSON array to check payment method
 * @param string $orderReference Order reference to match payment
 * @return float Calculated tech_fee_rate or default_tech_fee_rate if not applicable
 */
function calculateTechFeeRate($item_json_item, $payments_json, $orderReference) {
    $default_tech_fee_rate = 0.0000;
    
    // Check if payment was by card (not CASH)
    $isCardPayment = false;
    for ($p = 0; $p < count($payments_json); $p++) {
        $payment_orderRef = isset($payments_json[$p]->{"orderReference"}) ? strval($payments_json[$p]->{"orderReference"}) : null;
        if ($payment_orderRef === strval($orderReference)) {
            $refNumber = isset($payments_json[$p]->{"refNumber"}) ? $payments_json[$p]->{"refNumber"} : null;
            // Check if payment was by card (not CASH)
            if ($refNumber !== null && strtoupper(trim($refNumber)) !== "CASH") {
                $isCardPayment = true;
                break;
            }
        }
    }
    
    if (!$isCardPayment) {
        return $default_tech_fee_rate;
    }
    
    // Get orderItem data
    if (!isset($item_json_item->{"orderItem"})) {
        return $default_tech_fee_rate;
    }
    
    $orderItem = $item_json_item->{"orderItem"};
    $orderItem_iUUID = isset($orderItem->{"iUUID"}) ? $orderItem->{"iUUID"} : null;
    $orderItem_price = isset($orderItem->{"price"}) ? floatval($orderItem->{"price"}) : 0;
    
    if (empty($orderItem_iUUID) || $orderItem_price <= 0) {
        return $default_tech_fee_rate;
    }
    
    // Get base item price from the same JSON element
    if (!isset($item_json_item->{"item"})) {
        return $default_tech_fee_rate;
    }
    
    $item = $item_json_item->{"item"};
    $item_iUUID = isset($item->{"iUUID"}) ? $item->{"iUUID"} : null;
    $item_price = isset($item->{"price"}) ? floatval($item->{"price"}) : 0;
    
    // Verify iUUID matches
    if ($item_iUUID !== $orderItem_iUUID || $item_price <= 0) {
        return $default_tech_fee_rate;
    }
    
    // Calculate tech_fee_rate if orderItem price > item price
    if ($orderItem_price > $item_price) {
        $tech_fee_rate = $orderItem_price / $item_price;
        
        // Ensure tech_fee_rate is within decimal(5,4) range (max 9.9999)
        if ($tech_fee_rate > 9.9999) {
            $tech_fee_rate = 9.9999;
        }
        
        return $tech_fee_rate;
    }
    
    return $default_tech_fee_rate;
}

/**
 * Build an order item row array for batch insertion
 * 
 * @param PDO $pdo Database connection
 * @param array $itemData Normalized item data
 * @param array $order Order data
 * @param bool $isOnlinePlatform Whether this is an online platform order
 * @param int $agents_id Agent ID
 * @param int $vendors_id Vendor ID
 * @param int $terminals_id Terminal ID
 * @param string $group_name Group name
 * @param int $orders_id Order ID
 * @param float|null $tech_fee_rate Optional tech_fee_rate to include in the row
 * @return array Order item row ready for batch insertion
 */
function buildOrderItemRow($pdo, $itemData, $order, $isOnlinePlatform, $agents_id, $vendors_id, $terminals_id, $group_name, $orders_id, $tech_fee_rate = 0.0000) {
    // Get iUUID for querying items table
    $iUUID = $isOnlinePlatform ? ($itemData["uuid"] ?? null) : ($itemData["iUUID"] ?? null);
    
    // Get crv from itemData
    $crv = $itemData["crv"] ?? 0;
    $crv_taxable = $itemData["crv_taxable"] ?? 0;
    
    // If crv is 0, try to get it from items table using iUUID
    if ($crv == 0 && $iUUID !== null) {
        try {
            $stmt = $pdo->prepare("SELECT crv FROM items WHERE uuid = ? LIMIT 1");
            $stmt->execute([$iUUID]);
            $itemRow = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($itemRow && isset($itemRow["crv"]) && $itemRow["crv"] !== null) {
                // crv is stored as JSON in items table, need to decode it
                $itemsCrv = $itemRow["crv"];
                if (is_string($itemsCrv)) {
                    $itemsCrvDecoded = json_decode($itemsCrv, true);
                    if (is_array($itemsCrvDecoded) && isset($itemsCrvDecoded["val"])) {
                        $crv = floatval($itemsCrvDecoded["val"]);
                        if (isset($itemsCrvDecoded["is_taxable"])) {
                            $crv_taxable = intval($itemsCrvDecoded["is_taxable"]);
                        }
                    } elseif (is_numeric($itemsCrvDecoded)) {
                        $crv = floatval($itemsCrvDecoded);
                    }
                } elseif (is_numeric($itemsCrv)) {
                    $crv = floatval($itemsCrv);
                } elseif (is_array($itemsCrv) && isset($itemsCrv["val"])) {
                    // PDO might return JSON as array directly
                    $crv = floatval($itemsCrv["val"]);
                    if (isset($itemsCrv["is_taxable"])) {
                        $crv_taxable = intval($itemsCrv["is_taxable"]);
                    }
                }
            }
        } catch (PDOException $e) {
            // If query fails, use the original crv value (0)
            print_r("Warning: Failed to query crv from items table for iUUID: " . $iUUID . " - " . $e->getMessage() . " \n");
        }
    }
    
    // Get secondary_tax_list and convert to JSON string if it's an object/array
    $secondary_tax_list = $itemData["secondaryTaxList"] ?? null;
    if ($secondary_tax_list !== null && !is_string($secondary_tax_list)) {
        $secondary_tax_list = json_encode($secondary_tax_list);
    }
    
    return [
        'itemUuid' => $iUUID,
        'orderUuid' => $order["uuid"],
        'agents_id' => $agents_id,
        'vendors_id' => $vendors_id,
        'terminals_id' => $terminals_id,
        'group_name' => $group_name,
        'orders_id' => $orders_id,
        'cost' => $itemData["cost"],
        'description' => $itemData["description"],
        'group_id' => $itemData["group"],
        'notes' => $itemData["notes"],
        'price' => $itemData["price"],
        'taxable' => $itemData["taxable"],
        'qty' => $itemData["qty"],
        'items_id' => $isOnlinePlatform ? $itemData["itemId"] : '0',
        'discount' => $itemData["discount"],
        'orderReference' => $orders_id,
        'taxamount' => $itemData["taxAmount"],
        'itemid' => $itemData["itemId"],
        'ebt' => $itemData["ebt"] ?? 0,
        'crv' => $crv,
        'crv_taxable' => $crv_taxable,
        'itemDiscount' => $itemData["itemDiscount"] ?? 0,
        'status' => getStatusCode($itemData["status"] ?? null),
        'itemsAddedDateTime' => time(),
        'lastMod' => time(),
        'tech_fee_rate' => $tech_fee_rate,
        'secondary_tax_list' => $secondary_tax_list
    ];
}

/**
 * Build a modifier row array for batch insertion
 * 
 * @param array $modData Normalized modifier data
 * @param array $order Order data
 * @param bool $isOnlinePlatform Whether this is an online platform order
 * @param int $agents_id Agent ID
 * @param int $vendors_id Vendor ID
 * @param int $terminals_id Terminal ID
 * @param int $orders_id Order ID
 * @param int $parent_item_id Parent item ID
 * @return array Modifier row ready for batch insertion
 */
function buildModifierRow($modData, $order, $isOnlinePlatform, $agents_id, $vendors_id, $terminals_id, $orders_id, $parent_item_id) {
    // Get secondary_tax_list and convert to JSON string if it's an object/array
    $secondary_tax_list = $modData["secondaryTaxList"] ?? null;
    if ($secondary_tax_list !== null && !is_string($secondary_tax_list)) {
        $secondary_tax_list = json_encode($secondary_tax_list);
    }
    
    return [
        'itemUuid' => ($isOnlinePlatform ? ($modData["uuid"] ?? null) : ($modData["iUUID"] ?? null)),
        'orderUuid' => $order["uuid"],
        'agents_id' => $agents_id ?? -1,
        'vendors_id' => $vendors_id,
        'terminals_id' => $terminals_id,
        'group_name' => null,
        'orders_id' => $orders_id,
        'cost' => $modData["cost"],
        'description' => $modData["description"],
        'group_id' => $modData["group"],
        'notes' => $modData["notes"] ?? "",
        'price' => $modData["price"],
        'taxable' => $modData["taxable"],
        'qty' => 1,
        'items_id' => $parent_item_id,
        'discount' => 0,
        'orderReference' => $orders_id,
        'taxamount' => $modData["taxAmount"],
        'itemid' => $modData["itemId"],
        'ebt' => $modData["ebt"] ?? 0,
        'crv' => $modData["crv"] ?? 0,
        'crv_taxable' => $modData["crv_taxable"] ?? 0,
        'itemDiscount' => 0,
        'status' => 0,
        'itemsAddedDateTime' => time(),
        'lastMod' => time(),
        'tech_fee_rate' => 0.0000,
        'secondary_tax_list' => $secondary_tax_list
    ];
}

/**
 * Process order items for a specific order reference
 * 
 * @param PDO $pdo Database connection
 * @param array $items_json All items JSON data
 * @param string $orderReference Order reference to match
 * @param array $order Order data
 * @param bool $isOnlinePlatform Whether this is an online platform order
 * @param int $agents_id Agent ID
 * @param int $vendors_id Vendor ID
 * @param int $terminals_id Terminal ID
 * @param array $group_names Array of group names for non-online platforms
 * @param array $orderRefMap Order reference mapping
 * @param array $payments_json Payments JSON data (optional, for tech_fee_rate calculation)
 * @return void
 */
function processOrderItemsForOrder($pdo, $items_json, $orderReference, $order, $isOnlinePlatform, $agents_id, $vendors_id, $terminals_id, $group_names, $orderRefMap, $payments_json = []) {
    $batch_items = [];
    $items_with_mods = [];
    $processed_item_uuids = []; // Track processed itemUuids to prevent duplicates
    
    print_r("DEBUG: Starting batch processing for orderReference: $orderReference" . " \n");
    print_r("DEBUG: Total items to process: " . count($items_json) . " \n");
    
    // Collect matching items
    for ($j = 0; $j < count($items_json); $j++) {
        $itemOrderRef = strval($items_json[$j]->{"orderItem"}->{"orderReference"});
        print_r("DEBUG: Item $j - orderRef: $itemOrderRef vs current orderRef: $orderReference" . " \n");
        
        if ($orderReference == $itemOrderRef) {
            // Deduplicate by itemUuid to prevent duplicate inserts
            $itemUuid = $isOnlinePlatform 
                ? ($items_json[$j]->{"orderItem"}->{"uuid"} ?? null) 
                : ($items_json[$j]->{"orderItem"}->{"iUUID"} ?? null);
            
            if ($itemUuid !== null && isset($processed_item_uuids[$itemUuid])) {
                print_r("DEBUG: Skipping duplicate itemUuid: $itemUuid for orderReference: $orderReference" . " \n");
                continue;
            }
            
            if ($itemUuid !== null) {
                $processed_item_uuids[$itemUuid] = true;
            }
            print_r("DEBUG: Match found - processing item $j for orderReference: $orderReference" . " \n");
            
            $col = (array) $items_json[$j]->{"orderItem"};
            $col = normalizeItemData($col);
            
            $group_name = getGroupName($pdo, $isOnlinePlatform, $col["group"], $group_names);
            $orders_id = $orderRefMap[strval($orderReference)];
            
            // Calculate tech_fee_rate from JSON data if payment was by card
            $tech_fee_rate = calculateTechFeeRate($items_json[$j], $payments_json, $orderReference);
            if ($tech_fee_rate > 0) {
                print_r("Calculated tech_fee_rate: " . $tech_fee_rate . " for item iUUID: " . ($col["iUUID"] ?? "null") . " \n");
            }
            
            $item_row = buildOrderItemRow($pdo, $col, $order, $isOnlinePlatform, $agents_id, $vendors_id, $terminals_id, $group_name, $orders_id, $tech_fee_rate);
            
            $batch_items[] = $item_row;
            print_r("DEBUG: Added item to batch - total batch items now: " . count($batch_items) . " \n");
            
            if (isset($items_json[$j]->{"mods"}) && count($items_json[$j]->{"mods"}) > 0) {
                $items_with_mods[] = [
                    'item_data' => $items_json[$j],
                    'batch_index' => count($batch_items) - 1
                ];
                print_r("DEBUG: Item has mods - added to mods processing queue" . " \n");
            }
        } else {
            print_r("DEBUG: No match - skipping item $j (orderRef: $itemOrderRef)" . " \n");
        }
    }
    
    print_r("DEBUG: Finished processing items - final batch count: " . count($batch_items) . " \n");
    
    if (!empty($batch_items)) {
        print_r("DEBUG: About to execute batch insert for " . count($batch_items) . " items, orderRef: $orderReference" . " \n");
        
        // Clear old order items
        $orders_id = $orderRefMap[strval($orderReference)];
        $stmt_clear_old_order_items = $pdo->prepare("DELETE FROM orderItems WHERE orders_id = ?");
        $stmt_clear_old_order_items->execute([$orders_id]);
        print_r("DEBUG: Cleared old order items, affected rows: " . $stmt_clear_old_order_items->rowCount() . " \n");
        
        print_r("DEBUG: Batch items: " . json_encode($batch_items) . " \n");
        $inserted_item_ids = batchInsertOrderItems($pdo, $batch_items);
        print_r("DEBUG: Batch inserted " . count($inserted_item_ids) . " items for payment reference $orderReference" . " \n");
        
        // Process modifiers
        if (!empty($items_with_mods)) {
            $mod_batch_items = [];
            
            foreach ($items_with_mods as $item_with_mods) {
                $item_data = $item_with_mods['item_data'];
                $batch_index = $item_with_mods['batch_index'];
                $parent_item_id = $inserted_item_ids[$batch_index];
                
                for ($k = 0; $k < count($item_data->{"mods"}); $k++) {
                    $col = (array) $item_data->{"mods"}[$k];
                    $col = normalizeItemData($col);
                    
                    $mod_row = buildModifierRow($col, $order, $isOnlinePlatform, $agents_id, $vendors_id, $terminals_id, $orders_id, $parent_item_id);
                    $mod_batch_items[] = $mod_row;
                }
            }
            
            if (!empty($mod_batch_items)) {
                batchInsertOrderItems($pdo, $mod_batch_items);
                print_r("Batch inserted " . count($mod_batch_items) . " modifiers for payment reference $orderReference" . " \n");
            }
        }
    } else {
        print_r("DEBUG: No batch items to insert for orderReference: $orderReference" . " \n");
    }
}

/**
 * Check if order exists and handle existing order logic
 * 
 * @param PDO $pdo Database connection
 * @param PDOStatement $stmt Prepared statement that checks for existing order
 * @param bool $isOnlinePlatform Whether this is an online platform order
 * @param string $orderReference Order reference
 * @param string $onlineorder_id Online order ID (for online platform)
 * @param int $terminals_id Terminal ID
 * @param int $vendors_id Vendor ID
 * @param array $orderRefMap Order reference mapping (passed by reference)
 * @param array $orderData Order data for update (for online platform)
 * @return bool True if order exists and should be skipped, false otherwise
 */
function checkAndHandleExistingOrder($pdo, $stmt, $isOnlinePlatform, $orderReference, $onlineorder_id, $terminals_id, $vendors_id, &$orderRefMap, $orderData = null) {
    if ($stmt->rowCount() != 0) {
        if ($isOnlinePlatform == true) {
            print("order - onlineorder_id: " . $onlineorder_id . " isOnlinePlatform: true - has existing order" . " \n");
        } else {
            print("order - orderReference: " . $orderReference . " isOnlinePlatform: false - has existing order" . " \n");
        }
        
        $fp = fopen("./tmp/aa.txt", "w");
        fputs($fp, time() . " 資料庫中仍有相同lastMod的資料 跳過\n");
        fclose($fp);
        
        // Add existing order to map for payment processing
        $existing_order_stmt = $pdo->prepare("SELECT id FROM orders WHERE vendors_id = ? AND terminals_id = ? AND orderReference = ? ORDER BY id DESC LIMIT 1");
        $existing_order_stmt->execute([$vendors_id, $terminals_id, $orderReference]);
        if ($existing_order_stmt->rowCount() > 0) {
            $existing_order = $existing_order_stmt->fetch();
            $orderRefMap[strval($orderReference)] = $existing_order["id"];
            print_r("Added existing order to map: " . $orderReference . " -> " . $existing_order["id"] . " \n");
        }
        
        // Update online platform order if needed
        if ($isOnlinePlatform == true && $orderData !== null) {
            $stmt_update_online_platform_order = $pdo->prepare("UPDATE orders SET 
                employee_id = ?,
                lastMod = ?,
                notes = ?,
                uuid = ?,
                orderReference = ?,
                onlineorder_id = ?,
                onlinetrans_id = ?,
                orderDate = ?,
                orderName = ?,
                status = ?,
                subTotal = ?,
                tax = ?,
                delivery_fee = ?,
                delivery_type = ?,
                total = ?,
                store_uuid = ?
                WHERE terminals_id = ? AND onlineorder_id = ?");
            $stmt_update_online_platform_order->execute([
                $orderData['employee_id'],
                $orderData['lastMod'],
                $orderData['notes'],
                $orderData['uuid'],
                $orderData['orderReference'],
                $orderData['onlineorder_id'],
                $orderData['onlinetrans_id'],
                $orderData['orderDate'],
                $orderData['orderName'],
                0,
                $orderData['subTotal'],
                $orderData['tax'],
                $orderData['delivery_fee'],
                $orderData['delivery_type'],
                $orderData['total'],
                $orderData['store_uuid'],
                $terminals_id,
                $onlineorder_id
            ]);
            print_r("Updated online platform order with onlineorder_id " . $onlineorder_id . " to orderReference " . $orderReference . " - Rows affected: " . $stmt_update_online_platform_order->rowCount() . " \n");
        }
        
        return true; // Order exists, should skip
    }
    
    return false; // Order doesn't exist, continue processing
}

ini_set("display_errors", 1);
include_once "./library/utils.php";

$base_url = "https://portal.olapay.us/api/v1/";
$base_url_local = "http://localhost:8090/api/v1/";
$base_url_staging = "https://portalstg.olapay.us/api/v1/";
// load env file

// Debug  $_ENV ENV value
print_r("ENV value: " . $_ENV['ENV'] . " \n");

function call_inventory_lock($orderUuid)
{
  if (!$orderUuid) {
    return null;
  }

  // Skip actual inventory lock in test mode
  if (isset($_SERVER['HTTP_X_TEST_MODE']) && $_SERVER['HTTP_X_TEST_MODE'] === 'true') {
    return null;
  }

  global $base_url, $base_url_local, $base_url_staging;
  $url = $base_url; // Default to production
  
  if (  $_ENV['ENV'] == 'local') {
    $url = $base_url_local;
  } else if (  $_ENV['ENV'] == 'staging') {
    $url = $base_url_staging;
  }

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url . "inventory/order-lock/" . $orderUuid);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  $response = curl_exec($ch);
  $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  // Log response details for debugging
  print_r("Inventory lock API response code: " . $httpcode . " \n");
  print_r("Inventory lock API response body: " . $response . " \n");

  if ($httpcode != 200) {
    $error = json_decode($response, true);
    return isset($error['error']) ? $error['error'] : 'Failed to lock inventory';
  }
  return null;
}

function call_inventory_paid($orderUuid)
{
  if (!$orderUuid) {
    return null;
  }

  // Skip actual inventory paid in test mode
  if (isset($_SERVER['HTTP_X_TEST_MODE']) && $_SERVER['HTTP_X_TEST_MODE'] === 'true') {
    return null;
  }

  global $base_url, $base_url_local, $base_url_staging;
  $url = $base_url; // Default to production
  
  if (  $_ENV['ENV'] == 'local') {
    $url = $base_url_local;
  } else if (  $_ENV['ENV'] == 'staging') {
    $url = $base_url_staging;
  }

  print_r("Full api url: " . $url . "inventory/order-paid/" . $orderUuid . " \n");
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url . "inventory/order-paid/" . $orderUuid);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  $response = curl_exec($ch);
  $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  // Log response details for debugging
  print_r("Inventory release API response code: " . $httpcode . " \n");
  print_r("Inventory release API response body: " . $response . " \n");

  if ($httpcode != 200) {
    $error = json_decode($response, true);
    return isset($error['error']) ? $error['error'] : 'Failed to process order payment';
  }
  return null;
}

function call_broadcast_order_paid($orderUuid)
{
  if (!$orderUuid) {
    return null;
  }

  // Skip actual inventory paid in test mode
  if (isset($_SERVER['HTTP_X_TEST_MODE']) && $_SERVER['HTTP_X_TEST_MODE'] === 'true') {
    return null;
  }

  global $base_url, $base_url_local, $base_url_staging;
  $url = $base_url; // Default to production
  
  if (  $_ENV['ENV'] == 'local') {
    $url = $base_url_local;
  } else if (  $_ENV['ENV'] == 'staging') {
    $url = $base_url_staging;
  }

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url . "inventory/broadcast/order-paid/" . $orderUuid);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  $response = curl_exec($ch);
  $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  // Log response details for debugging
  print_r("Broadcast order paid API response code: " . $httpcode . " \n");
  print_r("Broadcast order paid API response body: " . $response . " \n");

  if ($httpcode != 200) {
    $error = json_decode($response, true);
    return isset($error['error']) ? $error['error'] : 'Failed to process order payment';
  }
  return null;
}

//enable_cors();
$msgfornoterminal = "No permission";
$msgforsqlerror = "Unable to insert data";
$pdo = connect_db_and_set_http_method("POST");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
$params = get_params_from_http_body([
  "serial",
  "json"
]);
$stmt = $pdo->prepare("select * from terminals where serial = ?");
$stmt->execute([$params["serial"]]);
if ($stmt->rowCount() == 0) {
  send_http_status_and_exit("403", $msgfornoterminal);
}
$row = $stmt->fetch();

#get terminal id, vendor id, and agent id
$terminals_id = $row["id"];
$vendors_id = $row["vendors_id"];
$stmt2 = $pdo->prepare("select * from accounts where id = ?");
$stmt2->execute([$vendors_id]);
print_r("vendors_id: " . $vendors_id . " \n");
$row2 = $stmt2->fetch();
$agents_id = $row2["accounts_id"];
print_r("agents_id: " . $agents_id . " \n");

####log raw data
$params["json"] = str_replace('&quot;', '"', $params["json"]);
$stmt = $pdo->prepare("insert into json set serial = ?, content = ?");
$res = $stmt->execute([$params["serial"], $params["json"]]);

#deal with test data here
#$stmt = $pdo->prepare("select * from json where id = ?");
#$stmt->execute([ "330" ]);
#$row = $stmt->fetch();
// Log raw JSON data for debugging
print_r("Received JSON params: " . $params["json"] . " \n");

$row["content"] = str_replace('&quot;', '"', $params["json"]);

$decoded_content = json_decode($row["content"]);
$payments = json_decode($decoded_content->{"payments"} ?? "[]") ?? [];
$items_json = json_decode($decoded_content->{"items"} ?? "[]") ?? [];
$orders_json = json_decode($decoded_content->{"orders"} ?? "[]") ?? [];
$groups_json = json_decode($decoded_content->{"groups"} ?? "[]") ?? [];
$termId = $decoded_content->{"termId"} ?? null;
$itemdata_json = isset($decoded_content->{"itemdata"}) ? json_decode($decoded_content->{"itemdata"}) ?? [] : [];
$hasInventory = $decoded_content->{"hasInventory"} ?? false;
$isOnlinePlatform = $decoded_content->{"isOnlinePlatform"} ?? false;
$store_uuid_raw = $decoded_content->{"store_uuid"} ?? "";
// Handle double-encoded store_uuid (remove quotes if it's a JSON string)
$store_uuid = is_string($store_uuid_raw) && (substr($store_uuid_raw, 0, 1) === '"' && substr($store_uuid_raw, -1) === '"') 
  ? json_decode($store_uuid_raw) ?? $store_uuid_raw 
  : $store_uuid_raw;
print_r("hasInventory: " . $hasInventory . " \n");
print_r("payments: " . json_encode($payments) . " \n");
print_r("store_uuid: " . $store_uuid . " \n");
$group_names = array();
for ($i = 0; $i < count($groups_json); $i++) {
  $groups_id = $groups_json[$i]->{"id"};
  $description = $groups_json[$i]->{"description"};
  $groupType = $groups_json[$i]->{"groupType"};
  $notes = $groups_json[$i]->{"notes"};
  $lastMod = $groups_json[$i]->{"lastMod"};
  $group_names[$groups_id] = $description;
  #  $stmt = $pdo->prepare("select * from groups where lastMod = ?");
  #  $stmt->execute([ $lastMod ]);
  #  if ( $stmt->rowCount() != 0 ) { //should be zero
  #    continue;
  #  } else {
  #    $stmt = $pdo->prepare("insert into groups set agents_id = ?, vendors_id = ?, terminals_id = ?, groups_id = ?, description = ?, groupType = ?, notes = ?, lastMod = ?");
  #    $stmt->execute([ $agents_id, $vendors_id, $terminals_id, $groups_id, $description, $groupType, $notes, $lastMod ]);
  #  }
}

$fp = fopen("./tmp/aa.txt", "a");
fputs($fp, time() . " 開始處理資料\n");
fclose($fp);

// Build a map of orderUuid/orderReference -> order total from orders_json
// This allows us to get the CORRECT full order total instead of individual payment total
$orderTotalMap = array();
for ($j = 0; $j < count($orders_json); $j++) {
  $oUUID = $orders_json[$j]->{"oUUID"} ?? ($orders_json[$j]->{"uuid"} ?? null);
  $oRef = $orders_json[$j]->{"id"} ?? null;
  $oTotal = $orders_json[$j]->{"total"} ?? null;
  if ($oUUID !== null && $oTotal !== null) {
    $orderTotalMap["uuid_" . $oUUID] = $oTotal;
  }
  if ($oRef !== null && $oTotal !== null) {
    $orderTotalMap["ref_" . $oRef] = $oTotal;
  }
}

#必須先掃一次傳上來的payment; 如果payDate已經存在，就要把order, orderPayments, orderItems都清空, 再重新插入; 這邊是因應orderPayments在refund後lastmod改變但payrDate不變
for ($i = 0; $i < count($payments); $i++) {
  $payDate = strtotime($payments[$i]->{"payDate"});

  # Check if has orderUuid and paymentUuid -> if order exist, update orderUuid if paymentUuid is not null -> update payment
  $orderUuid = $payments[$i]->{"orderUuid"} ?? null;
  $paymentUuid = $payments[$i]->{"paymentUuid"} ?? null;

  if($orderUuid && $paymentUuid){
    // This is for new build of the pos
    $stmt = $pdo->prepare("select * from orders where uuid = ? and terminals_id = ?");
    $stmt->execute([$orderUuid, $terminals_id]);
    $existing_order = $stmt->fetch();
    if($existing_order){
      // Update order tips if lastmod is greater than old lastmod
      try {
        print("Start checking order tips" . " \n");
        print("payments[$i]->{\"lastMod\"]: " . $payments[$i]->{"lastMod"});
        print("existing_order[\"lastMod\"]: " . $existing_order["lastMod"]);
        if ($payments[$i]->{"lastMod"} > $existing_order["lastMod"]) {
          print("Update order tip / total / techfee" . " \n");
          // Use order total from orders_json (not payment total) - fixes split payment bug
          $orderTotalFromJson = $orderTotalMap["uuid_" . $orderUuid] ?? $payments[$i]->{"total"};
          print("Using order total from orders_json: " . $orderTotalFromJson . " (payment total was: " . $payments[$i]->{"total"} . ")\n");
          $stmt = $pdo->prepare("update orders set tip = ?, total = ?, tech_fee = ? where id = ?");
          $stmt->execute([(float)$payments[$i]->{"tips"}, (float)$orderTotalFromJson, (float)$payments[$i]->{"techfee"}, $existing_order["id"]]);
        }
        print("End checking order tip / total / techfee" . " \n");
      } catch (Exception $e) {
        print_r("Error updating order tip / total / techfee: " . $e->getMessage() . " \n");
      }
    }

    $stmt = $pdo->prepare("select * from ordersPayments where terminals_id = ? and payDate = ? and paymentUuid = ?");
    $stmt->execute([$terminals_id, $payDate, $paymentUuid]);
    if ($stmt->rowCount() != 0) {
      $row = $stmt->fetch();
      $existing_payment = $row["id"];
      $new_tips = $payments[$i]->{"tips"};
      $new_amtPaid = $payments[$i]->{"amtPaid"};
      $new_total = $payments[$i]->{"total"};
      $new_refund = $payments[$i]->{"refund"};
      $new_originalTotal = $payments[$i]->{"orgTotal"} ?? null;
      $stmt2 = $pdo->prepare("update ordersPayments set amtPaid = ?, total = ?, refund = ?, tips = ?, lastMod = ?, originalTotal = ? where id = ?");
      $stmt2->execute([(float)$new_amtPaid, (float)$new_total, (float)$new_refund, (float)$new_tips, $payments[$i]->{"lastMod"}, $new_originalTotal, $existing_payment]);
    }
  }else{ 
    // This is for old build of the pos
    #if online order then order reference is okay; if not online order then order reference duplication would be long ago 
    $orderReference = $payments[$i]->{"orderReference"};
    $stmt = $pdo->prepare("select * from orders where terminals_id = ? and orderReference = ? order by id desc limit 0,1");
    $stmt->execute([$terminals_id,  $orderReference]);
    $row2 = $stmt->fetch();

    // Update order tips if lastmod is greater than old lastmod
    if ($row2 !== false && is_array($row2)) {
      try {
        print("Start checking order tips" . " \n");
        print("payments[$i]->{\"lastMod\"]: " . $payments[$i]->{"lastMod"});
        print("row2[\"lastMod\"]: " . $row2["lastMod"]);
        if ($payments[$i]->{"lastMod"} > $row2["lastMod"]) {
          print("Update order tip / total / techfee" . " \n");
          // Use order total from orders_json (not payment total) - fixes split payment bug
          $orderTotalFromJson = $orderTotalMap["ref_" . $orderReference] ?? $payments[$i]->{"total"};
          print("Using order total from orders_json: " . $orderTotalFromJson . " (payment total was: " . $payments[$i]->{"total"} . ")\n");
          $stmt = $pdo->prepare("update orders set tip = ?, total = ?, tech_fee = ? where id = ?");
          $stmt->execute([(float)$payments[$i]->{"tips"}, (float)$orderTotalFromJson, (float)$payments[$i]->{"techfee"}, $row2["id"]]);
        }
        print("End checking order tip / total / techfee" . " \n");
      } catch (Exception $e) {
        print_r("Error updating order tip / total / techfee: " . $e->getMessage() . " \n");
      }
    }

    $stmt = $pdo->prepare("select * from ordersPayments where terminals_id = ? and payDate = ? and payDate != 0 and orderReference = ?");
    $orderRefForPayment = ($row2 !== false && is_array($row2)) ? $row2["id"] : null;
    if ($orderRefForPayment === null) {
      continue; // Skip if order not found
    }
    $stmt->execute([$terminals_id, $payDate, $orderRefForPayment]);
    if ($stmt->rowCount() != 0) {
      $row = $stmt->fetch();
      $id = $row["id"];
      $new_tips = $payments[$i]->{"tips"};
      $new_amtPaid = $payments[$i]->{"amtPaid"};
      $new_total = $payments[$i]->{"total"};
      $new_refund = $payments[$i]->{"refund"};
      $new_originalTotal = $payments[$i]->{"orgTotal"} ?? null;
      $stmt2 = $pdo->prepare("update ordersPayments set amtPaid = ?, total = ?, refund = ?, tips = ?, lastMod = ?, originalTotal = ? where id = ?");
      $stmt2->execute([(float)$new_amtPaid, (float)$new_total, (float)$new_refund, (float)$new_tips, $payments[$i]->{"lastMod"}, $new_originalTotal, $id]);
      #$orderReference = $row["orderReference"];
      #$stmt = $pdo->prepare("delete from ordersPayments where terminals_id = ? and id = ?");
      #$stmt->execute([ $terminals_id, $id ]);
      #$stmt = $pdo->prepare("delete from orders where terminals_id = ? and id = ?");
      #$stmt->execute([ $terminals_id, $orderReference ]);
      #$stmt = $pdo->prepare("delete from orderItems where terminals_id = ? and orders_id = ?");
      #$stmt->execute([ $terminals_id, $orderReference ]);

      $fp = fopen("./tmp/aa.txt", "a");
      fputs($fp, time() . " 更新refund資料\n");
      fclose($fp);
    }
  }  
}


#id map, orderPayments的orderReference不應該是機器上的Orders ID,應該要是主機上的Orders ID
$orderRefMap = array();
for ($i = 0; $i < count($orders_json); $i++) {
  $orderReference = $orders_json[$i]->{"id"};
  $subTotal = $orders_json[$i]->{"subTotal"};
  $tax = $orders_json[$i]->{"tax"};
  $total = $orders_json[$i]->{"total"};
  $notes = $orders_json[$i]->{"notes"};
  $lastMod = $orders_json[$i]->{"lastMod"};
  $employee_id = $orders_json[$i]->{"employeeId"};
  $orderDate = date('Y-m-d H:i:s', strtotime($orders_json[$i]->{"orderDate"}));
  $orderName = $orders_json[$i]->{"orderName"} ?? '';
  // new fields
  if ($isOnlinePlatform == true) {
    print("order - uuid: " . $orders_json[$i]->{"uuid"} . " isOnlinePlatform: true" . " \n");
    $order_uuid = $orders_json[$i]->{"uuid"};
  } else {
    print("order - oUUID: " . $orders_json[$i]->{"oUUID"} . " isOnlinePlatform: false" . " \n");
    $order_uuid = $orders_json[$i]->{"oUUID"};
  }
  $employee_pin = $orders_json[$i]->{"employeePIN"};
  $delivery_type = "";
  $delivery_fee = "0";
  $onlineorder_id = "";
  $onlinetrans_id = "";
  if (isset($orders_json[$i]->{"delivery_fee"})) {
    $delivery_fee = $orders_json[$i]->{"delivery_fee"};
  }
  if (isset($orders_json[$i]->{"delivery_type"})) {
    $delivery_type = $orders_json[$i]->{"delivery_type"};
  }
  if (isset($orders_json[$i]->{"onlineorder_id"})) {
    $onlineorder_id = $orders_json[$i]->{"onlineorder_id"};
  }
  if (isset($orders_json[$i]->{"onlinetrans_id"})) {
    $onlinetrans_id = $orders_json[$i]->{"onlinetrans_id"};
  }
  #$stmt = $pdo->prepare("select * from orders where terminals_id = ? and lastMod = ?");
  #$stmt->execute([ $terminals_id, $lastMod ]);
  if ($isOnlinePlatform == false) {
    $stmt = $pdo->prepare("select * from orders where terminals_id = ? and orderDate = ? and orderReference = ?");
    $stmt->execute([$terminals_id, $orderDate, $orderReference]);
  } else {
    $online_platform_order_uuid = $orders_json[$i]->{"uuid"};
    $stmt = $pdo->prepare("select * from orders where terminals_id = ? and onlineorder_id = ?");
    $stmt->execute([$terminals_id, $onlineorder_id]);
  }
  // Prepare order data for update if needed
  $secondary_tax_list = "";
  if (isset($orders_json[$i]->{"secondaryTaxList"})) {
    // Convert object to JSON string for database storage
    $secondary_tax_list = json_encode($orders_json[$i]->{"secondaryTaxList"});
  }
  $orderData = null;
  if ($isOnlinePlatform == true) {
    $orderData = [
      'employee_id' => $employee_id,
      'lastMod' => $lastMod,
      'notes' => $notes,
      'uuid' => $online_platform_order_uuid,
      'orderReference' => $orderReference,
      'onlineorder_id' => $onlineorder_id,
      'onlinetrans_id' => $onlinetrans_id,
      'orderDate' => $orderDate,
      'orderName' => $orderName,
      'subTotal' => $subTotal,
      'tax' => $tax,
      'delivery_fee' => $delivery_fee,
      'delivery_type' => getDeliveryServiceCode($delivery_type),
      'total' => $total,
      'store_uuid' => $store_uuid
    ];
  }
  
  // Check if order exists and handle accordingly
  if (checkAndHandleExistingOrder($pdo, $stmt, $isOnlinePlatform, $orderReference, $onlineorder_id, $terminals_id, $vendors_id, $orderRefMap, $orderData)) {
    // Check if this json has items and match orderReference, if there is any, upsert the order items
    if (isset($orderRefMap[strval($orderReference)])) {
      $existing_order_stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND vendors_id = ? AND terminals_id = ?");
      $existing_order_stmt->execute([$orderRefMap[strval($orderReference)], $vendors_id, $terminals_id]);
      $existing_order = $existing_order_stmt->fetch();
      
      if ($existing_order) {
        print_r("DEBUG: Processing items for existing order - orderReference: $orderReference" . " \n");
        processOrderItemsForOrder($pdo, $items_json, $orderReference, $existing_order, $isOnlinePlatform, $agents_id, $vendors_id, $terminals_id, $group_names, $orderRefMap, $payments);
      }
    }
    continue;
  } else {
    print("order - orderReference: " . $orderReference . " isOnlinePlatform: " . $isOnlinePlatform . " no existing order ");
    $fp = fopen("./tmp/aa.txt", "w");
    fputs($fp, time() . " 資料庫中沒有相同lastMod的資料 新增\n");
    fclose($fp);

    $stmt = $pdo->prepare("insert into orders set 
      uuid = ?,
      agents_id = ?,
      vendors_id = ?,
      terminals_id = ?,
      orderReference = ?,
      subTotal = ?,
      tax = ?,
      total = ?,
      notes = ?,
      lastMod = ?,
      employee_id = ?,
      orderDate = ?,
      delivery_fee = ?,
      delivery_type = ?,
      employee_pin = ?,
      onlineorder_id = ?,
      onlinetrans_id = ?,
      orderName = ?, status = ?, store_uuid = ?, secondary_tax_list = ?");

    if ($isOnlinePlatform == true && $hasInventory == true) {
      print("=== Insert case Online Platform and has inventory");
      // temporarily set online platform's orderReference as order uuid , this will be updated later
      $stmt->execute([
        $order_uuid ?? null,
        $agents_id,
        $vendors_id,
        $terminals_id,
        0,
        0.0,
        0.0,
        0.0,
        $notes,
        $lastMod,
        0,
        $orderDate,
        $delivery_fee,
        getDeliveryServiceCode($delivery_type),
        $employee_pin,
        $onlineorder_id,
        $onlinetrans_id,
        $orderName,
        0, // default status, @Todo: change to enum 0 -> PAID
        $store_uuid,
        $secondary_tax_list
      ]);
      // set orderReference to the new order id for later process
      if (empty($orderReference) || is_null($orderReference)) {
        $orderReference = $pdo->lastInsertId();
      }
      print("===orderReference");
      print_r($orderReference);
    } else {
      print("=== Insert case NOT Online Platform or has NO inventory ");
      $stmt->execute([
        $order_uuid ?? null,
        $agents_id,
        $vendors_id,
        $terminals_id,
        $orderReference,
        $subTotal,
        $tax,
        $total,
        $notes,
        $lastMod,
        $employee_id,
        $orderDate,
        $delivery_fee,
        getDeliveryServiceCode($delivery_type),
        $employee_pin,
        $onlineorder_id,
        $onlinetrans_id,
        $orderName,
        0,
        $store_uuid,
        $secondary_tax_list
      ]);
    }
    print("query status: " . $stmt->rowCount() . " \n");
    $orderRefMap[strval($orderReference)] = $pdo->lastInsertId();
    print("orderRefMap: " . json_encode($orderRefMap) . " \n");
    print_r("===orderRefMap" . " \n");
    print_r(json_encode($orderRefMap) . " \n");

    // Process order items for new orders
    $new_order_stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $new_order_stmt->execute([$orderRefMap[strval($orderReference)]]);
    $order = $new_order_stmt->fetch();
    
    if ($order) {
      print("new order created with id: ". $order["id"]);
      print_r("DEBUG: Processing items for new order - orderReference: $orderReference" . " \n");
      
      processOrderItemsForOrder($pdo, $items_json, $orderReference, $order, $isOnlinePlatform, $agents_id, $vendors_id, $terminals_id, $group_names, $orderRefMap, $payments);
    }

  }
}

for ($i = 0; $i < count($payments); $i++) {
  #check if lastmod exist, if so next
  $employee_id = $payments[$i]->{"employeeId"};
  $amtPaid = $payments[$i]->{"amtPaid"};
  #$payDate = $payments[$i]->{"payDate"};
  $total = $payments[$i]->{"total"};
  $refNumber = $payments[$i]->{"refNumber"};
  $tips = $payments[$i]->{"tips"};
  $refund = $payments[$i]->{"refund"};
  $payDate = strtotime($payments[$i]->{"payDate"});
  $techFee = $payments[$i]->{"techfee"};
  $lastMod = $payments[$i]->{"lastMod"};
  $orderId = $payments[$i]->{"orderID"};
  if ($hasInventory) {
    print("Process payment - 2nd part - hasInventory: true" . " \n");
    // If hasInventory is true, get orderRef from orders table using oUUID
    $order_stmt = $pdo->prepare("select id from orders where uuid = ?");
    if ($isOnlinePlatform == true) {
      $order_stmt->execute([$payments[$i]->{"orderUUID"}]);
      $order_uuid = $payments[$i]->{"orderUUID"} ?? null;
    } else {
      $order_stmt->execute([$payments[$i]->{"oUUID"}]);
      $order_uuid = $payments[$i]->{"oUUID"} ?? null;
    }
    $order_row = $order_stmt->fetch();
    $orderRef = $order_row["id"];
  } else {
    print("Process payment - 2nd part - hasInventory: false" . " \n");
    // If hasInventory is false, use the existing mapping logic
    print_r("orderRefMap" . " \n");
    print_r(json_encode($orderRefMap) . " \n");
    print_r("orderUuid: " . $order_uuid . " \n");

    if ($isOnlinePlatform == true) {
      $order_uuid = $payments[$i]->{"orderUUID"} ?? null;
    } else {
      $order_uuid = $payments[$i]->{"oUUID"} ?? null;
    }

    print("order_uuid: " . $order_uuid . " \n");

    if($order_uuid){
      // new build of the pos
      $existing_order = $pdo->prepare("select * from orders where uuid = ? and terminals_id = ?");
      $existing_order->execute([$order_uuid, $terminals_id]);
      print("query state : select * from orders where uuid = " . $order_uuid . " and terminals_id = " . $terminals_id . " \n");
      $existing_order = $existing_order->fetch();
      if($existing_order){
        print("existing order found with id: " . $existing_order["id"] . " \n");
        $orderRef = $existing_order["id"];
      }else{
        print("existing order not found" . " \n");
        $orderRef = null;
      }
    }else{
      // old build of the pos
      $orderRefKey = strval($payments[$i]->{"orderReference"});
      if (!isset($orderRefMap[$orderRefKey])) {
        // Try to find the order in database
        $fallback_stmt = $pdo->prepare("select id from orders where terminals_id = ? and orderReference = ? order by id desc limit 1");
        $fallback_stmt->execute([$terminals_id, $payments[$i]->{"orderReference"}]);
        if ($fallback_stmt->rowCount() > 0) {
          $fallback_order = $fallback_stmt->fetch();
          $orderRefMap[$orderRefKey] = $fallback_order["id"];
          $orderRef = $fallback_order["id"];
          print_r("Found order in fallback lookup: " . $orderRefKey . " -> " . $orderRef . " \n");
        } else {
          print_r("ERROR: Could not find order for payment orderReference: " . $orderRefKey . " \n");
          continue; // Skip this payment if order not found
        }
      } else {
        $orderRef = $orderRefMap[$orderRefKey];
      }
    }
    

  }
  $payment_uuid = $payments[$i]->{"pUUID"} ?? null;
  $olapayApprovalId = $payments[$i]->{"olapayApprovalId"} ?? null;
  #echo $amtPaid . " " . $total . " " . $lastMod . " " . $orderRef;
  // Check for duplicate payment by paymentUuid and orderUuid only (not terminals_id)
  // This prevents duplicate inserts when multiple terminals sync the same payment
  $stmt = $pdo->prepare("select * from ordersPayments where paymentUuid = ? and orderUuid = ?");
  $stmt->execute([$payment_uuid, $order_uuid]);
  if ($stmt->rowCount() != 0) { //should be zero
    // Payment exists - check if we should update with newer data (e.g., refund info)
    $existing_payment = $stmt->fetch();
    if ($payments[$i]->{"lastMod"} > $existing_payment["lastMod"]) {
      $new_originalTotal = $payments[$i]->{"orgTotal"} ?? null;
      $stmt_update = $pdo->prepare("update ordersPayments set amtPaid = ?, total = ?, refund = ?, tips = ?, lastMod = ?, originalTotal = ? where paymentUuid = ? and orderUuid = ?");
      $stmt_update->execute([
        (float)$amtPaid, 
        (float)$total, 
        (float)$refund, 
        (float)$tips, 
        $lastMod, 
        $new_originalTotal,
        $payment_uuid,
        $order_uuid
      ]);
      print_r("Updated payment for orderUuid: " . $order_uuid . " and paymentUuid: " . $payment_uuid . " with newer lastMod \n");
    } else {
      print_r("Payment already exists for orderUuid: " . $order_uuid . " and paymentUuid: " . $payment_uuid . " \n");
    }
    continue;
  } else {
    print_r("Payment does not exist for orderUuid: " . $order_uuid . " and paymentUuid: " . $payment_uuid . " \n");
    $originalTotal = $payments[$i]->{"orgTotal"} ?? null;
    $editTerminalSerial = $payments[$i]->{"editTermSerial"} ?? null;
    $editEmployeeId = $payments[$i]->{"editEmployeeId"} ?? null;
    $editEmployeePIN = $payments[$i]->{"editEmployeePIN"} ?? null;
    $stmt = $pdo->prepare("insert into ordersPayments set 
    paymentUuid = ?, 
    orderUuid = ?, 
    olapayApprovalId = ?, 
    agents_id = ?, 
    vendors_id = ?, 
    terminals_id = ?, 
    amtPaid = ?, 
    total = ?, 
    refNumber = ?, 
    tips = ?, 
    techFee = ?, 
    orderReference = ?, 
    orderId = ?, 
    refund = ?, 
    payDate = ?, 
    lastMod = ?, 
    employee_id = ?,
    status = ?,
    originalTotal = ?,
    editTerminalSerial = ?,
    editEmployeeId = ?,
    editEmployeePIN = ?");
    $stmt->execute([
      $payment_uuid ?? null,
      $order_uuid ?? null,
      $olapayApprovalId ?? null,
      $agents_id ?? -1,
      $vendors_id,
      $terminals_id,
      $amtPaid,
      $total,
      $refNumber,
      $tips,
      $techFee,
      $orderRef,
      $orderId,
      $refund,
      $payDate,
      $lastMod,
      $employee_id,
      0,
      $originalTotal,
      $editTerminalSerial,
      $editEmployeeId,
      $editEmployeePIN
    ]);
    print_r("DEBUG: " . $stmt->rowCount() . " rows affected" . " \n");
    $orders_id = $pdo->lastInsertId();
    print_r("DEBUG: orderPayments id: " . $orders_id . " \n");
    #update orderId
    #$stmt = $pdo->prepare("update ordersPayments set orderId = ? where id = ?");
    #$stmt->execute([ $termId . '0' . $orderRef, $orders_id  ]);
    
    // Delete pending order
    if ($order_uuid) {
      $delete_pending_order_stmt = $pdo->prepare("delete from pending_orders where uuid = ?");
      if (!$delete_pending_order_stmt->execute([$order_uuid])) {
        print_r("Failed to delete pending order for UUID: " . $order_uuid . " \n");
      }
    }

    // Release inventory lock only if hasInventory is true
    if ($hasInventory) {
      print_r("Releasing inventory lock for order UUID: " . $order_uuid. " \n");
      $error = call_inventory_paid($order_uuid);
      if ($error) {
        print_r("Inventory paid failed for order UUID: " . $order_uuid . " Error: " . $error. " \n");
      }
      print_r("Inventory lock released for order UUID: " . $order_uuid. " \n");
    } 
    $error = call_broadcast_order_paid($order_uuid);
    if ($error) {
      print_r("Broadcast order paid failed for order UUID: " . $order_uuid . " Error: " . $error. " \n");
    }
  }
  ////
  #insert and get new payment id
  /*
  for ( $j = 0; $j < count($items_json); $j++ ) {
    if ( $payments[$i]->{"orderReference"} == strval($items_json[$j]->{"orderItem"}->{"orderReference"}) && $ignoreOrderItemMap[strval($orderReference)] == "0") {
       #insert all item detail with new payment id
      $col = (array) $items_json[$j]->{"orderItem"};
      $col2 = (array) $items_json[$j]->{"item"};
      if ( !isset($col2["notes"]) ) { $col2["notes"] = ""; }
      if ( !isset($col["taxAmount"]) ) { $col["taxAmount"] = "0"; }
      if ( !isset($col["itemId"]) ) { $col["itemId"] = "0"; }
      #var_dump( $col );
      $stmt = $pdo->prepare("insert into orderItems set agents_id = ?, vendors_id = ?, terminals_id = ?, group_name = ?, orders_id = ?, cost = ?, description = ?, group_id = ?, notes = ?, price = ?, taxable = ?, qty = ?, items_id = ?, discount = ?, taxamount = ?, itemid = ?");
      $stmt->execute([ $agents_id, $vendors_id, $terminals_id, $group_names[$col["group"]], $orderRef, $col["cost"], $col["description"],  $col["group"], $col2["notes"], $col["price"], $col["taxable"], $col["qty"], '0', $col["discount"], $col["taxAmount"], $col["itemId"] ]);
      $items_id = $pdo->lastInsertId();
      for ( $k = 0; $k < count($items_json[$j]->{"mods"}); $k++ ) {
	      $col = (array) $items_json[$j]->{"mods"}[$k];
	      if ( !isset($col["taxAmount"]) ) { $col["taxAmount"] = "0"; }
              if ( !isset($col["itemId"]) ) { $col["itemId"] = "0"; }
	$stmt = $pdo->prepare("insert into orderItems set agents_id = ?, vendors_id = ?, terminals_id = ?, orders_id = ?, cost = ?, description = ?, group_id = ?, notes = ?, price = ?, taxable = ?, items_id = ?, taxamount = ?, itemid = ?");
        $stmt->execute([ $agents_id, $vendors_id, $terminals_id, $orderRef, $col["cost"], $col["description"],  $col["group"], $col["notes"], $col["price"], $col["taxable"], $items_id, $col["taxAmount"], $col["itemId"] ]);
      }
    }
  }
*/
  ////
}
for ($i = 0; $i < count($itemdata_json); $i++) {
  $stmt = $pdo->prepare("select * from items where terminals_id = ? and `desc` = ?");
  $stmt->execute([$terminals_id, $itemdata_json[$i]->{"description"}]);
  if ($stmt->rowCount() == 0) {
    $stmt = $pdo->prepare("insert into items set
      agents_id = ?,
      vendors_id = ?,
      items_id = ?, 
      cost = ?,
      price = ?,
      notes = ?,
      upc = ?,
      taxable = ?,
      taxrate = ?,
      `group` = ?,
      amount_on_hand = ?,
      enterdate = now(),
      terminals_id = ?,
      `desc` = ?
    ");
  } else {
    $stmt = $pdo->prepare("update items set
      agents_id = ?,
      vendors_id = ?,
      items_id = ?,
      cost = ?,
      price = ?,
      notes = ?,
      upc = ?,
      taxable = ?,
      taxrate = ?,
      `group` = ?,
      amount_on_hand = ?,
      enterdate = now()
      where terminals_id = ? and `desc` = ?
    ");
  }
  if (!isset($itemdata_json[$i]->{"group"})) {
    $itemdata_json[$i]->{"group"} = 0;
  }
  $stmt->execute([
    $agents_id,
    $vendors_id,
    $itemdata_json[$i]->{"id"},
    $itemdata_json[$i]->{"cost"},
    $itemdata_json[$i]->{"price"},
    $itemdata_json[$i]->{"notes"},
    $itemdata_json[$i]->{"upc"},
    $itemdata_json[$i]->{"taxable"},
    $itemdata_json[$i]->{"taxRate"},
    $itemdata_json[$i]->{"group"},
    $itemdata_json[$i]->{"amountOnHand"},
    $terminals_id,
    $itemdata_json[$i]->{"description"}
  ]);
}
/*for ( $i = 0; $i < count($groups_json); $i++) {
  $groups_id = $groups_json[$i]->{"id"};
  $description = $groups_json[$i]->{"description"};
  $groupType = $groups_json[$i]->{"groupType"};
  $notes = $groups_json[$i]->{"notes"};
  $lastMod = $groups_json[$i]->{"lastMod"};
  $stmt = $pdo->prepare("select * from groups where lastMod = ?");
  $stmt->execute([ $lastMod ]);
  if ( $stmt->rowCount() != 0 ) { //should be zero
    continue;
  } else {
    $stmt = $pdo->prepare("insert into groups set agents_id = ?, vendors_id = ?, terminals_id = ?, groups_id = ?, description = ?, groupType = ?, notes = ?, lastMod = ?");
    $stmt->execute([ $agents_id, $vendors_id, $terminals_id, $groups_id, $description, $groupType, $notes, $lastMod ]);
  }
}
*/

#if($res){
send_http_status_and_exit("200", "Data was successfully inserted.");
#} else {
#send_http_status_and_exit("400",$msgforsqlerror);
#}

$fp = fopen("/var/www/html/posliteweb/dist/api/log.txt", "a");
fputs($fp, "xxx");
fclose($fp);
