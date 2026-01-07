DELIMITER $$

DROP PROCEDURE IF EXISTS preview_delete_orders_simple$$

CREATE PROCEDURE preview_delete_orders_simple(
    IN p_vendors_id INT,
    IN p_lastmod_from BIGINT,
    IN p_terminal_id INT
)
BEGIN
    DECLARE v_terminal_serial VARCHAR(255);
    
    -- Get terminal serial if terminal_id provided
    IF p_terminal_id IS NOT NULL THEN
        SELECT serial INTO v_terminal_serial
        FROM terminals 
        WHERE id = p_terminal_id AND vendors_id = p_vendors_id;
    END IF;
    
    -- Show orders that would be deleted
    SELECT 'ORDERS' as table_name, COUNT(*) as record_count
    FROM orders 
    WHERE vendors_id = p_vendors_id 
    AND lastMod >= p_lastmod_from
    AND (p_terminal_id IS NULL OR terminals_id = p_terminal_id);
    
    -- Show JSON records that would be deleted
    IF p_terminal_id IS NOT NULL AND v_terminal_serial IS NOT NULL THEN
        SELECT 'JSON' as table_name, COUNT(*) as record_count
        FROM json 
        WHERE serial = v_terminal_serial AND lastmod >= FROM_UNIXTIME(p_lastmod_from);
    END IF;
    
    -- Show order items count
    SELECT 'ORDER_ITEMS' as table_name, COUNT(*) as record_count
    FROM orderItems 
    WHERE orders_id IN (
        SELECT id FROM orders 
        WHERE vendors_id = p_vendors_id 
        AND lastMod >= p_lastmod_from
        AND (p_terminal_id IS NULL OR terminals_id = p_terminal_id)
    );
    
    -- Show order payments count
    SELECT 'ORDER_PAYMENTS' as table_name, COUNT(*) as record_count
    FROM ordersPayments 
    WHERE orderReference IN (
        SELECT id FROM orders 
        WHERE vendors_id = p_vendors_id 
        AND lastMod >= p_lastmod_from
        AND (p_terminal_id IS NULL OR terminals_id = p_terminal_id)
    );
    
END$$

DELIMITER ;
