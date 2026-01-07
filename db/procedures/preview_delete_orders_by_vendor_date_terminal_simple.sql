DELIMITER $$

DROP PROCEDURE IF EXISTS preview_delete_orders_by_vendor_date_terminal_simple$$

CREATE PROCEDURE preview_delete_orders_by_vendor_date_terminal_simple(
    IN p_vendors_id INT,
    IN p_lastmod_from BIGINT,
    IN p_terminal_id INT
)
main_proc: BEGIN
    DECLARE v_terminal_serial VARCHAR(255);
    DECLARE v_orders_count INT DEFAULT 0;
    DECLARE v_json_count INT DEFAULT 0;
    DECLARE v_total_payments_count INT DEFAULT 0;
    DECLARE v_total_items_count INT DEFAULT 0;
    
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
    
    -- Show terminal information if provided
    IF p_terminal_id IS NOT NULL THEN
        SELECT 'TERMINAL INFO' as info_type, 
               p_terminal_id as terminal_id,
               v_terminal_serial as terminal_serial,
               p_vendors_id as vendors_id;
    END IF;
    
    -- Preview JSON records that would be deleted
    IF p_terminal_id IS NOT NULL AND v_terminal_serial IS NOT NULL THEN
        SELECT COUNT(*) INTO v_json_count 
        FROM json 
        WHERE serial = v_terminal_serial;
        
        IF v_json_count > 0 THEN
            SELECT 'JSON RECORDS TO DELETE' as info_type, 
                   id, serial, lastmod, 
                   LEFT(content, 100) as content_preview
            FROM json 
            WHERE serial = v_terminal_serial;
        ELSE
            SELECT 'JSON RECORDS TO DELETE' as info_type, 
                   'No JSON records found for terminal serial' as message;
        END IF;
    END IF;
    
    -- Preview orders that would be deleted
    SELECT COUNT(*) INTO v_orders_count 
    FROM orders 
    WHERE vendors_id = p_vendors_id 
    AND lastMod >= p_lastmod_from
    AND (p_terminal_id IS NULL OR terminals_id = p_terminal_id);
    
    IF v_orders_count > 0 THEN
        SELECT 'ORDERS TO DELETE' as info_type, 
               id, orderReference, vendors_id, terminals_id, 
               orderName, total, OrderDate, lastMod
        FROM orders 
        WHERE vendors_id = p_vendors_id 
        AND lastMod >= p_lastmod_from
        AND (p_terminal_id IS NULL OR terminals_id = p_terminal_id)
        ORDER BY id;
    ELSE
        SELECT 'ORDERS TO DELETE' as info_type, 
               'No orders found matching criteria' as message;
    END IF;
    
    -- Preview order items that would be deleted (using subquery instead of JOIN)
    SELECT COUNT(*) INTO v_total_items_count
    FROM orderItems 
    WHERE orders_id IN (
        SELECT id FROM orders 
        WHERE vendors_id = p_vendors_id 
        AND lastMod >= p_lastmod_from
        AND (p_terminal_id IS NULL OR terminals_id = p_terminal_id)
    );
    
    IF v_total_items_count > 0 THEN
        SELECT 'ORDER ITEMS TO DELETE' as info_type, 
               id, orders_id, name, quantity, price
        FROM orderItems 
        WHERE orders_id IN (
            SELECT id FROM orders 
            WHERE vendors_id = p_vendors_id 
            AND lastMod >= p_lastmod_from
            AND (p_terminal_id IS NULL OR terminals_id = p_terminal_id)
        )
        ORDER BY orders_id, id;
    ELSE
        SELECT 'ORDER ITEMS TO DELETE' as info_type, 
               'No order items found matching criteria' as message;
    END IF;
    
    -- Preview order payments that would be deleted (using subquery instead of JOIN)
    SELECT COUNT(*) INTO v_total_payments_count
    FROM ordersPayments 
    WHERE orderReference IN (
        SELECT id FROM orders 
        WHERE vendors_id = p_vendors_id 
        AND lastMod >= p_lastmod_from
        AND (p_terminal_id IS NULL OR terminals_id = p_terminal_id)
    );
    
    IF v_total_payments_count > 0 THEN
        SELECT 'ORDER PAYMENTS TO DELETE' as info_type, 
               id, orderReference, paymentMethod, amount
        FROM ordersPayments 
        WHERE orderReference IN (
            SELECT id FROM orders 
            WHERE vendors_id = p_vendors_id 
            AND lastMod >= p_lastmod_from
            AND (p_terminal_id IS NULL OR terminals_id = p_terminal_id)
        )
        ORDER BY orderReference, id;
    ELSE
        SELECT 'ORDER PAYMENTS TO DELETE' as info_type, 
               'No order payments found matching criteria' as message;
    END IF;
    
    -- Summary
    SELECT 'SUMMARY' as info_type,
           p_vendors_id as vendors_id,
           p_lastmod_from as lastmod_from,
           p_terminal_id as terminal_id,
           v_terminal_serial as terminal_serial,
           v_json_count as json_records_count,
           v_orders_count as orders_count,
           v_total_items_count as order_items_count,
           v_total_payments_count as order_payments_count,
           (v_json_count + v_orders_count + v_total_items_count + v_total_payments_count) as total_records_count,
           'PREVIEW COMPLETE - No data was deleted' as message;
        
END$$

DELIMITER ;
