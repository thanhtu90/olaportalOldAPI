<?php
// Test script for rate limiting functionality
// This script simulates multiple requests to test the rate limiter

$base_url = 'http://localhost/jsonOlaPay.php'; // Adjust URL as needed
$test_ip = '192.168.1.100'; // Test IP address

echo "Testing rate limiting (30 requests per second)...\n";
echo "Making 35 requests rapidly to test rate limiting...\n\n";

$success_count = 0;
$rate_limited_count = 0;

for ($i = 1; $i <= 35; $i++) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $base_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'serial' => 'test_serial_' . $i,
        'content' => json_encode(['orderID' => 'test_' . $i, 'trans_date' => date('Y-m-d H:i:s')]),
        'lastmod' => time()
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Forwarded-For: ' . $test_ip,
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code == 200) {
        $success_count++;
        echo "Request $i: SUCCESS (200)\n";
    } elseif ($http_code == 429) {
        $rate_limited_count++;
        echo "Request $i: RATE LIMITED (429)\n";
    } else {
        echo "Request $i: ERROR ($http_code)\n";
    }
    
    // Small delay to ensure requests are processed
    usleep(10000); // 10ms delay
}

echo "\n=== Test Results ===\n";
echo "Successful requests: $success_count\n";
echo "Rate limited requests: $rate_limited_count\n";
echo "Total requests: " . ($success_count + $rate_limited_count) . "\n";

if ($rate_limited_count > 0) {
    echo "✅ Rate limiting is working correctly!\n";
} else {
    echo "⚠️  No requests were rate limited. Check your configuration.\n";
}
?>
