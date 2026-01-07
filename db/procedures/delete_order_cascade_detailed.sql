DELIMITER $$

DROP PROCEDURE IF EXISTS delete_order_cascade_detailed$$

CREATE PROCEDURE delete_order_cascade_detailed(
    IN p_order_id INT
)
main_proc: BEGIN
    DECLARE v_payments_count INT DEFAULT 0;
    DECLARE v_items_count INT DEFAULT 0;
    DECLARE v_order_exists INT DEFAULT 0;
    
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
    
    -- Validate input parameter
    IF p_order_id IS NULL OR p_order_id <= 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid order ID provided';
    END IF;
    
    -- Check if order exists and get counts before deletion
    SELECT COUNT(*) INTO v_order_exists FROM orders WHERE id = p_order_id;
    
    IF v_order_exists = 0 THEN
        SELECT 
            'WARNING' as status,
            p_order_id as order_id,
            'Order not found' as message;
        LEAVE main_proc;
    END IF;
    
    -- Get counts before deletion for reporting
    SELECT COUNT(*) INTO v_payments_count FROM ordersPayments WHERE orderReference = p_order_id;
    SELECT COUNT(*) INTO v_items_count FROM orderItems WHERE orders_id = p_order_id;
    
    START TRANSACTION;
    
    -- Step 1: Delete order payments (child table)
    DELETE FROM ordersPayments WHERE orderReference = p_order_id;
    
    -- Step 2: Delete order items (child table)  
    DELETE FROM orderItems WHERE orders_id = p_order_id;
    
    -- Step 3: Delete the order itself (parent table)
    DELETE FROM orders WHERE id = p_order_id;
    
    COMMIT;
    
    -- Return detailed success message
    SELECT 
        'SUCCESS' as status,
        p_order_id as deleted_order_id,
        v_payments_count as payments_deleted,
        v_items_count as items_deleted,
        1 as orders_deleted,
        (v_payments_count + v_items_count + 1) as total_records_deleted,
        'Order and all related data deleted successfully' as message;
        
END$$

DELIMITER ;