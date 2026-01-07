# OlaPay Transaction Deduplication Solution

This solution addresses the issue of duplicate transactions in the `jsonOlaPay` table by creating a new deduplicated table and updating the relevant queries.

## Problem
The `jsonOlaPay` table contains duplicate transaction data due to the POS system's backup mechanism that sends batches of transactions at intervals, creating multiple copies of the same transaction.

## Solution Overview
1. **New Table**: `unique_olapay_transactions` - stores deduplicated transaction data
2. **Data Migration**: Script to migrate existing data from `jsonOlaPay` to the new table
3. **Updated Queries**: Version 2 of affected files that use the new table
4. **Backward Compatibility**: Updated insertion scripts that populate both tables

## Implementation Steps

### Step 1: Run the Database Migration
```bash
# Run the phinx migration to create the new table
php vendor/bin/phinx migrate -e production
```

### Step 2: Migrate Existing Data
```bash
# Run the data migration script
php migrate_olapay_data.php
```

### Step 3: Update Your Application
Replace the following files with their v2 versions:
- `dashboardtopmerchantsolapay.php` → `dashboardtopmerchantsolapay_v2.php`
- `olapayTerminalRecord.php` → `olapayTerminalRecord_v2.php`
- `jsonOlaPay.php` → `jsonOlaPay_v2.php`

### Step 4: Test the New Implementation
1. Verify that the new table contains deduplicated data
2. Test the v2 endpoints to ensure they return the same results as the original
3. Monitor performance improvements

## Table Structure

### unique_olapay_transactions
- `id` (int, auto-increment, primary key)
- `serial` (varchar(255)) - POS terminal serial number
- `content` (text) - Raw JSON transaction data
- `lastmod` (bigint) - Last modified timestamp
- `order_id` (varchar(255), nullable) - Extracted from JSON content
- `trans_date` (varchar(255), nullable) - Extracted from JSON content
- `trans_id` (varchar(255), nullable) - Extracted from JSON content
- `created_at` (datetime) - Record creation timestamp

### Unique Index
The table has a unique index on `(serial, order_id, trans_date, trans_id)` to ensure no duplicates are inserted.

## Key Benefits

1. **Deduplication**: Eliminates duplicate transactions based on business logic
2. **Performance**: Faster queries due to reduced data volume
3. **Accuracy**: More accurate reporting and analytics
4. **Backward Compatibility**: Original table remains unchanged
5. **Gradual Migration**: Can switch between old and new implementations

## Files Created/Modified

### New Files
- `db/migrations/20250101000000_create_unique_olapay_transactions.php`
- `migrate_olapay_data.php`
- `dashboardtopmerchantsolapay_v2.php`
- `olapayTerminalRecord_v2.php`
- `jsonOlaPay_v2.php`

### Migration Script Features
- Processes all existing `jsonOlaPay` records
- Extracts uniqueness fields from JSON content
- Uses `INSERT IGNORE` to skip duplicates
- Provides detailed progress reporting
- Handles JSON parsing errors gracefully

## Monitoring and Maintenance

### Check Migration Status
```sql
-- Compare record counts
SELECT 
    (SELECT COUNT(*) FROM jsonOlaPay) as original_count,
    (SELECT COUNT(*) FROM unique_olapay_transactions) as unique_count;
```

### Monitor for New Duplicates
```sql
-- Check for any new duplicates in the original table
SELECT serial, order_id, trans_date, trans_id, COUNT(*) as count
FROM jsonOlaPay 
WHERE JSON_EXTRACT(content, '$.orderID') IS NOT NULL
GROUP BY serial, JSON_EXTRACT(content, '$.orderID'), 
         JSON_EXTRACT(content, '$.trans_date'), 
         JSON_EXTRACT(content, '$.trans_id')
HAVING COUNT(*) > 1;
```

## Rollback Plan

If issues arise, you can:
1. Continue using the original files (they remain unchanged)
2. Drop the new table: `DROP TABLE unique_olapay_transactions;`
3. Revert to original file versions

## Performance Considerations

- The new table has optimized indexes for common query patterns
- Queries should be significantly faster due to reduced data volume
- Consider adding additional indexes based on your specific query patterns

## Support

For issues or questions regarding this implementation, please refer to the migration logs and error handling in the migration script. 