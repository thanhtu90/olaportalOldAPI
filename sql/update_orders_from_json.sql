DELIMITER //

DROP PROCEDURE IF EXISTS update_orders_from_json//

CREATE PROCEDURE update_orders_from_json(IN p_date DATE, IN p_vendors_id INT)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_order_id INT;
    DECLARE v_order_uuid VARCHAR(255);
    
    DECLARE order_cursor CURSOR FOR 
        SELECT o.id, o.uuid 
        FROM orders o
        WHERE DATE(FROM_UNIXTIME(o.lastMod)) = p_date 
        AND o.vendors_id = p_vendors_id
        AND o.uuid IS NOT NULL;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Debug table for logging
    DROP TABLE IF EXISTS debug_log;
    CREATE TABLE debug_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        step VARCHAR(50),
        message TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    
    -- Log input parameters
    INSERT INTO debug_log (step, message) 
    VALUES ('START', CONCAT('Input parameters: date=', p_date, ', vendor_id=', p_vendors_id));
    
    -- Log matching orders count
    INSERT INTO debug_log (step, message)
    SELECT 'ORDERS_COUNT', CONCAT('Found ', COUNT(*), ' orders for date=', p_date, ' vendor_id=', p_vendors_id)
    FROM orders o
    WHERE DATE(FROM_UNIXTIME(o.lastMod)) = p_date 
    AND o.vendors_id = p_vendors_id;
    
    -- Open cursor
    OPEN order_cursor;
    
    order_loop: LOOP
        FETCH order_cursor INTO v_order_id, v_order_uuid;
        IF done THEN
            LEAVE order_loop;
        END IF;
        
        -- Log order being processed
        INSERT INTO debug_log (step, message)
        VALUES ('PROCESS_ORDER', CONCAT('Processing order_id=', v_order_id, ', uuid=', v_order_uuid));
        
        -- Find matching JSON records
        INSERT INTO debug_log (step, message)
        SELECT 'JSON_FOUND', 
               CONCAT('Found JSON record: lastmod=', j.lastmod, 
                     ', serial=', j.serial,
                     ', content_length=', LENGTH(j.content))
        FROM json j
        INNER JOIN terminals t ON t.serial = j.serial
        WHERE t.vendors_id = p_vendors_id
        AND j.content LIKE CONCAT('%', v_order_uuid, '%')
        AND DATE(j.lastmod) >= p_date
        ORDER BY j.lastmod DESC
        LIMIT 1;
        
        -- Extract and log payment data
        WITH latest_json_record AS (
            SELECT j.content, j.serial, j.lastmod
            FROM json j
            INNER JOIN terminals t ON t.serial = j.serial
            WHERE t.vendors_id = p_vendors_id
            AND j.content LIKE CONCAT('%', v_order_uuid, '%')
            AND DATE(j.lastmod) >= p_date
            ORDER BY j.lastmod DESC
            LIMIT 1
        ),
        payment_data AS (
            SELECT 
                JSON_UNQUOTE(JSON_EXTRACT(p.payment_data, '$.tips')) as json_tips,
                JSON_UNQUOTE(JSON_EXTRACT(p.payment_data, '$.total')) as json_total,
                JSON_UNQUOTE(JSON_EXTRACT(p.payment_data, '$.lastMod')) as last_mod
            FROM latest_json_record j
            CROSS JOIN JSON_TABLE(
                REPLACE(REPLACE(j.content->>'$.payments', '\\\\', '\\'), '\\"', '"'),
                '$[*]'
                COLUMNS (
                    payment_data JSON PATH '$'
                )
            ) p
        )
        INSERT INTO debug_log (step, message)
        SELECT 'PAYMENT_DATA', 
               CONCAT('Payment data found: tips=', json_tips,
                     ', total=', json_total,
                     ', lastMod=', last_mod)
        FROM payment_data
        ORDER BY CAST(last_mod AS UNSIGNED) DESC
        LIMIT 1;
        
        -- Compare current and new values
        INSERT INTO debug_log (step, message)
        SELECT 'COMPARE_VALUES',
               CONCAT('Current values: tip=', o.tip,
                     ', total=', o.total,
                     ', lastMod=', o.lastMod,
                     ' | Would update to: tip=', COALESCE(CAST(p.json_tips AS DECIMAL(10,2)), o.tip),
                     ', total=', COALESCE(CAST(p.json_total AS FLOAT), o.total),
                     ', lastMod=', CAST(p.last_mod AS UNSIGNED))
        FROM orders o
        CROSS JOIN (
            SELECT json_tips, json_total, last_mod
            FROM payment_data
            ORDER BY CAST(last_mod AS UNSIGNED) DESC
            LIMIT 1
        ) p
        WHERE o.id = v_order_id;
        
    END LOOP;
    
    CLOSE order_cursor;
    
    -- Show all debug logs in order
    SELECT * FROM debug_log ORDER BY id;
    
END //

DELIMITER ;
