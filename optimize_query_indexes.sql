-- =====================================================
-- Query Optimization Indexes - PRIORITY ORDER
-- Based on EXPLAIN analysis showing full table scan on ordersPayments
-- =====================================================

-- **CRITICAL PRIORITY 1**: Fix the full table scan on ordersPayments (369,462 rows!)
-- This will provide the biggest performance improvement
CREATE INDEX IF NOT EXISTS `idx_orderspayments_olapay_approval_id` 
ON `ordersPayments`(`olapayApprovalId`);

-- **PRIORITY 2**: Optimize the main query filtering and sorting
-- This will eliminate "Using temporary; Using filesort"
CREATE INDEX IF NOT EXISTS `idx_uot_serial_lastmod_status_type` 
ON `unique_olapay_transactions`(`serial`, `lastmod`, `status`, `trans_type`);

-- **PRIORITY 3**: Optimize terminals lookup (already efficient but can be better)
CREATE INDEX IF NOT EXISTS `idx_terminals_serial_id` 
ON `terminals`(`serial`, `id`);

-- **PRIORITY 4**: Alternative composite index for better range query performance
-- This optimizes the serial + lastmod range query first
CREATE INDEX IF NOT EXISTS `idx_uot_serial_lastmod_desc` 
ON `unique_olapay_transactions`(`serial`, `lastmod` DESC);

-- **PRIORITY 5**: Index for the NOT EXISTS subquery optimization
-- This helps with the trans_id lookups in the subquery
CREATE INDEX IF NOT EXISTS `idx_uot_trans_id_serial` 
ON `unique_olapay_transactions`(`trans_id`, `serial`);

-- **PRIORITY 6**: Composite index for ordersPayments JOIN with terminals
CREATE INDEX IF NOT EXISTS `idx_orderspayments_terminals_olapay` 
ON `ordersPayments`(`terminals_id`, `olapayApprovalId`);

-- =====================================================
-- Additional Performance Optimizations
-- =====================================================

-- 7. If you frequently query by status and trans_type combinations,
-- create a covering index that includes the content field
CREATE INDEX IF NOT EXISTS `idx_uot_covering_query` 
ON `unique_olapay_transactions`(`serial`, `lastmod`, `status`, `trans_type`, `trans_id`, `content`(100));

-- =====================================================
-- Query Rewrite Suggestions
-- =====================================================

-- Alternative query structure that might perform better:
-- Consider using LEFT JOIN instead of NOT EXISTS for better performance
/*
SELECT u.lastmod, u.content
FROM unique_olapay_transactions u
LEFT JOIN ordersPayments op ON CONVERT(op.olapayApprovalId USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(u.trans_id USING utf8mb4) COLLATE utf8mb4_general_ci
LEFT JOIN terminals t ON t.id = op.terminals_id AND CONVERT(t.serial USING utf8mb4) COLLATE utf8mb4_general_ci = CONVERT(u.serial USING utf8mb4) COLLATE utf8mb4_general_ci
WHERE u.serial = ?
  AND u.lastmod > ?
  AND u.lastmod < ?
  AND u.status NOT IN ('', 'FAIL', 'REFUNDED')
  AND u.trans_type NOT IN ('Return Cash', '', 'Auth')
  AND op.id IS NULL
ORDER BY u.lastmod DESC;
*/

-- =====================================================
-- Index Usage Verification
-- =====================================================

-- Use EXPLAIN to verify index usage:
-- EXPLAIN SELECT u.lastmod, u.content
-- FROM unique_olapay_transactions u
-- WHERE u.serial = 'your_serial'
--   AND u.lastmod > 1640995200
--   AND u.lastmod < 1672531200
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
