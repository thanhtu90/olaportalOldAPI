<?php
ini_set("display_errors",1);
include_once "./library/utils.php";
//enable_cors();

$msgfornoterminal = "No permission";
$msgforsqlerror = "No entry found";
$pdo = connect_db_and_set_http_method( "POST" );

$params = get_params_from_http_body([
    "serial",
    "phone",
    "point",
]);

try {
	error_log("Debug: /update-loyalty-point: Customer with phone {$params['phone']} and serial {$params['serial']}");
	$pdo->beginTransaction();

	// Prepare statement to find vendors_id
	$sql_stmt = "select * from terminals where serial = ?";
	$terminal_serial = $params["serial"];
	$stmt = $pdo->prepare($sql_stmt);
	$stmt->execute([$terminal_serial]);


	// No Record of vendors_id with searial
	if ( $stmt->rowCount() == 0 ) {
		error_log("Error: No found vendorId for provided serial");
		echo json_encode(['status' => 'error', 'message' =>'no found vendorId']);
		exit(0);
	}
	
	$row =  $stmt->fetch(PDO::FETCH_ASSOC);
	$vendors_id = $row['vendors_id'];
	$add_point = $params['point'];

	// Prepare statement to get customer
	$sql_stmt = "select id,point FROM customer WHERE phone = :phone AND vendors_id = :vendors_id FOR UPDATE";
	$stmt = $pdo->prepare($sql_stmt);
	$stmt->bindParam(':phone', $params['phone'], PDO::PARAM_STR);
	$stmt->bindParam(':vendors_id', $vendors_id, PDO::PARAM_INT);
	$stmt->execute();
	
	// No record of customer with provided phone
	if ( $stmt->rowCount() == 0 ) {
   		error_log("Error: No found customer with phone number {$params['phone']}");
		echo json_encode(['status' => 'error', 'message' =>'no found customer']);
		exit(0);	
	}

	$row = $stmt->fetch(PDO::FETCH_ASSOC);
	
	// Update point for customer
	$curr_point = $row['point'] + $add_point;
	error_log("Debug: Customer with phone {$params['phone']} hav prev_points {$row['point']} and points_tod_add {$add_point}");
	$sql_stmt = "UPDATE customer SET point = :newPoints WHERE id = :customer_id";
	$stmt = $pdo->prepare($sql_stmt);
	$stmt->bindParam(':customer_id', $row['id'], PDO::PARAM_INT);
	$stmt->bindParam(':newPoints', $curr_point, PDO::PARAM_INT);
	$stmt->execute();
	
	$pdo->commit();	
	error_log("Debug: Customer with phone number {$params['phone']} update point {$curr_point} successfully");
	echo json_encode(['status' => 'ok']);


} catch (Throwale $e) {
	error_log("Error: Customer with phone number {$params['phone']} failed with error {$e->getMessage()}");
    	echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
exit(0);
?>

