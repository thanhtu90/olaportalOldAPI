# Testing Duplicate Payment Prevention

This directory contains test files to verify that the duplicate payment fix in `json.php` is working correctly.

## Files

1. **test_payload_order_681126.json** - The actual payload (double-encoded JSON string) that can be sent to json.php
2. **test_payload_order_681126_readable.json** - Human-readable version for inspection (NOT for direct use)
3. **test_duplicate_payment.php** - Script to send the test payload to json.php

## Test Scenario

Order 681126 had 651 duplicate payments because:
- The payload contains `paymentUuid` (online order format)
- The code was only checking for `pUUID` (POS format)
- This caused `paymentUuid` to be `null` in the database
- The deduplication check failed because `NULL = NULL` is false in SQL

## How to Test

### Option 1: Using the test script

```bash
# Test against local json.php
php test_duplicate_payment.php --url=http://localhost/json.php

# Test against staging
php test_duplicate_payment.php --url=https://staging.example.com/json.php
```

### Option 2: Using curl directly

```bash
curl -X POST http://localhost/json.php \
  -H "Content-Type: application/json" \
  -d @test_payload_order_681126.json
```

### Option 3: Using Postman or similar tool

1. Import `test_payload_order_681126.json`
2. Send POST request to `http://localhost/json.php`
3. Check the response

## Expected Behavior (After Fix)

1. **First request**: Payment is created successfully
2. **Subsequent requests with same paymentUuid**: 
   - Should detect existing payment
   - Should UPDATE the existing payment (if lastMod is newer)
   - Should NOT create duplicate payments

## Verification

After sending the test payload, verify:

```bash
# Check for duplicates
php remove_duplicate_payments.php --order-id=681126

# Should show: "No duplicates found. All payments are unique."
```

Or query the database directly:

```sql
SELECT 
  id, 
  paymentUuid, 
  orderUuid, 
  orderReference, 
  amtPaid, 
  lastMod 
FROM ordersPayments 
WHERE orderReference = 681126 
ORDER BY id;
```

## Payload Structure

The payload contains:
- **serial**: Terminal serial number
- **json**: Double-encoded JSON string containing:
  - `isOnlinePlatform: true` (indicates online order)
  - `payments`: Array with `paymentUuid` field (NOT `pUUID`)
  - `orders`: Order information
  - `items`: Order items
  - `groups`: Item groups

## Key Points

- Online orders use `paymentUuid` in the payload
- POS orders use `pUUID` in the payload
- The fix checks both field names: `paymentUuid ?? pUUID`
- Deduplication works by: `paymentUuid + orderUuid` (or fallback to `orderUuid + payDate + orderReference`)
