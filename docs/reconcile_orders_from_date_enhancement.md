# Reconcile Orders - From-Date Enhancement

## Overview

The `reconcile_orders.php` script has been enhanced to support a new `from-date` parameter that allows filtering JSON records starting from a specific date onwards, instead of just processing a single day.

## New Parameter: `from-date`

### Purpose
- Processes JSON records from a specified start date onwards
- Uses `WHERE lastmod >= 'YYYY-MM-DD 00:00:00'` SQL condition
- Useful for bulk reconciliation of multiple days of data

### Usage Examples

#### Command Line Interface
```bash
# Process records from 2025-01-15 onwards
php reconcile_orders.php --from-date=2025-01-15

# With debug mode and vendor filtering
php reconcile_orders.php --from-date=2025-01-15 --debug --vendors_id=35

# With vendor filtering only
php reconcile_orders.php --from-date=2025-01-15 --vendors_id=35
```

#### Web Interface
```
# Process records from 2025-01-15 onwards
?from-date=2025-01-15

# With debug mode and vendor filtering
?from-date=2025-01-15&debug=true&vendors_id=35

# With vendor filtering only
?from-date=2025-01-15&vendors_id=35
```

## Parameter Validation

### Mutually Exclusive Parameters
The script now enforces that only ONE of these parameters can be used at a time:
- `date` - Process specific single day (YYYY-MM-DD)
- `from-date` - Process from date onwards (YYYY-MM-DD)  
- `json_id` - Process specific JSON record ID

### Date Format Validation
- Both `date` and `from-date` must be in `YYYY-MM-DD` format
- Invalid format returns HTTP 400 error with clear message

## SQL Query Logic

### From-Date Query (without vendor filtering)
```sql
SELECT serial, content 
FROM json 
WHERE lastmod >= '2025-01-15 00:00:00' 
ORDER BY id DESC
```

### From-Date Query (with vendor filtering)
```sql
SELECT serial, content 
FROM json 
WHERE lastmod >= '2025-01-15 00:00:00' 
  AND serial IN (?, ?, ?, ...) 
ORDER BY id DESC
```

## Implementation Details

### 1. Parameter Extraction
- ✅ CLI: `--from-date=YYYY-MM-DD`
- ✅ Web: `?from-date=YYYY-MM-DD`

### 2. Validation Logic
- ✅ Conflict checking between date/from-date/json_id
- ✅ Date format validation
- ✅ Clear error messages with usage examples

### 3. SQL Query Building
- ✅ Uses `lastmod >= ?` condition for from-date
- ✅ Integrates with existing vendor filtering logic
- ✅ Maintains ORDER BY id DESC for processing order

### 4. Error Handling
- ✅ Empty results handling for from-date scenarios
- ✅ Vendor-specific error messages when applicable

### 5. Progress Display
- ✅ Shows "from date onwards" in reconciliation start message
- ✅ Final summary shows from-date completion message

## Comparison with Existing Parameters

| Parameter | Scope | SQL Condition | Use Case |
|-----------|--------|---------------|----------|
| `date` | Single day | `lastmod BETWEEN 'YYYY-MM-DD 00:00:00' AND 'YYYY-MM-DD 23:59:59'` | Daily reconciliation |
| `from-date` | Multiple days | `lastmod >= 'YYYY-MM-DD 00:00:00'` | Bulk/catch-up reconciliation |
| `json_id` | Single record | `id = ?` | Specific record reprocessing |

## Benefits

### 1. **Bulk Processing**
- Process multiple days of data in one operation
- Useful for catching up after system downtime

### 2. **Flexibility** 
- No need to run separate commands for each day
- Can process weeks/months of data at once

### 3. **Performance**
- Single database connection for multiple days
- Efficient for large-scale reconciliation

### 4. **Vendor Filtering Compatible**
- Works seamlessly with existing `vendors_id` filtering
- Maintains all existing performance optimizations

## Example Output

### Starting Message
```
Starting reconciliation from date: 2025-01-15 onwards
Filtering by vendors_id: 35
Found 3 terminals for vendors_id 35
Querying for lastmod from '2025-01-15 00:00:00' onwards
Filtering JSON records by 3 terminal serials
Found 127 records to process (ordered latest first).
```

### Completion Message
```
==================================================
Reconciliation finished from date: 2025-01-15 onwards (vendors_id: 35)
Total records processed: 127/127
Unique orders reconciled: 89
Total elapsed time: 45:23
==================================================
```

### Error Messages
```bash
# No parameters provided
Please provide either a date, from-date, OR a json_id parameter.
Usage: php reconcile_orders.php --date=YYYY-MM-DD [--debug] [--vendors_id=ID]
   OR: php reconcile_orders.php --from-date=YYYY-MM-DD [--debug] [--vendors_id=ID]
   OR: php reconcile_orders.php --json_id=12345 [--debug] [--vendors_id=ID]

# Conflicting parameters
Please provide only ONE of: date, from-date, or json_id parameters.

# Invalid date format
From-date must be in YYYY-MM-DD format.

# No records found
No records found from date: 2025-01-15 onwards with vendors_id: 35
```

## Integration with Existing Features

### ✅ Works With:
- Vendor filtering (`--vendors_id` parameter)
- Debug mode (`--debug` parameter)  
- Memory management and connection optimization
- Progress tracking and elapsed time calculation
- Duplicate order handling
- Batch insert optimizations

### ✅ Maintains:
- All existing validation rules
- Error handling and logging
- Performance optimizations
- Database connection reuse
- Memory cleanup routines

## Use Cases

### 1. **Daily Catch-up**
```bash
# Process yesterday onwards (in case some records were missed)
php reconcile_orders.php --from-date=$(date -d "yesterday" +%Y-%m-%d)
```

### 2. **Weekly Reconciliation**
```bash
# Process this week's data for specific vendor
php reconcile_orders.php --from-date=2025-01-13 --vendors_id=35
```

### 3. **System Recovery**
```bash
# After system downtime, process all records since last known good date
php reconcile_orders.php --from-date=2025-01-10 --debug
```

### 4. **Vendor-Specific Bulk Processing**
```bash
# Process all data for vendor 35 from start of month
php reconcile_orders.php --from-date=2025-01-01 --vendors_id=35
```

## Performance Considerations

### Recommended Practices:
1. **Use vendor filtering** when possible to reduce dataset size
2. **Monitor memory usage** for very large date ranges
3. **Run during off-peak hours** for bulk processing
4. **Use debug mode** first to verify scope before production runs

### Memory Management:
- Existing memory cleanup routines work with from-date
- Garbage collection every 25 records  
- Connection maintenance every 50 records
- Memory usage monitoring and reporting

The from-date enhancement provides a powerful tool for bulk reconciliation while maintaining all existing safety features and performance optimizations.