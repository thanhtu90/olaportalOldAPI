-- =====================================================
-- Setup Script for olapayTerminalRecord.php Optimization
-- Run this BEFORE creating the indexes
-- =====================================================
-- 
-- This script ensures the required computed columns exist
-- If they already exist, these statements will fail gracefully
-- =====================================================

-- Step 1: Add computed columns if they don't exist
-- These extract commonly used fields from the JSON content field
-- Note: These are STORED computed columns (MySQL 5.7+ / 8.0+)

-- Check if columns exist first (manual check required):
-- DESCRIBE unique_olapay_transactions;

-- Add trans_type column (if it doesn't exist)
-- This extracts trans_type from JSON content
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'unique_olapay_transactions' 
    AND COLUMN_NAME = 'trans_type'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE unique_olapay_transactions 
     ADD COLUMN trans_type VARCHAR(50) AS(JSON_UNQUOTE(JSON_EXTRACT(content, ''$.trans_type''))) STORED',
    'SELECT ''Column trans_type already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add status column (if it doesn't exist)
-- Note: The JSON field is 'Status' (capital S) but we'll store it as 'status'
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'unique_olapay_transactions' 
    AND COLUMN_NAME = 'status'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE unique_olapay_transactions 
     ADD COLUMN status VARCHAR(50) AS(JSON_UNQUOTE(JSON_EXTRACT(content, ''$.Status''))) STORED',
    'SELECT ''Column status already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add amount column (optional, but useful for other queries)
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'unique_olapay_transactions' 
    AND COLUMN_NAME = 'amount'
);

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE unique_olapay_transactions 
     ADD COLUMN amount DECIMAL(10, 2) AS(CAST(JSON_UNQUOTE(JSON_EXTRACT(content, ''$.amount'')) AS DECIMAL(10, 2))) STORED',
    'SELECT ''Column amount already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- Alternative: Simple ALTER TABLE (if columns don't exist)
-- Uncomment and run if the above dynamic SQL doesn't work
-- =====================================================
/*
ALTER TABLE unique_olapay_transactions 
ADD COLUMN IF NOT EXISTS trans_type VARCHAR(50) AS(JSON_UNQUOTE(JSON_EXTRACT(content, '$.trans_type'))) STORED,
ADD COLUMN IF NOT EXISTS status VARCHAR(50) AS(JSON_UNQUOTE(JSON_EXTRACT(content, '$.Status'))) STORED,
ADD COLUMN IF NOT EXISTS amount DECIMAL(10, 2) AS(CAST(JSON_UNQUOTE(JSON_EXTRACT(content, '$.amount')) AS DECIMAL(10, 2))) STORED;
*/

-- =====================================================
-- Verification
-- =====================================================
-- After running this script, verify the columns exist:
-- DESCRIBE unique_olapay_transactions;
-- 
-- You should see:
-- - trans_type (VARCHAR(50), GENERATED ALWAYS AS ... STORED)
-- - status (VARCHAR(50), GENERATED ALWAYS AS ... STORED)
-- - amount (DECIMAL(10,2), GENERATED ALWAYS AS ... STORED)

