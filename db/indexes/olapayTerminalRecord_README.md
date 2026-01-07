# olapayTerminalRecord.php Optimization Guide

## Overview
This optimization reduces the execution time of `olapayTerminalRecord.php` from 16+ seconds to under 2 seconds by:
1. Creating optimal database indexes
2. Optimizing PHP query structure
3. Increasing async concurrency

## Performance Improvements

### Before Optimization
- **Execution Time**: 16+ seconds
- **Bottlenecks**: 
  - Missing composite index on `unique_olapay_transactions`
  - Sequential query execution
  - Inefficient index usage

### After Optimization
- **Expected Execution Time**: < 2 seconds
- **Improvements**:
  - Composite index enables fast lookups
  - Parallel query execution (8 concurrent workers)
  - Optimized query structure

## Setup Instructions

### Step 1: Verify Computed Columns Exist

First, check if the required computed columns exist:

```sql
DESCRIBE unique_olapay_transactions;
```

You should see columns:
- `trans_type` (VARCHAR(50), GENERATED ALWAYS AS ... STORED)
- `status` (VARCHAR(50), GENERATED ALWAYS AS ... STORED)

If these columns don't exist, run:

```sql
ALTER TABLE unique_olapay_transactions 
ADD COLUMN trans_type VARCHAR(50) AS(JSON_UNQUOTE(JSON_EXTRACT(content, '$.trans_type'))) STORED,
ADD COLUMN status VARCHAR(50) AS(JSON_UNQUOTE(JSON_EXTRACT(content, '$.Status'))) STORED;
```

**Note**: If you get an error that the columns already exist, that's fine - skip this step.

### Step 2: Create Indexes

Run the index creation script:

```bash
mysql -u your_user -p your_database < db/indexes/olapayTerminalRecord_optimization_indexes.sql
```

Or execute the SQL directly:

```sql
-- Main composite index for the query
CREATE INDEX IF NOT EXISTS `idx_uot_serial_lastmod_status_type` 
ON `unique_olapay_transactions`(`serial`, `lastmod`, `status`, `trans_type`);

-- Terminal table indexes
CREATE INDEX IF NOT EXISTS `idx_terminals_vendors_serial` 
ON `terminals`(`vendors_id`, `serial`);

CREATE INDEX IF NOT EXISTS `idx_terminals_serial_description` 
ON `terminals`(`serial`, `description`);
```

### Step 3: Verify Indexes Were Created

```sql
SHOW INDEX FROM `unique_olapay_transactions` WHERE Key_name = 'idx_uot_serial_lastmod_status_type';
SHOW INDEX FROM `terminals` WHERE Key_name LIKE 'idx_terminals%';
```

### Step 4: Test the Optimization

1. The PHP code has already been updated with optimizations
2. Test the endpoint and monitor execution time
3. Use `EXPLAIN` to verify index usage:

```sql
EXPLAIN SELECT lastmod, content
FROM unique_olapay_transactions
WHERE serial = 'YOUR_SERIAL'
  AND lastmod > 1640995200
  AND lastmod < 1672531200
  AND status NOT IN ('', 'FAIL', 'REFUNDED')
  AND trans_type NOT IN ('Return Cash', '', 'Auth')
ORDER BY lastmod DESC;
```

Look for:
- `key`: Should show `idx_uot_serial_lastmod_status_type`
- `Extra`: Should NOT show "Using filesort" or "Using temporary"

## Code Changes Made

### 1. Increased Async Concurrency
- Changed from 4 to 8 concurrent workers
- Adjust based on your server's CPU and database connection limits

### 2. Optimized Terminal Query
- Simplified JOIN structure
- Changed `a.id != 172 AND a.id != 183` to `a.id NOT IN (172, 183)`

### 3. Query Comments
- Added comments explaining index usage

## Index Details

### Primary Index: `idx_uot_serial_lastmod_status_type`

**Column Order**: `(serial, lastmod, status, trans_type)`

**Why this order?**
1. `serial` - Equality filter (most selective, first in WHERE clause)
2. `lastmod` - Range filter + ORDER BY column
3. `status` - Additional filter
4. `trans_type` - Additional filter

**Benefits**:
- Enables index-only lookups for the WHERE clause
- Supports efficient range scanning on `lastmod`
- Helps with ORDER BY optimization
- Eliminates full table scans

## Troubleshooting

### Issue: Index not being used

**Check**:
```sql
EXPLAIN SELECT ... -- Your query
```

**Solutions**:
1. Ensure computed columns exist (Step 1)
2. Verify index was created successfully
3. Run `ANALYZE TABLE unique_olapay_transactions;` to update statistics
4. Check if query conditions match index structure

### Issue: Still slow after optimization

**Possible causes**:
1. Too many terminals being queried - consider pagination
2. Date range too large - consider limiting date ranges
3. Database server resources - check CPU, memory, I/O
4. Network latency - check database connection speed

**Solutions**:
- Reduce concurrency if database connections are limited
- Add LIMIT to queries if appropriate
- Consider caching frequently accessed data
- Monitor database server performance

### Issue: MySQL version compatibility

**Computed columns** require MySQL 5.7+ or 8.0+

**IF NOT EXISTS** syntax requires MySQL 5.7.4+

If you're on an older version:
- Remove `IF NOT EXISTS` from CREATE INDEX statements
- Check for column existence manually before ALTER TABLE

## Performance Monitoring

### Monitor Query Performance

```sql
-- Enable slow query log
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2; -- Log queries > 2 seconds
```

### Check Index Usage Statistics

```sql
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    SEQ_IN_INDEX,
    COLUMN_NAME,
    CARDINALITY
FROM INFORMATION_SCHEMA.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'unique_olapay_transactions'
AND INDEX_NAME = 'idx_uot_serial_lastmod_status_type';
```

## Rollback Plan

If you need to rollback:

```sql
-- Drop the indexes
DROP INDEX `idx_uot_serial_lastmod_status_type` ON `unique_olapay_transactions`;
DROP INDEX `idx_terminals_vendors_serial` ON `terminals`;
DROP INDEX `idx_terminals_serial_description` ON `terminals`;
```

Then revert the PHP file changes using git or your version control system.

## Additional Optimizations (Future)

1. **Partitioning**: Consider partitioning `unique_olapay_transactions` by date
2. **Caching**: Implement Redis/Memcached for frequently accessed terminal data
3. **Read Replicas**: Use read replicas for heavy read operations
4. **Query Result Caching**: Cache results for common date ranges

## Support

If you encounter issues:
1. Check MySQL error logs
2. Verify all indexes were created
3. Run EXPLAIN on your queries
4. Monitor database server resources

