<?php
include_once "./library/utils.php";
enable_cors();

// Accept both GET (for retrieving merchant data) and PUT (for updating merchant data)
$http_method = $_SERVER["REQUEST_METHOD"];
$pdo = connect_db_and_set_http_method($http_method);
$tablename = "accounts";
$msgforinvalidjwt = "No permission";

// require "vendor/autoload.php";
// use \Firebase\JWT\JWT;

// Check JWT validity
// $jwt = getBearerToken();
// try {
//     $decoded = JWT::decode($jwt, "YOUR_SECRET_KEY", array('HS256'));
// } catch (Exception $e) {
//     send_http_status_and_exit("403", $msgforinvalidjwt);
// }

// // Check admin privilege
// $role = $decoded->{'data'}->{'role'};
// if ($role != "admin") {
//     send_http_status_and_exit("403", $msgforinvalidjwt);
// }

// Get merchant_id from URL parameter
if (!isset($_GET['id']) || empty($_GET['id'])) {
    send_http_status_and_exit("400", "Missing merchant ID");
}
$merchant_id = $_GET['id'];

// Handle PUT request to update merchant data
if ($http_method === "PUT") {
    // Get JSON payload from request body
    $json_data = file_get_contents('php://input');
    $data = json_decode($json_data, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        send_http_status_and_exit("400", "Invalid JSON payload");
    }
    
    // Prepare update query and parameters
    $update_fields = [];
    $params = [];
    
    // Check if onboarding_status is provided in the payload
    if (isset($data['onboarding_status'])) {
        $update_fields[] = "onboarding_status = ?";
        $params[] = $data['onboarding_status'];
    }
    
    // Check if reward_status is provided in the payload
    if (isset($data['reward_status'])) {
        $update_fields[] = "reward_status = ?";
        $params[] = $data['reward_status'];
    }
    
    // If no valid fields to update, return error
    if (empty($update_fields)) {
        send_http_status_and_exit("400", "No valid fields to update. Accepted fields: onboarding_status, reward_status");
    }
    
    // Add merchant_id to params
    $params[] = $merchant_id;
    
    // Construct and execute update query
    $update_query = "UPDATE $tablename SET " . implode(", ", $update_fields) . " WHERE id = ?";
    $stmt = $pdo->prepare($update_query);
    
    try {
        $stmt->execute($params);
        
        if ($stmt->rowCount() === 0) {
            send_http_status_and_exit("404", "Merchant not found");
        }
        
        send_http_status_and_exit("200", json_encode([
            "status" => "success",
            "message" => "Merchant updated successfully"
        ]));
    } catch (PDOException $e) {
        send_http_status_and_exit("500", "Database error: " . $e->getMessage());
    }
}
// Handle GET request to retrieve merchant data
else if ($http_method === "GET") {
    // Prepare and execute query to get merchant data
    $stmt = $pdo->prepare("SELECT id, firstname, lastname, email, enterdate, role, title, companyname, onboarding_status, reward_status FROM $tablename WHERE id = ?");
    
    try {
        $stmt->execute([$merchant_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            send_http_status_and_exit("404", "Merchant not found");
        }
        
        // Format response
        $response = [
            "id" => $row["id"],
            "name" => $row["firstname"] . " " . $row["lastname"],
            "email" => $row["email"],
            "enterdate" => $row["enterdate"],
            "role" => $row["role"],
            "title" => $row["title"],
            "companyname" => $row["companyname"],
            "onboarding_status" => $row["onboarding_status"],
            "reward_status" => $row["reward_status"]
        ];
        
        send_http_status_and_exit("200", json_encode($response));
    } catch (PDOException $e) {
        send_http_status_and_exit("500", "Database error: " . $e->getMessage());
    }
} else {
    send_http_status_and_exit("405", "Method not allowed");
}
?>
