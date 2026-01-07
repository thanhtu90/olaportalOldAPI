# Order Deletion Stored Procedures

This directory contains MySQL stored procedures for safely deleting orders and their related data.

## Procedures

### 1. `delete_order_cascade(order_id)`
Basic procedure that deletes an order and all related data.

### 2. `delete_order_cascade_detailed(order_id)`  
Enhanced version that provides detailed feedback about what was deleted.

### 3. `preview_order_delete(order_id)`
Dry-run procedure that shows what would be deleted without actually deleting anything.

## Bulk Deletion Procedures

### 4. `delete_orders_by_vendor_date(vendors_id, lastmod_from)`
Bulk delete orders by vendor and timestamp criteria.

### 5. `preview_orders_by_vendor_date(vendors_id, lastmod_from)`
Preview what orders would be deleted by vendor and timestamp.

### 6. `delete_orders_by_vendor_date_safe(vendors_id, lastmod_from, confirm_delete)`
Safe bulk deletion with explicit confirmation required.

## Installation

### Single Order Procedures
```sql
SOURCE db/procedures/delete_order_cascade.sql;
SOURCE db/procedures/delete_order_cascade_detailed.sql;
SOURCE db/procedures/preview_order_delete.sql;
```

### Bulk Vendor Date Procedures
```sql
SOURCE db/procedures/install_vendor_date_procedures.sql;
```

### Install All Procedures
```sql
SOURCE db/procedures/install_delete_procedures.sql;
SOURCE db/procedures/install_vendor_date_procedures.sql;
```

## Usage Examples

### Single Order Deletion

**Preview what would be deleted (recommended first step)**
```sql
CALL preview_order_delete(12345);
```

**Delete with basic feedback**
```sql
CALL delete_order_cascade(12345);
```

**Delete with detailed feedback**
```sql
CALL delete_order_cascade_detailed(12345);
```

### Bulk Order Deletion by Vendor and Date

**Preview bulk deletion (always recommended first)**
```sql
-- Preview orders for vendor 35 with lastMod >= 1754172998
CALL preview_orders_by_vendor_date(35, 1754172998);
```

**Safe bulk deletion with confirmation**
```sql
-- Requires explicit confirmation
CALL delete_orders_by_vendor_date_safe(35, 1754172998, 'YES');
```

**Direct bulk deletion**
```sql
-- Use with caution - no confirmation prompt
CALL delete_orders_by_vendor_date(35, 1754172998);
```

## What Gets Deleted (in order)

1. **ordersPayments** - All payment records where `orderReference = order_id`
2. **orderItems** - All order items where `orders_id = order_id`  
3. **orders** - The order record where `id = order_id`

## Error Handling

- All procedures use transactions with rollback on error
- Input validation for order_id parameter
- Checks if order exists before attempting deletion
- Detailed error messages for troubleshooting

## Troubleshooting "Commands out of sync" Error

If you encounter **#2014 - Commands out of sync**, try these steps:

### Step 1: Reset Connection
```sql
SET FOREIGN_KEY_CHECKS = 1;
```

### Step 2: Test Simple Version First
```sql
-- Install troubleshooting procedure
SOURCE db/procedures/troubleshoot_procedures.sql;

-- Test basic functionality
CALL test_order_delete(499553);
```

### Step 3: Use Procedures One at a Time
```sql
-- Close any previous connections/queries first
-- Then run ONE procedure at a time:

CALL preview_order_delete(499553);
-- Review results, then if OK:

CALL delete_order_cascade(499553);
```

### Step 4: Alternative for phpMyAdmin Users
If using phpMyAdmin:
1. Refresh the page after each procedure call
2. Or use the MySQL command line instead:
```bash
mysql -u username -p database_name
```

## Safety Features

- Transaction-based operations (atomic - all or nothing)
- Input validation
- Existence checks before deletion
- Error handling with rollback
- Preview mode for testing

## Example Output

### Preview Output
```
status: PREVIEW
order_id: 12345
order_uuid: f1230893-746e-46d7-9c58-8ffbf5714c56
order_total: 25.50
payments_to_delete: 2
items_to_delete: 5
total_records_to_delete: 8
message: This is a preview. No data will be deleted.
```

### Success Output
```
status: SUCCESS
deleted_order_id: 12345
payments_deleted: 2
items_deleted: 5
orders_deleted: 1
total_records_deleted: 8
message: Order and all related data deleted successfully
```