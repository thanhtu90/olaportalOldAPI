-- =============================================================================
-- PERFORMANCE ANALYSIS FOR dashboardtopmerchantsolapay.php API
-- =============================================================================
-- Issue: API response > 1 minute for date range 2026-01-04 to 2026-01-10
-- Root causes and solutions documented below
-- =============================================================================

-- =====================
-- 1. CHECK TABLE SIZE
-- =====================
SELECT 
    TABLE_NAME,
    TABLE_ROWS,
    ROUND(DATA_LENGTH / 1024 / 1024, 2) AS data_size_mb,
    ROUND(INDEX_LENGTH / 1024 / 1024, 2) AS index_size_mb,
    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) AS total_size_mb
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'unique_olapay_transactions';

-- =====================
-- 2. CHECK EXISTING INDEXES
-- =====================
SHOW INDEX FROM `unique_olapay_transactions`;

-- =====================
-- 3. ANALYZE ORIGINAL QUERY (SLOW)
-- =====================
-- The original query uses JSON_EXTRACT which:
-- - Cannot use indexes
-- - Parses JSON for EVERY row
-- - Very CPU intensive

-- EXPLAIN the original slow query pattern:
EXPLAIN ANALYZE
SELECT DISTINCT
    accounts.id,
    accounts.companyname AS business,
    COUNT(DISTINCT JSON_EXTRACT(uot.content, '$.trans_id')) AS transactions
FROM unique_olapay_transactions uot
JOIN terminals ON terminals.serial = uot.serial
JOIN accounts ON terminals.vendors_id = accounts.id
WHERE uot.lastmod > UNIX_TIMESTAMP('2026-01-04')
AND uot.lastmod < UNIX_TIMESTAMP('2026-01-11')
AND JSON_UNQUOTE(JSON_EXTRACT(uot.content, '$.Status')) NOT IN ('', 'FAIL', 'REFUNDED')
AND JSON_UNQUOTE(JSON_EXTRACT(uot.content, '$.trans_type')) NOT IN ('Return Cash', '', 'Auth')
GROUP BY accounts.id
LIMIT 10;

-- =====================
-- 4. ANALYZE OPTIMIZED QUERY (FAST)
-- =====================
-- Uses generated columns which are:
-- - Pre-computed and stored
-- - Indexed
-- - No JSON parsing at query time

EXPLAIN ANALYZE
SELECT 
    accounts.id,
    accounts.companyname AS business,
    COUNT(DISTINCT uot.trans_id) AS transactions,
    SUM(CASE WHEN uot.trans_type IN ('Refund', 'Return') THEN uot.amount ELSE 0 END) AS refund,
    SUM(CASE WHEN uot.trans_id IS NOT NULL THEN uot.amount ELSE 0 END) 
        - SUM(CASE WHEN uot.trans_type IN ('Refund', 'Return') THEN uot.amount ELSE 0 END) AS amount
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
ORDER BY amount DESC
LIMIT 10;

-- =====================
-- 5. RECOMMENDED INDEXES
-- =====================
-- Create composite index optimized for this query pattern:

-- Check if index exists
SELECT COUNT(*) AS index_exists
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
AND TABLE_NAME = 'unique_olapay_transactions'
AND INDEX_NAME = 'idx_uot_lastmod_status_type_transid';

-- Create optimized index if not exists
-- This index covers: lastmod range scan + status/trans_type filtering + trans_id for aggregation
CREATE INDEX IF NOT EXISTS `idx_uot_lastmod_status_type_transid` 
ON `unique_olapay_transactions`(`lastmod`, `status`, `trans_type`, `trans_id`);

-- =====================
-- 6. ANALYZE TABLE TO UPDATE STATISTICS
-- =====================
ANALYZE TABLE unique_olapay_transactions;
ANALYZE TABLE terminals;
ANALYZE TABLE accounts;

-- =====================
-- 7. COUNT RECORDS IN DATE RANGE
-- =====================
-- This shows how many records the query needs to process
SELECT 
    COUNT(*) as total_records,
    COUNT(DISTINCT serial) as unique_terminals,
    MIN(FROM_UNIXTIME(lastmod)) as min_date,
    MAX(FROM_UNIXTIME(lastmod)) as max_date
FROM unique_olapay_transactions
WHERE lastmod > UNIX_TIMESTAMP('2026-01-04')
AND lastmod < UNIX_TIMESTAMP('2026-01-11');

-- =====================
-- 8. CHECK GENERATED COLUMNS DATA
-- =====================
SELECT 
    status,
    COUNT(*) as count
FROM unique_olapay_transactions
WHERE lastmod > UNIX_TIMESTAMP('2026-01-04')
AND lastmod < UNIX_TIMESTAMP('2026-01-11')
GROUP BY status;

SELECT 
    trans_type,
    COUNT(*) as count
FROM unique_olapay_transactions
WHERE lastmod > UNIX_TIMESTAMP('2026-01-04')
AND lastmod < UNIX_TIMESTAMP('2026-01-11')
GROUP BY trans_type;

-- =====================
-- 9. PERFORMANCE COMPARISON
-- =====================
-- Run both queries and compare execution times:

SET @start_time = NOW(6);

-- Original slow query (commented out to prevent accidental execution)
-- Uncomment to test:
/*
SELECT DISTINCT
    accounts.id,
    accounts.companyname AS business,
    COUNT(DISTINCT JSON_EXTRACT(uot.content, '$.trans_id')) AS transactions
FROM unique_olapay_transactions uot
JOIN terminals ON terminals.serial = uot.serial
JOIN accounts ON terminals.vendors_id = accounts.id
WHERE uot.lastmod > UNIX_TIMESTAMP('2026-01-04')
AND uot.lastmod < UNIX_TIMESTAMP('2026-01-11')
AND JSON_UNQUOTE(JSON_EXTRACT(uot.content, '$.Status')) NOT IN ('', 'FAIL', 'REFUNDED')
AND JSON_UNQUOTE(JSON_EXTRACT(uot.content, '$.trans_type')) NOT IN ('Return Cash', '', 'Auth')
GROUP BY accounts.id
ORDER BY COUNT(DISTINCT JSON_EXTRACT(uot.content, '$.trans_id')) DESC
LIMIT 10;
*/

SET @end_time = NOW(6);
SELECT TIMESTAMPDIFF(MICROSECOND, @start_time, @end_time) / 1000000 AS original_query_seconds;

SET @start_time = NOW(6);

-- Optimized query
SELECT 
    accounts.id,
    accounts.companyname AS business,
    COUNT(DISTINCT uot.trans_id) AS transactions,
    SUM(CASE WHEN uot.trans_type IN ('Refund', 'Return') THEN uot.amount ELSE 0 END) AS refund,
    SUM(CASE WHEN uot.trans_id IS NOT NULL THEN uot.amount ELSE 0 END) 
        - SUM(CASE WHEN uot.trans_type IN ('Refund', 'Return') THEN uot.amount ELSE 0 END) AS amount
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
ORDER BY amount DESC
LIMIT 10;

SET @end_time = NOW(6);
SELECT TIMESTAMPDIFF(MICROSECOND, @start_time, @end_time) / 1000000 AS optimized_query_seconds;
