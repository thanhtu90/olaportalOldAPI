-- =====================================================
-- Database Indexes for olapayTerminalRecord.php Optimization
-- These indexes are critical for sub-5-second response times
-- =====================================================
-- 
-- NOTE: If an index already exists, you'll get an error.
-- To check existing indexes, run:
-- SHOW INDEX FROM `unique_olapay_transactions`;
--
-- To drop an existing index before recreating:
-- DROP INDEX `index_name` ON `table_name`;
-- =====================================================

-- PRIORITY 1: CRITICAL - Composite index optimized for the query pattern
-- This index covers: serial (equality) + lastmod (range + ORDER BY) + status + trans_type (filters)
-- Query pattern: WHERE serial = ? AND lastmod > ? AND lastmod < ? 
--                AND status NOT IN (...) AND trans_type NOT IN (...) 
--                ORDER BY lastmod DESC
CREATE INDEX IF NOT EXISTS `idx_uot_serial_lastmod_status_type` 
ON `unique_olapay_transactions`(`serial`, `lastmod`, `status`, `trans_type`);

-- PRIORITY 2: Alternative index for MySQL 8.0+ with DESC support for better ORDER BY performance
-- This is more optimal if your MySQL version supports descending indexes
-- Uncomment if you're using MySQL 8.0 or later:
-- CREATE INDEX IF NOT EXISTS `idx_uot_serial_lastmod_desc_status_type` 
-- ON `unique_olapay_transactions`(`serial`, `lastmod` DESC, `status`, `trans_type`);

-- PRIORITY 3: Ensure terminals table has proper indexes for the JOIN
-- This optimizes the terminal lookup queries
CREATE INDEX IF NOT EXISTS `idx_terminals_vendors_serial` 
ON `terminals`(`vendors_id`, `serial`);

CREATE INDEX IF NOT EXISTS `idx_terminals_serial_description` 
ON `terminals`(`serial`, `description`);

-- =====================================================
-- Verification Queries (run after creating indexes)
-- =====================================================

-- Check if indexes were created:
-- SHOW INDEX FROM `unique_olapay_transactions` WHERE Key_name = 'idx_uot_serial_lastmod_status_type';
-- SHOW INDEX FROM `terminals` WHERE Key_name LIKE 'idx_terminals%';

-- =====================================================
-- Performance Notes:
-- =====================================================
-- 1. The composite index `idx_uot_serial_lastmod_status_type` will:
--    - Enable fast lookups by serial number
--    - Optimize date range filtering on lastmod
--    - Support filtering on status and trans_type
--    - Help with ORDER BY lastmod DESC (MySQL can use index for sorting)
--
-- 2. Index column order matters:
--    - `serial` first (equality filter - most selective)
--    - `lastmod` second (range filter + ORDER BY)
--    - `status` and `trans_type` last (additional filters)
--
-- 3. This index should eliminate:
--    - Full table scans
--    - Filesort operations (if MySQL can use index for ORDER BY)
--    - Slow sequential scans

-- =====================================================
-- Expected Performance Improvement:
-- =====================================================
-- Before: 16+ seconds (full table scans or inefficient index usage)
-- After: < 2 seconds (index-optimized queries with parallel processing)

