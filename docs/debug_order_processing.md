# Debug Order Processing Issues

This guide helps you debug and resolve order processing problems in the reconcile script.

## Problem: Orders Being Skipped

### Enhanced Debugging Features

The reconcile script now includes comprehensive debugging output to help identify why orders are being skipped.

### Sample Output

```
Found 2 orders in JSON:
  [0] Order ID: 70, UUID: c003bfe1-ea66-415d-a320-1d5acc5377b1, Status: PAID
  [1] Order ID: 206, UUID: e7d1d2b2-5a95-49a9-a9a3-483b34b90f98, Status: PAID

--- Processing Order [0]: ID=70, UUID=c003bfe1-ea66-415d-a320-1d5acc5377b1 ---
    Order lookup: 2.5ms | ✅ SUCCESS: Order reconciliation completed

--- Processing Order [1]: ID=206, UUID=e7d1d2b2-5a95-49a9-a9a3-483b34b90f98 ---
❌ SKIPPED: Order with UUID e7d1d2b2-5a95-49a9-a9a3-483b34b90f98 not found in orders table.
    Recent orders in database for terminal 123:
      UUID: c003bfe1-ea66-415d-a320-1d5acc5377b1, ID: 15234, Ref: 70, Total: 0.0
      UUID: aa11bb22-1234-5678-9abc-def012345678, ID: 15233, Ref: 69, Total: 25.50

JSON Record Summary:
Orders found in JSON: 2
Orders processed: 1
Orders skipped: 1
```

## Common Skip Reasons

### 1. Order Not Found in Database
**Symptom:** `❌ SKIPPED: Order with UUID xxx not found in orders table`

**Causes:**
- Order was never inserted into the database
- Order exists with different UUID
- Order was deleted from database

**Debug Steps:**
```bash
# Check if order exists by UUID
php debug_order_lookup.php --uuid=e7d1d2b2-5a95-49a9-a9a3-483b34b90f98

# Check recent orders for the terminal
php debug_order_lookup.php --terminal_id=123

# Check all orders for vendor
php debug_order_lookup.php --vendors_id=456
```

### 2. Order Already Reconciled
**Symptom:** `❌ SKIPPED: Order UUID xxx already reconciled`

**Causes:**
- Order was processed in previous run
- Duplicate orders in same JSON payload

**Debug Steps:**
```bash
# Run reconcile with debug to see processing details
php reconcile_orders.php --json_id=12345 --debug
```

### 3. Missing Order UUID
**Symptom:** `❌ SKIPPED: Order UUID is missing for order ID xxx`

**Causes:**
- JSON data corruption
- Missing UUID field in order data

**Debug Steps:**
```bash
# Check the raw JSON content
php find_json_records.php --date=2024-01-15
# Then examine the specific JSON record
```

### 4. User Cancellation (Debug Mode)
**Symptom:** `❌ SKIPPED: Deletion cancelled by user`

**Causes:**
- User chose not to proceed with item deletion in debug mode

## Debug Tools

### 1. Enhanced Reconcile Script
```bash
# Process with detailed debugging
php reconcile_orders.php --json_id=12345 --debug

# Process specific vendor with debugging
php reconcile_orders.php --date=2024-01-15 --vendors_id=123 --debug
```

### 2. Order Lookup Tool
```bash
# Look up specific order by UUID
php debug_order_lookup.php --uuid=c003bfe1-ea66-415d-a320-1d5acc5377b1

# Check orders for specific terminal
php debug_order_lookup.php --terminal_id=123

# Check all orders for vendor
php debug_order_lookup.php --vendors_id=456
```

### 3. JSON Record Finder
```bash
# Find JSON records containing problematic orders
php find_json_records.php --date=2024-01-15 --vendors_id=123
```

## Troubleshooting Workflow

### Step 1: Identify the Problem
```bash
# Run reconcile with debugging to see what's being skipped
php reconcile_orders.php --json_id=12345 --debug
```

### Step 2: Investigate Missing Orders
```bash
# Check if the order exists in database
php debug_order_lookup.php --uuid=PROBLEMATIC_UUID

# If not found, check recent orders for the terminal
php debug_order_lookup.php --terminal_id=TERMINAL_ID
```

### Step 3: Verify JSON Data
```bash
# Find and examine the JSON record
php find_json_records.php --serial=TERMINAL_SERIAL

# Check the raw JSON content for data integrity
```

### Step 4: Check Order Creation Process
If orders are missing from database:

1. **Check if orders were properly inserted during initial processing**
2. **Verify the order creation logic in json.php**
3. **Check for database constraints or foreign key issues**

## Sample JSON Analysis

For your sample data:
```json
"orders":"[{\"id\":70,\"oUUID\":\"c003bfe1-ea66-415d-a320-1d5acc5377b1\",\"status\":\"PAID\"},{\"id\":206,\"oUUID\":\"e7d1d2b2-5a95-49a9-a9a3-483b34b90f98\",\"status\":\"PAID\"}]"
```

**Analysis:**
- 2 orders in JSON
- Both have valid UUIDs and PAID status
- If second order is skipped, it's likely not in the orders table

**Debug Commands:**
```bash
# Check both orders
php debug_order_lookup.php --uuid=c003bfe1-ea66-415d-a320-1d5acc5377b1
php debug_order_lookup.php --uuid=e7d1d2b2-5a95-49a9-a9a3-483b34b90f98

# Find the JSON record containing this data
php find_json_records.php --date=2025-08-02  # Based on order date

# Reconcile with full debugging
php reconcile_orders.php --json_id=FOUND_JSON_ID --debug
```

## Prevention

### 1. Monitor Order Creation
- Ensure json.php properly creates all orders
- Check for database errors during order insertion
- Verify foreign key relationships

### 2. Regular Verification
```bash
# Daily verification script
#!/bin/bash
DATE=$(date +%Y-%m-%d)
echo "Verifying orders for $DATE"

# Count JSON orders vs database orders
JSON_COUNT=$(php count_json_orders.php --date=$DATE)
DB_COUNT=$(php count_db_orders.php --date=$DATE)

if [ "$JSON_COUNT" != "$DB_COUNT" ]; then
    echo "WARNING: Mismatch found!"
    echo "JSON Orders: $JSON_COUNT"
    echo "DB Orders: $DB_COUNT"
    # Run detailed reconciliation
    php reconcile_orders.php --date=$DATE --debug
fi
```

### 3. Error Logging
Monitor error logs for:
- Order insertion failures
- UUID constraint violations  
- Foreign key violations
- JSON parsing errors

This enhanced debugging system will help you quickly identify and resolve order processing issues.