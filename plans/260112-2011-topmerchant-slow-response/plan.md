---
title: "Fix Top Merchants OlaPay API Slow Response"
description: "Deploy V3 optimization for dashboardtopmerchantsolapay.php reducing response from >60s to <10s"
status: pending
priority: P1
effort: 2h
branch: fix/topmerchant-slow-response
tags: [performance, database, api, optimization]
created: 2026-01-12
---

# Fix Top Merchants OlaPay API Slow Response

## Overview

**Problem:** `dashboardtopmerchantsolapay.php` API responds in >60 seconds for 7-day date ranges.

**Root Cause:** V2 used `JSON_EXTRACT` operations (8+ per row) which cannot use indexes and are CPU-intensive.

**Solution:** V3 already implemented using generated columns (`trans_type`, `status`, `amount`) instead of JSON parsing.

**Expected Improvement:** 10-25x faster (target <10s response)

## Current Status

| Component | Status |
|-----------|--------|
| V3 Code | Implemented (uncommitted) |
| Composite Index | Needs verification |
| Generated Columns | Needs verification |
| Production Deploy | Pending |

## Implementation Phases

### [Phase 1: Database Verification](./phase-01-database-verification.md)
**Status:** Pending | **Effort:** 30min

Verify index and generated columns exist and are populated correctly.

### [Phase 2: Code Verification & Testing](./phase-02-code-verification-testing.md)
**Status:** Pending | **Effort:** 45min

Validate V3 implementation and test performance.

### [Phase 3: Deployment](./phase-03-deployment.md)
**Status:** Pending | **Effort:** 45min

Commit, deploy, and monitor production performance.

## Key Files

| File | Purpose |
|------|---------|
| `dashboardtopmerchantsolapay.php` | Main API (V3 implemented) |
| `dashboardtopmerchantsolapay_optimized.php` | Reference backup |
| `db/indexes/dashboardtopmerchantsolapay_performance_analysis.sql` | Index/analysis scripts |

## Success Criteria

- [ ] API response <10 seconds for 7-day date range
- [ ] Composite index `idx_uot_lastmod_status_type_transid` exists
- [ ] No `JSON_EXTRACT` in WHERE/SELECT clauses
- [ ] Query uses index (verified via EXPLAIN)

## Related Documentation

- [Debug Report](../reports/debugger-260112-1954-dashboardtopmerchantsolapay-slow-response.md)
