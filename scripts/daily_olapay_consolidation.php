<?php
/**
 * Daily OlaPay Data Consolidation Script
 * 
 * Elite Performance Optimization - Part of NASA/Tesla-level architecture
 * 
 * Purpose: Process previous day's OlaPay transaction data into consolidated tables
 * Schedule: Daily at 2:00 AM via cron
 * 
 * Performance Impact: Enables sub-200ms dashboard queries
 * 
 * @author Elite Optimization Team
 * @version 3.0
 */

// Set script execution limits
set_time_limit(1800); // 30 minutes max
ini_set('memory_limit', '512M');

// Include database utilities
require_once __DIR__ . '/../library/utils.php';

/**
 * Log message with timestamp and prefix
 */
function log_message($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $prefix = "[{$timestamp}] OLAPAY-CRON-DAILY [{$level}]:";
    error_log("{$prefix} {$message}");
    echo "{$prefix} {$message}\n";
}

/**
 * Log performance metrics
 */
function log_performance($operation, $start_time, $affected_rows = null) {
    $duration = round(microtime(true) - $start_time, 3);
    $memory = round(memory_get_peak_usage() / 1024 / 1024, 2);
    
    $message = "PERFORMANCE - {$operation}: {$duration}s, Memory: {$memory}MB";
    if ($affected_rows !== null) {
        $message .= ", Rows: {$affected_rows}";
    }
    
    log_message($message, 'PERF');
}

/**
 * Main execution function
 */
function main() {
    $script_start = microtime(true);
    
    try {
        log_message("=== Daily OlaPay Consolidation Started ===");
        
        // Connect to database
        $pdo = connect_db_and_set_http_method("POST");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Process yesterday's data (allow time for late transactions)
        $target_date = date('Y-m-d', strtotime('-1 day'));
        log_message("Processing data for date: {$target_date}");
        
        // Step 1: Update daily stats for target date
        $step_start = microtime(true);
        log_message("Step 1: Updating daily OlaPay stats...");
        
        $stmt = $pdo->prepare("CALL UpdateDailyOlaPayStats(?)");
        $stmt->execute([$target_date]);
        
        // Get the result message from stored procedure
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && isset($result['result'])) {
            log_message("Stored procedure result: " . $result['result']);
        }
        
        log_performance("UpdateDailyOlaPayStats", $step_start);
        
        // Step 2: Data quality verification
        $step_start = microtime(true);
        log_message("Step 2: Verifying data quality...");
        
        $quality_check = $pdo->prepare("
            SELECT 
                COUNT(*) as merchant_count,
                SUM(transaction_count) as total_transactions,
                SUM(net_amount) as total_net_amount
            FROM merchant_daily_olapay_stats 
            WHERE date = ?
        ");
        $quality_check->execute([$target_date]);
        $quality_result = $quality_check->fetch(PDO::FETCH_ASSOC);
        
        log_message(sprintf(
            "Data quality check - Merchants: %d, Transactions: %d, Net Amount: $%.2f",
            $quality_result['merchant_count'],
            $quality_result['total_transactions'],
            $quality_result['total_net_amount']
        ));
        
        log_performance("Data quality check", $step_start, $quality_result['merchant_count']);
        
        // Step 3: Cleanup old data (keep 2 years + 30 days buffer)
        $step_start = microtime(true);
        log_message("Step 3: Cleaning up old data...");
        
        $cleanup_date = date('Y-m-d', strtotime('-2 years -30 days'));
        $cleanup_stmt = $pdo->prepare("
            DELETE FROM merchant_daily_olapay_stats 
            WHERE date < ?
        ");
        $cleanup_stmt->execute([$cleanup_date]);
        $deleted_rows = $cleanup_stmt->rowCount();
        
        if ($deleted_rows > 0) {
            log_message("Cleanup completed. Removed {$deleted_rows} records older than: {$cleanup_date}");
        } else {
            log_message("No old records to cleanup (cutoff: {$cleanup_date})");
        }
        
        log_performance("Data cleanup", $step_start, $deleted_rows);
        
        // Step 4: Update registry last_transaction_date for inactive merchants
        $step_start = microtime(true);
        log_message("Step 4: Updating merchant registry status...");
        
        $registry_update = $pdo->prepare("
            UPDATE olapay_merchants_registry 
            SET status = 'inactive', updated_at = CURRENT_TIMESTAMP 
            WHERE last_transaction_date < DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            AND status = 'active'
        ");
        $registry_update->execute();
        $inactive_merchants = $registry_update->rowCount();
        
        if ($inactive_merchants > 0) {
            log_message("Marked {$inactive_merchants} merchants as inactive (no transactions in 90 days)");
        }
        
        log_performance("Registry status update", $step_start, $inactive_merchants);
        
        // Step 5: Generate daily statistics summary
        $step_start = microtime(true);
        log_message("Step 5: Generating daily summary...");
        
        $summary_query = $pdo->prepare("
            SELECT 
                DATE(date) as date,
                COUNT(DISTINCT merchant_id) as active_merchants,
                SUM(transaction_count) as total_transactions,
                SUM(total_amount) as gross_amount,
                SUM(refund_amount) as refund_amount,
                SUM(net_amount) as net_amount,
                AVG(net_amount) as avg_per_merchant
            FROM merchant_daily_olapay_stats 
            WHERE date >= DATE_SUB(?, INTERVAL 6 DAY)
            AND date <= ?
            GROUP BY DATE(date)
            ORDER BY date DESC
        ");
        $summary_query->execute([$target_date, $target_date]);
        
        log_message("=== 7-Day Summary ===");
        while ($row = $summary_query->fetch(PDO::FETCH_ASSOC)) {
            log_message(sprintf(
                "Date: %s | Merchants: %d | Transactions: %d | Net: $%.2f | Avg/Merchant: $%.2f",
                $row['date'],
                $row['active_merchants'],
                $row['total_transactions'],
                $row['net_amount'],
                $row['avg_per_merchant']
            ));
        }
        
        log_performance("Daily summary generation", $step_start);
        
        // Final performance summary
        log_performance("TOTAL SCRIPT EXECUTION", $script_start);
        log_message("=== Daily OlaPay Consolidation Completed Successfully ===");
        
        return 0; // Success
        
    } catch (Exception $e) {
        log_message("CRITICAL ERROR: " . $e->getMessage(), 'ERROR');
        log_message("Stack trace: " . $e->getTraceAsString(), 'ERROR');
        log_message("=== Daily OlaPay Consolidation Failed ===", 'ERROR');
        
        return 1; // Failure
    }
}

// Execute main function and exit with appropriate code
$exit_code = main();
exit($exit_code);
?> 