# Split Payment & Dual Pricing Bug Fixes

**Date:** January 21, 2026  
**Affected Merchant:** Ngoc Lan Noodle House (and others with split payments)

## Summary

Fixed two related bugs in `json.php` that caused incorrect order totals and missing items when:
1. Orders were paid with **split payments** (e.g., part cash, part card)
2. **Dual pricing** was enabled (different prices for cash vs card)

---

## Bug 1: Incorrect Order Total for Split Payments

### Problem

When an order was paid with multiple payments (split payment), the `orders.total` was being overwritten with each payment's individual total instead of the full order total.

**Example:**
- Order #676259 had total of $141.19
- Cash payment: $114.63
- Card payment: $26.56
- After card payment processed, `orders.total` was incorrectly set to $26.56

### Root Cause

In `json.php`, when processing payments, the code was using `$payments[$i]->{"total"}` (the individual payment amount) to update `orders.total`, instead of `$orders_json[$i]->{"total"}` (the full order total from the orders array in the JSON).

The POS sends both:
- `payments[].total` = Individual payment amount
- `orders[].total` = Full order total (correct value)

### Solution

Created a map of order UUID/reference to order total from `orders_json`, then use that when updating orders:

```php
// Build a map of orderUuid/orderReference -> order total from orders_json
$orderTotalMap = array();
for ($j = 0; $j < count($orders_json); $j++) {
  $oUUID = $orders_json[$j]->{"oUUID"} ?? ($orders_json[$j]->{"uuid"} ?? null);
  $oRef = $orders_json[$j]->{"id"} ?? null;
  $oTotal = $orders_json[$j]->{"total"} ?? null;
  if ($oUUID !== null && $oTotal !== null) {
    $orderTotalMap["uuid_" . $oUUID] = $oTotal;
  }
  if ($oRef !== null && $oTotal !== null) {
    $orderTotalMap["ref_" . $oRef] = $oTotal;
  }
}

// When updating order, use order total from map instead of payment total
$orderTotalFromJson = $orderTotalMap["uuid_" . $orderUuid] ?? $payments[$i]->{"total"};
```

### Files Changed

- `json.php` - Lines 780-793 (map creation), Lines 816-820 (new POS build), Lines 857-861 (old POS build)
- `reconcile_orders.php` - No changes needed (already uses correct order total from JSON)

---

## Bug 2: Missing Items with Dual Pricing

### Problem

When dual pricing was enabled, items added after the initial payment (with a different price) were not being saved to `orderItems`. The deduplication logic was skipping them because they had the same `itemUuid`.

**Example:**
- "1. Special Combination Beef Noodle Soup" 
  - Cash price: $16.95 (saved)
  - Card price: $17.46 (NOT saved - skipped as duplicate)

### Root Cause

The deduplication logic only checked `itemUuid`:

```php
// OLD: Only checked UUID - skipped items with same UUID but different price
if ($itemUuid !== null && isset($processed_item_uuids[$itemUuid])) {
    continue; // Skipped!
}
```

### Solution

Changed deduplication to include both `itemUuid` AND `price`:

```php
// NEW: Check UUID + price - allows dual pricing (same item, different price)
$itemPrice = $items_json[$j]->{"orderItem"}->{"price"} ?? 0;
$dedupeKey = $itemUuid . '_' . $itemPrice;

if ($itemUuid !== null && isset($processed_item_uuids[$dedupeKey])) {
    continue;
}
```

### Files Changed

- `json.php` - Lines 400-416 (deduplication logic in `processOrderItemsForOrder` function)
- `reconcile_orders.php` - Lines 757-777 (same deduplication fix)

---

## Data Correction Scripts

Two scripts were created to fix existing data:

### Script 1: `fix_split_payment_totals.php`

Fixes incorrect `orders.total` for split payment orders.

```bash
# Preview changes (dry run)
php fix_split_payment_totals.php --dry-run --back-to=2026-01-01

# Fix all orders from today back to specified date
php fix_split_payment_totals.php --back-to=2026-01-01
```

**How it works:**
1. Finds orders where `orders.total` doesn't match `SUM(ordersPayments.amtPaid)`
2. Looks up correct total from raw JSON data
3. Updates `orders.total` with the correct value

### Script 2: `fix_dual_pricing_items.php`

Fixes missing/duplicate order items caused by dual pricing deduplication bug.

```bash
# Preview changes (dry run)
php fix_dual_pricing_items.php --dry-run --back-to=2026-01-01

# Fix all orders from today back to specified date
php fix_dual_pricing_items.php --back-to=2026-01-01
```

**How it works:**
1. Finds orders with multiple payments (potential dual pricing)
2. Compares items in raw JSON vs `orderItems` table (keyed by UUID + price)
3. Inserts missing items (existed in JSON but not DB)
4. Deletes duplicate items (more in DB than expected from JSON)

### Parameters (both scripts)

| Parameter | Default | Description |
|-----------|---------|-------------|
| `--back-to=DATE` | `2025-01-01` | Process orders back to this date (YYYY-MM-DD) |
| `--batch=N` | `100` | Number of orders per batch |
| `--dry-run` | - | Preview only, no database updates |

---

## Verification

### Before Fix

| Order | orders.total | SUM(payments) | Status |
|-------|-------------|---------------|--------|
| #676259 | $26.56 | $141.19 | WRONG |

### After Fix

| Order | orders.total | SUM(payments) | Status |
|-------|-------------|---------------|--------|
| #676259 | $141.19 | $141.19 | CORRECT |

---

## Related Issues

- Transaction count mismatch between merchant view and dashboard (split payments counted multiple times)
- Subtotal discrepancies in reports
- Modifier prices not calculated correctly (related to dual pricing)

---

## Testing Recommendations

1. Create a test order with split payment (cash + card)
2. Verify `orders.total` equals sum of all payments
3. With dual pricing enabled, add items after first payment
4. Verify all items (at both cash and card prices) are saved to `orderItems`
