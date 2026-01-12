---
parent: ./plan.md
status: pending
priority: P1
effort: 30min
---

# Phase 1: Database Verification

## Overview

Verify database prerequisites for V3 optimization: composite index and generated columns.

## Context

V3 query relies on:
1. **Composite index** on `(lastmod, status, trans_type, trans_id)` for range scan + filtering
2. **Generated columns** (`trans_type`, `status`, `amount`) for avoiding JSON parsing

## Requirements

### 1.1 Verify Composite Index Exists

**Query:**
```sql
SELECT COUNT(*) AS index_exists
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'unique_olapay_transactions'
AND INDEX_NAME = 'idx_uot_lastmod_status_type_transid';
```

**Expected:** `index_exists = 1`

**If missing, create:**
```sql
CREATE INDEX `idx_uot_lastmod_status_type_transid`
ON `unique_olapay_transactions`(`lastmod`, `status`, `trans_type`, `trans_id`);
```

**Risk:** Index creation on large table may take 5-30 minutes. Run during low-traffic.

### 1.2 Verify Generated Columns Populated

**Query:**
```sql
SELECT
    COUNT(*) as total_rows,
    SUM(CASE WHEN trans_type IS NULL THEN 1 ELSE 0 END) as null_trans_type,
    SUM(CASE WHEN amount IS NULL THEN 1 ELSE 0 END) as null_amount,
    SUM(CASE WHEN status IS NULL THEN 1 ELSE 0 END) as null_status
FROM unique_olapay_transactions
WHERE lastmod > UNIX_TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 30 DAY));
```

**Expected:** All null counts = 0

**If issues found:**
```sql
-- Rebuild table to regenerate stored columns (CAUTION: SLOW on large tables)
ALTER TABLE unique_olapay_transactions ENGINE=InnoDB;
```

### 1.3 Update Table Statistics

```sql
ANALYZE TABLE unique_olapay_transactions;
ANALYZE TABLE terminals;
ANALYZE TABLE accounts;
ANALYZE TABLE terminal_payment_methods;
ANALYZE TABLE payment_methods;
```

## Implementation Steps

- [ ] Connect to production database
- [ ] Run index verification query (1.1)
- [ ] If missing, create index during maintenance window
- [ ] Run generated columns verification query (1.2)
- [ ] Run ANALYZE TABLE commands (1.3)
- [ ] Document results

## Success Criteria

- [ ] Composite index exists on `unique_olapay_transactions`
- [ ] Generated columns have <0.1% NULL values
- [ ] Table statistics updated

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| Index creation slow | Med | Run during off-hours |
| Generated columns missing data | High | May need table rebuild |
| Lock contention during ANALYZE | Low | Runs quickly |

## Related Files

- `db/indexes/dashboardtopmerchantsolapay_performance_analysis.sql`
