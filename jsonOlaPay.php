<?php
// v2 - support for unique_olapay_transactions table with computed columns
ini_set("display_errors",1);
include_once "./library/utils.php";

// Rate limiting configuration
define('RATE_LIMIT_REQUESTS', 30); // 30 requests
define('RATE_LIMIT_WINDOW', 1);    // per second
define('REDIS_ADDR', 'redis-15081.c326.us-east-1-3.ec2.redns.redis-cloud.com:15081');
define('REDIS_USERNAME', 'default');
define('REDIS_PASSWORD', 'YdNJH0ThZXIEI0C0hYQ7X6EFIJJUEesV');

// Rate limiting function
function checkRateLimit($ip) {
    try {
        // Check if Predis is available
        if (!class_exists('Predis\Client')) {
            error_log("Predis library not found. Rate limiting disabled.");
            return true;
        }
        
        // Parse Redis connection details
        list($host, $port) = explode(':', REDIS_ADDR);
        
        // Connect to Redis using Predis
        $redis = new Predis\Client([
            'scheme' => 'tcp',
            'host'   => $host,
            'port'   => $port,
            'username' => REDIS_USERNAME,
            'password' => REDIS_PASSWORD,
        ]);
        
        // Test connection
        $redis->ping();
        
        // Create rate limit key
        $key = "rate_limit:" . $ip;
        
        // Use Redis pipeline for atomic operations
        $pipe = $redis->pipeline();
        
        // Get current count and increment atomically
        $current_count = $redis->get($key);
        
        if ($current_count === null) {
            // First request in this window
            $redis->setex($key, RATE_LIMIT_WINDOW, 1);
            return true;
        }
        
        if ($current_count >= RATE_LIMIT_REQUESTS) {
            // Rate limit exceeded
            return false;
        }
        
        // Increment counter
        $redis->incr($key);
        return true;
        
    } catch (Exception $e) {
        // If Redis is unavailable, allow the request (fail open)
        error_log("Rate limiting error: " . $e->getMessage());
        return true;
    }
}

// Get client IP address
function getClientIP() {
    $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            $ip = $_SERVER[$key];
            if (strpos($ip, ',') !== false) {
                $ip = explode(',', $ip)[0];
            }
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

// Check rate limit before processing
$client_ip = getClientIP();
if (!checkRateLimit($client_ip)) {
    http_response_code(429);
    header('Retry-After: 1');
    echo json_encode(['error' => 'Rate limit exceeded. Maximum 30 requests per second allowed.']);
    exit;
}

$msgfornoterminal = "No permission";
$msgforsqlerror = "Unable to get data";
$pdo = connect_db_and_set_http_method("POST");

// Insert into original jsonOlaPay table (maintain backward compatibility)
$stmt = $pdo->prepare("insert into jsonOlaPay set serial = ?, content = ?, lastmod = ?");
$stmt->execute([$_REQUEST["serial"], $_REQUEST["content"], $_REQUEST["lastmod"]]);

// Also insert into the new unique_olapay_transactions table
try {
    $content = $_REQUEST["content"];
    $serial = $_REQUEST["serial"];
    $lastmod = $_REQUEST["lastmod"];
    
    // Parse JSON content to extract uniqueness fields
    $json_data = json_decode($content, true);
    
    if ($json_data !== null) {
        // Extract the required fields for uniqueness
        $order_id = isset($json_data['orderID']) ? $json_data['orderID'] : null;
        $trans_date = isset($json_data['trans_date']) ? $json_data['trans_date'] : null;
        $trans_id = isset($json_data['trans_id']) ? $json_data['trans_id'] : null;
        $trans_type = isset($json_data['trans_type']) ? $json_data['trans_type'] : null;
        $status = isset($json_data['Status']) ? $json_data['Status'] : null;
        $requested_amount = isset($json_data['requested_amount']) ? $json_data['requested_amount'] : (isset($json_data['amount']) ? $json_data['amount'] : null);
        
        // Deduplication Strategy:
        // 1. For records WITH trans_id: Use existing INSERT IGNORE (unique constraint handles it)
        // 2. For records WITHOUT trans_id (CREATED/polling records): Check for existing record and update
        //    - These are polling/status check records that should update instead of creating duplicates
        //    - Same serial + order_id + status + lastmod = definitely a duplicate
        
        $existingRecord = null;
        $shouldInsert = true;
        
        if ($trans_id && $trans_type && $status && $trans_date) {
            // For records with trans_id, check for exact duplicates
            $checkDuplicate = $pdo->prepare("
                SELECT id, content, lastmod 
                FROM unique_olapay_transactions 
                WHERE serial = ? AND trans_id = ? AND trans_type = ? AND status = ?
                AND JSON_UNQUOTE(JSON_EXTRACT(content, '$.requested_amount')) = ?
                AND trans_date = ?
            ");
            $checkDuplicate->execute([
                $serial, 
                $trans_id, 
                $trans_type, 
                $status, 
                (string)$requested_amount,
                $trans_date
            ]);
            $existingRecord = $checkDuplicate->fetch(PDO::FETCH_ASSOC);
        } elseif (!$trans_id && $order_id && $status) {
            // For NULL trans_id records (CREATED/polling/status check records)
            // These are pre-payment polling records that OlaPay sends repeatedly
            // Check for existing record with same serial + order_id + status
            $checkPollingDuplicate = $pdo->prepare("
                SELECT id, content, lastmod 
                FROM unique_olapay_transactions 
                WHERE serial = ? 
                AND order_id = ? 
                AND (trans_id IS NULL OR trans_id = '')
                AND status = ?
                ORDER BY lastmod DESC
                LIMIT 1
            ");
            $checkPollingDuplicate->execute([
                $serial, 
                $order_id, 
                $status
            ]);
            $existingRecord = $checkPollingDuplicate->fetch(PDO::FETCH_ASSOC);
        }
        
        if ($existingRecord) {
            $shouldInsert = false;
            
            // Only update if this record has newer data (higher lastmod)
            if ($lastmod > $existingRecord['lastmod']) {
                $updateUnique = $pdo->prepare("
                    UPDATE unique_olapay_transactions 
                    SET content = ?, lastmod = ?, trans_date = ?, order_id = ?
                    WHERE id = ?
                ");
                $updateUnique->execute([$content, $lastmod, $trans_date, $order_id, $existingRecord['id']]);
                
                if ($updateUnique->rowCount() > 0) {
                    error_log("Updated existing unique_olapay_transactions record ID: " . $existingRecord['id']);
                }
            }
            // If same or older lastmod, skip silently (it's a duplicate)
        }
        
        if ($shouldInsert) {
            // Insert into new table (INSERT IGNORE will skip duplicates for records with trans_id)
            $stmt_unique = $pdo->prepare("
                INSERT IGNORE INTO unique_olapay_transactions 
                (
                    serial, 
                    content, 
                    lastmod, 
                    order_id, 
                    trans_date, 
                    trans_id,
                    created_at
                ) 
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt_unique->execute([
                $serial,
                $content,
                $lastmod,
                $order_id,
                $trans_date,
                $trans_id
            ]);

            // Verify the insert and log any computation errors
            if ($stmt_unique->rowCount() > 0) {
                // Optional: Verify computed columns are working as expected
                $last_id = $pdo->lastInsertId();
                $verify_stmt = $pdo->prepare("
                    SELECT trans_type, amount, status 
                    FROM unique_olapay_transactions 
                    WHERE id = ?
                ");
                $verify_stmt->execute([$last_id]);
                $result = $verify_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['trans_type'] === null || $result['amount'] === null || $result['status'] === null) {
                    error_log("Warning: Computed columns may have null values for ID: $last_id");
                }
            }
        }
    }
} catch (Exception $e) {
    // Enhanced error logging
    error_log("Error in jsonOlaPay.php: " . $e->getMessage());
    error_log("JSON Content: " . substr($content ?? '', 0, 1000)); // Log first 1000 chars of content
    error_log("Stack trace: " . $e->getTraceAsString());
}

send_http_status_and_exit("200", json_encode(["status" => "success"]));
?> 