<?php
ini_set("display_errors",1);
include_once "./library/utils.php";                                                                                                                            
//enable_cors();                                                                                                                                               
                                                                                                                                                               
$msgfornoterminal = "No permission";                                                                                                                           
$msgforsqlerror = "No entry found";                                                                                                                            
$pdo = connect_db_and_set_http_method( "POST" );                                                                                                               

/** Thang: Refactor code from get_params_from_http_body to allow non-set field from payload**/                                                                 
function getArrayPayload($params) {                                                                                                                            
    $jsonObj = json_decode(file_get_contents("php://input"),true);                                                                                             
  
    if (is_null($jsonObj)) {                                                                                                                                   
    	error_log("Error: Failed for empty json payload");
        echo json_encode(['status' => 'error', 'message' =>'empty/invalid json format']);
        exit(0);                                                                                                   
	}
	if (!isset($jsonObj['phone'])) {
		writeErrLogAndOutput("phone");	
	}
	if (!isset($jsonObj['first_name'])) {
        writeErrLogAndOutput("first_name");
	}
	if (!isset($jsonObj['last_name'])) {
        writeErrLogAndOutput("last_name");
	}
	if (!isset($jsonObj['serial'])) {
        writeErrLogAndOutput("serial");
    }
    $rtnArray = [];
    foreach( $params as $key => $value ){                                                                                                                      
        if (isset($jsonObj[$value])) {                                                                                                                         
            $rtnArray[$value] = htmlspecialchars($jsonObj[$value]);  
        }   
    } 
     
    return $rtnArray;                                                                                                                                          
}                                                                                                                                                              
    
$params = getArrayPayload([                                                                                                                                    
	#"vendors_id",
	"serial",
	"first_name",
	"last_name",
	"phone",
	"email",
    "street",
    "city",
    "zip",                                                                                                                                                     
    "dob",                                                                                                                                                     
    "point",
    "membership",
    "loyalty",
    "status",
    "age",                                                                                                                                                     
    "gender",
    "node",
            
]);                                                                                                                                                            
//echo $json_encode($params);
try {
	 error_log("Debug: /insert-customer: Customer with phone {$params['phone']} and serial {$params['serial']}");
	$pdo->beginTransaction();
	// Prepare statement to find vendors_id
	$sql_vendorId = "select * from terminals where serial = ?";
	$terminal_serial = $params["serial"];
	$stmt = $pdo->prepare($sql_vendorId);
	$stmt->execute([$terminal_serial]);


	// No Record of vendors_id with searial
	if ( $stmt->rowCount() == 0 ) {
		error_log("Error: No found vendorId for provided serial");
		echo json_encode(['status' => 'error', 'message' =>'no found vendorId']);
		exit(0);
	}

	//Remove serial from params and set vendors_id
	$row =  $stmt->fetch(PDO::FETCH_ASSOC);

	error_log("Debug: Found vendors_id {$row['vendors_id']} for serial {$params['serial']}");
	unset($params['serial']);
	$params['vendors_id'] = $row['vendors_id'];

    // Prepare the SQL Insert Statement
	$fields = implode(", ", array_keys($params));
    $placeholders = ":" . implode(", :", array_keys($params));

    $sql = "INSERT INTO customer ($fields) VALUES ($placeholders)";
    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => &$value) {
        $stmt->bindParam(":$key", $value);
    }

  
    $stmt->execute();
    $pdo->commit();
  	
  	error_log("Debug: Customer with phone number {$params['phone']} successfully inserted");	
    echo json_encode(['status' => 'ok']);
} catch (Throwable $e) {
   	
	error_log("Error: Customer with phone number {$params['phone']} failed with error {$e->getMessage()}");
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
exit(0);                                                                                                                                                               
?>  
