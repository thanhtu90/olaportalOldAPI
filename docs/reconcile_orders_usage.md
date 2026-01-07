# Reconcile Orders Script Usage Guide

The reconcile orders script processes JSON data to reconcile orders and their items. It now supports multiple execution modes for maximum flexibility.

## Basic Usage

### 1. Reconcile by Date Range (Original Mode)
Process all JSON records from a specific date:

```bash
# CLI usage
php reconcile_orders.php --date=2024-01-15

# Web usage  
?date=2024-01-15
```

### 2. Reconcile Specific JSON Record (New Feature)
Process a single JSON record by its ID:

```bash
# CLI usage
php reconcile_orders.php --json_id=12345

# Web usage
?json_id=12345
```

## Advanced Filtering

### Filter by Vendor
Combine either mode with vendor filtering:

```bash
# Date + Vendor
php reconcile_orders.php --date=2024-01-15 --vendors_id=123

# JSON ID + Vendor  
php reconcile_orders.php --json_id=12345 --vendors_id=123
```

### Debug Mode
Add detailed debugging information:

```bash
# Date mode with debug
php reconcile_orders.php --date=2024-01-15 --debug

# JSON ID mode with debug
php reconcile_orders.php --json_id=12345 --debug
```

## Finding JSON Record IDs

Use the helper script to find specific JSON record IDs:

```bash
# Find records by date
php find_json_records.php --date=2024-01-15

# Find records by terminal serial
php find_json_records.php --serial=ABC123

# Find records by vendor
php find_json_records.php --vendors_id=123

# Combine criteria with limit
php find_json_records.php --date=2024-01-15 --vendors_id=123 --limit=10
```

## Parameter Reference

| Parameter | Required | Description | Example |
|-----------|----------|-------------|---------|
| `date` | Yes* | Date in YYYY-MM-DD format | `--date=2024-01-15` |
| `json_id` | Yes* | Specific JSON record ID | `--json_id=12345` |
| `vendors_id` | No | Filter by vendor ID | `--vendors_id=123` |
| `debug` | No | Enable debug mode | `--debug` |

*Either `date` OR `json_id` is required, not both.

## Usage Examples

### Common Scenarios

**Reconcile today's data:**
```bash
php reconcile_orders.php --date=$(date +%Y-%m-%d)
```

**Reconcile specific vendor's data:**
```bash
php reconcile_orders.php --date=2024-01-15 --vendors_id=123
```

**Debug a problematic JSON record:**
```bash
# First find the JSON ID
php find_json_records.php --serial=PROBLEMATIC_TERMINAL

# Then reconcile with debug
php reconcile_orders.php --json_id=12345 --debug
```

**Process single record for testing:**
```bash
# Find recent records
php find_json_records.php --date=2024-01-15 --limit=5

# Test reconcile one record  
php reconcile_orders.php --json_id=12345
```

## Output Examples

### Date Mode Output
```
Starting reconciliation for date: 2024-01-15
Filtering by vendors_id: 123
Found 5 terminals for vendors_id 123
Querying for lastmod between '2024-01-15 00:00:00' and '2024-01-15 23:59:59'
Found 25 records to process (ordered latest first).
```

### JSON ID Mode Output  
```
Starting reconciliation for JSON ID: 12345
Filtering by vendors_id: 123
Querying for JSON record with ID: 12345
Found 1 records to process.
```

## Error Handling

### Common Errors and Solutions

**"Please provide either a date OR a json_id parameter"**
- Solution: Provide exactly one of `--date` or `--json_id`

**"JSON ID must be a valid number"**
- Solution: Ensure `json_id` is numeric (e.g., `12345`, not `abc`)

**"No JSON record found with ID: 12345"**
- Solution: Verify the JSON ID exists using `find_json_records.php`

**"No terminals found for vendors_id: 123"**
- Solution: Check if the vendor ID exists in the terminals table

## Performance Notes

### When to Use Each Mode

**Date Mode:**
- ✅ Regular daily/batch reconciliation
- ✅ Processing large datasets
- ✅ Scheduled automation
- ✅ **Creating missing orders** from JSON data
- ✅ **Recovering lost order data**

**JSON ID Mode:**
- ✅ Debugging specific issues
- ✅ Reprocessing single transactions
- ✅ Testing and development
- ✅ Processing specific problematic records
- ✅ **Creating missing orders** for specific JSON records
- ✅ **Fixing data inconsistencies**

## NEW: Automatic Order Creation

### Feature Overview
The reconcile script now **automatically creates missing orders** instead of skipping them. When an order exists in JSON but not in the database, the script will:

1. ✅ **Extract order data** from JSON
2. ✅ **Create the missing order** in database  
3. ✅ **Continue with item reconciliation** as normal
4. ✅ **Report creation success** with timing

### Sample Output
```
--- Processing Order [1]: ID=206, UUID=e7d1d2b2-5a95-49a9-a9a3-483b34b90f98 ---
🔄 Order with UUID e7d1d2b2-5a95-49a9-a9a3-483b34b90f98 not found in database. Creating missing order...
✅ Created missing order with ID: 499554 in 3.2ms
✅ SUCCESS: Order UUID e7d1d2b2-5a95-49a9-a9a3-483b34b90f98 reconciliation completed

JSON Record Summary:
Orders found in JSON: 2
Orders processed: 2
Orders created: 1
Orders skipped: 0
```

### Testing Order Creation
```bash
# Test order creation functionality
php test_order_creation.php --json_id=12345

# Find JSON records with missing orders
php find_json_records.php --date=2024-01-15

# Run reconcile to create missing orders
php reconcile_orders.php --json_id=12345 --debug
```

### Performance Tips

1. **Use vendor filtering** when possible to reduce dataset size
2. **JSON ID mode is fastest** for single records
3. **Date mode with vendor filter** is efficient for targeted reconciliation
4. **Debug mode adds overhead** - use only when needed
5. **Order creation adds ~3-5ms** per missing order

## Integration Examples

### Cron Job Setup
```bash
# Daily reconciliation at 2 AM
0 2 * * * /usr/bin/php /path/to/reconcile_orders.php --date=$(date +%Y-%m-%d) > /var/log/reconcile.log 2>&1

# Vendor-specific reconciliation
0 3 * * * /usr/bin/php /path/to/reconcile_orders.php --date=$(date +%Y-%m-%d) --vendors_id=123
```

### Error Recovery Workflow
```bash
#!/bin/bash
# Find problematic records from yesterday
php find_json_records.php --date=$(date -d yesterday +%Y-%m-%d) > problem_records.txt

# Process each record individually with debug
while read -r json_id; do
    echo "Processing JSON ID: $json_id"
    php reconcile_orders.php --json_id=$json_id --debug
done < problem_records.txt
```

This enhanced reconcile script provides both bulk processing capabilities and precise single-record control for maximum operational flexibility.