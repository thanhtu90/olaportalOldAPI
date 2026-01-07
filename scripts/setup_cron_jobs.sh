#!/bin/bash

# =====================================================
# OlaPay Data Pipeline - Cron Jobs Setup Script
# 
# Elite Performance Optimization - Automation Setup
# 
# This script sets up the automated data pipeline for
# the OlaPay dashboard optimization system.
# =====================================================

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PROJECT_ROOT="/home/olaportal/olaportal/api"
PHP_PATH="/usr/bin/php"
LOG_DIR="/var/log/olapay"
CRON_USER="www-data"

echo -e "${BLUE}=== OlaPay Data Pipeline Cron Setup ===${NC}"
echo ""

# Function to print status messages
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    print_error "Please run this script as root (sudo)"
    exit 1
fi

# Verify project directory exists
if [ ! -d "$PROJECT_ROOT" ]; then
    print_error "Project directory not found: $PROJECT_ROOT"
    print_warning "Please update PROJECT_ROOT variable in this script"
    exit 1
fi

# Verify PHP scripts exist
if [ ! -f "$PROJECT_ROOT/scripts/daily_olapay_consolidation.php" ]; then
    print_error "Daily consolidation script not found"
    exit 1
fi

if [ ! -f "$PROJECT_ROOT/scripts/weekly_olapay_rebuild.php" ]; then
    print_error "Weekly rebuild script not found"
    exit 1
fi

# Create log directory
print_status "Creating log directory..."
mkdir -p "$LOG_DIR"
chown "$CRON_USER:$CRON_USER" "$LOG_DIR"
chmod 755 "$LOG_DIR"

# Create cron jobs file
CRON_FILE="/etc/cron.d/olapay-optimization"

print_status "Creating cron jobs configuration..."

cat > "$CRON_FILE" << EOF
# OlaPay Dashboard Optimization - Automated Data Pipeline
# Elite Performance System - Cron Jobs Configuration
#
# This file contains the cron jobs for maintaining the OlaPay
# dashboard optimization system with pre-computed aggregates.
#
# Performance Impact: Enables sub-200ms dashboard loading
#
# Schedule:
# - Daily at 2:00 AM: Process previous day's data
# - Weekly on Sunday at 3:00 AM: Full system rebuild (safety net)

# Set environment variables
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin
MAILTO=""

# Daily OlaPay data consolidation
# Runs every day at 2:00 AM to process previous day's transactions
0 2 * * * $CRON_USER cd $PROJECT_ROOT && $PHP_PATH scripts/daily_olapay_consolidation.php >> $LOG_DIR/daily-consolidation.log 2>&1

# Weekly OlaPay data rebuild (safety net)
# Runs every Sunday at 3:00 AM for full historical rebuild
0 3 * * 0 $CRON_USER cd $PROJECT_ROOT && $PHP_PATH scripts/weekly_olapay_rebuild.php >> $LOG_DIR/weekly-rebuild.log 2>&1

# Log rotation trigger (daily at 1:00 AM, before data processing)
0 1 * * * root /usr/sbin/logrotate -f /etc/logrotate.d/olapay-optimization

EOF

# Set proper permissions for cron file
chmod 644 "$CRON_FILE"
chown root:root "$CRON_FILE"

print_status "Cron jobs file created: $CRON_FILE"

# Create logrotate configuration
LOGROTATE_FILE="/etc/logrotate.d/olapay-optimization"

print_status "Creating log rotation configuration..."

cat > "$LOGROTATE_FILE" << EOF
# OlaPay Optimization - Log Rotation Configuration
$LOG_DIR/*.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
    create 644 $CRON_USER $CRON_USER
    postrotate
        # Send log rotation notification
        echo "Log rotation completed: \$(date)" >> $LOG_DIR/rotation.log
    endscript
}
EOF

chmod 644 "$LOGROTATE_FILE"
chown root:root "$LOGROTATE_FILE"

print_status "Log rotation configuration created: $LOGROTATE_FILE"

# Create monitoring script
MONITOR_SCRIPT="$PROJECT_ROOT/scripts/monitor_olapay_health.php"

print_status "Creating health monitoring script..."

cat > "$MONITOR_SCRIPT" << 'EOF'
<?php
/**
 * OlaPay Optimization Health Monitor
 * 
 * This script checks the health of the OlaPay optimization system
 * and can be called manually or via monitoring systems.
 */

require_once __DIR__ . '/../library/utils.php';

function check_table_health($pdo, $table_name, $expected_days = 7) {
    $query = "
        SELECT 
            COUNT(DISTINCT date) as days_with_data,
            COUNT(*) as total_records,
            MIN(date) as earliest_date,
            MAX(date) as latest_date
        FROM {$table_name}
        WHERE date >= DATE_SUB(CURDATE(), INTERVAL {$expected_days} DAY)
    ";
    
    $result = $pdo->query($query)->fetch(PDO::FETCH_ASSOC);
    
    return [
        'table' => $table_name,
        'status' => $result['days_with_data'] >= ($expected_days - 2) ? 'healthy' : 'warning',
        'days_with_data' => $result['days_with_data'],
        'total_records' => $result['total_records'],
        'date_range' => $result['earliest_date'] . ' to ' . $result['latest_date']
    ];
}

try {
    $pdo = connect_db_and_set_http_method("GET");
    
    echo "=== OlaPay Optimization System Health Check ===\n";
    echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";
    
    // Check merchant registry
    $registry_count = $pdo->query("SELECT COUNT(*) FROM olapay_merchants_registry WHERE status = 'active'")->fetchColumn();
    echo "Active merchants in registry: {$registry_count}\n";
    
    // Check daily stats health
    $stats_health = check_table_health($pdo, 'merchant_daily_olapay_stats');
    echo "Daily stats health: {$stats_health['status']}\n";
    echo "- Days with data (last 7): {$stats_health['days_with_data']}\n";
    echo "- Total records: {$stats_health['total_records']}\n";
    echo "- Date range: {$stats_health['date_range']}\n\n";
    
    // Check procedures exist
    $procedures = $pdo->query("
        SELECT ROUTINE_NAME 
        FROM information_schema.ROUTINES 
        WHERE ROUTINE_SCHEMA = DATABASE() 
        AND ROUTINE_NAME LIKE '%OlaPay%'
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Stored procedures: " . implode(', ', $procedures) . "\n\n";
    
    // Performance test
    $start = microtime(true);
    $test_query = "
        SELECT COUNT(DISTINCT merchant_id) as merchants, SUM(net_amount) as total
        FROM merchant_daily_olapay_stats 
        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ";
    $test_result = $pdo->query($test_query)->fetch(PDO::FETCH_ASSOC);
    $duration = round((microtime(true) - $start) * 1000, 2);
    
    echo "Performance test: {$duration}ms for 30-day aggregation\n";
    echo "- Merchants: {$test_result['merchants']}\n";
    echo "- Total amount: $" . number_format($test_result['total'], 2) . "\n\n";
    
    if ($duration < 100) {
        echo "System Status: EXCELLENT\n";
    } elseif ($duration < 500) {
        echo "System Status: GOOD\n";
    } else {
        echo "System Status: NEEDS ATTENTION\n";
    }
    
} catch (Exception $e) {
    echo "HEALTH CHECK FAILED: " . $e->getMessage() . "\n";
    exit(1);
}
EOF

chmod +x "$MONITOR_SCRIPT"
chown "$CRON_USER:$CRON_USER" "$MONITOR_SCRIPT"

print_status "Health monitoring script created: $MONITOR_SCRIPT"

# Restart cron service
print_status "Restarting cron service..."
systemctl restart cron

if systemctl is-active --quiet cron; then
    print_status "Cron service restarted successfully"
else
    print_error "Failed to restart cron service"
    exit 1
fi

# Display current cron jobs
print_status "Current cron jobs for OlaPay optimization:"
echo ""
crontab -l -u "$CRON_USER" 2>/dev/null | grep -i olapay || echo "No existing OlaPay cron jobs found"
echo ""
cat "$CRON_FILE"

echo ""
echo -e "${GREEN}=== Setup Complete ===${NC}"
echo ""
echo "Next steps:"
echo "1. Run the initial historical consolidation:"
echo "   mysql -u[user] -p[pass] [database] -e 'CALL ConsolidateHistoricalOlaPayStats();'"
echo ""
echo "2. Test the cron jobs manually:"
echo "   sudo -u $CRON_USER $PHP_PATH $PROJECT_ROOT/scripts/daily_olapay_consolidation.php"
echo ""
echo "3. Monitor logs:"
echo "   tail -f $LOG_DIR/daily-consolidation.log"
echo "   tail -f $LOG_DIR/weekly-rebuild.log"
echo ""
echo "4. Check system health:"
echo "   $PHP_PATH $MONITOR_SCRIPT"
echo ""
echo "5. Deploy the optimized API endpoint:"
echo "   Copy dashboardtopmerchantsolapay_v3.php to production"
echo ""
echo -e "${BLUE}Elite optimization system ready! 🚀${NC}" 