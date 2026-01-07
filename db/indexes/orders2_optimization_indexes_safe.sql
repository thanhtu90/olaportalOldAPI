-- =====================================================
-- Database Indexes for orders2.php Optimization (Safe Version)
-- This version checks if indexes exist before creating them
-- Use this if you're unsure whether indexes already exist
-- =====================================================

-- Helper: Drop indexes if they exist, then create them
-- This ensures the script can be run multiple times safely

-- PRIORITY 1: Composite indexes on orders table
DROP INDEX IF EXISTS `idx_orders_lastmod_agents` ON `orders`;
CREATE INDEX `idx_orders_lastmod_agents` 
ON `orders`(`lastMod`, `agents_id`);

DROP INDEX IF EXISTS `idx_orders_lastmod_vendors` ON `orders`;
CREATE INDEX `idx_orders_lastmod_vendors` 
ON `orders`(`lastMod`, `vendors_id`);

DROP INDEX IF EXISTS `idx_orders_lastmod_terminals` ON `orders`;
CREATE INDEX `idx_orders_lastmod_terminals` 
ON `orders`(`lastMod`, `terminals_id`);

DROP INDEX IF EXISTS `idx_orders_lastmod` ON `orders`;
CREATE INDEX `idx_orders_lastmod` 
ON `orders`(`lastMod`);

DROP INDEX IF EXISTS `idx_orders_uuid_lastmod` ON `orders`;
CREATE INDEX `idx_orders_uuid_lastmod` 
ON `orders`(`uuid`, `lastMod` DESC);

-- PRIORITY 3: Index on orderItems
DROP INDEX IF EXISTS `idx_orderitems_ordersid` ON `orderItems`;
CREATE INDEX `idx_orderitems_ordersid` 
ON `orderItems`(`orders_id`);

-- PRIORITY 4 & 5: Indexes on ordersPayments
DROP INDEX IF EXISTS `idx_orderspayments_orderref` ON `ordersPayments`;
CREATE INDEX `idx_orderspayments_orderref` 
ON `ordersPayments`(`orderReference`);

DROP INDEX IF EXISTS `idx_orderspayments_orderref_uuid_lastmod` ON `ordersPayments`;
CREATE INDEX `idx_orderspayments_orderref_uuid_lastmod` 
ON `ordersPayments`(`orderReference`, `paymentUuid`, `lastMod` DESC);

-- PRIORITY 6: Index on terminals (usually not needed as PRIMARY KEY exists)
-- Uncomment only if you need an explicit index
-- DROP INDEX IF EXISTS `idx_terminals_id` ON `terminals`;
-- CREATE INDEX `idx_terminals_id` ON `terminals`(`id`);

-- =====================================================
-- Verification Queries (run after creating indexes)
-- =====================================================

-- Check if indexes were created:
-- SHOW INDEX FROM `orders` WHERE Key_name LIKE 'idx_orders%';
-- SHOW INDEX FROM `orderItems` WHERE Key_name LIKE 'idx_orderitems%';
-- SHOW INDEX FROM `ordersPayments` WHERE Key_name LIKE 'idx_orderspayments%';
-- SHOW INDEX FROM `terminals` WHERE Key_name LIKE 'idx_terminals%';

