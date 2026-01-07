DELIMITER $$

DROP PROCEDURE IF EXISTS preview_olapay_by_serial$$

-- =============================================================================
-- Procedure: preview_olapay_by_serial
-- Purpose: Preview jsonOlaPay and unique_olapay_transactions records by serial
--          This procedure NEVER deletes data - preview only
-- Parameters:
--   p_serial        - Terminal serial number (required)
--   p_lastmod_from  - Start timestamp (optional, NULL = no lower bound)
--   p_lastmod_to    - End timestamp (optional, NULL = no upper bound)
--
-- NOTE: If BOTH p_lastmod_from AND p_lastmod_to are NULL, shows ALL records
-- =============================================================================
CREATE PROCEDURE preview_olapay_by_serial(
    IN p_serial VARCHAR(255),
    IN p_lastmod_from BIGINT,
    IN p_lastmod_to BIGINT
)
main_proc: BEGIN
    DECLARE v_json_count INT DEFAULT 0;
    DECLARE v_unique_count INT DEFAULT 0;
    DECLARE v_earliest_date DATETIME;
    DECLARE v_latest_date DATETIME;
    DECLARE v_earliest_unique DATETIME;
    DECLARE v_latest_unique DATETIME;
    DECLARE v_delete_all BOOLEAN DEFAULT FALSE;
    DECLARE v_date_range_desc VARCHAR(100);
    
    -- Validate serial
    IF p_serial IS NULL OR TRIM(p_serial) = '' THEN
        SELECT 
            'ERROR' as status, 
            'Serial number is required' as message;
        LEAVE main_proc;
    END IF;
    
    -- Check if showing all (no date range provided)
    IF p_lastmod_from IS NULL AND p_lastmod_to IS NULL THEN
        SET v_delete_all = TRUE;
        SET v_date_range_desc = 'ALL RECORDS (no date filter)';
    ELSEIF p_lastmod_from IS NOT NULL AND p_lastmod_to IS NOT NULL THEN
        SET v_date_range_desc = CONCAT(
            FROM_UNIXTIME(p_lastmod_from), ' to ', FROM_UNIXTIME(p_lastmod_to)
        );
    ELSEIF p_lastmod_from IS NOT NULL THEN
        SET v_date_range_desc = CONCAT('From ', FROM_UNIXTIME(p_lastmod_from), ' onwards');
    ELSE
        SET v_date_range_desc = CONCAT('Up to ', FROM_UNIXTIME(p_lastmod_to));
    END IF;
    
    -- Build dynamic WHERE clause for jsonOlaPay
    SET @where_json = CONCAT('serial = ''', p_serial, '''');
    IF p_lastmod_from IS NOT NULL THEN
        SET @where_json = CONCAT(@where_json, ' AND lastmod >= ', p_lastmod_from);
    END IF;
    IF p_lastmod_to IS NOT NULL THEN
        SET @where_json = CONCAT(@where_json, ' AND lastmod <= ', p_lastmod_to);
    END IF;
    
    -- Build dynamic WHERE clause for unique_olapay_transactions
    SET @where_unique = CONCAT('serial = ''', p_serial, '''');
    IF p_lastmod_from IS NOT NULL THEN
        SET @where_unique = CONCAT(@where_unique, ' AND lastmod >= ', p_lastmod_from);
    END IF;
    IF p_lastmod_to IS NOT NULL THEN
        SET @where_unique = CONCAT(@where_unique, ' AND lastmod <= ', p_lastmod_to);
    END IF;
    
    -- Count and get date range from jsonOlaPay
    SET @stats_json = CONCAT(
        'SELECT COUNT(*), FROM_UNIXTIME(MIN(lastmod)), FROM_UNIXTIME(MAX(lastmod)) ',
        'INTO @json_cnt, @json_earliest, @json_latest FROM jsonOlaPay WHERE ', @where_json
    );
    PREPARE stmt FROM @stats_json;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
    SET v_json_count = @json_cnt;
    SET v_earliest_date = @json_earliest;
    SET v_latest_date = @json_latest;
    
    -- Count and get date range from unique_olapay_transactions
    SET @stats_unique = CONCAT(
        'SELECT COUNT(*), FROM_UNIXTIME(MIN(lastmod)), FROM_UNIXTIME(MAX(lastmod)) ',
        'INTO @unique_cnt, @unique_earliest, @unique_latest FROM unique_olapay_transactions WHERE ', @where_unique
    );
    PREPARE stmt FROM @stats_unique;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
    SET v_unique_count = @unique_cnt;
    SET v_earliest_unique = @unique_earliest;
    SET v_latest_unique = @unique_latest;
    
    -- Return preview results
    SELECT 
        'PREVIEW' as status,
        p_serial as serial,
        v_delete_all as will_delete_all,
        v_date_range_desc as date_range,
        v_json_count as jsonOlaPay_records,
        v_earliest_date as jsonOlaPay_earliest,
        v_latest_date as jsonOlaPay_latest,
        v_unique_count as unique_olapay_records,
        v_earliest_unique as unique_olapay_earliest,
        v_latest_unique as unique_olapay_latest,
        (v_json_count + v_unique_count) as total_records,
        CASE WHEN v_delete_all THEN 
            '⚠️ No date filter - will delete ALL records for this serial'
        ELSE 
            'This is a preview. No data will be deleted.'
        END as message,
        CONCAT(
            'CALL delete_olapay_by_serial(''', p_serial, ''', ',
            IFNULL(p_lastmod_from, 'NULL'), ', ',
            IFNULL(p_lastmod_to, 'NULL'), ', ''YES'');'
        ) as delete_command;
        
END$$

DELIMITER ;
