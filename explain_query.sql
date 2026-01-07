-- =====================================================
-- EXPLAIN Query for Performance Analysis
-- Using actual parameter values from the log
-- =====================================================

EXPLAIN FORMAT=JSON
SELECT u.lastmod, u.content
FROM unique_olapay_transactions u
WHERE u.serial = 'WPYB002345000033'
  AND u.lastmod > 1760770800
  AND u.lastmod < 1760857200
  AND u.status NOT IN ('', 'FAIL', 'REFUNDED')
  AND u.trans_type NOT IN ('Return Cash', '', 'Auth')
  AND NOT EXISTS (
    SELECT 1
    FROM ordersPayments op
    JOIN terminals t ON t.id = op.terminals_id
    WHERE CONVERT(op.olapayApprovalId USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(u.trans_id USING utf8mb4) COLLATE utf8mb4_general_ci
      AND CONVERT(t.serial USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(u.serial USING utf8mb4) COLLATE utf8mb4_general_ci
  )
ORDER BY u.lastmod DESC;

-- =====================================================
-- Alternative EXPLAIN formats for different analysis needs
-- =====================================================

-- Standard EXPLAIN (easier to read)
EXPLAIN
SELECT u.lastmod, u.content
FROM unique_olapay_transactions u
WHERE u.serial = 'WPYB002345000033'
  AND u.lastmod > 1760770800
  AND u.lastmod < 1760857200
  AND u.status NOT IN ('', 'FAIL', 'REFUNDED')
  AND u.trans_type NOT IN ('Return Cash', '', 'Auth')
  AND NOT EXISTS (
    SELECT 1
    FROM ordersPayments op
    JOIN terminals t ON t.id = op.terminals_id
    WHERE CONVERT(op.olapayApprovalId USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(u.trans_id USING utf8mb4) COLLATE utf8mb4_general_ci
      AND CONVERT(t.serial USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(u.serial USING utf8mb4) COLLATE utf8mb4_general_ci
  )
ORDER BY u.lastmod DESC;

-- =====================================================
-- EXPLAIN ANALYZE (if supported by your MySQL version)
-- This will actually execute the query and show real performance metrics
-- =====================================================

-- EXPLAIN ANALYZE
-- SELECT u.lastmod, u.content
-- FROM unique_olapay_transactions u
-- WHERE u.serial = 'WPYB002345000033'
--   AND u.lastmod > 1760770800
--   AND u.lastmod < 1760857200
--   AND u.status NOT IN ('', 'FAIL', 'REFUNDED')
--   AND u.trans_type NOT IN ('Return Cash', '', 'Auth')
--   AND NOT EXISTS (
--     SELECT 1
--     FROM ordersPayments op
--     JOIN terminals t ON t.id = op.terminals_id
--     WHERE CONVERT(op.olapayApprovalId USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(u.trans_id USING utf8mb4) COLLATE utf8mb4_general_ci
--       AND CONVERT(t.serial USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(u.serial USING utf8mb4) COLLATE utf8mb4_general_ci
--   )
-- ORDER BY u.lastmod DESC;

-- =====================================================
-- What to look for in the EXPLAIN output:
-- =====================================================

/*
Key columns to analyze:

1. **type**: 
   - 'ALL' = Full table scan (BAD)
   - 'index' = Full index scan (OK)
   - 'range' = Index range scan (GOOD)
   - 'ref' = Index lookup (GOOD)
   - 'eq_ref' = Unique index lookup (BEST)

2. **key**: Which index is being used
   - NULL = No index used (BAD)
   - Index name = Good

3. **rows**: Estimated rows to examine
   - Lower is better

4. **Extra**: Additional information
   - 'Using filesort' = Sorting in memory/disk (SLOW)
   - 'Using temporary' = Creating temp table (SLOW)
   - 'Using index' = Covering index (GOOD)

5. **filtered**: Percentage of rows filtered by WHERE clause
   - Higher is better (closer to 100%)

Expected issues with current query:
- Full table scan on unique_olapay_transactions
- No index usage for the complex WHERE conditions
- Expensive string collation conversions in subquery
- Potential full table scans on ordersPayments and terminals

After adding the recommended indexes, you should see:
- 'range' or 'ref' type for unique_olapay_transactions
- Proper index usage in the 'key' column
- Lower 'rows' values
- 'Using index' in Extra column where possible
*/
