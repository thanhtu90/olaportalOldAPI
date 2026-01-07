<?php
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

//enable_cors();
$msgfornoterminal = "No permission";
$msgforsqlerror = "Unable to insert data";
$pdo = connect_db_and_set_http_method("POST");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
$params = get_params_from_http_body([
    "serial",
    "json"
]);

// Check if terminal exists and is attached to a vendor
$terminalCheck = $pdo->prepare("SELECT id, vendors_id FROM terminals WHERE serial = ?");
$terminalCheck->execute([$params["serial"]]);
$terminal = $terminalCheck->fetch(PDO::FETCH_ASSOC);

if (!$terminal) {
    http_response_code(404);
    echo json_encode(['error' => 'Terminal not found']);
    exit;
}

if (empty($terminal['vendors_id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Terminal not attached to a vendor']);
    exit;
}

####log raw data
$params["json"] = str_replace('&quot;','"',$params["json"]);
$jsonArray = json_decode($params["json"], true);

if (!is_array($jsonArray)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON array format']);
    exit;
}

// Prepare statements for both tables
$stmtLegacy = $pdo->prepare("INSERT INTO jsonOlaPay SET serial = ?, content = ?, lastmod = ?");
$stmtUnique = $pdo->prepare("
    INSERT IGNORE INTO unique_olapay_transactions 
    (serial, content, lastmod, order_id, trans_date, trans_id) 
    VALUES (?, ?, ?, ?, ?, ?)
");

$insertCount = 0;
$uniqueInsertCount = 0;
$errors = [];
$duplicates = 0;

try {
    $pdo->beginTransaction();
    
    foreach ($jsonArray as $index => $jsonItem) {
        // Convert trans_date to Unix timestamp
        $lastmod = isset($jsonItem['trans_date']) ? strtotime($jsonItem['trans_date']) : time();
        
        // Extract fields for unique table
        $order_id = isset($jsonItem['orderID']) ? $jsonItem['orderID'] : null;
        $trans_date = isset($jsonItem['trans_date']) ? $jsonItem['trans_date'] : null;
        $trans_id = isset($jsonItem['trans_id']) ? $jsonItem['trans_id'] : null;
        $trans_type = isset($jsonItem['trans_type']) ? $jsonItem['trans_type'] : null;
        $command = isset($jsonItem['command']) ? $jsonItem['command'] : null;
        
        // If command and trans_type are both TipAdjustment, use tip instead of amount
        if ($command === 'TipAdjustment' && $trans_type === 'TipAdjustment') {
            $amount = isset($jsonItem['tip']) ? $jsonItem['tip'] : null;
        } else {
            $amount = isset($jsonItem['amount']) ? $jsonItem['amount'] : null;
        }
        
        $status = isset($jsonItem['Status']) ? $jsonItem['Status'] : null;
        $requested_amount = isset($jsonItem['requested_amount']) ? $jsonItem['requested_amount'] : $amount;
        
        // Deduplication Strategy:
        // Use serial + trans_id + trans_type + status + requested_amount + trans_date
        // - serial: Terminal serial number (stable)
        // - trans_id: TSYS generated, unique per transaction chain (never resets)
        // - trans_type: Sale, TipAdjustment, Void, Return (differentiates transaction types)
        // - status: PASS, FAIL, etc. (keeps failed/successful separately)
        // - requested_amount: differentiates transactions with different amounts
        // - trans_date: handles rare case of same amount at different times (e.g., tip $2 → $3 → $2)
        // 
        // Note: device local 'id' is NOT reliable - it resets when app is reinstalled
        $existingRecord = null;
        
        if ($trans_id && $trans_type && $status && $trans_date) {
            // Primary check: serial + trans_id + trans_type + status + requested_amount + trans_date
            // This deduplicates exact matches while keeping distinct transactions
            $checkDuplicate = $pdo->prepare("
                SELECT id, content, lastmod 
                FROM unique_olapay_transactions 
                WHERE serial = ? AND trans_id = ? AND trans_type = ? AND status = ?
                AND JSON_UNQUOTE(JSON_EXTRACT(content, '$.requested_amount')) = ?
                AND trans_date = ?
            ");
            $checkDuplicate->execute([
                $params["serial"], 
                $trans_id, 
                $trans_type, 
                $status, 
                (string)$requested_amount,
                $trans_date
            ]);
            $existingRecord = $checkDuplicate->fetch(PDO::FETCH_ASSOC);
        }
        
        if ($existingRecord) {
            $duplicates++;
            
            // Only update if this record has newer data (higher lastmod)
            if ($lastmod > $existingRecord['lastmod']) {
                // Update unique_olapay_transactions with newer content
                $updateUnique = $pdo->prepare("
                    UPDATE unique_olapay_transactions 
                    SET content = ?, lastmod = ?, trans_date = ?, order_id = ?
                    WHERE id = ?
                ");
                $updateUnique->execute([json_encode($jsonItem), $lastmod, $trans_date, $order_id, $existingRecord['id']]);
                
                if ($updateUnique->rowCount() > 0) {
                    $uniqueInsertCount++;
                }
            }
            
            // Always insert into jsonOlaPay (it's the raw log table, keeps all records)
            $stmtLegacy->execute([
                $params["serial"],
                json_encode($jsonItem),
                $lastmod
            ]);
            $insertCount++;
            
            continue; // Skip the regular insert
        }

        // Insert into legacy table
        $resLegacy = $stmtLegacy->execute([
            $params["serial"],
            json_encode($jsonItem),
            $lastmod
        ]);
        
        if ($resLegacy) {
            $insertCount++;
            
            // Insert into unique table
            $resUnique = $stmtUnique->execute([
                $params["serial"],
                json_encode($jsonItem),
                $lastmod,
                $order_id,
                $trans_date,
                $trans_id,
            ]);

            // log raw query
            error_log("Raw query: " . $stmtUnique->queryString . "\n");
            error_log("Params: " . implode(', ', [$params["serial"], json_encode($jsonItem), $lastmod, $order_id, $trans_date, $trans_id]));
            
            if ($resUnique) {
                $uniqueInsertCount++;
            } else {
                $errors[] = "Failed to insert into unique table at index $index";
            }
        } else {
            $errors[] = "Failed to insert into legacy table at index $index";
        }
    }
    
    $pdo->commit();
    
    // Log batch processing details
    error_log(sprintf(
        "Batch processing - Serial: %s, Total: %d, Inserted: %d, Unique: %d, Duplicates: %d, Errors: %d, Trans Type: %s, Amount: %s, Status: %s ",
        $params["serial"],
        count($jsonArray),
        $insertCount,
        $uniqueInsertCount,
        $duplicates,
        count($errors),
        $trans_type,
        $amount,
        $status
    ));
    
    echo json_encode([
        'status' => 'success',
        'inserted_count' => $insertCount,
        'unique_inserted_count' => $uniqueInsertCount,
        'duplicates_skipped' => $duplicates,
        'total_items' => count($jsonArray),
        'errors' => $errors
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    
    // Enhanced error logging
    error_log(sprintf(
        "Error in batch processing - Serial: %s, Error: %s, Stack: %s",
        $params["serial"],
        $e->getMessage(),
        $e->getTraceAsString()
    ));
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $msgforsqlerror,
        'details' => $e->getMessage()
    ]);
}
?>
