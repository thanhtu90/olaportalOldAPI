<?php
/**
 * Weekly OlaPay Data Rebuild Script
 * 
 * Elite Performance Optimization - Safety Net Component
 * 
 * Purpose: Weekly full rebuild of OlaPay consolidated data and merchant registry
 * Schedule: Weekly on Sunday at 3:00 AM via cron
 * 
 * This serves as a safety net to ensure data consistency and catch any
 * issues with daily incremental updates.
 * 
 * @author Elite Optimization Team
 * @version 3.0
 */

// Set extended execution limits for weekly rebuild
set_time_limit(7200); // 2 hours max
ini_set('memory_limit', '1G');

// Include database utilities
require_once __DIR__ . '/../library/utils.php';

/**
 * Log message with timestamp and prefix
 */
function log_message($message, $level = 'INFO') {
    $timestamp = date('Y-m-d H:i:s');
    $prefix = "[{$timestamp}] OLAPAY-CRON-WEEKLY [{$level}]:";
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
 * Send alert notification (implement based on your notification system)
 */
function send_alert($subject, $message, $level = 'WARNING') {
    // TODO: Implement your notification system here
    // Examples: email, Slack, PagerDuty, etc.
    log_message("ALERT [{$level}] {$subject}: {$message}", 'ALERT');
}

/**
 * Main execution function
 */
function main() {
    $script_start = microtime(true);
    
    try {
        log_message("=== Weekly OlaPay Rebuild Started ===");
        
        // Connect to database
        $pdo = connect_db_and_set_http_method("POST");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Step 1: Backup current data before rebuild
        $step_start = microtime(true);
        log_message("Step 1: Creating backup of current data...");
        
        $backup_queries = [
            "CREATE TABLE IF NOT EXISTS merchant_daily_olapay_stats_backup LIKE merchant_daily_olapay_stats",
            "TRUNCATE TABLE merchant_daily_olapay_stats_backup",
            "INSERT INTO merchant_daily_olapay_stats_backup SELECT * FROM merchant_daily_olapay_stats",
            
            "CREATE TABLE IF NOT EXISTS olapay_merchants_registry_backup LIKE olapay_merchants_registry",
            "TRUNCATE TABLE olapay_merchants_registry_backup",
            "INSERT INTO olapay_merchants_registry_backup SELECT * FROM olapay_merchants_registry"
        ];
        
        foreach ($backup_queries as $query) {
            $pdo->exec($query);
        }
        
        // Get backup counts
        $stats_backup_count = $pdo->query("SELECT COUNT(*) FROM merchant_daily_olapay_stats_backup")->fetchColumn();
        $registry_backup_count = $pdo->query("SELECT COUNT(*) FROM olapay_merchants_registry_backup")->fetchColumn();
        
        log_message("Backup created - Stats: {$stats_backup_count} records, Registry: {$registry_backup_count} merchants");
        log_performance("Data backup", $step_start);
        
        // Step 2: Rebuild merchant registry
        $step_start = microtime(true);
        log_message("Step 2: Rebuilding merchant registry...");
        
        $stmt = $pdo->prepare("CALL RebuildOlaPayMerchantsRegistry()");
        $stmt->execute();
        
        // Get result from stored procedure
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && isset($result['result'])) {
            log_message("Registry rebuild result: " . $result['result']);
        }
        
        log_performance("Registry rebuild", $step_start);
        
        // Step 3: Full historical consolidation
        $step_start = microtime(true);
        log_message("Step 3: Full historical data consolidation (this may take a while)...");
        
        $stmt = $pdo->prepare("CALL ConsolidateHistoricalOlaPayStats()");
        $stmt->execute();
        
        // Get result from stored procedure
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && isset($result['result'])) {
            log_message("Historical consolidation result: " . $result['result']);
        }
        
        log_performance("Historical consolidation", $step_start);
        
        // Step 4: Data integrity verification
        $step_start = microtime(true);
        log_message("Step 4: Comprehensive data integrity verification...");
        
        // Check for data consistency
        $integrity_checks = [
            // Check if all merchants in stats exist in registry
            "SELECT COUNT(*) as orphaned_stats FROM merchant_daily_olapay_stats mds 
             LEFT JOIN olapay_merchants_registry omr ON mds.merchant_id = omr.merchant_id 
             WHERE omr.merchant_id IS NULL",
             
            // Check for negative amounts (data quality issue)
            "SELECT COUNT(*) as negative_amounts FROM merchant_daily_olapay_stats 
             WHERE net_amount < 0",
             
            // Check for future dates (data quality issue)
            "SELECT COUNT(*) as future_dates FROM merchant_daily_olapay_stats 
             WHERE date > CURDATE()",
             
            // Check recent data completeness (last 7 days should have data)
            "SELECT COUNT(DISTINCT date) as recent_days FROM merchant_daily_olapay_stats 
             WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND date < CURDATE()"
        ];
        
        $integrity_issues = [];
        
        foreach ($integrity_checks as $check_name => $query) {
            $result = $pdo->query($query)->fetch(PDO::FETCH_ASSOC);
            $value = reset($result);
            $metric_name = key($result);
            
            log_message("Integrity check - {$metric_name}: {$value}");
            
            // Flag potential issues
            if (($metric_name === 'orphaned_stats' || $metric_name === 'negative_amounts' || $metric_name === 'future_dates') && $value > 0) {
                $integrity_issues[] = "{$metric_name}: {$value}";
            }
            if ($metric_name === 'recent_days' && $value < 5) {
                $integrity_issues[] = "Insufficient recent data: only {$value} days in last 7 days";
            }
        }
        
        if (!empty($integrity_issues)) {
            send_alert("Data Integrity Issues Found", implode(', ', $integrity_issues), 'WARNING');
        }
        
        log_performance("Data integrity verification", $step_start);
        
        // Step 5: Performance benchmark test
        $step_start = microtime(true);
        log_message("Step 5: Performance benchmark test...");
        
        // Test query performance with the new data
        $benchmark_query = "
            SELECT 
                merchant_id,
                business_name,
                SUM(transaction_count) as transactions,
                SUM(net_amount) as amount
            FROM merchant_daily_olapay_stats 
            WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY merchant_id, business_name
            ORDER BY amount DESC
            LIMIT 10
        ";
        
        $benchmark_start = microtime(true);
        $benchmark_result = $pdo->query($benchmark_query);
        $benchmark_rows = $benchmark_result->fetchAll(PDO::FETCH_ASSOC);
        $benchmark_duration = round(microtime(true) - $benchmark_start, 3);
        
        log_message("Benchmark query completed in {$benchmark_duration}s for " . count($benchmark_rows) . " merchants");
        
        if ($benchmark_duration > 1.0) {
            send_alert("Performance Degradation", "Benchmark query took {$benchmark_duration}s (expected < 1s)", 'WARNING');
        }
        
        log_performance("Performance benchmark", $step_start, count($benchmark_rows));
        
        // Step 6: Generate comprehensive weekly report
        $step_start = microtime(true);
        log_message("Step 6: Generating weekly summary report...");
        
        $weekly_stats = $pdo->query("
            SELECT 
                COUNT(DISTINCT merchant_id) as total_merchants,
                COUNT(DISTINCT date) as days_with_data,
                SUM(transaction_count) as total_transactions,
                SUM(net_amount) as total_net_amount,
                AVG(net_amount) as avg_daily_amount,
                MIN(date) as earliest_date,
                MAX(date) as latest_date
            FROM merchant_daily_olapay_stats
        ")->fetch(PDO::FETCH_ASSOC);
        
        log_message("=== Weekly Summary Report ===");
        log_message("Total merchants in system: " . $weekly_stats['total_merchants']);
        log_message("Days with data: " . $weekly_stats['days_with_data']);
        log_message("Total transactions: " . number_format($weekly_stats['total_transactions']));
        log_message("Total net amount: $" . number_format($weekly_stats['total_net_amount'], 2));
        log_message("Average daily amount: $" . number_format($weekly_stats['avg_daily_amount'], 2));
        log_message("Date range: {$weekly_stats['earliest_date']} to {$weekly_stats['latest_date']}");
        
        log_performance("Weekly report generation", $step_start);
        
        // Step 7: Cleanup old backup tables (keep only latest)
        $step_start = microtime(true);
        log_message("Step 7: Cleaning up old backup tables...");
        
        $cleanup_queries = [
            "DROP TABLE IF EXISTS merchant_daily_olapay_stats_backup_old",
            "DROP TABLE IF EXISTS olapay_merchants_registry_backup_old"
        ];
        
        foreach ($cleanup_queries as $query) {
            $pdo->exec($query);
        }
        
        log_performance("Backup cleanup", $step_start);
        
        // Final performance summary
        log_performance("TOTAL WEEKLY REBUILD", $script_start);
        log_message("=== Weekly OlaPay Rebuild Completed Successfully ===");
        
        return 0; // Success
        
    } catch (Exception $e) {
        log_message("CRITICAL ERROR: " . $e->getMessage(), 'ERROR');
        log_message("Stack trace: " . $e->getTraceAsString(), 'ERROR');
        
        // Send critical alert
        send_alert("Weekly Rebuild Failed", $e->getMessage(), 'CRITICAL');
        
        // Attempt to restore from backup if rebuild failed
        try {
            log_message("Attempting to restore from backup...", 'ERROR');
            $pdo->exec("TRUNCATE TABLE merchant_daily_olapay_stats");
            $pdo->exec("INSERT INTO merchant_daily_olapay_stats SELECT * FROM merchant_daily_olapay_stats_backup");
            $pdo->exec("TRUNCATE TABLE olapay_merchants_registry");
            $pdo->exec("INSERT INTO olapay_merchants_registry SELECT * FROM olapay_merchants_registry_backup");
            log_message("Backup restore completed", 'ERROR');
        } catch (Exception $restore_error) {
            log_message("Backup restore failed: " . $restore_error->getMessage(), 'ERROR');
            send_alert("Backup Restore Failed", $restore_error->getMessage(), 'CRITICAL');
        }
        
        log_message("=== Weekly OlaPay Rebuild Failed ===", 'ERROR');
        
        return 1; // Failure
    }
}

// Execute main function and exit with appropriate code
$exit_code = main();
exit($exit_code);
?> 