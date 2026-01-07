<?php
require "vendor/autoload.php";

use \Firebase\JWT\JWT;

$payload = [
    "data" => [
        "id" => 1,
        "role" => "admin"
    ]
];

$jwt = JWT::encode($payload, "YOUR_SECRET_KEY", 'HS256');
echo $jwt;
