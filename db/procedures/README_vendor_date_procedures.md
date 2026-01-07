# Vendor Date Order Deletion Procedures

This directory contains MySQL stored procedures for bulk deleting orders by vendor and timestamp criteria.

## Procedures

### 1. `delete_orders_by_vendor_date(vendors_id, lastmod_from)`
Basic procedure that deletes multiple orders based on vendor and timestamp.

### 2. `preview_orders_by_vendor_date(vendors_id, lastmod_from)`  
Preview procedure that shows what would be deleted without actually deleting anything.

### 3. `delete_orders_by_vendor_date_safe(vendors_id, lastmod_from, confirm_delete)`
Enhanced safe version with explicit confirmation required and batch processing.

## Installation

Run the installation script:
```sql
SOURCE db/procedures/install_vendor_date_procedures.sql;
```

Or install individually:
```sql
SOURCE db/procedures/delete_orders_by_vendor_date.sql;
SOURCE db/procedures/preview_orders_by_vendor_date.sql;
SOURCE db/procedures/delete_orders_by_vendor_date_safe.sql;
```

## Usage Examples

### Preview what would be deleted (recommended first step)
```sql
-- Preview orders for vendor 35 with lastMod >= 1754172998
CALL preview_orders_by_vendor_date(35, 1754172998);
```

### Safe deletion with confirmation
```sql
-- First call requires confirmation
CALL delete_orders_by_vendor_date_safe(35, 1754172998);

-- After reviewing, confirm deletion
CALL delete_orders_by_vendor_date_safe(35, 1754172998, 'YES');
```

### Basic deletion (use with caution)
```sql
-- Direct deletion without confirmation prompt
CALL delete_orders_by_vendor_date(35, 1754172998);
```

## Query Criteria

All procedures use the same WHERE clause:
```sql
WHERE vendors_id = p_vendors_id AND lastMod >= p_lastmod_from
```

This matches your example:
```sql
SELECT * FROM orders WHERE vendors_id = 35 AND lastMod >= 1754172998
```

## What Gets Deleted (in order)

For each matching order:

1. **ordersPayments** - All payment records where `orderReference = order_id`
2. **orderItems** - All order items where `orders_id = order_id`  
3. **orders** - The order record itself

## Safety Features

### All Procedures:
- ✅ **Transaction-based operations** (atomic - all or nothing)
- ✅ **Input validation** for parameters
- ✅ **Error handling** with rollback
- ✅ **Detailed error messages**

### Safe Version Additional Features:
- ✅ **Explicit confirmation required** (`confirm_delete = 'YES'`)
- ✅ **Batch processing** (100 orders at a time)
- ✅ **Vendor name validation**
- ✅ **Progress reporting**

## Example Output

### Preview Output
```
status: PREVIEW
vendors_id: 35
vendor_name: Test Restaurant
lastmod_from: 1754172998
lastmod_from_readable: 2025-08-02 15:03:18
orders_to_delete: 15
payments_to_delete: 18
items_to_delete: 45
total_records_to_delete: 78
earliest_order_date: 2025-08-02 14:30:00
latest_order_date: 2025-08-02 15:45:00
total_order_amount: 450.75
message: This is a preview. No data will be deleted.
```

### Safe Deletion - Confirmation Required
```
status: CONFIRMATION_REQUIRED
vendors_id: 35
vendor_name: Test Restaurant
orders_to_delete: 15
lastmod_from: 1754172998
message: To proceed with deletion, call with p_confirm_delete = "YES"
example_call: CALL delete_orders_by_vendor_date_safe(35, 1754172998, "YES");
```

### Success Output
```
status: SUCCESS
vendors_id: 35
vendor_name: Test Restaurant
lastmod_from: 1754172998
batches_processed: 1
payments_deleted: 18
items_deleted: 45
orders_deleted: 15
total_records_deleted: 78
message: Orders and all related data deleted successfully
```

## Timestamp Conversion

The `lastmod` field uses Unix timestamps. You can convert between formats:

```sql
-- Convert readable date to timestamp
SELECT UNIX_TIMESTAMP('2025-08-02 15:03:18') as timestamp;
-- Result: 1754172998

-- Convert timestamp to readable date  
SELECT FROM_UNIXTIME(1754172998) as readable_date;
-- Result: 2025-08-02 15:03:18
```

## Recommended Workflow

### 1. Preview First (Always Recommended)
```sql
CALL preview_orders_by_vendor_date(35, 1754172998);
```

### 2. Use Safe Deletion
```sql
-- Get confirmation prompt
CALL delete_orders_by_vendor_date_safe(35, 1754172998);

-- After reviewing, confirm
CALL delete_orders_by_vendor_date_safe(35, 1754172998, 'YES');
```

### 3. Verify Deletion
```sql
-- Check that orders were deleted
SELECT COUNT(*) as remaining_orders 
FROM orders 
WHERE vendors_id = 35 AND lastMod >= 1754172998;
```

## Performance Notes

- **Batch Processing**: Safe version processes 100 orders at a time
- **Index Usage**: Procedures use indexes on `vendors_id` and `lastMod`
- **Memory Efficient**: Temporary tables are cleaned up after each batch
- **Transaction Safe**: Full rollback on any error

## Error Handling

### Common Errors and Solutions

**"Invalid vendors_id provided"**
- Ensure vendors_id is a positive integer

**"Invalid lastmod timestamp provided"**  
- Ensure lastmod is a valid Unix timestamp (positive integer)

**"Vendor not found"**
- Check if the vendors_id exists in the accounts table

**"No orders found matching criteria"**
- Verify the WHERE clause matches existing data
- Check that lastmod timestamps are correct

## Backup Recommendation

Before running deletion procedures on production data:

```sql
-- Create backup of affected data
CREATE TABLE backup_orders_YYYYMMDD AS 
SELECT * FROM orders WHERE vendors_id = 35 AND lastMod >= 1754172998;

CREATE TABLE backup_payments_YYYYMMDD AS
SELECT op.* FROM ordersPayments op
INNER JOIN orders o ON op.orderReference = o.id
WHERE o.vendors_id = 35 AND o.lastMod >= 1754172998;

CREATE TABLE backup_items_YYYYMMDD AS  
SELECT oi.* FROM orderItems oi
INNER JOIN orders o ON oi.orders_id = o.id
WHERE o.vendors_id = 35 AND o.lastMod >= 1754172998;
```

This ensures you can restore data if needed.