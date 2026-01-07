DELIMITER $$

DROP PROCEDURE IF EXISTS preview_order_delete$$

CREATE PROCEDURE preview_order_delete(
    IN p_order_id INT
)
main_proc: BEGIN
    DECLARE v_payments_count INT DEFAULT 0;
    DECLARE v_items_count INT DEFAULT 0;
    DECLARE v_order_exists INT DEFAULT 0;
    DECLARE v_order_uuid VARCHAR(36) DEFAULT '';
    DECLARE v_order_total DECIMAL(10,2) DEFAULT 0.00;
    DECLARE v_order_date DATETIME DEFAULT NULL;
    
    -- Validate input parameter
    IF p_order_id IS NULL OR p_order_id <= 0 THEN
        SELECT 
            'ERROR' as status,
            'Invalid order ID provided' as message;
        LEAVE main_proc;
    END IF;
    
    -- Check if order exists and get order details
    SELECT 
        COUNT(*), 
        COALESCE(MAX(uuid), ''),
        COALESCE(MAX(total), 0.00),
        MAX(orderDate)
    INTO v_order_exists, v_order_uuid, v_order_total, v_order_date
    FROM orders 
    WHERE id = p_order_id;
    
    IF v_order_exists = 0 THEN
        SELECT 
            'NOT_FOUND' as status,
            p_order_id as order_id,
            'Order not found' as message;
        LEAVE main_proc;
    END IF;
    
    -- Get counts of related records
    SELECT COUNT(*) INTO v_payments_count FROM ordersPayments WHERE orderReference = p_order_id;
    SELECT COUNT(*) INTO v_items_count FROM orderItems WHERE orders_id = p_order_id;
    
    -- Return preview of what would be deleted (single result set only)
    SELECT 
        'PREVIEW' as status,
        p_order_id as order_id,
        v_order_uuid as order_uuid,
        v_order_total as order_total,
        v_order_date as order_date,
        v_payments_count as payments_to_delete,
        v_items_count as items_to_delete,
        1 as orders_to_delete,
        (v_payments_count + v_items_count + 1) as total_records_to_delete,
        'This is a preview. No data will be deleted.' as message;
        
END$$

DELIMITER ;