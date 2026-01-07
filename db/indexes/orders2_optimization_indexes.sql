-- =====================================================
-- Database Indexes for orders2.php Optimization
-- These indexes are critical for sub-2-second response times
-- =====================================================
-- 
-- NOTE: If an index already exists, you'll get an error.
-- To check existing indexes, run:
-- SHOW INDEX FROM `orders`;
-- SHOW INDEX FROM `orderItems`;
-- SHOW INDEX FROM `ordersPayments`;
-- SHOW INDEX FROM `terminals`;
--
-- To drop an existing index before recreating:
-- DROP INDEX `index_name` ON `table_name`;
-- =====================================================

-- PRIORITY 1: Composite index on orders table for date range queries with filters
-- This covers the main WHERE clause: lastMod range + agent/merchant/terminal filter
CREATE INDEX `idx_orders_lastmod_agents` 
ON `orders`(`lastMod`, `agents_id`);

CREATE INDEX `idx_orders_lastmod_vendors` 
ON `orders`(`lastMod`, `vendors_id`);

CREATE INDEX `idx_orders_lastmod_terminals` 
ON `orders`(`lastMod`, `terminals_id`);

-- For site-wide queries (no filter), we still need lastMod index
CREATE INDEX `idx_orders_lastmod` 
ON `orders`(`lastMod`);

-- PRIORITY 2: Index for UUID deduplication (used in ORDER BY)
CREATE INDEX `idx_orders_uuid_lastmod` 
ON `orders`(`uuid`, `lastMod` DESC);

-- PRIORITY 3: Index on orderItems for discount aggregation
-- This speeds up the SUM(discount * qty) query
CREATE INDEX `idx_orderitems_ordersid` 
ON `orderItems`(`orders_id`);

-- PRIORITY 4: Composite index on ordersPayments for bulk payment fetching
-- This optimizes the WHERE orderReference IN (...) query
CREATE INDEX `idx_orderspayments_orderref` 
ON `ordersPayments`(`orderReference`);

-- PRIORITY 5: Index for payment deduplication by UUID
CREATE INDEX `idx_orderspayments_orderref_uuid_lastmod` 
ON `ordersPayments`(`orderReference`, `paymentUuid`, `lastMod` DESC);

-- PRIORITY 6: Index on terminals for JOIN (should already exist, but ensure it does)
-- Note: PRIMARY KEY already covers this, but creating explicit index for clarity
CREATE INDEX `idx_terminals_id` 
ON `terminals`(`id`);

-- =====================================================
-- Verification Queries (run after creating indexes)
-- =====================================================

-- Check if indexes were created:
-- SHOW INDEX FROM `orders` WHERE Key_name LIKE 'idx_orders%';
-- SHOW INDEX FROM `orderItems` WHERE Key_name LIKE 'idx_orderitems%';
-- SHOW INDEX FROM `ordersPayments` WHERE Key_name LIKE 'idx_orderspayments%';
-- SHOW INDEX FROM `terminals` WHERE Key_name LIKE 'idx_terminals%';

-- =====================================================
-- Performance Notes:
-- =====================================================
-- 1. These indexes will significantly speed up:
--    - Date range filtering on orders (idx_orders_lastmod_*)
--    - UUID-based deduplication (idx_orders_uuid_lastmod)
--    - Discount aggregation (idx_orderitems_ordersid)
--    - Payment bulk fetching (idx_orderspayments_orderref)
--    - Payment deduplication (idx_orderspayments_orderref_uuid_lastmod)
--
-- 2. Index maintenance overhead is minimal compared to query performance gains
--
-- 3. For very large datasets, consider partitioning the orders table by lastMod
--    if performance still doesn't meet requirements

