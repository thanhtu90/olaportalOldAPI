-- =====================================================
-- Safe Index Creation Script (MySQL 5.7+ compatible)
-- =====================================================

-- Method 1: Check if index exists first, then create
-- Run these one by one to avoid errors

-- 1. CRITICAL - Fix the 369K row full table scan on ordersPayments
CREATE INDEX `idx_orderspayments_olapay_approval_id` 
ON `ordersPayments`(`olapayApprovalId`);

-- 2. HIGH IMPACT - Fix sorting and filtering on unique_olapay_transactions
CREATE INDEX `idx_uot_serial_lastmod_status_type` 
ON `unique_olapay_transactions`(`serial`, `lastmod`, `status`, `trans_type`);

-- 3. NICE TO HAVE - Optimize terminals lookup
CREATE INDEX `idx_terminals_serial_id` 
ON `terminals`(`serial`, `id`);

-- =====================================================
-- Alternative Method: Drop first, then create (if you want to be extra safe)
-- =====================================================

-- Uncomment these if you want to drop existing indexes first:
-- DROP INDEX IF EXISTS `idx_orderspayments_olapay_approval_id` ON `ordersPayments`;
-- DROP INDEX IF EXISTS `idx_uot_serial_lastmod_status_type` ON `unique_olapay_transactions`;
-- DROP INDEX IF EXISTS `idx_terminals_serial_id` ON `terminals`;

-- =====================================================
-- Check existing indexes before creating (optional)
-- =====================================================

-- Run this to see existing indexes:
-- SHOW INDEX FROM `unique_olapay_transactions`;
-- SHOW INDEX FROM `ordersPayments`;
-- SHOW INDEX FROM `terminals`;

-- =====================================================
-- Verify indexes were created successfully
-- =====================================================

-- After creating indexes, run this to verify:
-- SHOW INDEX FROM `unique_olapay_transactions` WHERE Key_name = 'idx_uot_serial_lastmod_status_type';
-- SHOW INDEX FROM `ordersPayments` WHERE Key_name = 'idx_orderspayments_olapay_approval_id';
-- SHOW INDEX FROM `terminals` WHERE Key_name = 'idx_terminals_serial_id';
