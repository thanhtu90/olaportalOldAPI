DELIMITER $$

DROP PROCEDURE IF EXISTS delete_orders_by_vendor_date_terminal$$

CREATE PROCEDURE delete_orders_by_vendor_date_terminal(
    IN p_vendors_id INT,
    IN p_lastmod_from BIGINT,
    IN p_terminal_id INT
)
main_proc: BEGIN
    DECLARE v_terminal_serial VARCHAR(255);
    DECLARE v_orders_count INT DEFAULT 0;
    DECLARE v_total_payments_deleted INT DEFAULT 0;
    DECLARE v_total_items_deleted INT DEFAULT 0;
    DECLARE v_total_orders_deleted INT DEFAULT 0;
    DECLARE v_json_count INT DEFAULT 0;
    DECLARE v_current_order_id INT;
    DECLARE v_payments_deleted INT DEFAULT 0;
    DECLARE v_items_deleted INT DEFAULT 0;
    DECLARE done INT DEFAULT FALSE;
    
    -- Cursor to iterate through matching orders
    DECLARE order_cursor CURSOR FOR 
        SELECT id FROM orders 
        WHERE vendors_id = p_vendors_id 
        AND lastMod >= p_lastmod_from
        AND (p_terminal_id IS NULL OR terminals_id = p_terminal_id)
        ORDER BY id;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
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
            @text as error_message;
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
    
    -- If terminal_id is provided, validate it and get terminal serial
    IF p_terminal_id IS NOT NULL THEN
        IF p_terminal_id <= 0 THEN
            SELECT 'ERROR' as status, 'Invalid terminal_id provided' as message;
            LEAVE main_proc;
        END IF;
        
        SELECT serial INTO v_terminal_serial
        FROM terminals 
        WHERE id = p_terminal_id AND vendors_id = p_vendors_id;
        
        IF v_terminal_serial IS NULL THEN
            SELECT 'ERROR' as status, CONCAT('Terminal with ID ', p_terminal_id, ' not found for vendor ', p_vendors_id) as message;
            LEAVE main_proc;
        END IF;
    END IF;
    
    -- Check if any orders exist matching criteria
    SELECT COUNT(*) INTO v_orders_count 
    FROM orders 
    WHERE vendors_id = p_vendors_id 
    AND lastMod >= p_lastmod_from
    AND (p_terminal_id IS NULL OR terminals_id = p_terminal_id);
    
    IF v_orders_count = 0 THEN
        SELECT 
            'WARNING' as status,
            p_vendors_id as vendors_id,
            p_lastmod_from as lastmod_from,
            p_terminal_id as terminal_id,
            'No orders found matching criteria' as message;
        LEAVE main_proc;
    END IF;
    
    START TRANSACTION;
    
    -- Delete JSON records if terminal_id is provided
    IF p_terminal_id IS NOT NULL AND v_terminal_serial IS NOT NULL THEN
        -- Delete JSON records for this terminal with lastmod filter
        DELETE FROM json WHERE serial = v_terminal_serial AND lastmod >= FROM_UNIXTIME(p_lastmod_from);
        SET v_json_count = ROW_COUNT();
    END IF;
    
    -- Open cursor and iterate through orders
    OPEN order_cursor;
    
    delete_loop: LOOP
        FETCH order_cursor INTO v_current_order_id;
        IF done THEN
            LEAVE delete_loop;
        END IF;
        
        -- Delete order payments for this order
        DELETE FROM ordersPayments WHERE orderReference = v_current_order_id;
        SET v_payments_deleted = ROW_COUNT();
        SET v_total_payments_deleted = v_total_payments_deleted + v_payments_deleted;
        
        -- Delete order items for this order
        DELETE FROM orderItems WHERE orders_id = v_current_order_id;
        SET v_items_deleted = ROW_COUNT();
        SET v_total_items_deleted = v_total_items_deleted + v_items_deleted;
        
        -- Delete the order itself
        DELETE FROM orders WHERE id = v_current_order_id;
        SET v_total_orders_deleted = v_total_orders_deleted + ROW_COUNT();
        
    END LOOP;
    
    CLOSE order_cursor;
    
    COMMIT;
    
    -- Return detailed success message
    SELECT 
        'SUCCESS' as status,
        p_vendors_id as vendors_id,
        p_lastmod_from as lastmod_from,
        p_terminal_id as terminal_id,
        v_terminal_serial as terminal_serial,
        v_orders_count as orders_found,
        v_json_count as json_records_deleted,
        v_total_payments_deleted as payments_deleted,
        v_total_items_deleted as items_deleted,
        v_total_orders_deleted as orders_deleted,
        (v_json_count + v_total_payments_deleted + v_total_items_deleted + v_total_orders_deleted) as total_records_deleted,
        'Orders and all related data deleted successfully' as message;
        
END$$

DELIMITER ;
