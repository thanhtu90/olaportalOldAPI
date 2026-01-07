-- =====================================================
-- OlaPay Data Consolidation Stored Procedures
-- Elite Performance Optimization for Dashboard
-- =====================================================

-- Drop existing procedures if they exist
DROP PROCEDURE IF EXISTS `ConsolidateHistoricalOlaPayStats`;
DROP PROCEDURE IF EXISTS `UpdateDailyOlaPayStats`;
DROP PROCEDURE IF EXISTS `RebuildOlaPayMerchantsRegistry`;

DELIMITER $$

-- =====================================================
-- Procedure: RebuildOlaPayMerchantsRegistry
-- Purpose: Rebuild the OlaPay merchants registry table
-- Usage: Called by historical consolidation and weekly rebuild
-- =====================================================
CREATE PROCEDURE `RebuildOlaPayMerchantsRegistry`()
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION 
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;
    
    -- Clear existing registry
    TRUNCATE TABLE `olapay_merchants_registry`;
    
    -- Rebuild registry with OlaPay-only merchants
    INSERT INTO `olapay_merchants_registry` (merchant_id, business_name, is_olapay_only, last_transaction_date)
    SELECT DISTINCT 
        a.id as merchant_id,
        a.companyname as business_name,
        TRUE as is_olapay_only,
        MAX(DATE(FROM_UNIXTIME(uot.lastmod))) as last_transaction_date
    FROM accounts a
    JOIN terminals t ON t.vendors_id = a.id
    JOIN terminal_payment_methods tpm ON tpm.terminal_id = t.id
    JOIN payment_methods pm ON pm.id = tpm.payment_method_id
    LEFT JOIN unique_olapay_transactions uot ON uot.serial = t.serial
    WHERE pm.code = 'olapay'
    AND a.id NOT IN (
        -- Exclude merchants that also have olapos payment method
        SELECT DISTINCT a2.id
        FROM accounts a2
        JOIN terminals t2 ON t2.vendors_id = a2.id
        JOIN terminal_payment_methods tpm2 ON tpm2.terminal_id = t2.id
        JOIN payment_methods pm2 ON pm2.id = tpm2.payment_method_id
        WHERE pm2.code = 'olapos'
    )
    GROUP BY a.id, a.companyname;
    
    COMMIT;
    
    -- Log the result
    SELECT CONCAT('OlaPay merchants registry rebuilt. Total merchants: ', ROW_COUNT()) as result;
END$$

-- =====================================================
-- Procedure: ConsolidateHistoricalOlaPayStats
-- Purpose: One-time historical data consolidation (2 years)
-- Usage: Run once during initial setup
-- =====================================================
CREATE PROCEDURE `ConsolidateHistoricalOlaPayStats`()
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION 
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;
    
    -- Step 1: Rebuild merchants registry
    CALL RebuildOlaPayMerchantsRegistry();
    
    -- Step 2: Clear existing historical stats
    TRUNCATE TABLE `merchant_daily_olapay_stats`;
    
    -- Step 3: Consolidate daily stats for past 2 years
    INSERT INTO `merchant_daily_olapay_stats` 
    (merchant_id, business_name, date, transaction_count, total_amount, refund_amount, net_amount)
    SELECT 
        omr.merchant_id,
        omr.business_name,
        DATE(FROM_UNIXTIME(uot.lastmod)) as date,
        COUNT(DISTINCT uot.trans_id) as transaction_count,
        SUM(
            CASE 
                WHEN uot.trans_id IS NOT NULL AND uot.trans_id != ''
                THEN uot.amount
                ELSE 0 
            END
        ) as total_amount,
        SUM(
            CASE 
                WHEN uot.trans_type IN ('Refund', 'Return')
                THEN uot.amount
                ELSE 0 
            END
        ) as refund_amount,
        SUM(
            CASE 
                WHEN uot.trans_id IS NOT NULL AND uot.trans_id != ''
                THEN uot.amount
                ELSE 0 
            END
        ) - SUM(
            CASE 
                WHEN uot.trans_type IN ('Refund', 'Return')
                THEN uot.amount
                ELSE 0 
            END
        ) as net_amount
    FROM olapay_merchants_registry omr
    JOIN terminals t ON t.vendors_id = omr.merchant_id
    JOIN unique_olapay_transactions uot ON uot.serial = t.serial
    WHERE uot.lastmod >= UNIX_TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 YEAR))
    AND uot.status NOT IN ('', 'FAIL', 'REFUNDED')
    AND uot.trans_type NOT IN ('Return Cash', '', 'Auth')
    AND uot.trans_id IS NOT NULL
    AND uot.trans_id != ''
    AND omr.status = 'active'
    GROUP BY omr.merchant_id, omr.business_name, DATE(FROM_UNIXTIME(uot.lastmod))
    HAVING transaction_count > 0
    ORDER BY date, omr.merchant_id;
    
    COMMIT;
    
    -- Log the result
    SELECT CONCAT('Historical consolidation completed. Total records: ', ROW_COUNT()) as result;
END$$

-- =====================================================
-- Procedure: UpdateDailyOlaPayStats
-- Purpose: Daily incremental updates for specific date
-- Usage: Called by cron job for previous day's data
-- =====================================================
CREATE PROCEDURE `UpdateDailyOlaPayStats`(IN target_date DATE)
BEGIN
    DECLARE affected_rows INT DEFAULT 0;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION 
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;
    
    -- Update/Insert daily stats for specified date
    INSERT INTO `merchant_daily_olapay_stats` 
    (merchant_id, business_name, date, transaction_count, total_amount, refund_amount, net_amount)
    SELECT 
        omr.merchant_id,
        omr.business_name,
        target_date as date,
        COUNT(DISTINCT uot.trans_id) as transaction_count,
        SUM(
            CASE 
                WHEN uot.trans_id IS NOT NULL AND uot.trans_id != ''
                THEN uot.amount
                ELSE 0 
            END
        ) as total_amount,
        SUM(
            CASE 
                WHEN uot.trans_type IN ('Refund', 'Return')
                THEN uot.amount
                ELSE 0 
            END
        ) as refund_amount,
        SUM(
            CASE 
                WHEN uot.trans_id IS NOT NULL AND uot.trans_id != ''
                THEN uot.amount
                ELSE 0 
            END
        ) - SUM(
            CASE 
                WHEN uot.trans_type IN ('Refund', 'Return')
                THEN uot.amount
                ELSE 0 
            END
        ) as net_amount
    FROM olapay_merchants_registry omr
    JOIN terminals t ON t.vendors_id = omr.merchant_id
    JOIN unique_olapay_transactions uot ON uot.serial = t.serial
    WHERE DATE(FROM_UNIXTIME(uot.lastmod)) = target_date
    AND uot.status NOT IN ('', 'FAIL', 'REFUNDED')
    AND uot.trans_type NOT IN ('Return Cash', '', 'Auth')
    AND uot.trans_id IS NOT NULL
    AND uot.trans_id != ''
    AND omr.status = 'active'
    GROUP BY omr.merchant_id, omr.business_name
    HAVING transaction_count > 0
    ON DUPLICATE KEY UPDATE
        transaction_count = VALUES(transaction_count),
        total_amount = VALUES(total_amount),
        refund_amount = VALUES(refund_amount),
        net_amount = VALUES(net_amount),
        updated_at = CURRENT_TIMESTAMP;
    
    SET affected_rows = ROW_COUNT();
    
    -- Update last_transaction_date in registry for merchants with new transactions
    UPDATE olapay_merchants_registry omr
    SET last_transaction_date = target_date,
        updated_at = CURRENT_TIMESTAMP
    WHERE omr.merchant_id IN (
        SELECT DISTINCT mds.merchant_id 
        FROM merchant_daily_olapay_stats mds 
        WHERE mds.date = target_date
    )
    AND (omr.last_transaction_date IS NULL OR omr.last_transaction_date < target_date);
    
    COMMIT;
    
    -- Return result
    SELECT CONCAT('Daily update completed for ', target_date, '. Affected rows: ', affected_rows) as result;
END$$

DELIMITER ;

-- =====================================================
-- Create indexes for optimal stored procedure performance
-- =====================================================

-- Ensure we have the required indexes on unique_olapay_transactions
CREATE INDEX IF NOT EXISTS idx_uot_lastmod_status_type ON unique_olapay_transactions(lastmod, status, trans_type);
CREATE INDEX IF NOT EXISTS idx_uot_serial_lastmod ON unique_olapay_transactions(serial, lastmod);

-- Ensure we have the required indexes on terminals
CREATE INDEX IF NOT EXISTS idx_terminals_vendors_serial ON terminals(vendors_id, serial);

-- Grant necessary permissions (adjust as needed for your setup)
-- GRANT EXECUTE ON PROCEDURE ConsolidateHistoricalOlaPayStats TO 'api_user'@'%';
-- GRANT EXECUTE ON PROCEDURE UpdateDailyOlaPayStats TO 'api_user'@'%';
-- GRANT EXECUTE ON PROCEDURE RebuildOlaPayMerchantsRegistry TO 'api_user'@'%'; 