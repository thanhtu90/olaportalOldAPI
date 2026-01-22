# OlaPay Subtotal Calculation Logic

## Overview

OlaPay transactions require special handling for subtotal display based on when the device syncs with the portal (every 5 minutes).

## The Problem

Device sync timing affects the `subtotal` value stored in the database:

1. **Status: PASS** - Sale synced BEFORE tip adjustment
   - `subtotal` is already the correct net amount (base price only)
   - No recalculation needed

2. **Status: TIPPED** - Sale synced AFTER tip adjustment  
   - `subtotal` includes tax and techFee (surcharge)
   - Needs recalculation: `subtotal - tax - techFee`

## Example Data

### Case 1: Status PASS (synced before tip adjustment)
```json
{
  "command": "Sale",
  "amount": "1.16",
  "subtotal": "1.00",  // Already correct net amount
  "tax": "0.10",
  "tech_fee_amount": "0.06",
  "Status": "PASS"
}
```
Display subtotal: **$1.00** (as-is)

### Case 2: Status TIPPED (synced after tip adjustment)
```json
{
  "command": "Sale",
  "amount": "0.28",
  "subtotal": "0.23",  // Includes tax + techFee
  "tax": "0.02",
  "tech_fee_amount": "0.01",
  "Status": "TIPPED",
  "tip": "0.05"
}
```
Display subtotal: **$0.20** (0.23 - 0.02 - 0.01)

## Two Status Fields (Display vs Calculation)

### `status` - For DISPLAY
- Reflects current transaction state
- Changes from "PASS" to "TIPPED" after TipAdjustment
- Used for showing transaction status in UI

### `originalSaleStatus` - For CALCULATION
- Preserved from the first Sale record
- Never modified by TipAdjustment
- Used to determine if subtotal needs recalculation

## Why Separate Status Fields?

When a Sale (status: PASS) gets a TipAdjustment:
- `status` changes to "TIPPED" for display purposes
- But the `subtotal` value was recorded when status was "PASS"
- So `originalSaleStatus` = "PASS" tells us subtotal is already correct

## Implementation Details

### Files Modified

1. **`src/utils/olapay.util.ts`** - `calculateOlapayTotals()`
   - Preserves `originalSaleStatus` from the first Sale record
   - Stored separately from `status` which may be modified

2. **`src/app/transaction2/components/olapay-transactions-table/olapay-transactions-table.component.ts`**
   - `getNetSubtotal()` uses `originalSaleStatus` for calculation logic
   - Interface includes both `status` and `originalSaleStatus` fields

### Calculation Logic (`getNetSubtotal`)

```typescript
getNetSubtotal(data: OlapayTransactionRow): string {
  const subtotal = Number(data.subtotal || 0);
  const originalStatus = (data.originalSaleStatus || data.status || '').toUpperCase();

  // PASS: subtotal is already net
  if (originalStatus === 'PASS') {
    return toDecimalFixed(subtotal, 2);
  }

  // TIPPED: subtract tax and techFee
  const tax = Number(data.tax || 0);
  const techFee = Number(data.techFee || 0);
  const netSubtotal = subtotal - tax - techFee;
  return toDecimalFixed(netSubtotal, 2);
}
```

## Transaction Flow Example

For trans_id `5607340911`:

| Step | Command | Status | subtotal | originalSaleStatus | Displayed Subtotal |
|------|---------|--------|----------|-------------------|-------------------|
| 1 | Sale | PASS | 1.00 | PASS | $1.00 |
| 2 | TipAdjustment | PASS | 1.00 | PASS | $1.00 |
| 3 | Return | PASS | 1.00 | PASS | $1.00 |

Even though `status` changes to "TIPPED" after step 2, `originalSaleStatus` remains "PASS", so subtotal displays correctly as $1.00.
