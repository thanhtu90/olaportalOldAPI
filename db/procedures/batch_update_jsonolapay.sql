-- Simple direct batch processing script to update lastmod values
-- No stored procedures, no multiple result sets, just direct SQL

-- Clear any previous sessions
DELIMITER ;

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";

-- This will update all records matching the criteria directly
-- without using a stored procedure for batch processing

-- Step 1: Create a temporary table with the IDs to process
DROP TEMPORARY TABLE IF EXISTS temp_ids;
CREATE TEMPORARY TABLE temp_ids AS
SELECT id 
FROM jsonOlaPay
WHERE serial LIKE 'WPYB002446000599' AND lastmod >= 1741539600 limit 10;

-- Step 2: Create a temporary table with the calculated timestamps
DROP TEMPORARY TABLE IF EXISTS temp_timestamps;
CREATE TEMPORARY TABLE temp_timestamps AS
SELECT 
    j.id,
    -- Manual timestamp calculation for California time (PDT = UTC-7)
    UNIX_TIMESTAMP(DATE_ADD(STR_TO_DATE(
        JSON_UNQUOTE(JSON_EXTRACT(j.content, '$.trans_date')),
        '%m/%d/%Y %h:%i:%s %p'
    ), INTERVAL 7 HOUR)) AS new_timestamp
FROM 
    jsonOlaPay j
INNER JOIN temp_ids t ON j.id = t.id;

-- Step 3: Update the records
UPDATE jsonOlaPay j
JOIN temp_timestamps t ON j.id = t.id
SET j.lastmod = t.new_timestamp;

-- Step 4: Clean up
DROP TEMPORARY TABLE IF EXISTS temp_ids;
DROP TEMPORARY TABLE IF EXISTS temp_timestamps;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1; 