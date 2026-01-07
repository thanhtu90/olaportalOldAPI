-- Disable foreign key checks during transaction for safety
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- This procedure updates the lastmod field in the jsonOlaPay table
-- to match the Unix timestamp of the trans_date field within the JSON content
-- Using manual calculation for California timezone (PDT = UTC-7)

DELIMITER $$

DROP PROCEDURE IF EXISTS update_jsonolapay_lastmod$$

CREATE PROCEDURE update_jsonolapay_lastmod(IN id_list TEXT)
BEGIN
    DECLARE updated_count INT DEFAULT 0;
    
    -- Create temporary table with manual calculation (PDT = UTC-7)
    CREATE TEMPORARY TABLE temp_timestamps AS
    SELECT 
        id,
        -- Manual timestamp calculation for California time (PDT = UTC-7)
        UNIX_TIMESTAMP(DATE_ADD(STR_TO_DATE(
            JSON_UNQUOTE(JSON_EXTRACT(content, '$.trans_date')),
            '%m/%d/%Y %h:%i:%s %p'
        ), INTERVAL 7 HOUR)) AS new_timestamp
    FROM 
        jsonOlaPay
    WHERE
        FIND_IN_SET(id, id_list) > 0 OR id_list = '';
    
    -- Update the lastmod field with the calculated timestamp
    UPDATE jsonOlaPay j
    JOIN temp_timestamps t ON j.id = t.id
    SET j.lastmod = t.new_timestamp
    WHERE j.lastmod != t.new_timestamp;
    
    -- Get count of updated records (using variable to avoid multiple result sets)
    SET updated_count = ROW_COUNT();
    
    -- Return a single result set with the count
    SELECT updated_count AS 'Records_Updated';
    
    -- Drop the temporary table
    DROP TEMPORARY TABLE temp_timestamps;
END$$

DELIMITER ;

-- Example usage:
-- CALL update_jsonolapay_lastmod('90739,90740,90741');  -- Process specific IDs
-- CALL update_jsonolapay_lastmod('');                   -- Process all records

COMMIT;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1; 