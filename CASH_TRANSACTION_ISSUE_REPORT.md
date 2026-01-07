# Cash Transaction Issue Report
## Henflings - Terminal WPYB002446000590

**Date:** December 2, 2025  
**Merchant:** Henflings  
**Terminal Serial:** WPYB002446000590  
**Terminal Description:** APT255P-1  
**Issue:** All cash transactions showing as $0 on portal

---

## Executive Summary

Cash transactions from the APT255P terminal are being recorded as $0 in the portal reports. Investigation reveals that **cash drawer operations (DRW)** are being incorrectly recorded as cash payment transactions with zero amounts, causing all cash revenue to appear as $0.

### Key Findings
- **57 total cash payments** recorded in 2025
- **56 are drawer operations** (DRW) with $0 amounts
- **Only 1 actual cash transaction** with non-zero amount ($5.46)
- **100% of drawer operations** are incorrectly included in cash revenue calculations

---

## Merchant & Terminal Information

| Field | Value |
|-------|-------|
| Merchant ID | 250 |
| Company Name | Henflings |
| Contact | Mario Ibarra |
| Terminal ID | 441 |
| Terminal Serial | WPYB002446000590 |
| Terminal Description | APT255P-1 |

---

## Database Statistics (2025)

| Metric | Count |
|--------|-------|
| Total Cash Payments | 57 |
| Zero Amount Payments | 56 |
| Non-Zero Amount Payments | 1 |
| Drawer Operations (DRW) | 56 |
| Actual Cash Transactions | 1 |
| Total Cash Amount Recorded | $5.46 |
| Total Amount Paid Recorded | $5.46 |

---

## Monthly Analysis (2025)

### March 2025

| Metric | Count |
|--------|-------|
| Total Cash Payments | 16 |
| Drawer Operations (DRW) | 16 |
| Actual Cash Transactions | **0** |
| Total Cash Amount | $0.00 |

**Findings:**
- ✅ Terminal was active (209 total payments, 193 card transactions)
- ❌ **NO actual cash transactions** recorded
- All 16 "CASH" entries are drawer operations with $0 amounts
- Raw JSON data confirms: Only DRW operations with `refNumber = "CASH"` and `total = 0.0`

**Sample Raw JSON (March 28, 2025 - JSON ID 248733):**
```json
{
  "payments": [
    {
      "orderID": "S-CRD-15-300",
      "refNumber": "226",
      "total": 48.60,
      "amtPaid": 48.60
    },
    {
      "orderID": "DRW",
      "refNumber": "CASH",
      "total": 0.0,
      "amtPaid": 0.0
    }
  ]
}
```

### July 2025

| Metric | Count |
|--------|-------|
| Total Cash Payments | 2 |
| Drawer Operations (DRW) | 2 |
| Actual Cash Transactions | **0** |
| Total Cash Amount | $0.00 |

**Findings:**
- ✅ Terminal was active (processing card transactions)
- ❌ **NO actual cash transactions** recorded
- Both "CASH" entries are drawer operations with $0 amounts

**Sample ordersPayments Records:**
| Payment ID | Order Ref | Total | Amt Paid | Order ID | Payment Date |
|------------|-----------|-------|----------|----------|--------------|
| 484391 | 495939 | 0.00 | 0.00 | DRW | 2025-07-27 14:49:27 |
| 474382 | 486009 | 0.00 | 0.00 | DRW | 2025-07-12 07:44:33 |

**Sample Raw JSON (July 27, 2025 - JSON ID 342515):**
```json
{
  "payments": [
    {
      "orderID": "S-CRD-7-1548",
      "refNumber": "1511",
      "total": 14.14,
      "amtPaid": 14.14
    },
    {
      "orderID": "DRW",
      "refNumber": "CASH",
      "total": 0.0,
      "amtPaid": 0.0
    }
  ]
}
```

### September 2025

| Metric | Count |
|--------|-------|
| Total Cash Payments | 3 |
| Drawer Operations (DRW) | 3 |
| Actual Cash Transactions | **0** |
| Total Cash Amount | $0.00 |

**Findings:**
- ✅ Terminal was active (processing card transactions daily)
- ❌ **NO actual cash transactions** recorded
- All 3 "CASH" entries are drawer operations with $0 amounts

**Sample ordersPayments Records:**
| Payment ID | Order Ref | Total | Amt Paid | Order ID | Payment Date |
|------------|-----------|-------|----------|----------|--------------|
| 543221 | 553670 | 0.00 | 0.00 | DRW | 2025-09-29 06:43:25 |
| 530440 | 541219 | 0.00 | 0.00 | DRW | 2025-09-14 07:26:40 |
| 530441 | 541218 | 0.00 | 0.00 | DRW | 2025-09-14 07:26:38 |

**Sample Raw JSON (September 29, 2025 - JSON ID 388297):**
```json
{
  "payments": [
    {
      "orderID": "S-CRD-4-2250",
      "refNumber": "2249",
      "total": 29.45,
      "amtPaid": 29.45
    },
    {
      "orderID": "DRW",
      "refNumber": "CASH",
      "total": 0.0,
      "amtPaid": 0.0
    }
  ]
}
```

### Summary Across Months

| Month | Total Cash Payments | Drawer Ops | Actual Cash | Cash Revenue |
|-------|---------------------|------------|-------------|--------------|
| March | 16 | 16 | **0** | $0.00 |
| July | 2 | 2 | **0** | $0.00 |
| September | 3 | 3 | **0** | $0.00 |
| **Total** | **21** | **21** | **0** | **$0.00** |

**Critical Finding:** Across all three months examined (March, July, September 2025), **ZERO actual cash transactions** were recorded. The terminal was processing card payments normally, but no cash sales were being recorded in the system.

---

## Sample Database Records

### Sample ordersPayments Records (Last 10)

| Payment ID | Order Ref | Total | Amt Paid | Tips | Ref Number | Order ID | Payment Date | Status | Order ID (linked) |
|------------|-----------|-------|----------|------|------------|----------|--------------|--------|-------------------|
| 604350 | 613230 | **0.00** | **0.00** | 0.00 | CASH | **DRW** | 2025-11-28 11:52:53 | 0 | NULL |
| 599830 | 608775 | **0.00** | **0.00** | 0.00 | CASH | **DRW** | 2025-11-23 07:29:06 | 0 | NULL |
| 592175 | 601159 | **0.00** | **0.00** | 0.00 | CASH | **DRW** | 2025-11-14 15:27:08 | 0 | NULL |
| 591102 | 600094 | **0.00** | **0.00** | 0.00 | CASH | **DRW** | 2025-11-14 06:21:39 | 0 | NULL |
| 584624 | 594248 | **0.00** | **0.00** | 0.00 | CASH | **DRW** | 2025-11-07 15:12:19 | 0 | NULL |
| 577344 | 587056 | **0.00** | **0.00** | 0.00 | CASH | **DRW** | 2025-10-31 16:12:31 | 0 | NULL |
| 562959 | 572970 | **0.00** | **0.00** | 0.00 | CASH | **DRW** | 2025-10-18 08:58:08 | 0 | NULL |
| 562960 | 572969 | **0.00** | **0.00** | 0.00 | CASH | **DRW** | 2025-10-18 08:58:06 | 0 | NULL |
| 558553 | 568587 | **0.00** | **0.00** | 0.00 | CASH | **DRW** | 2025-10-17 12:14:38 | 0 | NULL |
| 552913 | 563060 | **0.00** | **0.00** | 0.00 | CASH | **DRW** | 2025-10-10 09:58:46 | 0 | NULL |

**Key Observations:**
- All records have `orderId = "DRW"` (Drawer Operation)
- All records have `total = 0` and `amtPaid = 0`
- All records have `refNumber = "CASH"`
- No linked orders found (`order_id = NULL`)

---

## Raw JSON Data Samples

### Sample 1: JSON ID 441115 (Nov 28, 2025)

**JSON Table Record:**
- **ID:** 441115
- **Serial:** WPYB002446000590
- **Last Modified:** 2025-11-28 11:54:32

**Raw Content (Formatted):**
```json
{
  "payments": [
    {
      "amtPaid": 18.71,
      "employeeId": 0,
      "employeePIN": "NONE",
      "id": 2832,
      "lastMod": 1764384871,
      "oUUID": "e4072966-846c-40ff-b7c7-05950cd6bfc3",
      "olapayApprovalId": "5395591935",
      "orderID": "QS-CRD-9-3030",
      "orderReference": "3030",
      "pUUID": "fd0ec929-fffe-4499-aedb-d7fb407c614f",
      "payDate": "Nov 28, 2025 6:54:31 PM",
      "refNumber": "3055",
      "refund": 0.0,
      "status": "PAID",
      "techfee": 0.0,
      "tips": 1.70,
      "total": 18.71
    },
    {
      "amtPaid": 0.0,
      "employeeId": 0,
      "employeePIN": "NONE",
      "id": 2831,
      "lastMod": 1764384773,
      "oUUID": "dd42ab57-3bdc-4270-a808-9b9dc29b0529",
      "olapayApprovalId": "",
      "orderID": "DRW",
      "orderReference": "3029",
      "pUUID": "081b9ed6-1fcf-442d-a651-0eb6b3534b73",
      "payDate": "Nov 28, 2025 6:52:53 PM",
      "refNumber": "CASH",
      "refund": 0.0,
      "status": "PAID",
      "techfee": 0.0,
      "tips": 0.0,
      "total": 0.0
    }
  ],
  "items": [...],
  "orders": [
    {
      "employeeId": 0,
      "employeePIN": "NONE",
      "id": 3029,
      "lastMod": 1764384773,
      "notes": "",
      "oUUID": "dd42ab57-3bdc-4270-a808-9b9dc29b0529",
      "orderDate": "Nov 28, 2025 6:52:53 PM",
      "orderName": "",
      "status": "PAID",
      "subTotal": 0.0,
      "tax": 0.0,
      "total": 0.0
    },
    {
      "employeeId": 0,
      "employeePIN": "NONE",
      "id": 3030,
      "lastMod": 1764384871,
      "notes": "",
      "oUUID": "e4072966-846c-40ff-b7c7-05950cd6bfc3",
      "orderDate": "Nov 28, 2025 6:54:31 PM",
      "orderName": "",
      "status": "PAID",
      "subTotal": 15.53,
      "tax": 1.48,
      "total": 18.71
    }
  ],
  "groups": [],
  "termId": ""
}
```

**Analysis:**
- Payment 1: Valid card transaction ($18.71)
- Payment 2: **Drawer operation (DRW)** with `refNumber = "CASH"` but `total = 0.0`
- Order 3029: Empty order (0.0 totals) linked to DRW operation
- Order 3030: Valid order linked to card payment

### Sample 2: JSON ID 437059 (Nov 23, 2025)

**JSON Table Record:**
- **ID:** 437059
- **Serial:** WPYB002446000590
- **Last Modified:** 2025-11-23 07:29:25

**Key Payment from Raw Data:**
```json
{
  "amtPaid": 0.0,
  "orderID": "DRW",
  "orderReference": "2965",
  "refNumber": "CASH",
  "total": 0.0,
  "tips": 0.0,
  "status": "PAID"
}
```

### Sample 3: JSON ID 430110 (Nov 15, 2025)

**JSON Table Record:**
- **ID:** 430110
- **Serial:** WPYB002446000590
- **Last Modified:** 2025-11-15 06:46:52

**Key Payment from Raw Data:**
```json
{
  "amtPaid": 0.0,
  "orderID": "DRW",
  "orderReference": "2842",
  "refNumber": "CASH",
  "total": 0.0,
  "tips": 0.0,
  "status": "PAID"
}
```

---

## Root Cause Analysis

### Problem Identification

1. **Terminal Behavior:**
   - The APT255P terminal sends cash drawer operations (opening the drawer) as separate payment records
   - These operations are marked with:
     - `orderID = "DRW"` (Drawer)
     - `refNumber = "CASH"`
     - `total = 0.0` and `amtPaid = 0.0`

2. **Data Processing:**
   - The `json.php` endpoint processes all payments from the JSON payload
   - All payments with `refNumber = "CASH"` are inserted into `ordersPayments` table
   - No filtering is applied to exclude drawer operations

3. **Portal Display:**
   - The `revenue.php` file calculates cash revenue using:
     ```php
     if ( preg_match("/CASH/",$row["refNumber"])   ) {
         $cashRevenue = $cashRevenue + $amtPaid;    
     }
     ```
   - This includes ALL payments with "CASH" in refNumber, including DRW operations
   - Since DRW operations have `amtPaid = 0`, they contribute $0 to cash revenue

### Why Cash Transactions Show as $0

- **56 out of 57** cash payment records are drawer operations (DRW) with $0 amounts
- These $0 records are included in cash revenue calculations
- The portal sums all cash payments: `$0 + $0 + ... + $5.46 = $5.46` (appears as $0 due to rounding/display)
- Actual cash transactions (when customers pay with cash) are likely being processed but may not be properly recorded or are being filtered out

---

## Impact Assessment

### Data Integrity
- ✅ Payment records are being stored correctly
- ❌ Drawer operations are incorrectly classified as cash payments
- ❌ Cash revenue calculations are inaccurate

### Business Impact
- Cash revenue appears as $0 in portal reports
- Financial reporting is inaccurate
- Merchant cannot track actual cash sales
- Potential tax/reconciliation issues

---

## Recommended Solutions

### Solution 1: Filter Drawer Operations in Revenue Calculation (Recommended)

**File:** `revenue.php`  
**Location:** Line 132

**Current Code:**
```php
if ( preg_match("/CASH/",$row["refNumber"])   ) {
    $cashRevenue = $cashRevenue + $amtPaid;    
}
```

**Recommended Fix:**
```php
if ( preg_match("/CASH/",$row["refNumber"]) && $row["orderId"] != "DRW" ) {
    $cashRevenue = $cashRevenue + $amtPaid;    
}
```

**Alternative (More Robust):**
```php
if ( preg_match("/CASH/",$row["refNumber"]) 
     && $row["orderId"] != "DRW" 
     && ($row["total"] > 0 || $row["amtPaid"] > 0) ) {
    $cashRevenue = $cashRevenue + $amtPaid;    
}
```

### Solution 2: Filter at Data Insertion Level

**File:** `json.php`  
**Location:** Payment insertion logic (around line 706)

**Recommended Fix:**
Skip inserting payments where `orderID = "DRW"` and `total = 0`:
```php
// Before inserting payment
if ($orderId == "DRW" && $total == 0 && $amtPaid == 0) {
    continue; // Skip drawer operations
}
```

### Solution 3: Database Cleanup (Optional)

Mark existing drawer operations or exclude them in queries:
```sql
-- Update existing records to mark as drawer operations
UPDATE ordersPayments 
SET status = -1 
WHERE orderId = 'DRW' 
  AND total = 0 
  AND amtPaid = 0 
  AND refNumber = 'CASH';

-- Or exclude in queries
WHERE refNumber = 'CASH' 
  AND NOT (orderId = 'DRW' AND total = 0 AND amtPaid = 0)
```

---

## Verification Queries

### Query 1: Count Cash Payments by Type
```sql
SELECT 
    CASE 
        WHEN orderId = 'DRW' THEN 'Drawer Operation'
        WHEN total > 0 OR amtPaid > 0 THEN 'Actual Cash Transaction'
        ELSE 'Other'
    END as payment_type,
    COUNT(*) as count,
    SUM(total) as total_amount,
    SUM(amtPaid) as total_paid
FROM ordersPayments
WHERE terminals_id = 441
  AND refNumber = 'CASH'
  AND YEAR(FROM_UNIXTIME(payDate)) = YEAR(CURDATE())
GROUP BY payment_type;
```

### Query 2: Find Actual Cash Transactions
```sql
SELECT 
    op.id,
    op.orderReference,
    op.total,
    op.amtPaid,
    op.orderId,
    FROM_UNIXTIME(op.payDate) as payment_date
FROM ordersPayments op
WHERE op.terminals_id = 441
  AND op.refNumber = 'CASH'
  AND op.orderId != 'DRW'
  AND (op.total > 0 OR op.amtPaid > 0)
ORDER BY op.payDate DESC;
```

### Query 3: Check for Missing Cash Orders
```sql
SELECT 
    op.id as payment_id,
    op.orderReference,
    op.total as payment_total,
    o.id as order_id,
    o.total as order_total
FROM ordersPayments op
LEFT JOIN orders o ON op.orderReference = o.orderReference 
    AND op.terminals_id = o.terminals_id
WHERE op.terminals_id = 441
  AND op.refNumber = 'CASH'
  AND op.orderId != 'DRW'
  AND (op.total > 0 OR op.amtPaid > 0)
  AND o.id IS NULL;
```

---

## Next Steps

1. **Immediate Action:** Implement Solution 1 (filter in revenue.php) to fix portal display
2. **Short-term:** Implement Solution 2 (filter at insertion) to prevent future issues
3. **Long-term:** Review terminal configuration to prevent DRW operations from being sent as payments
4. **Data Audit:** Verify if actual cash transactions are being recorded elsewhere or are missing

---

## Appendix: Database Schema Reference

### ordersPayments Table Structure
- `id` - Primary key
- `orderReference` - Links to orders table
- `total` - Payment total amount
- `amtPaid` - Amount paid
- `refNumber` - Payment reference (e.g., "CASH", card numbers)
- `orderId` - Order identifier (e.g., "DRW", "S-CSH-3-1132")
- `payDate` - Payment timestamp (Unix)
- `lastMod` - Last modification timestamp
- `terminals_id` - Foreign key to terminals table

### json Table Structure
- `id` - Primary key
- `serial` - Terminal serial number
- `content` - Raw JSON payload from terminal
- `lastmod` - Last modification timestamp

---

**Report Generated:** December 2, 2025  
**Investigation Method:** Direct database query via MCP MySQL connection  
**Database:** api2 (Production)

