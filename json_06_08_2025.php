<?php

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
            'notes', 'price', 'taxable', 'qty', 'items_id', 'discount',
            'taxamount', 'itemid', 'ebt', 'crv', 'crv_taxable', 'itemDiscount',
            'status', 'itemsAddedDateTime', 'lastMod'
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
        
        // Return array of inserted IDs
        $inserted_ids = [];
        $first_id = $pdo->lastInsertId();
        for ($i = 0; $i < count($items); $i++) {
            $inserted_ids[] = $first_id + $i;
        }
        
        return $inserted_ids;
        
    } catch (PDOException $e) {
        error_log("SQL batch insert failed: " . $e->getMessage());
        error_log("SQL: " . ($sql ?? 'SQL not built'));
        error_log("Values count: " . count($values ?? []));
        throw $e;
    }
}

ini_set("display_errors", 1);
include_once "./library/utils.php";

$base_url = "https://portal.olapay.us/api/v1/";
$base_url_local = "http://localhost:8090/api/v1/";
$base_url_staging = "https://portalstg.olapay.us/api/v1/";
// Debug getenv ENV value
error_log("ENV value: " . getenv('ENV'));

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
  
  if (getenv('ENV') === 'local') {
    $url = $base_url_local;
  } else if (getenv('ENV') === 'staging') {
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
  error_log("Inventory lock API response code: " . $httpcode);
  error_log("Inventory lock API response body: " . $response);

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
  
  if (getenv('ENV') === 'local') {
    $url = $base_url_local;
  } else if (getenv('ENV') === 'staging') {
    $url = $base_url_staging;
  }

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url . "inventory/order-paid/" . $orderUuid);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  $response = curl_exec($ch);
  $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  // Log response details for debugging
  error_log("Inventory release API response code: " . $httpcode);
  error_log("Inventory release API response body: " . $response);

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
  
  if (getenv('ENV') === 'local') {
    $url = $base_url_local;
  } else if (getenv('ENV') === 'staging') {
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
  error_log("Broadcast order paid API response code: " . $httpcode);
  error_log("Broadcast order paid API response body: " . $response);

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
error_log("vendors_id: " . $vendors_id);
$row2 = $stmt2->fetch();
$agents_id = $row2["accounts_id"];
error_log("agents_id: " . $agents_id);

####log raw data
$params["json"] = str_replace('&quot;', '"', $params["json"]);
$stmt = $pdo->prepare("insert into json set serial = ?, content = ?");
$res = $stmt->execute([$params["serial"], $params["json"]]);

#deal with test data here
#$stmt = $pdo->prepare("select * from json where id = ?");
#$stmt->execute([ "330" ]);
#$row = $stmt->fetch();
// Log raw JSON data for debugging
error_log("Received JSON params: " . $params["json"]);

$row["content"] = str_replace('&quot;', '"', $params["json"]);

$payments = json_decode(json_decode($row["content"])->{"payments"}) ?? [];
$items_json = json_decode(json_decode($row["content"])->{"items"}) ?? [];
$orders_json = json_decode(json_decode($row["content"])->{"orders"}) ?? [];
$groups_json = json_decode(json_decode($row["content"])->{"groups"}) ?? [];
$termId = json_decode(json_decode($row["content"])->{"termId"});
$itemdata_json = json_decode(json_decode($row["content"])->{"itemdata"}) ?? [];
$hasInventory = json_decode(json_decode($row["content"])->{"hasInventory"}) ?? false;
$isOnlinePlatform = json_decode(json_decode($row["content"])->{"isOnlinePlatform"}) ?? false;
$store_uuid = json_decode(json_decode($row["content"])->{"store_uuid"}) ?? "";
error_log("hasInventory: " . $hasInventory);
error_log("payments: " . json_encode($payments));
error_log("store_uuid: " . $store_uuid);
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

#必須先掃一次傳上來的payment; 如果payDate已經存在，就要把order, orderPayments, orderItems都清空, 再重新插入; 這邊是因應orderPayments在refund後lastmod改變但payrDate不變
for ($i = 0; $i < count($payments); $i++) {
  $payDate = strtotime($payments[$i]->{"payDate"});

  #if online order then order reference is okay; if not online order then order reference duplication would be long ago 
  $orderReference = $payments[$i]->{"orderReference"};
  $stmt = $pdo->prepare("select * from orders where terminals_id = ? and orderReference = ? order by id desc limit 0,1");
  $stmt->execute([$terminals_id,  $orderReference]);
  $row2 = $stmt->fetch();

  // Update order tips if lastmod is greater than old lastmod
  try {
    print("Start checking order tips");
    print("payments[$i]->{\"lastMod\"]: " . $payments[$i]->{"lastMod"});
    print("row2[\"lastMod\"]: " . $row2["lastMod"]);
    if ($payments[$i]->{"lastMod"} > $row2["lastMod"]) {
      print("Update order tip");
      $stmt = $pdo->prepare("update orders set tip = ? where id = ?");
      $stmt->execute([(float)$payments[$i]->{"tips"}, $row2["id"]]);
    }
    print("End checking order tips");
  } catch (Exception $e) {
    error_log("Error updating order tips: " . $e->getMessage());
  }

  // Update total if lastmod is greater than old lastmod
  try {
    print("Start checking order total");
    print("payments[$i]->{\"lastMod\"}: " . $payments[$i]->{"lastMod"});
    print("row2[\"lastMod\"]: " . $row2["lastMod"]);
    if ($payments[$i]->{"lastMod"} > $row2["lastMod"]) {
      print("Update order total");
      $stmt = $pdo->prepare("update orders set total = ? where id = ?");
      $stmt->execute([(float)$payments[$i]->{"total"}, $row2["id"]]);
    }
    print("End checking order total");
  } catch (Exception $e) {
    error_log("Error updating order total: " . $e->getMessage());
  }

  // Update techfee if lastmod is greater than old lastmod
  try {
    print("Start checking order techfee");
    print("payments[$i]->{\"lastMod\"}: " . $payments[$i]->{"lastMod"});
    print("row2[\"lastMod\"]: " . $row2["lastMod"]);
    if ($payments[$i]->{"lastMod"} > $row2["lastMod"]) {
      print("Update order techfee");
      $stmt = $pdo->prepare("update orders set tech_fee = ? where id = ?");
      $stmt->execute([(float)$payments[$i]->{"techfee"}, $row2["id"]]);
    }
    print("End checking order techfee");
  } catch (Exception $e) {
    error_log("Error updating order techfee: " . $e->getMessage());
  }

  $stmt = $pdo->prepare("select * from ordersPayments where terminals_id = ? and payDate = ? and payDate != 0 and orderReference = ?");
  $stmt->execute([$terminals_id, $payDate, $row2["id"]]);
  if ($stmt->rowCount() != 0) {
    $row = $stmt->fetch();
    $id = $row["id"];
    $new_tips = $payments[$i]->{"tips"};
    $stmt2 = $pdo->prepare("update ordersPayments set refund = ?, tips = ?, lastMod = ? where id = ?");
    $stmt2->execute([$payments[$i]->{"refund"}, $new_tips, $payments[$i]->{"lastMod"}, $id]);
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
    $order_uuid = $orders_json[$i]->{"uuid"};
  } else {
    $order_uuid = $orders_json[$i]->{"oUUID"};
  }
  $employee_pin = $orders_json[$i]->{"employeePIN"};
  $delivery_type = "";
  $delivery_fee = "";
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
  if ($stmt->rowCount() != 0) { //should be zero
    $fp = fopen("./tmp/aa.txt", "w");
    fputs($fp, time() . " 資料庫中仍有相同lastMod的資料 跳過\n");
    fclose($fp);

    // Add existing order to map for payment processing
    $existing_order_stmt = $pdo->prepare("select id from orders where terminals_id = ? and orderReference = ? order by id desc limit 1");
    $existing_order_stmt->execute([$terminals_id, $orderReference]);
    if ($existing_order_stmt->rowCount() > 0) {
      $existing_order = $existing_order_stmt->fetch();
      $orderRefMap[strval($orderReference)] = $existing_order["id"];
      error_log("Added existing order to map: " . $orderReference . " -> " . $existing_order["id"]);
    }

    if ($isOnlinePlatform == true) {
      # update order using uuid
      // employeeId
      // id :1223
      // lastMod :1738382969,
      // notes :\\"\\",
      // uuid :\\"f1230893-746e-46d7-9c58-8ffbf5714c56\\",
      // orderReference :1223,
      // onlineorder_id :\\"173-201-892\\",
      // onlinetrans_id :\\"250201040927534\\",
      // orderDate :\\"2025-02-01T04:09:29.562Z\\",
      // orderName :\\"\\",
      // status :\\"PAID\\",
      // subTotal :1.99,
      // tax :0,
      // delivery_fee :0,
      // delivery_type :\\"SELF-PICKUP\\",
      // total :1.99
      $stmt_update_online_platform_order = $pdo->prepare("update orders set 
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
        where terminals_id = ? and onlineorder_id = ?");
      $stmt_update_online_platform_order->execute([
        $employee_id,
        $lastMod,
        $notes,
        $online_platform_order_uuid,
        $orderReference,
        $onlineorder_id,
        $onlinetrans_id,
        $orderDate,
        $orderName,
        0,
        $subTotal,
        $tax,
        $delivery_fee,
        getDeliveryServiceCode($delivery_type),
        $total,
        $store_uuid,
        $terminals_id,
        $onlineorder_id
      ]);
      error_log("Updated online platform order with onlineorder_id " . $onlineorder_id . " to orderReference " . $orderReference . " - Rows affected: " . $stmt_update_online_platform_order->rowCount());
    }

    // Process order items for existing orders
    $existing_order_stmt = $pdo->prepare("select * from orders where id = ?;");
    $existing_order_stmt->execute([$orderRefMap[strval($orderReference)]]);
    $order = $existing_order_stmt->fetch();
    
    if ($order) {
      error_log("DEBUG: Processing items for existing order - orderReference: $orderReference");
      
      // Collect all items for batch insert
      $batch_items = [];
      $items_with_mods = [];
      
      error_log("DEBUG: Starting batch processing for orderReference: $orderReference");
      error_log("DEBUG: Total items to process: " . count($items_json));
      
      for ($j = 0; $j < count($items_json); $j++) {
        $itemOrderRef = strval($items_json[$j]->{"orderItem"}->{"orderReference"});
        error_log("DEBUG: Item $j - orderRef: $itemOrderRef vs current orderRef: $orderReference");
        
        if ($orderReference == $itemOrderRef) {
          error_log("DEBUG: Match found - processing item $j for orderReference: $orderReference");
          
          $col = (array) $items_json[$j]->{"orderItem"};
          $item_notes = $col["notes"] ?? "";
          if (empty($item_notes)) {
            $item_notes = "";
          }
          if (!isset($col["taxAmount"])) {
            $col["taxAmount"] = "0";
          }
          if (!isset($col["itemId"])) {
            $col["itemId"] = "0";
          }
          
          $group_name = '';
          if ($isOnlinePlatform == true) {
            $stmt_online_order_groups = $pdo->prepare("select * from online_order_groups where id = ?");
            $stmt_online_order_groups->execute([$col["group"]]);
            $row_online_order_groups = $stmt_online_order_groups->fetch();
            $group_name = $row_online_order_groups["name"] ?? '';
          } else {
            $group_name = $group_names[$col["group"]] ?? '';
          }
          
          $item_row = [
            'itemUuid' => ($isOnlinePlatform ? $col["uuid"] : $col["iUUID"]) ?? null,
            'orderUuid' => $order["uuid"],
            'agents_id' => $agents_id,
            'vendors_id' => $vendors_id,
            'terminals_id' => $terminals_id,
            'group_name' => $group_name,
            'orders_id' => $orderRefMap[strval($orderReference)],
            'cost' => $col["cost"],
            'description' => $col["description"],
            'group_id' => $col["group"],
            'notes' => $item_notes,
            'price' => $col["price"],
            'taxable' => $col["taxable"],
            'qty' => $col["qty"],
            'items_id' => $isOnlinePlatform ? $col["itemId"] : '0',
            'discount' => $col["discount"],
            'taxamount' => $col["taxAmount"],
            'itemid' => $col["itemId"],
            'ebt' => $col["ebt"] ?? 0,
            'crv' => $col["crv"] ?? 0,
            'crv_taxable' => $col["crv_taxable"] ?? 0,
            'itemDiscount' => $col["itemDiscount"] ?? 0,
            'status' => getStatusCode($col["status"] ?? null),
            'itemsAddedDateTime' => time(),
            'lastMod' => time()
          ];
          
          $batch_items[] = $item_row;
          error_log("DEBUG: Added item to batch - total batch items now: " . count($batch_items));
          
          if (isset($items_json[$j]->{"mods"}) && count($items_json[$j]->{"mods"}) > 0) {
            $items_with_mods[] = [
              'item_data' => $items_json[$j],
              'batch_index' => count($batch_items) - 1
            ];
            error_log("DEBUG: Item has mods - added to mods processing queue");
          }
        } else {
          error_log("DEBUG: No match - skipping item $j (orderRef: $itemOrderRef)");
        }
      }
      
      error_log("DEBUG: Finished processing items - final batch count: " . count($batch_items));
      
      if (!empty($batch_items)) {
        error_log("DEBUG: About to execute batch insert for " . count($batch_items) . " items, orderRef: $orderReference");
        
        $stmt_clear_old_order_items = $pdo->prepare("delete from orderItems where orders_id = ?");
        $stmt_clear_old_order_items->execute([$orderRefMap[strval($orderReference)]]);
        error_log("DEBUG: Cleared old order items, affected rows: " . $stmt_clear_old_order_items->rowCount());

        $inserted_item_ids = batchInsertOrderItems($pdo, $batch_items);
        error_log("DEBUG: Batch inserted " . count($inserted_item_ids) . " items for payment reference $orderReference");
        
        // Handle mods
        if (!empty($items_with_mods)) {
          $mod_batch_items = [];
          
          foreach ($items_with_mods as $item_with_mods) {
            $item_data = $item_with_mods['item_data'];
            $batch_index = $item_with_mods['batch_index'];
            $parent_item_id = $inserted_item_ids[$batch_index];
            
            for ($k = 0; $k < count($item_data->{"mods"}); $k++) {
              $col = (array) $item_data->{"mods"}[$k];
              if (!isset($col["taxAmount"])) {
                $col["taxAmount"] = "0";
              }
              if (!isset($col["itemId"])) {
                $col["itemId"] = "0";
              }
              
              $mod_row = [
                'itemUuid' => ($isOnlinePlatform ? $col["uuid"] : $col["iUUID"]) ?? null,
                'orderUuid' => $order["uuid"],
                'agents_id' => $agents_id ?? -1,
                'vendors_id' => $vendors_id,
                'terminals_id' => $terminals_id,
                'group_name' => null,
                'orders_id' => $orderRefMap[strval($orderReference)],
                'cost' => $col["cost"],
                'description' => $col["description"],
                'group_id' => $col["group"],
                'notes' => $col["notes"] ?? "",
                'price' => $col["price"],
                'taxable' => $col["taxable"],
                'qty' => 1,
                'items_id' => $parent_item_id,
                'discount' => 0,
                'taxamount' => $col["taxAmount"],
                'itemid' => $col["itemId"],
                'ebt' => $col["ebt"] ?? 0,
                'crv' => $col["crv"] ?? 0,
                'crv_taxable' => $col["crv_taxable"] ?? 0,
                'itemDiscount' => 0,
                'status' => 0,
                'itemsAddedDateTime' => time(),
                'lastMod' => time()
              ];
              
              $mod_batch_items[] = $mod_row;
            }
          }
          
          if (!empty($mod_batch_items)) {
            batchInsertOrderItems($pdo, $mod_batch_items);
            error_log("Batch inserted " . count($mod_batch_items) . " modifiers for payment reference $orderReference");
          }
        }
      } else {
        error_log("DEBUG: No batch items to insert for orderReference: $orderReference");
      }
    }

    continue;
  } else {
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
      orderName = ?, status = ?, store_uuid = ?");

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
        $store_uuid
      ]);
      // set orderReference to the new order id for later process
      if (empty($orderReference) || is_null($orderReference)) {
        $orderReference = $pdo->lastInsertId();
      }
      print("===orderReference");
      print_r($orderReference);
    } else {
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
        $store_uuid
      ]);
    }
    $orderRefMap[strval($orderReference)] = $pdo->lastInsertId();
    error_log("===orderRefMap");
    error_log(json_encode($orderRefMap));

    // Process order items for new orders (reuse the same logic as existing orders)
    $new_order_stmt = $pdo->prepare("select * from orders where id = ?;");
    $new_order_stmt->execute([$orderRefMap[strval($orderReference)]]);
    $order = $new_order_stmt->fetch();
    
    if ($order) {
      error_log("DEBUG: Processing items for new order - orderReference: $orderReference");
      
      // Collect all items for batch insert
      $batch_items = [];
      $items_with_mods = [];
      
      error_log("DEBUG: Starting batch processing for orderReference: $orderReference");
      error_log("DEBUG: Total items to process: " . count($items_json));
      
      for ($j = 0; $j < count($items_json); $j++) {
        $itemOrderRef = strval($items_json[$j]->{"orderItem"}->{"orderReference"});
        error_log("DEBUG: Item $j - orderRef: $itemOrderRef vs current orderRef: $orderReference");
        
        if ($orderReference == $itemOrderRef) {
          error_log("DEBUG: Match found - processing item $j for orderReference: $orderReference");
          
          $col = (array) $items_json[$j]->{"orderItem"};
          $item_notes = $col["notes"] ?? "";
          if (empty($item_notes)) {
            $item_notes = "";
          }
          if (!isset($col["taxAmount"])) {
            $col["taxAmount"] = "0";
          }
          if (!isset($col["itemId"])) {
            $col["itemId"] = "0";
          }
          
          $group_name = '';
          if ($isOnlinePlatform == true) {
            $stmt_online_order_groups = $pdo->prepare("select * from online_order_groups where id = ?");
            $stmt_online_order_groups->execute([$col["group"]]);
            $row_online_order_groups = $stmt_online_order_groups->fetch();
            $group_name = $row_online_order_groups["name"] ?? '';
          } else {
            $group_name = $group_names[$col["group"]] ?? '';
          }
          
          $item_row = [
            'itemUuid' => ($isOnlinePlatform ? $col["uuid"] : $col["iUUID"]) ?? null,
            'orderUuid' => $order["uuid"],
            'agents_id' => $agents_id,
            'vendors_id' => $vendors_id,
            'terminals_id' => $terminals_id,
            'group_name' => $group_name,
            'orders_id' => $orderRefMap[strval($orderReference)],
            'cost' => $col["cost"],
            'description' => $col["description"],
            'group_id' => $col["group"],
            'notes' => $item_notes,
            'price' => $col["price"],
            'taxable' => $col["taxable"],
            'qty' => $col["qty"],
            'items_id' => $isOnlinePlatform ? $col["itemId"] : '0',
            'discount' => $col["discount"],
            'taxamount' => $col["taxAmount"],
            'itemid' => $col["itemId"],
            'ebt' => $col["ebt"] ?? 0,
            'crv' => $col["crv"] ?? 0,
            'crv_taxable' => $col["crv_taxable"] ?? 0,
            'itemDiscount' => $col["itemDiscount"] ?? 0,
            'status' => getStatusCode($col["status"] ?? null),
            'itemsAddedDateTime' => time(),
            'lastMod' => time()
          ];
          
          $batch_items[] = $item_row;
          error_log("DEBUG: Added item to batch - total batch items now: " . count($batch_items));
          
          if (isset($items_json[$j]->{"mods"}) && count($items_json[$j]->{"mods"}) > 0) {
            $items_with_mods[] = [
              'item_data' => $items_json[$j],
              'batch_index' => count($batch_items) - 1
            ];
            error_log("DEBUG: Item has mods - added to mods processing queue");
          }
        } else {
          error_log("DEBUG: No match - skipping item $j (orderRef: $itemOrderRef)");
        }
      }
      
      error_log("DEBUG: Finished processing items - final batch count: " . count($batch_items));
      
      if (!empty($batch_items)) {
        error_log("DEBUG: About to execute batch insert for " . count($batch_items) . " items, orderRef: $orderReference");
        
        $stmt_clear_old_order_items = $pdo->prepare("delete from orderItems where orders_id = ?");
        $stmt_clear_old_order_items->execute([$orderRefMap[strval($orderReference)]]);
        error_log("DEBUG: Cleared old order items, affected rows: " . $stmt_clear_old_order_items->rowCount());

        $inserted_item_ids = batchInsertOrderItems($pdo, $batch_items);
        error_log("DEBUG: Batch inserted " . count($inserted_item_ids) . " items for payment reference $orderReference");
        
        // Handle mods
        if (!empty($items_with_mods)) {
          $mod_batch_items = [];
          
          foreach ($items_with_mods as $item_with_mods) {
            $item_data = $item_with_mods['item_data'];
            $batch_index = $item_with_mods['batch_index'];
            $parent_item_id = $inserted_item_ids[$batch_index];
            
            for ($k = 0; $k < count($item_data->{"mods"}); $k++) {
              $col = (array) $item_data->{"mods"}[$k];
              if (!isset($col["taxAmount"])) {
                $col["taxAmount"] = "0";
              }
              if (!isset($col["itemId"])) {
                $col["itemId"] = "0";
              }
              
              $mod_row = [
                'itemUuid' => ($isOnlinePlatform ? $col["uuid"] : $col["iUUID"]) ?? null,
                'orderUuid' => $order["uuid"],
                'agents_id' => $agents_id ?? -1,
                'vendors_id' => $vendors_id,
                'terminals_id' => $terminals_id,
                'group_name' => null,
                'orders_id' => $orderRefMap[strval($orderReference)],
                'cost' => $col["cost"],
                'description' => $col["description"],
                'group_id' => $col["group"],
                'notes' => $col["notes"] ?? "",
                'price' => $col["price"],
                'taxable' => $col["taxable"],
                'qty' => 1,
                'items_id' => $parent_item_id,
                'discount' => 0,
                'taxamount' => $col["taxAmount"],
                'itemid' => $col["itemId"],
                'ebt' => $col["ebt"] ?? 0,
                'crv' => $col["crv"] ?? 0,
                'crv_taxable' => $col["crv_taxable"] ?? 0,
                'itemDiscount' => 0,
                'status' => 0,
                'itemsAddedDateTime' => time(),
                'lastMod' => time()
              ];
              
              $mod_batch_items[] = $mod_row;
            }
          }
          
          if (!empty($mod_batch_items)) {
            batchInsertOrderItems($pdo, $mod_batch_items);
            error_log("Batch inserted " . count($mod_batch_items) . " modifiers for payment reference $orderReference");
          }
        }
      } else {
        error_log("DEBUG: No batch items to insert for orderReference: $orderReference");
      }
    }

    error_log("count(payments): " . count($payments));
    error_log("hasInventory: " . $hasInventory);
    // call lock inventory if there is no payment and hasInventory is true
    if ((count($payments) == 0) && $hasInventory) {
      error_log("call_inventory_lock");
      $error = call_inventory_lock($order_uuid);
      if ($error) {
        error_log("Inventory lock failed for order: " . $order_uuid . " Error: " . $error);
      }
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
    // If hasInventory is false, use the existing mapping logic
    error_log("orderRefMap");
    error_log(json_encode($orderRefMap));
    
    $orderRefKey = strval($payments[$i]->{"orderReference"});
    if (!isset($orderRefMap[$orderRefKey])) {
      // Try to find the order in database
      $fallback_stmt = $pdo->prepare("select id from orders where terminals_id = ? and orderReference = ? order by id desc limit 1");
      $fallback_stmt->execute([$terminals_id, $payments[$i]->{"orderReference"}]);
      if ($fallback_stmt->rowCount() > 0) {
        $fallback_order = $fallback_stmt->fetch();
        $orderRefMap[$orderRefKey] = $fallback_order["id"];
        $orderRef = $fallback_order["id"];
        error_log("Found order in fallback lookup: " . $orderRefKey . " -> " . $orderRef);
      } else {
        error_log("ERROR: Could not find order for payment orderReference: " . $orderRefKey);
        continue; // Skip this payment if order not found
      }
    } else {
      $orderRef = $orderRefMap[$orderRefKey];
    }
    
    if ($isOnlinePlatform == true) {
      $order_uuid = $payments[$i]->{"orderUUID"} ?? null;
    } else {
      $order_uuid = $payments[$i]->{"oUUID"} ?? null;
    }
  }
  $payment_uuid = $payments[$i]->{"pUUID"} ?? null;
  $olapayApprovalId = $payments[$i]->{"olapayApprovalId"} ?? null;
  #echo $amtPaid . " " . $total . " " . $lastMod . " " . $orderRef;
  $stmt = $pdo->prepare("select * from ordersPayments where terminals_id = ? and lastMod = ?");
  $stmt->execute([$terminals_id, $lastMod]);
  if ($stmt->rowCount() != 0) { //should be zero
    //if ( 0 ) {
    continue;
  } else {
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
    status = ?");
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
      0
    ]);
    #$orders_id = $pdo->lastInsertId();
    #update orderId
    #$stmt = $pdo->prepare("update ordersPayments set orderId = ? where id = ?");
    #$stmt->execute([ $termId . '0' . $orderRef, $orders_id  ]);
    // Delete pending order
    if ($order_uuid) {
      $delete_pending_order_stmt = $pdo->prepare("delete from pending_orders where uuid = ?");
      if (!$delete_pending_order_stmt->execute([$order_uuid])) {
        error_log("Failed to delete pending order for UUID: " . $order_uuid);
      }
    }

    // Release inventory lock only if hasInventory is true
    if ($hasInventory) {
      $error = call_inventory_paid($order_uuid);
      if ($error) {
        error_log("Inventory paid failed for order UUID: " . $order_uuid . " Error: " . $error);
      }
    } 
    $error = call_broadcast_order_paid($order_uuid);
    if ($error) {
      error_log("Broadcast order paid failed for order UUID: " . $order_uuid . " Error: " . $error);
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
