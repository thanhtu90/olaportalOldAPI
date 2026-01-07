DELIMITER $$

DROP PROCEDURE IF EXISTS delete_order_cascade$$

CREATE PROCEDURE delete_order_cascade(
    IN p_order_id INT
)
main_proc: BEGIN
    DECLARE v_order_exists INT DEFAULT 0;
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    -- Validate input parameter
    IF p_order_id IS NULL OR p_order_id <= 0 THEN
        SELECT 'ERROR' as status, 'Invalid order ID provided' as message;
        LEAVE main_proc;
    END IF;
    
    -- Check if order exists
    SELECT COUNT(*) INTO v_order_exists FROM orders WHERE id = p_order_id;
    
    IF v_order_exists = 0 THEN
        SELECT 'ERROR' as status, 'Order not found' as message;
        LEAVE main_proc;
    END IF;
    
    START TRANSACTION;
    
    -- Step 1: Delete order payments (child table)
    DELETE FROM ordersPayments WHERE orderReference = p_order_id;
    
    -- Step 2: Delete order items (child table)
    DELETE FROM orderItems WHERE orders_id = p_order_id;
    
    -- Step 3: Delete the order itself (parent table)
    DELETE FROM orders WHERE id = p_order_id;
    
    COMMIT;
    
    -- Return success message (single result set)
    SELECT 
        'SUCCESS' as status,
        p_order_id as deleted_order_id,
        'Order and all related data deleted successfully' as message;
        
END$$

DELIMITER ;