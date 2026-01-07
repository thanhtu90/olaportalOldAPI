DELIMITER $$

DROP PROCEDURE IF EXISTS preview_orders_by_vendor_date$$

CREATE PROCEDURE preview_orders_by_vendor_date(
    IN p_vendors_id INT,
    IN p_lastmod_from BIGINT
)
main_proc: BEGIN
    DECLARE v_orders_count INT DEFAULT 0;
    DECLARE v_total_payments_count INT DEFAULT 0;
    DECLARE v_total_items_count INT DEFAULT 0;
    DECLARE v_vendor_name VARCHAR(255) DEFAULT '';
    DECLARE v_earliest_order_date DATETIME DEFAULT NULL;
    DECLARE v_latest_order_date DATETIME DEFAULT NULL;
    DECLARE v_total_amount DECIMAL(10,2) DEFAULT 0.00;
    
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
    
    -- Get order statistics
    SELECT 
        COUNT(*),
        COALESCE(MIN(orderDate), NULL),
        COALESCE(MAX(orderDate), NULL),
        COALESCE(SUM(total), 0.00)
    INTO v_orders_count, v_earliest_order_date, v_latest_order_date, v_total_amount
    FROM orders 
    WHERE vendors_id = p_vendors_id AND lastMod >= p_lastmod_from;
    
    IF v_orders_count = 0 THEN
        SELECT 
            'NOT_FOUND' as status,
            p_vendors_id as vendors_id,
            v_vendor_name as vendor_name,
            p_lastmod_from as lastmod_from,
            'No orders found matching criteria' as message;
        LEAVE main_proc;
    END IF;
    
    -- Count related records that would be deleted
    SELECT COUNT(*) INTO v_total_payments_count
    FROM ordersPayments op
    INNER JOIN orders o ON op.orderReference = o.id
    WHERE o.vendors_id = p_vendors_id AND o.lastMod >= p_lastmod_from;
    
    SELECT COUNT(*) INTO v_total_items_count
    FROM orderItems oi
    INNER JOIN orders o ON oi.orders_id = o.id
    WHERE o.vendors_id = p_vendors_id AND o.lastMod >= p_lastmod_from;
    
    -- Return preview of what would be deleted
    SELECT 
        'PREVIEW' as status,
        p_vendors_id as vendors_id,
        v_vendor_name as vendor_name,
        p_lastmod_from as lastmod_from,
        FROM_UNIXTIME(p_lastmod_from) as lastmod_from_readable,
        v_orders_count as orders_to_delete,
        v_total_payments_count as payments_to_delete,
        v_total_items_count as items_to_delete,
        (v_orders_count + v_total_payments_count + v_total_items_count) as total_records_to_delete,
        v_earliest_order_date as earliest_order_date,
        v_latest_order_date as latest_order_date,
        v_total_amount as total_order_amount,
        'This is a preview. No data will be deleted.' as message;
        
    -- Show sample orders that would be affected
    SELECT 
        'ORDERS_PREVIEW' as record_type,
        o.id,
        o.uuid,
        o.orderReference,
        o.total,
        o.orderDate,
        o.lastMod,
        FROM_UNIXTIME(o.lastMod) as lastMod_readable
    FROM orders o
    WHERE o.vendors_id = p_vendors_id AND o.lastMod >= p_lastmod_from
    ORDER BY o.lastMod DESC
    LIMIT 10;
        
END$$

DELIMITER ;