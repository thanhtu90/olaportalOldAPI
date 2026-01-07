<?php
ini_set("display_errors",1);
include_once "./library/utils.php";
//enable_cors();
$msgfornoterminal = "No permission";
$msgforsqlerror = "Unable to insert data";
$pdo = connect_db_and_set_http_method( "POST" );
$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING );
#$params = get_params_from_http_body([
#    "serial",
#    "json"
#]);
$params["serial"] = "PP35541912000051";
$stmt = $pdo->prepare("select * from terminals where serial = ?");
$stmt->execute([ $params["serial"] ]);
if ( $stmt->rowCount() == 0 ) {
    send_http_status_and_exit("403",$msgfornoterminal);
}
$row = $stmt->fetch();

#get terminal id, vendor id, and agent id
$terminals_id = $row["id"];
$vendors_id = $row["vendors_id"];
$stmt2 = $pdo->prepare("select * from accounts where id = ?");
$stmt2->execute([ $vendors_id ]);
$row2 = $stmt2->fetch();
$agents_id = $row2["accounts_id"];

####log raw data
#$params["json"] = str_replace('&quot;','"',$params["json"]);
#$stmt = $pdo->prepare("insert into json set serial = ?, content = ?");
#$res = $stmt->execute([ $params["serial"], $params["json"] ]);

#deal with test data here
$stmt = $pdo->prepare("select * from json where id = ?");
$stmt->execute([ "24848" ]);
$row = $stmt->fetch();
#$row["content"] = str_replace('&quot;','"',$params["json"]);

$payments =json_decode(json_decode($row["content"])->{"payments"});
$items_json = json_decode(json_decode($row["content"])->{"items"});
$orders_json = json_decode(json_decode($row["content"])->{"orders"});
$groups_json = json_decode(json_decode($row["content"])->{"groups"});
$termId = json_decode(json_decode($row["content"])->{"termId"});
$itemdata_json = json_decode(json_decode($row["content"])->{"itemdata"});

$group_names = Array();
for ( $i = 0; $i < count($groups_json); $i++) {
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

$fp = fopen("./tmp/aa.txt","a");
fputs( $fp, time() . " 開始處理資料\n" );
fclose($fp);
#var_dump($payments);
var_dump($items_json);
#必須先掃一次傳上來的payment; 如果payDate已經存在，就要把order, orderPayments, orderItems都清空, 再重新插入; 這邊是因應orderPayments在refund後lastmod改變但payrDate不變
/*
for ( $i = 0; $i < count($payments); $i++) {
  $payDate = strtotime($payments[$i]->{"payDate"});
  $stmt = $pdo->prepare("select * from ordersPayments where terminals_id = ? and payDate = ? and payDate != 0");
  $stmt->execute([ $terminals_id, $payDate ]);
  if ( $stmt->rowCount() != 0 ) {
    $row = $stmt->fetch();
    $id = $row["id"];
    $orderReference = $row["orderReference"];
    $stmt = $pdo->prepare("delete from ordersPayments where terminals_id = ? and id = ?");
    $stmt->execute([ $terminals_id, $id ]);
    $stmt = $pdo->prepare("delete from orders where terminals_id = ? and id = ?");
    $stmt->execute([ $terminals_id, $orderReference ]);
    $stmt = $pdo->prepare("delete from orderItems where terminals_id = ? and orders_id = ?");
    $stmt->execute([ $terminals_id, $orderReference ]);

    $fp = fopen("./tmp/aa.txt","a");
    fputs( $fp, time() . " 刪除refund資料\n" );
    fclose($fp);
  }
}
*/

/*
#id map, orderPayments的orderReference不應該是機器上的Orders ID,應該要是主機上的Orders ID
$orderRefMap = array();
for ( $i = 0; $i < count($orders_json); $i++) {
  $orderReference = $orders_json[$i]->{"id"};
  $subTotal = $orders_json[$i]->{"subTotal"};
  $tax = $orders_json[$i]->{"tax"}; 
  $total = $orders_json[$i]->{"total"};
  $notes = $orders_json[$i]->{"notes"};
  $lastMod = $orders_json[$i]->{"lastMod"};
  $stmt = $pdo->prepare("select * from orders where terminals_id = ? and lastMod = ?");
  $stmt->execute([ $terminals_id, $lastMod ]);
  if ( $stmt->rowCount() != 0 ) { //should be zero

$fp = fopen("./tmp/aa.txt","a");
fputs( $fp, time() . " 資料庫中仍有相同lastMod的資料 跳過\n" );
fclose($fp);


    continue;
  } else {


$fp = fopen("./tmp/aa.txt","a");
fputs( $fp, time() . " 資料庫中沒有相同lastMod的資料 新增\n" );
fclose($fp);

    $stmt = $pdo->prepare("insert into orders set agents_id = ?, vendors_id = ?, terminals_id = ?, orderReference = ?, subTotal = ?, tax = ?, total = ?, notes = ?, lastMod = ?");
    $stmt->execute([ $agents_id, $vendors_id, $terminals_id, $orderReference, $subTotal, $tax, $total, $notes, $lastMod ]);
    $orderRefMap[strval($orderReference)] = $pdo->lastInsertId();
  }
}
*/

for ( $i = 0; $i < count($payments); $i++) {
  #check if lastmod exist, if so next
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
  $orderReference = $payments[$i]->{"orderReference"};
  #$orderRef = $orderRefMap[strval($payments[$i]->{"orderReference"})];
  #echo $amtPaid . " " . $total . " " . $lastMod . " " . $orderRef;
  $stmt = $pdo->prepare("select * from ordersPayments where terminals_id = ? and lastMod = ?");
  $stmt->execute([ $terminals_id, $lastMod ]);
  if ( $stmt->rowCount() != 0 ) { //should be zero
    $row = $stmt->fetch();
    $stmt2 = $pdo->prepare("select * from orderItems where orders_id = ?");
    $stmt2->execute([ $row["orderReference"] ]);
    
	  //if ( 0 ) {
    echo $orderReference . "#" . $row["orderReference"] . "#" . $stmt2->rowCount() . "\n";
    if ( $stmt2->rowCount() == 0 ) {
      //fill order item
      for ( $j = 0; $j < count($items_json); $j++ ) {
        if ( $orderReference == $items_json[$j]->{"orderItem"}->{"orderReference"} ) {
          #insert all item detail with new payment id
          $col = (array) $items_json[$j]->{"orderItem"};
          #$col2 = (array) $items_json[$j]->{"item"};
          #var_dump( $items_json[$j] );
          $stmt = $pdo->prepare("insert into orderItems set agents_id = ?, vendors_id = ?, terminals_id = ?, group_name = ?, orders_id = ?, cost = ?, description = ?, group_id = ?, notes = ?, price = ?, taxable = ?, qty = ?, items_id = ?, discount = ?");
          $stmt->execute([ $agents_id, $vendors_id, $terminals_id, $group_names[$col["group"]], $row["orderReference"], $col["cost"], $col["description"],  $col["group"], $col["notes"], $col["price"], $col["taxable"], $col["qty"], '0', $col["discount"]]);
	  $items_id = $pdo->lastInsertId();
	  echo "inserted " . $items_id . "\n";
          for ( $k = 0; $k < count($items_json[$j]->{"mods"}); $k++ ) {
            $col = (array) $items_json[$j]->{"mods"}[$k];
            $stmt = $pdo->prepare("insert into orderItems set agents_id = ?, vendors_id = ?, terminals_id = ?, orders_id = ?, cost = ?, description = ?, group_id = ?, notes = ?, price = ?, taxable = ?, items_id = ?");
            $stmt->execute([ $agents_id, $vendors_id, $terminals_id, $row["orderReference"], $col["cost"], $col["description"],  $col["group"], $col["notes"], $col["price"], $col["taxable"], $items_id ]);
          }
	}   
      }
    } 
  } else {
	  /*
    $stmt = $pdo->prepare("insert into ordersPayments set agents_id = ?, vendors_id = ?, terminals_id = ?, amtPaid = ?, total = ?, refNumber = ?, tips = ?, techFee = ?, orderReference = ?, orderId = ?, refund = ?, payDate = ?, lastMod = ?");
    $stmt->execute([ $agents_id, $vendors_id, $terminals_id, $amtPaid, $total, $refNumber, $tips, $techFee, $orderRef, $orderId, $refund, $payDate, $lastMod ]);
    $orders_id = $pdo->lastInsertId();
    #update orderId
    #$stmt = $pdo->prepare("update ordersPayments set orderId = ? where id = ?");
    #$stmt->execute([ $termId . '0' . $orderRef, $orders_id  ]);
  }
  #insert and get new payment id
  for ( $j = 0; $j < count($items_json); $j++ ) {
    if ( $payments[$i]->{"orderReference"} == strval($items_json[$j]->{"orderItem"}->{"orderReference"}) ) {
      #insert all item detail with new payment id
      $col = (array) $items_json[$j]->{"orderItem"};
      $col2 = (array) $items_json[$j]->{"item"};
      var_dump( $col["notes"] );
      $stmt = $pdo->prepare("insert into orderItems set agents_id = ?, vendors_id = ?, terminals_id = ?, group_name = ?, orders_id = ?, cost = ?, description = ?, group_id = ?, notes = ?, price = ?, taxable = ?, qty = ?, items_id = ?, discount = ?");
      $stmt->execute([ $agents_id, $vendors_id, $terminals_id, $group_names[$col["group"]], $orderRef, $col["cost"], $col["description"],  $col["group"], $col2["notes"], $col["price"], $col["taxable"], $col["qty"], '0', $col["discount"]]);
      $items_id = $pdo->lastInsertId();
     for ( $k = 0; $k < count($items_json[$j]->{"mods"}); $k++ ) {
        $col = (array) $items_json[$j]->{"mods"}[$k];
	$stmt = $pdo->prepare("insert into orderItems set agents_id = ?, vendors_id = ?, terminals_id = ?, orders_id = ?, cost = ?, description = ?, group_id = ?, notes = ?, price = ?, taxable = ?, items_id = ?");
        $stmt->execute([ $agents_id, $vendors_id, $terminals_id, $orderRef, $col["cost"], $col["description"],  $col["group"], $col["notes"], $col["price"], $col["taxable"], $items_id ]);
      }
    }
	   */
  }
}
/*
for ($i = 0; $i < count($itemdata_json); $i++) {
  $stmt = $pdo->prepare("select * from items where terminals_id = ? and `desc` = ?");
  $stmt->execute([ $terminals_id, $itemdata_json[$i]->{"description"} ]);
  if ( $stmt->rowCount() == 0 ) {
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
  if( !isset($itemdata_json[$i]->{"group"}) ) { $itemdata_json[$i]->{"group"} = 0; }
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
*/
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
#send_http_status_and_exit("200","Data was successfully inserted.");
#} else {
#send_http_status_and_exit("400",$msgforsqlerror);
#}

?>
