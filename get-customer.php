<?php
ini_set("display_errors",1);
include_once "./library/utils.php";
//enable_cors();

$msgfornoterminal = "No permission";
$msgforsqlerror = "No entry found";
$pdo = connect_db_and_set_http_method( "POST" );

$params = get_params_from_http_body([
    "serial",
    "phone"
]);

$terminal_serial = $params["serial"];

error_log("Debug: /get-customer: Customer with phone {$params['phone']} and serial {$params['serial']}");

$stmt = $pdo->prepare("select * from terminals where serial = ?");
$stmt->execute([$terminal_serial]);
if ( $stmt->rowCount() == 0 ) {
    send_http_status_and_exit("403",$msgfornoterminal);
}
$row = $stmt->fetch();

$customer_phone = $params["phone"];
$terminal_vendors_id = $row["vendors_id"];

error_log("Debug: Found vendors_id {$row['vendors_id']} for serial {$params['serial']}");
$stmt = $pdo->prepare("select * FROM customer WHERE phone = :phone AND vendors_id = :vendors_id");

// Bind parameters
$stmt->bindParam(':phone', $customer_phone, PDO::PARAM_STR);
$stmt->bindParam(':vendors_id', $terminal_vendors_id, PDO::PARAM_INT);

$stmt->execute();

if ( $stmt->rowCount() == 0 ) {
	send_http_status_and_exit("404",$msgforsqlerror);    
} else {
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $json_row = json_encode($row);
    echo $json_row;
}
exit(0)
?>
