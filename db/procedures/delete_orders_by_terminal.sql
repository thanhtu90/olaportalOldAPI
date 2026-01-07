DELIMITER $$

DROP PROCEDURE IF EXISTS delete_orders_by_terminal$$

CREATE PROCEDURE delete_orders_by_terminal(
    IN p_terminal_id INT
)
main_proc: BEGIN
    DECLARE v_terminal_serial VARCHAR(255);
    DECLARE v_vendors_id INT;
    DECLARE v_json_count INT DEFAULT 0;
    DECLARE v_jsonolapay_count INT DEFAULT 0;
    DECLARE v_online_pending_count INT DEFAULT 0;
    DECLARE v_orders_count INT DEFAULT 0;
    DECLARE v_total_payments_deleted INT DEFAULT 0;
    DECLARE v_total_items_deleted INT DEFAULT 0;
    DECLARE v_total_orders_deleted INT DEFAULT 0;
    DECLARE v_current_order_id INT;
    DECLARE v_payments_deleted INT DEFAULT 0;
    DECLARE v_items_deleted INT DEFAULT 0;
    DECLARE done INT DEFAULT FALSE;
    
    -- Cursor to iterate through matching orders
    DECLARE order_cursor CURSOR FOR 
        SELECT id FROM orders 
        WHERE terminals_id = p_terminal_id
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
    IF p_terminal_id IS NULL OR p_terminal_id <= 0 THEN
        SELECT 'ERROR' as status, 'Invalid terminal_id provided' as message;
        LEAVE main_proc;
    END IF;
    
    -- Get terminal information
    SELECT serial, vendors_id INTO v_terminal_serial, v_vendors_id
    FROM terminals 
    WHERE id = p_terminal_id;
    
    IF v_terminal_serial IS NULL THEN
        SELECT 'ERROR' as status, CONCAT('Terminal with ID ', p_terminal_id, ' not found') as message;
        LEAVE main_proc;
    END IF;
    
    -- Check if any JSON records exist for this terminal
    SELECT COUNT(*) INTO v_json_count 
    FROM json 
    WHERE serial = v_terminal_serial;
    
    SELECT COUNT(*) INTO v_jsonolapay_count 
    FROM jsonOlaPay 
    WHERE serial = v_terminal_serial;
    
    SELECT COUNT(*) INTO v_online_pending_count 
    FROM online_pending_orders 
    WHERE terminal_serial = v_terminal_serial;
    
    -- Check if any orders exist for this terminal
    SELECT COUNT(*) INTO v_orders_count 
    FROM orders 
    WHERE terminals_id = p_terminal_id;
    
    IF v_json_count = 0 AND v_jsonolapay_count = 0 AND v_online_pending_count = 0 AND v_orders_count = 0 THEN
        SELECT 
            'WARNING' as status,
            p_terminal_id as terminal_id,
            v_terminal_serial as terminal_serial,
            v_vendors_id as vendors_id,
            'No data found for this terminal' as message;
        LEAVE main_proc;
    END IF;
    
    START TRANSACTION;
    
    -- Delete JSON records for this terminal
    DELETE FROM json WHERE serial = v_terminal_serial;
    SET v_json_count = ROW_COUNT();
    
    -- Delete JSON OlaPay records for this terminal
    DELETE FROM jsonOlaPay WHERE serial = v_terminal_serial;
    SET v_jsonolapay_count = ROW_COUNT();
    
    -- Delete online pending orders for this terminal
    DELETE FROM online_pending_orders WHERE terminal_serial = v_terminal_serial;
    SET v_online_pending_count = ROW_COUNT();
    
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
        p_terminal_id as terminal_id,
        v_terminal_serial as terminal_serial,
        v_vendors_id as vendors_id,
        v_json_count as json_records_deleted,
        v_jsonolapay_count as jsonolapay_records_deleted,
        v_online_pending_count as online_pending_orders_deleted,
        v_total_payments_deleted as payments_deleted,
        v_total_items_deleted as items_deleted,
        v_total_orders_deleted as orders_deleted,
        (v_json_count + v_jsonolapay_count + v_online_pending_count + v_total_payments_deleted + v_total_items_deleted + v_total_orders_deleted) as total_records_deleted,
        'Terminal data and all related records deleted successfully' as message;
        
END$$

DELIMITER ;
