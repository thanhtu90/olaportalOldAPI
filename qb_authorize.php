<?php
// Suppress deprecation warnings
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 1);

include_once "./library/utils.php";
require 'vendor/autoload.php';
$config = require './config/qb_config.php';

use QuickBooksOnline\API\DataService\DataService;

enable_cors();

// Get the JSON data
$jsonData = json_decode(file_get_contents('php://input'), true);

// Access the id
$id = $jsonData['id'] ?? null;

if ($id === null) {
    http_response_code(400);
    echo json_encode(['error' => 'ID is required', 'authUrl' => null]);
    exit;
}

$state = base64_encode(json_encode([
    'id' => $id,
    'random' => bin2hex(random_bytes(10))
]));

$dataService = DataService::Configure(array(
    'auth_mode' => 'oauth2',
    'ClientID' => $config['client_id'],
    'ClientSecret' =>  $config['client_secret'],
    'RedirectURI' => $config['oauth_redirect_uri'],
    'scope' => $config['oauth_scope'],
    'state' => $state,
    'baseUrl' => "development"
));

$OAuth2LoginHelper = $dataService->getOAuth2LoginHelper();
$authUrl = $OAuth2LoginHelper->getAuthorizationCodeURL();

// Set JSON content type header
header('Content-Type: application/json');

// Return JSON response
echo json_encode(['authUrl' => $authUrl]);
exit;
