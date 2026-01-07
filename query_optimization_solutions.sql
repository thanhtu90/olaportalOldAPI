-- =====================================================
-- Query Optimization Solutions for 120s+ Query Time
-- Problem: CONVERT() operations preventing index usage
-- =====================================================

-- =====================================================
-- SOLUTION 1: Query Rewrite with LEFT JOIN (RECOMMENDED)
-- =====================================================

-- This eliminates the expensive NOT EXISTS and CONVERT operations
SELECT u.lastmod, u.content
FROM unique_olapay_transactions u
LEFT JOIN ordersPayments op ON op.olapayApprovalId = u.trans_id
LEFT JOIN terminals t ON t.id = op.terminals_id AND t.serial = u.serial
WHERE u.serial = 'WPYB002345000033'
  AND u.lastmod > 1760770800
  AND u.lastmod < 1760857200
  AND u.status NOT IN ('', 'FAIL', 'REFUNDED')
  AND u.trans_type NOT IN ('Return Cash', '', 'Auth')
  AND op.id IS NULL  -- This replaces NOT EXISTS
ORDER BY u.lastmod DESC;

-- =====================================================
-- SOLUTION 2: Create Specialized Indexes for CONVERT Operations
-- =====================================================

-- Index for ordersPayments with utf8mb4 collation
CREATE INDEX `idx_orderspayments_olapay_utf8mb4` 
ON `ordersPayments`(`olapayApprovalId`(50)) 
COLLATE utf8mb4_general_ci;

-- Index for terminals with utf8mb4 collation
CREATE INDEX `idx_terminals_serial_utf8mb4` 
ON `terminals`(`serial`(50)) 
COLLATE utf8mb4_general_ci;

-- =====================================================
-- SOLUTION 3: Alternative Query Structure
-- =====================================================

-- Use INNER JOIN with exclusion logic
SELECT u.lastmod, u.content
FROM unique_olapay_transactions u
WHERE u.serial = 'WPYB002345000033'
  AND u.lastmod > 1760770800
  AND u.lastmod < 1760857200
  AND u.status NOT IN ('', 'FAIL', 'REFUNDED')
  AND u.trans_type NOT IN ('Return Cash', '', 'Auth')
  AND u.trans_id NOT IN (
    SELECT DISTINCT op.olapayApprovalId
    FROM ordersPayments op
    JOIN terminals t ON t.id = op.terminals_id
    WHERE t.serial = 'WPYB002345000033'
      AND op.olapayApprovalId IS NOT NULL
      AND op.olapayApprovalId != ''
  )
ORDER BY u.lastmod DESC;

-- =====================================================
-- SOLUTION 4: Pre-filter the subquery (MOST EFFICIENT)
-- =====================================================

-- This reduces the subquery scope dramatically
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
    WHERE t.serial = 'WPYB002345000033'  -- Pre-filter by serial
      AND op.olapayApprovalId = u.trans_id  -- Remove CONVERT operations
      AND op.olapayApprovalId IS NOT NULL
      AND op.olapayApprovalId != ''
  )
ORDER BY u.lastmod DESC;

-- =====================================================
-- SOLUTION 5: Database Schema Fix (LONG-TERM SOLUTION)
-- =====================================================

-- Ensure all tables use the same collation to avoid CONVERT operations
-- This requires schema changes:

-- ALTER TABLE ordersPayments MODIFY olapayApprovalId VARCHAR(255) COLLATE utf8mb4_general_ci;
-- ALTER TABLE terminals MODIFY serial VARCHAR(255) COLLATE utf8mb4_general_ci;
-- ALTER TABLE unique_olapay_transactions MODIFY trans_id VARCHAR(255) COLLATE utf8mb4_general_ci;
-- ALTER TABLE unique_olapay_transactions MODIFY serial VARCHAR(255) COLLATE utf8mb4_general_ci;

-- =====================================================
-- PERFORMANCE TESTING QUERIES
-- =====================================================

-- Test each solution with EXPLAIN:
-- EXPLAIN [SOLUTION_QUERY_HERE];

-- =====================================================
-- IMMEDIATE ACTION PLAN
-- =====================================================

/*
1. Try SOLUTION 4 first (pre-filtered subquery) - should be fastest
2. If that doesn't work, try SOLUTION 1 (LEFT JOIN rewrite)
3. If still slow, implement SOLUTION 2 (specialized indexes)
4. For long-term, consider SOLUTION 5 (schema collation fix)

Expected performance improvement: 10-100x faster
*/

