-- =====================================================
-- AGGRESSIVE QUERY OPTIMIZATION
-- Problem: ordersPayments still doing full table scan (369,467 rows)
-- =====================================================

-- =====================================================
-- SOLUTION 1: Pre-filter ordersPayments by terminal (RECOMMENDED)
-- =====================================================

-- This dramatically reduces the subquery scope
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
    WHERE t.serial = 'WPYB002345000033'  -- Pre-filter by serial FIRST
      AND op.olapayApprovalId = u.trans_id
      AND op.olapayApprovalId IS NOT NULL
      AND op.olapayApprovalId != ''
  )
ORDER BY u.lastmod DESC;

-- =====================================================
-- SOLUTION 2: Use IN clause instead of NOT EXISTS (FASTER)
-- =====================================================

-- Get the terminal ID first, then use IN clause
SELECT u.lastmod, u.content
FROM unique_olapay_transactions u
WHERE u.serial = 'WPYB002345000033'
  AND u.lastmod > 1760770800
  AND u.lastmod < 1760857200
  AND u.status NOT IN ('', 'FAIL', 'REFUNDED')
  AND u.trans_type NOT IN ('Return Cash', '', 'Auth')
  AND u.trans_id NOT IN (
    SELECT op.olapayApprovalId
    FROM ordersPayments op
    JOIN terminals t ON t.id = op.terminals_id
    WHERE t.serial = 'WPYB002345000033'
      AND op.olapayApprovalId IS NOT NULL
      AND op.olapayApprovalId != ''
  )
ORDER BY u.lastmod DESC;

-- =====================================================
-- SOLUTION 3: Two-step approach (MOST EFFICIENT)
-- =====================================================

-- Step 1: Get terminal ID
-- SELECT id FROM terminals WHERE serial = 'WPYB002345000033';

-- Step 2: Use the terminal ID directly (replace TERMINAL_ID with actual ID)
SELECT u.lastmod, u.content
FROM unique_olapay_transactions u
WHERE u.serial = 'WPYB002345000033'
  AND u.lastmod > 1760770800
  AND u.lastmod < 1760857200
  AND u.status NOT IN ('', 'FAIL', 'REFUNDED')
  AND u.trans_type NOT IN ('Return Cash', '', 'Auth')
  AND u.trans_id NOT IN (
    SELECT olapayApprovalId
    FROM ordersPayments
    WHERE terminals_id = TERMINAL_ID  -- Replace with actual terminal ID
      AND olapayApprovalId IS NOT NULL
      AND olapayApprovalId != ''
  )
ORDER BY u.lastmod DESC;

-- =====================================================
-- SOLUTION 4: Create composite index for ordersPayments
-- =====================================================

-- This index will help with the JOIN condition
CREATE INDEX `idx_orderspayments_terminals_olapay` 
ON `ordersPayments`(`terminals_id`, `olapayApprovalId`);

-- =====================================================
-- SOLUTION 5: Alternative LEFT JOIN approach
-- =====================================================

-- This might be faster than NOT EXISTS
SELECT u.lastmod, u.content
FROM unique_olapay_transactions u
LEFT JOIN (
    SELECT DISTINCT op.olapayApprovalId
    FROM ordersPayments op
    JOIN terminals t ON t.id = op.terminals_id
    WHERE t.serial = 'WPYB002345000033'
      AND op.olapayApprovalId IS NOT NULL
      AND op.olapayApprovalId != ''
) excluded ON excluded.olapayApprovalId = u.trans_id
WHERE u.serial = 'WPYB002345000033'
  AND u.lastmod > 1760770800
  AND u.lastmod < 1760857200
  AND u.status NOT IN ('', 'FAIL', 'REFUNDED')
  AND u.trans_type NOT IN ('Return Cash', '', 'Auth')
  AND excluded.olapayApprovalId IS NULL
ORDER BY u.lastmod DESC;

-- =====================================================
-- IMMEDIATE ACTION PLAN
-- =====================================================

/*
1. Try SOLUTION 1 first (pre-filter by serial)
2. If still slow, try SOLUTION 2 (IN clause)
3. Create the composite index in SOLUTION 4
4. For maximum performance, use SOLUTION 3 (two-step approach)

The key insight: We need to reduce the scope of the ordersPayments scan
from 369,467 rows to just the rows for this specific terminal.
*/

