<?php
function enable_cors()
{
    //enable cors
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
    header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
    header('Access-Control-Max-Age: 0');
    //handle preflight
    if ($_SERVER["REQUEST_METHOD"] == "OPTIONS") {
        exit(0);
    }
}

function connect_db_and_set_http_method($http_method)
{
    include_once './config/database.php';
    #checking
    if (!preg_match('/' . $_SERVER["REQUEST_METHOD"] . '/', $http_method)) {
        http_response_code(405);
        exit(0);
    }

    $databaseService = new DatabaseService();
    $pdo = $databaseService->getConnection();
    return $pdo;
}

function send_http_status_and_exit($http_status_code, $api_message)
{
    http_response_code($http_status_code);
    echo json_encode(array("message" => $api_message));
    exit(0);
}

function get_params_from_http_body($params)
{
    $jsonObj = json_decode(file_get_contents("php://input"), true);
    if (is_null($jsonObj)) {
        send_http_status_and_exit("400", "Invalid JSON");
    }
    foreach ($params as $key => $value) {
        if (!isset($jsonObj[$value])) {
            send_http_status_and_exit("422", "Parameters inadequate:" . $value);
        } else {
            $rtnArray[$value] = is_string($jsonObj[$value]) ? htmlspecialchars($jsonObj[$value]) : $jsonObj[$value];
        }
    }
    return $rtnArray;
}

/** 
 * Get header Authorization https://stackoverflow.com/questions/40582161/how-to-properly-use-bearer-tokens
 * */
function getAuthorizationHeader()
{
    $headers = null;
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER["Authorization"]);
    } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
        $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        //print_r($requestHeaders);
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    return $headers;
}
/**
 * get access token from header https://stackoverflow.com/questions/40582161/how-to-properly-use-bearer-tokens
 * */
function getBearerToken()
{
    $headers = getAuthorizationHeader();
    // HEADER: Get the access token from the header
    if (!empty($headers)) {
        if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            return $matches[1];
        }
    }
    return null;
}
