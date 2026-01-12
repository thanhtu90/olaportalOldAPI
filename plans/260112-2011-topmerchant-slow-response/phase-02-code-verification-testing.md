---
parent: ./plan.md
status: pending
priority: P1
effort: 45min
---

# Phase 2: Code Verification & Testing

## Overview

Validate V3 implementation correctness and measure performance improvement.

## Context

V3 code already exists in `dashboardtopmerchantsolapay.php` with:
- Generated columns instead of `JSON_EXTRACT`
- `EXISTS`/`NOT EXISTS` instead of separate query + `IN` clause
- Single query instead of two round-trips

## Requirements

### 2.1 Code Verification Checklist

**File:** `dashboardtopmerchantsolapay.php`

- [ ] Line 2: Comment shows "V3 - OPTIMIZED"
- [ ] Line 72: Uses `uot.trans_id` (not `JSON_EXTRACT`)
- [ ] Line 99: Uses `uot.status` (not `JSON_EXTRACT`)
- [ ] Line 101: Uses `uot.trans_type` (not `JSON_EXTRACT`)
- [ ] Lines 106-117: Uses `EXISTS`/`NOT EXISTS` subqueries
- [ ] Line 119: `GROUP BY accounts.id, accounts.companyname` (no DISTINCT)

**Grep verification:**
```bash
# Should return 0 matches in the main query section
grep -n "JSON_EXTRACT" dashboardtopmerchantsolapay.php
```

### 2.2 Query Plan Verification

Run EXPLAIN ANALYZE to verify index usage:

```sql
EXPLAIN ANALYZE
SELECT
    accounts.id,
    accounts.companyname AS business,
    COUNT(DISTINCT uot.trans_id) AS transactions
FROM unique_olapay_transactions uot
INNER JOIN terminals ON terminals.serial = uot.serial
INNER JOIN accounts ON terminals.vendors_id = accounts.id
WHERE uot.lastmod > UNIX_TIMESTAMP('2026-01-04')
AND uot.lastmod < UNIX_TIMESTAMP('2026-01-11')
AND uot.status NOT IN ('', 'FAIL', 'REFUNDED')
AND uot.trans_type NOT IN ('Return Cash', '', 'Auth')
AND uot.trans_id IS NOT NULL
AND uot.trans_id != ''
GROUP BY accounts.id, accounts.companyname
ORDER BY COUNT(DISTINCT uot.trans_id) DESC
LIMIT 10;
```

**Expected:** Plan shows "Using index" on `idx_uot_lastmod_status_type_transid`

### 2.3 Performance Testing

**Test Scenarios:**

| Scenario | Date Range | Target Time |
|----------|------------|-------------|
| Reported issue | 2026-01-04 to 2026-01-10 | <10s |
| Last 24 Hours | Yesterday to now | <3s |
| Last 30 Days | 30 days ago to now | <15s |

**Test Commands:**
```bash
# Local test
curl -s -o /dev/null -w "%{time_total}s" \
  "http://localhost/dashboardtopmerchantsolapay.php?datetype=Custom&fromDate=2026-01-04&toDate=2026-01-10&type=site"

# Production test (after deployment)
curl -s -o /dev/null -w "%{time_total}s" \
  "https://api.example.com/dashboardtopmerchantsolapay.php?datetype=Custom&fromDate=2026-01-04&toDate=2026-01-10&type=site"
```

### 2.4 Data Accuracy Validation

Compare V2 vs V3 results for same date range (should match):

```sql
-- Run V2 query (from backup) and V3 query
-- Compare top 10 merchants, transaction counts, amounts
```

## Implementation Steps

- [ ] Verify V3 code matches expected optimizations (2.1)
- [ ] Run EXPLAIN ANALYZE to confirm index usage (2.2)
- [ ] Execute performance tests for all scenarios (2.3)
- [ ] Validate data accuracy against V2 baseline (2.4)
- [ ] Document test results

## Success Criteria

- [ ] No `JSON_EXTRACT` in production query
- [ ] Query plan shows index usage
- [ ] Response time <10s for 7-day range
- [ ] Data matches V2 output (same merchants, amounts)

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| Data mismatch V2 vs V3 | High | Compare before deploy |
| Index not being used | Med | Check EXPLAIN, verify stats |
| Edge case failures | Med | Test all date types |

## Related Files

- `dashboardtopmerchantsolapay.php` (main)
- `dashboardtopmerchantsolapay_optimized.php` (backup)
- `db/indexes/dashboardtopmerchantsolapay_performance_analysis.sql` (test queries)
