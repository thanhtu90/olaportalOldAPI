DELIMITER $$

DROP PROCEDURE IF EXISTS delete_orders_by_vendor_date_safe$$

CREATE PROCEDURE delete_orders_by_vendor_date_safe(
    IN p_vendors_id INT,
    IN p_lastmod_from BIGINT,
    IN p_confirm_delete VARCHAR(10) DEFAULT 'NO'
)
main_proc: BEGIN
    DECLARE v_orders_count INT DEFAULT 0;
    DECLARE v_total_payments_deleted INT DEFAULT 0;
    DECLARE v_total_items_deleted INT DEFAULT 0;
    DECLARE v_total_orders_deleted INT DEFAULT 0;
    DECLARE v_vendor_name VARCHAR(255) DEFAULT '';
    DECLARE v_batch_count INT DEFAULT 0;
    DECLARE v_batch_size INT DEFAULT 100; -- Process in batches for safety
    DECLARE v_current_batch_orders TEXT DEFAULT '';
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        GET DIAGNOSTICS CONDITION 1
            @sqlstate = RETURNED_SQLSTATE, 
            @errno = MYSQL_ERRNO, 
            @text = MESSAGE_TEXT;
        SELECT 
            'ERROR' as status,
            @errno as error_code,
            @sqlstate as sql_state,
            @text as error_message,
            'Transaction rolled back' as action_taken;
        RESIGNAL;
    END;
    
    -- Validate input parameters
    IF p_vendors_id IS NULL OR p_vendors_id <= 0 THEN
        SELECT 'ERROR' as status, 'Invalid vendors_id provided' as message;
        LEAVE main_proc;
    END IF;
    
    IF p_lastmod_from IS NULL OR p_lastmod_from <= 0 THEN
        SELECT 'ERROR' as status, 'Invalid lastmod timestamp provided' as message;
        LEAVE main_proc;
    END IF;
    
    -- Get vendor information
    SELECT name INTO v_vendor_name FROM accounts WHERE id = p_vendors_id;
    
    IF v_vendor_name IS NULL THEN
        SELECT 'ERROR' as status, 'Vendor not found' as message;
        LEAVE main_proc;
    END IF;
    
    -- Check if any orders exist matching criteria
    SELECT COUNT(*) INTO v_orders_count 
    FROM orders 
    WHERE vendors_id = p_vendors_id AND lastMod >= p_lastmod_from;
    
    IF v_orders_count = 0 THEN
        SELECT 
            'WARNING' as status,
            p_vendors_id as vendors_id,
            v_vendor_name as vendor_name,
            p_lastmod_from as lastmod_from,
            'No orders found matching criteria' as message;
        LEAVE main_proc;
    END IF;
    
    -- Safety check: require explicit confirmation for deletion
    IF UPPER(p_confirm_delete) != 'YES' THEN
        SELECT 
            'CONFIRMATION_REQUIRED' as status,
            p_vendors_id as vendors_id,
            v_vendor_name as vendor_name,
            v_orders_count as orders_to_delete,
            p_lastmod_from as lastmod_from,
            FROM_UNIXTIME(p_lastmod_from) as lastmod_from_readable,
            'To proceed with deletion, call with p_confirm_delete = "YES"' as message,
            'CALL delete_orders_by_vendor_date_safe(' || p_vendors_id || ', ' || p_lastmod_from || ', "YES");' as example_call;
        LEAVE main_proc;
    END IF;
    
    START TRANSACTION;
    
    -- Process orders in batches for better performance and logging
    batch_loop: WHILE v_orders_count > 0 DO
        SET v_batch_count = v_batch_count + 1;
        SET v_current_batch_orders = '';
        
        -- Get a batch of order IDs
        SET @batch_sql = CONCAT(
            'CREATE TEMPORARY TABLE temp_batch_orders_', v_batch_count, ' AS ',
            'SELECT id FROM orders ',
            'WHERE vendors_id = ', p_vendors_id, ' AND lastMod >= ', p_lastmod_from, ' ',
            'LIMIT ', v_batch_size
        );
        
        SET @drop_sql = CONCAT('DROP TEMPORARY TABLE IF EXISTS temp_batch_orders_', v_batch_count);
        PREPARE stmt FROM @drop_sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        
        PREPARE stmt FROM @batch_sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        
        -- Delete payments for this batch
        SET @delete_payments_sql = CONCAT(
            'DELETE op FROM ordersPayments op ',
            'INNER JOIN temp_batch_orders_', v_batch_count, ' tbo ON op.orderReference = tbo.id'
        );
        PREPARE stmt FROM @delete_payments_sql;
        EXECUTE stmt;
        SET v_total_payments_deleted = v_total_payments_deleted + ROW_COUNT();
        DEALLOCATE PREPARE stmt;
        
        -- Delete items for this batch
        SET @delete_items_sql = CONCAT(
            'DELETE oi FROM orderItems oi ',
            'INNER JOIN temp_batch_orders_', v_batch_count, ' tbo ON oi.orders_id = tbo.id'
        );
        PREPARE stmt FROM @delete_items_sql;
        EXECUTE stmt;
        SET v_total_items_deleted = v_total_items_deleted + ROW_COUNT();
        DEALLOCATE PREPARE stmt;
        
        -- Delete orders for this batch
        SET @delete_orders_sql = CONCAT(
            'DELETE o FROM orders o ',
            'INNER JOIN temp_batch_orders_', v_batch_count, ' tbo ON o.id = tbo.id'
        );
        PREPARE stmt FROM @delete_orders_sql;
        EXECUTE stmt;
        SET v_total_orders_deleted = v_total_orders_deleted + ROW_COUNT();
        DEALLOCATE PREPARE stmt;
        
        -- Clean up temp table
        PREPARE stmt FROM @drop_sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        
        -- Check if more orders remain
        SELECT COUNT(*) INTO v_orders_count 
        FROM orders 
        WHERE vendors_id = p_vendors_id AND lastMod >= p_lastmod_from;
        
    END WHILE batch_loop;
    
    COMMIT;
    
    -- Return detailed success message
    SELECT 
        'SUCCESS' as status,
        p_vendors_id as vendors_id,
        v_vendor_name as vendor_name,
        p_lastmod_from as lastmod_from,
        FROM_UNIXTIME(p_lastmod_from) as lastmod_from_readable,
        v_batch_count as batches_processed,
        v_total_payments_deleted as payments_deleted,
        v_total_items_deleted as items_deleted,
        v_total_orders_deleted as orders_deleted,
        (v_total_payments_deleted + v_total_items_deleted + v_total_orders_deleted) as total_records_deleted,
        'Orders and all related data deleted successfully' as message;
        
END$$

DELIMITER ;