<?php
ini_set("display_errors", 1);
include_once "./library/utils.php";
$base_url = "https://portal.olapay.us/api/v1/";
$base_url_local = "http://localhost:8090/api/v1/";
// Debug getenv ENV value
error_log("ENV value: " . (getenv('ENV') === 'local' ? 'local' : 'not set'));

function call_inventory_lock($orderUuid)
{
  if (!$orderUuid) {
    return null;
  }

  // Skip actual inventory lock in test mode
  if (isset($_SERVER['HTTP_X_TEST_MODE']) && $_SERVER['HTTP_X_TEST_MODE'] === 'true') {
    return null;
  }

  global $base_url, $base_url_local;
  $url = getenv('ENV') === 'local' ? $base_url_local : $base_url;

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url . "inventory/order-lock/" . $orderUuid);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  $response = curl_exec($ch);
  $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

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

  global $base_url, $base_url_local;
  $url = getenv('ENV') === 'local' ? $base_url_local : $base_url;

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url . "inventory/order-paid/" . $orderUuid);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  $response = curl_exec($ch);
  $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

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

// Handle both string and array cases for json parameter
$jsonContent = null;
if (is_array($params["json"])) {
  // If it's already an array, store it as JSON string for logging
  $jsonContent = json_encode($params["json"]);
} else {
  // If it's a string, clean it and use as is
  $jsonContent = str_replace('&quot;', '"', $params["json"]);
}

// Log raw data
$stmt = $pdo->prepare("insert into json set serial = ?, content = ?");
$res = $stmt->execute([$params["serial"], $jsonContent]);

// Decode the JSON for processing
$jsonData = json_decode($jsonContent);
if (json_last_error() !== JSON_ERROR_NONE) {
  send_http_status_and_exit("400", "Invalid JSON format: " . json_last_error_msg());
}

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
$row2 = $stmt2->fetch();

// Validate JSON data types while maintaining original logic
$payments = [];
$items_json = [];
$orders_json = [];
$groups_json = [];
$itemdata_json = [];

// Safely decode JSON arrays
if (isset($jsonData->payments)) {
  $payments = is_array($jsonData->payments) ? $jsonData->payments : (is_string($jsonData->payments) ? json_decode($jsonData->payments) : []);
}
if (isset($jsonData->items)) {
  $items_json = is_array($jsonData->items) ? $jsonData->items : (is_string($jsonData->items) ? json_decode($jsonData->items) : []);
}
if (isset($jsonData->orders)) {
  $orders_json = is_array($jsonData->orders) ? $jsonData->orders : (is_string($jsonData->orders) ? json_decode($jsonData->orders) : []);
}
if (isset($jsonData->groups)) {
  $groups_json = is_array($jsonData->groups) ? $jsonData->groups : (is_string($jsonData->groups) ? json_decode($jsonData->groups) : []);
}
if (isset($jsonData->itemdata)) {
  $itemdata_json = is_array($jsonData->itemdata) ? $jsonData->itemdata : (is_string($jsonData->itemdata) ? json_decode($jsonData->itemdata) : []);
}

// Ensure arrays are valid
$payments = is_array($payments) ? $payments : [];
$items_json = is_array($items_json) ? $items_json : [];
$orders_json = is_array($orders_json) ? $orders_json : [];
$groups_json = is_array($groups_json) ? $groups_json : [];
$itemdata_json = is_array($itemdata_json) ? $itemdata_json : [];

$termId = $jsonData->termId ?? null;
$hasInventory = $jsonData->hasInventory ?? false;

// Set default value of -1 for agents_id if null
$agents_id = $hasInventory ? ($row2["accounts_id"] ?? -1) : ($row2["accounts_id"] ?? -1);
$vendors_id = $row2["id"] ?? -1;  // Add default for vendors_id too

$group_names = array();
if (is_array($groups_json)) {
  for ($i = 0; $i < count($groups_json); $i++) {
    if (!isset($groups_json[$i])) {
      continue;
    }
    $groups_id = $groups_json[$i]->id ?? null;
    $description = $groups_json[$i]->description ?? '';
    $groupType = $groups_json[$i]->groupType ?? '';
    $notes = $groups_json[$i]->notes ?? '';
    $lastMod = $groups_json[$i]->lastMod ?? 0;

    if ($groups_id !== null) {
      $group_names[$groups_id] = $description;
    }
  }
}

$fp = fopen("./tmp/aa.txt", "a");
fputs($fp, time() . " 開始處理資料\n");
fclose($fp);

#必須先掃一次傳上來的payment; 如果payDate已經存在，就要把order, orderPayments, orderItems都清空, 再重新插入; 這邊是因應orderPayments在refund後lastmod改變但payrDate不變
for ($i = 0; $i < count($payments); $i++) {
  if (!isset($payments[$i])) {
    continue;
  }

  $payDate = isset($payments[$i]->payDate) ? strtotime($payments[$i]->payDate) : 0;
  if ($payDate === false || $payDate === 0) {
    error_log("Invalid payDate for payment index: " . $i);
    continue;
  }

  // Convert Unix timestamp to MySQL DATETIME format
  $payDateFormatted = date('Y-m-d H:i:s', $payDate);

  $orderReference = $payments[$i]->orderReference ?? null;
  if ($orderReference === null) {
    error_log("Missing orderReference for payment index: " . $i);
    continue;
  }

  try {
    $stmt = $pdo->prepare("select * from orders where terminals_id = ? and orderReference = ? order by id desc limit 0,1");
    if (!$stmt->execute([$terminals_id, $orderReference])) {
      error_log("Failed to query orders for orderReference: " . $orderReference);
      continue;
    }
    $row2 = $stmt->fetch();
    if (!$row2) {
      continue;
    }

    $stmt = $pdo->prepare("select * from ordersPayments where terminals_id = ? and payDate = ? and payDate != 0 and orderReference = ?");
    if (!$stmt->execute([$terminals_id, $payDateFormatted, $row2["id"]])) {
      error_log("Failed to query ordersPayments");
      continue;
    }

    if ($stmt->rowCount() != 0) {
      $row = $stmt->fetch();
      if (!$row) {
        continue;
      }
      $id = $row["id"];
      $stmt2 = $pdo->prepare("update ordersPayments set refund = ?, lastMod = ? where id = ?");
      if (!$stmt2->execute([$payments[$i]->refund ?? 0, $payments[$i]->lastMod ?? time(), $id])) {
        error_log("Failed to update ordersPayments refund for id: " . $id);
        continue;
      }

      $fp = fopen("./tmp/aa.txt", "a");
      fputs($fp, time() . " 更新refund資料\n");
      fclose($fp);
    }
  } catch (PDOException $e) {
    error_log("Database error processing payment: " . $e->getMessage());
    continue;
  }
}


#id map, orderPayments的orderReference不應該是機器上的Orders ID,應該要是主機上的Orders ID
$orderRefMap = array();
for ($i = 0; $i < count($orders_json); $i++) {
  if (!isset($orders_json[$i])) {
    continue;
  }

  try {
    $orderReference = $orders_json[$i]->id ?? null;
    if ($orderReference === null) {
      error_log("Missing order reference at index: " . $i);
      continue;
    }

    // new fields with proper type handling
    $order_uuid = $orders_json[$i]->oUUID ?? null;
    $employee_pin = $orders_json[$i]->employeePIN ?? null;
    $delivery_type = isset($orders_json[$i]->delivery_type) ? (int)$orders_json[$i]->delivery_type : 0;

    // Handle delivery fee - ensure it's a valid float
    $delivery_fee = $orders_json[$i]->delivery_fee ?? 0.0;
    $delivery_fee = is_numeric($delivery_fee) ? (float)$delivery_fee : 0.0;

    // Handle other numeric fields
    $subTotal = is_numeric($orders_json[$i]->subTotal) ? (float)$orders_json[$i]->subTotal : 0.0;
    $tax = is_numeric($orders_json[$i]->tax) ? (float)$orders_json[$i]->tax : 0.0;
    $total = is_numeric($orders_json[$i]->total) ? (float)$orders_json[$i]->total : 0.0;

    // Handle text fields
    $notes = $orders_json[$i]->notes ?? '';
    $orderName = $orders_json[$i]->orderName ?? '';

    // Handle integer fields
    $employee_id = isset($orders_json[$i]->employeeId) ? (int)$orders_json[$i]->employeeId : 0;
    $status = isset($orders_json[$i]->status) ? (int)$orders_json[$i]->status : 0;
    $lastMod = isset($orders_json[$i]->lastMod) ? (int)$orders_json[$i]->lastMod : 0;
    $terminal_id = isset($orders_json[$i]->terminal_id) ? (int)$orders_json[$i]->terminal_id : null;

    // Handle decimal fields
    $tip = isset($orders_json[$i]->tip) ? number_format((float)$orders_json[$i]->tip, 2, '.', '') : '0.00';
    $tech_fee = isset($orders_json[$i]->tech_fee) ? number_format((float)$orders_json[$i]->tech_fee, 2, '.', '') : '0.00';
    $discount_amount = isset($orders_json[$i]->discount_amount) ? number_format((float)$orders_json[$i]->discount_amount, 2, '.', '') : '0.00';

    // Handle JSON fields
    $delivery_info = isset($orders_json[$i]->delivery_info) ? json_encode($orders_json[$i]->delivery_info) : null;
    $customer_info = isset($orders_json[$i]->customer_info) ? json_encode($orders_json[$i]->customer_info) : null;
    $payment_info = isset($orders_json[$i]->payment_info) ? json_encode($orders_json[$i]->payment_info) : null;
    $order_items = isset($orders_json[$i]->order_items) ? json_encode($orders_json[$i]->order_items) : null;
    $delivery_service_update_payload = isset($orders_json[$i]->delivery_service_update_payload) ?
      json_encode($orders_json[$i]->delivery_service_update_payload) : null;

    // Handle other string fields
    $store_uuid = $orders_json[$i]->store_uuid ?? null;
    $delivery_status = $orders_json[$i]->delivery_status ?? null;
    $onlineorder_id = $orders_json[$i]->onlineorder_id ?? '';
    $onlinetrans_id = $orders_json[$i]->onlinetrans_id ?? null;

    // Handle integer fields with defaults
    $prep_time = isset($orders_json[$i]->prep_time) ? (int)$orders_json[$i]->prep_time : 30;
    $pending_order_id = isset($orders_json[$i]->pending_order_id) ? (int)$orders_json[$i]->pending_order_id : null;
    $new_order_timestamp = isset($orders_json[$i]->new_order_timestamp) ? (int)$orders_json[$i]->new_order_timestamp : null;

    // Handle orderDate - moved before its usage
    $orderDate = isset($orders_json[$i]->orderDate) ? strtotime($orders_json[$i]->orderDate) : 0;
    if ($orderDate === false || $orderDate === 0) {
      error_log("Invalid orderDate for order index: " . $i);
      continue;
    }

    // Convert Unix timestamp to MySQL DATETIME format
    $orderDateFormatted = date('Y-m-d H:i:s', $orderDate);

    $stmt = $pdo->prepare("select * from orders where terminals_id = ? and lastMod = ?");
    $stmt->execute([$terminals_id, $lastMod]);

    if ($stmt->rowCount() != 0) {
      $fp = fopen("./tmp/aa.txt", "w");
      fputs($fp, time() . " 資料庫中仍有相同lastMod的資料 跳過\n");
      fclose($fp);
      continue;
    }

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
        orderName = ?,
        employee_id = ?,
        orderDate = ?,
        delivery_type = ?,
        delivery_fee = ?,
        status = ?,
        lastMod = ?,
        employee_pin = ?,
        store_uuid = ?,
        terminal_id = ?,
        tip = ?,
        tech_fee = ?,
        delivery_info = ?,
        customer_info = ?,
        payment_info = ?,
        order_items = ?,
        discount_amount = ?,
        delivery_status = ?,
        delivery_service_update_payload = ?,
        prep_time = ?,
        pending_order_id = ?,
        new_order_timestamp = ?,
        onlineorder_id = ?,
        onlinetrans_id = ?");

    if (!$stmt->execute([
      $order_uuid,
      $agents_id,
      $vendors_id,
      $terminals_id,
      $orderReference,
      $subTotal,
      $tax,
      $total,
      $notes,
      $orderName,
      $employee_id,
      $orderDateFormatted,
      $delivery_type,
      $delivery_fee,
      $status,
      $lastMod,
      $employee_pin,
      $store_uuid,
      $terminal_id,
      $tip,
      $tech_fee,
      $delivery_info,
      $customer_info,
      $payment_info,
      $order_items,
      $discount_amount,
      $delivery_status,
      $delivery_service_update_payload,
      $prep_time,
      $pending_order_id,
      $new_order_timestamp,
      $onlineorder_id,
      $onlinetrans_id
    ])) {
      error_log("Failed to insert order: " . $orderReference);
      continue;
    }

    $orderRefMap[strval($orderReference)] = $pdo->lastInsertId();

    // call lock inventory if there is no payment and hasInventory is true
    if (count($payments) == 0 && $hasInventory) {
      $error = call_inventory_lock($order_uuid);
      if ($error) {
        error_log("Inventory lock failed for order: " . $order_uuid . " Error: " . $error);
        send_http_status_and_exit("400", $error);
      }
    }
  } catch (PDOException $e) {
    error_log("Database error processing order at index " . $i . ": " . $e->getMessage());
    continue;
  } catch (Exception $e) {
    error_log("General error processing order at index " . $i . ": " . $e->getMessage());
    continue;
  }
}

for ($i = 0; $i < count($payments); $i++) {
  if (!isset($payments[$i])) {
    continue;
  }

  try {
    // Extract payment data with validation
    $payment_uuid = $payments[$i]->pUUID ?? null;
    $order_uuid = $payments[$i]->oUUID ?? null;
    $olapayApprovalId = $payments[$i]->olapayApprovalId ?? null;
    $employee_id = $payments[$i]->employeeId ?? null;
    $amtPaid = $payments[$i]->amtPaid ?? 0.0;
    $total = $payments[$i]->total ?? 0.0;
    $refNumber = $payments[$i]->refNumber ?? '';
    $tips = $payments[$i]->tips ?? 0.0;
    $refund = $payments[$i]->refund ?? 0.0;

    // Handle payDate - store as Unix timestamp (int)
    $payDate = isset($payments[$i]->payDate) ? strtotime($payments[$i]->payDate) : 0;
    if ($payDate === false || $payDate === 0) {
      error_log("Invalid payDate for payment: " . ($payment_uuid ?? 'unknown'));
      continue;
    }

    // Validate timestamp range
    if ($payDate < 0) {
      error_log("PayDate is before Unix epoch for payment: " . ($payment_uuid ?? 'unknown'));
      continue;
    }

    $techFee = $payments[$i]->techfee ?? 0.0;
    $lastMod = $payments[$i]->lastMod ?? time();
    $orderId = $payments[$i]->orderID ?? '';

    if (!isset($payments[$i]->orderReference)) {
      error_log("Missing orderReference for payment: " . ($payment_uuid ?? 'unknown'));
      continue;
    }

    $orderRef = isset($orderRefMap[strval($payments[$i]->orderReference)]) ?
      $orderRefMap[strval($payments[$i]->orderReference)] : null;

    if ($orderRef === null) {
      error_log("Could not find order reference mapping for payment: " . ($payment_uuid ?? 'unknown'));
      continue;
    }

    $stmt = $pdo->prepare("select * from ordersPayments where terminals_id = ? and lastMod = ?");
    if (!$stmt->execute([$terminals_id, $lastMod])) {
      error_log("Failed to query ordersPayments for lastMod: " . $lastMod);
      continue;
    }

    if ($stmt->rowCount() != 0) {
      continue;
    }

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
      employee_id = ?");

    if (!$stmt->execute([
      $payment_uuid,
      $order_uuid,
      $olapayApprovalId,
      $agents_id !== null ? $agents_id : -1,  // Ensure -1 if null
      $vendors_id !== null ? $vendors_id : -1, // Ensure -1 if null
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
      $employee_id !== null ? $employee_id : -1  // Ensure -1 if null
    ])) {
      error_log("Failed to insert payment record for payment: " . ($payment_uuid ?? 'unknown'));
      continue;
    }

    // Delete pending order
    if ($order_uuid) {
      $delete_pending_order_stmt = $pdo->prepare("delete from pending_orders where uuid = ?");
      if (!$delete_pending_order_stmt->execute([$order_uuid])) {
        error_log("Failed to delete pending order for UUID: " . $order_uuid);
      }
    }

    // Release inventory lock only if hasInventory is true
    if ($hasInventory) {
      $orderIndex = array_search(strval($payments[$i]->orderReference), array_column($orders_json, 'id'));
      if ($orderIndex !== false && isset($orders_json[$orderIndex]->uuid)) {
        $orderUuid = $orders_json[$orderIndex]->uuid;
        $error = call_inventory_paid($orderUuid);
        if ($error) {
          error_log("Inventory paid failed for order UUID: " . $orderUuid . " Error: " . $error);
          send_http_status_and_exit("400", $error);
        }
      }
    }
  } catch (PDOException $e) {
    error_log("Database error processing payment at index " . $i . ": " . $e->getMessage());
    continue;
  } catch (Exception $e) {
    error_log("General error processing payment at index " . $i . ": " . $e->getMessage());
    continue;
  }
}

if ($hasInventory == false) {
  for ($i = 0; $i < count($itemdata_json); $i++) {
    if (!isset($itemdata_json[$i])) {
      continue;
    }

    try {
      $description = $itemdata_json[$i]->description ?? null;
      if ($description === null) {
        error_log("Missing description for item at index: " . $i);
        continue;
      }

      $stmt = $pdo->prepare("select * from items where terminals_id = ? and `desc` = ?");
      if (!$stmt->execute([$terminals_id, $description])) {
        error_log("Failed to query items for description: " . $description);
        continue;
      }

      if ($stmt->rowCount() == 0) {
        $stmt = $pdo->prepare("insert into items set
                    uuid = ?,
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
                    uuid = ?,
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

      $group = $itemdata_json[$i]->group ?? 0;

      if (!$stmt->execute([
        $itemdata_json[$i]->iUUID ?? null,
        $agents_id,
        $vendors_id,
        $itemdata_json[$i]->id ?? 0,
        $itemdata_json[$i]->cost ?? 0.0,
        $itemdata_json[$i]->price ?? 0.0,
        $itemdata_json[$i]->notes ?? '',
        $itemdata_json[$i]->upc ?? '',
        $itemdata_json[$i]->taxable ?? 0,
        $itemdata_json[$i]->taxRate ?? 0.0,
        $group,
        $itemdata_json[$i]->amountOnHand ?? 0,
        $terminals_id,
        $description
      ])) {
        error_log("Failed to save item with description: " . $description);
        continue;
      }
    } catch (PDOException $e) {
      error_log("Database error processing item at index " . $i . ": " . $e->getMessage());
      continue;
    } catch (Exception $e) {
      error_log("General error processing item at index " . $i . ": " . $e->getMessage());
      continue;
    }
  }
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
