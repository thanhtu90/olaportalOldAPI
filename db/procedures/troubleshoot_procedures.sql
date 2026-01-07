-- Troubleshooting commands for procedure execution
-- Run these if you encounter "Commands out of sync" errors

-- 1. Reset connection state
SET FOREIGN_KEY_CHECKS = 1;

-- 2. Clear any pending results
-- (In phpMyAdmin, refresh the page or reconnect)

-- 3. Test basic procedure call (simplest version)
DELIMITER $$

DROP PROCEDURE IF EXISTS test_order_delete$$

CREATE PROCEDURE test_order_delete(
    IN p_order_id INT
)
BEGIN
    DECLARE v_order_exists INT DEFAULT 0;
    
    -- Simple check without complex error handling
    SELECT COUNT(*) INTO v_order_exists FROM orders WHERE id = p_order_id;
    
    SELECT 
        p_order_id as order_id,
        v_order_exists as order_exists,
        CASE 
            WHEN v_order_exists > 0 THEN 'Order found'
            ELSE 'Order not found'
        END as status;
END$$

DELIMITER ;

-- 4. Test the simple version first
-- CALL test_order_delete(499553);

-- 5. If that works, then try the preview procedure
-- CALL preview_order_delete(499553);