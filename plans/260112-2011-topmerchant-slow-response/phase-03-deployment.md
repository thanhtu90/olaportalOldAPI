---
parent: ./plan.md
status: pending
priority: P1
effort: 45min
---

# Phase 3: Deployment

## Overview

Commit verified V3 changes, deploy to production, and monitor performance.

## Context

Prerequisites from Phase 1 & 2:
- Database index verified/created
- Generated columns verified
- V3 code tested and validated
- Performance improvement confirmed

## Requirements

### 3.1 Pre-Deployment Checklist

- [ ] Phase 1 completed (database ready)
- [ ] Phase 2 completed (code verified, tested)
- [ ] No other pending changes to same files
- [ ] Deployment window agreed (low traffic period)

### 3.2 Git Operations

```bash
# Check current status
git status

# Stage changes
git add dashboardtopmerchantsolapay.php
git add db/indexes/dashboardtopmerchantsolapay_performance_analysis.sql

# Commit with conventional commit message
git commit -m "perf(api): optimize dashboardtopmerchantsolapay.php query

- Replace JSON_EXTRACT with generated columns (trans_type, status, amount)
- Use EXISTS/NOT EXISTS instead of separate query + IN clause
- Remove redundant DISTINCT with GROUP BY
- Add composite index for query optimization

Reduces response time from >60s to <10s for 7-day date ranges.

Closes #[issue-number]"

# Push to remote
git push origin fix/topmerchant-slow-response
```

### 3.3 Production Deployment

**Deployment Steps:**

1. **Backup current production file:**
   ```bash
   ssh production "cp /path/to/dashboardtopmerchantsolapay.php /path/to/dashboardtopmerchantsolapay.php.bak"
   ```

2. **Deploy V3 code:**
   ```bash
   scp dashboardtopmerchantsolapay.php user@production:/path/to/api/
   ```

3. **Verify deployment:**
   ```bash
   ssh production "head -2 /path/to/dashboardtopmerchantsolapay.php"
   # Should show: // V3 - OPTIMIZED
   ```

### 3.4 Post-Deployment Monitoring

**Immediate (first 15 min):**
```bash
# Monitor error logs
ssh production "tail -f /var/log/apache2/error.log | grep dashboardtopmerchantsolapay"

# Test API response
curl -s -o /dev/null -w "%{time_total}s" \
  "https://api.example.com/dashboardtopmerchantsolapay.php?datetype=Custom&fromDate=2026-01-04&toDate=2026-01-10&type=site"
```

**Expected Results:**
- No PHP errors in logs
- Response time <10 seconds
- Data returned correctly

**Rollback Plan:**
```bash
# If issues found, restore backup immediately
ssh production "cp /path/to/dashboardtopmerchantsolapay.php.bak /path/to/dashboardtopmerchantsolapay.php"
```

### 3.5 Success Validation

Run these tests after deployment:

| Test | Command | Expected |
|------|---------|----------|
| Response time | curl timing | <10s |
| Error log | grep errors | None |
| Data accuracy | Compare to V2 | Match |
| All date types | Test each | Work |

## Implementation Steps

- [ ] Complete pre-deployment checklist (3.1)
- [ ] Commit and push changes (3.2)
- [ ] Create PR and get approval
- [ ] Deploy to production (3.3)
- [ ] Monitor for 15 minutes (3.4)
- [ ] Validate success criteria (3.5)
- [ ] Document deployment in changelog

## Success Criteria

- [ ] Code committed to git
- [ ] Deployed to production
- [ ] No errors in logs
- [ ] Response time <10s confirmed
- [ ] User validates improvement

## Risk Assessment

| Risk | Impact | Mitigation |
|------|--------|------------|
| Deployment failure | High | Backup + rollback plan |
| Production errors | High | Monitor logs, quick rollback |
| Performance regression | Med | Compare before/after metrics |

## Rollback Procedure

1. SSH to production server
2. Restore backup file
3. Clear any PHP opcode cache
4. Verify rollback successful
5. Investigate issue before retry

## Related Files

- `dashboardtopmerchantsolapay.php`
- `dashboardtopmerchantsolapay_optimized.php` (reference)
