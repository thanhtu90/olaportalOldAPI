<?php
ini_set("display_errors",1);
include_once "./library/utils.php";
//enable_cors();
$msgfornoterminal = "No permission";
$msgforsqlerror = "Unable to insert data";
$pdo = connect_db_and_set_http_method( "POST" );
$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING );
$params = get_params_from_http_body([
    "serial",
    "json"
]);

####log raw data
$params["json"] = str_replace('&quot;','"',$params["json"]);
$jsonArray = json_decode($params["json"], true);

if (!is_array($jsonArray)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON array format']);
    exit;
}

$stmt = $pdo->prepare("insert into jsonOlaPay set serial = ?, content = ?, lastmod = ?");
$insertCount = 0;
$errors = [];

try {
    $pdo->beginTransaction();
    
    foreach ($jsonArray as $index => $jsonItem) {
        // Convert trans_date to Unix timestamp
        $lastmod = isset($jsonItem['trans_date']) ? strtotime($jsonItem['trans_date']) : time();
        
        $res = $stmt->execute([ $params["serial"], json_encode($jsonItem), $lastmod ]);
        if ($res) {
            $insertCount++;
        } else {
            $errors[] = "Failed to insert item at index $index";
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'status' => 'success',
        'inserted_count' => $insertCount,
        'total_items' => count($jsonArray),
        'errors' => $errors
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $msgforsqlerror,
        'details' => $e->getMessage()
    ]);
}
?>
