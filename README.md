### Production

./vendor/bin/phinx migrate -e production
./vendor/bin/phinx migrate -e production -t 20241217050633
./vendor/bin/phinx migrate -e production -t 20241217084326

### Development

./vendor/bin/phinx migrate -e development
php vendor/bin/phinx migrate -e development -t 20240817072044
php vendor/bin/phinx migrate -e development -t 20240818162541
php vendor/bin/phinx migrate -e development -t 20240818163524
./vendor/bin/phinx migrate -e development -t 20241217050633
./vendor/bin/phinx seed:run -e development
./vendor/bin/phinx rollback -e development -t 20240821052355
./vendor/bin/phinx rollback -e development -t 20241217050633

php vendor/bin/phinx migrate -e development -t 20250109140333
php vendor/bin/phinx migrate -e development -t 20250109140405

### Testing

./vendor/bin/phinx migrate -e testing
./vendor/bin/phinx create OrderUuidColumnMigration
./vendor/bin/phinx seed:create AccountSeeder
./vendor/bin/phinx seed:run -e testing
php vendor/bin/phinx migrate -e testing -t 20240817072044
php vendor/bin/phinx migrate -e testing -t 20240818162541
php vendor/bin/phinx migrate -e testing -t 20240818163524
php vendor/bin/phinx migrate -e testing -t 20241217050633
php vendor/bin/phinx migrate -e testing -t 20241217084326
php vendor/bin/phinx migrate -e testing -t 20250109140333
php vendor/bin/phinx migrate -e testing -t 20250109140405
php vendor/bin/phinx migrate -e development -t 20250220083000

php vendor/bin/phinx breakpoint -e testing -x 20240817050935 -t 20241217084326
php vendor/bin/phinx breakpoint -e development -x 20240817072044 -t 20250209091121

php vendor/bin/phinx breakpoint -e development -t 20250209091121



./vendor/bin/phinx mark-migrated -e testing -t 20240817050935
./vendor/bin/phinx rollback -e testing
./vendor/bin/phinx rollback -e testing -t 20240821052355

### Create Migration

./vendor/bin/phinx create OrderEmployeePinColumnMigration
./vendor/bin/phinx create OrderItemsAddColumnEbtMigration
./vendor/bin/phinx create OrderItemsAddColumnCrvMigration
./vendor/bin/phinx create TerminalSystemsMigration
./vendor/bin/phinx create TerminalAlterColumnSystemMigration

vendor/bin/phpunit
vendor/bin/phpunit tests/insertOrderTest.php

### Unit test

vendor/bin/phpunit

scp /Users/tu/Desktop/Workspace/teamsable/olaportalAPI/db/migrations/20241217050633_terminal_payment_method_migration.php tu@3.18.227.3:/home/olaportal/olaportal/api/db/migrations/

===

get terminal have olapos

```
SELECT
    COUNT(1),
    `json`.`serial`
FROM
    `json`
WHERE
    `json`.`serial` IN(
    SELECT
        terminals.serial
    FROM
        terminals
)
GROUP BY
    `json`.`serial`;

```

execute procedure

```
CALL assign_olapay_payment_method();
CALL assign_olapos_payment_method();
```

===
DROP PROCEDURE IF EXISTS assign_olapay_payment_method;

DROP PROCEDURE IF EXISTS assign_olapos_payment_method;

===

scp tu@3.18.227.3:/home/olaportal/olaportal/api/orders2.php ./
scp tu@3.18.227.3:/home/olaportal/olaportal/api/olapayTerminalRecord.php ./

===
ALTER TABLE json ADD INDEX idx_lastmod_id (lastmod, id);

=== Query to get olapayApprovalId

```mysql
SELECT DISTINCT
JSON_VALUE(
JSON_UNQUOTE(JSON_EXTRACT(j.content, '$.payments')),
        '$[0].olapayApprovalId'
) as olapayApprovalId
FROM
json j
WHERE
j.lastmod >= '2025-01-06 18:00:00'
AND JSON_VALUE(
JSON_UNQUOTE(JSON_EXTRACT(j.content, '$.payments')),
        '$[0].olapayApprovalId'
) IS NOT NULL
ORDER BY
j.lastmod DESC, j.id DESC
LIMIT 10;
```

=== Query to get trans_id

```mysql
SELECT
    JSON_UNQUOTE(JSON_EXTRACT(content, '$.trans_id')) as trans_id,
    lastmod,
    content
FROM
    jsonOlaPay
WHERE
    lastmod > 1736139398
ORDER BY
    lastmod DESC
LIMIT 10;
```

==

```mysql
WITH olapay_approvals AS (
    SELECT DISTINCT
        JSON_VALUE(
            JSON_UNQUOTE(JSON_EXTRACT(j.content, '$.payments')),
            '$[0].olapayApprovalId'
        ) as olapayApprovalId
    FROM json j
    WHERE j.lastmod >= '2025-01-06 18:00:00'
    AND JSON_VALUE(
        JSON_UNQUOTE(JSON_EXTRACT(j.content, '$.payments')),
        '$[0].olapayApprovalId'
    ) IS NOT NULL
)
SELECT
    accounts.id,
    accounts.companyname AS business,
    COUNT(DISTINCT jo.id) AS transactions,
    SUM(
        CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(jo.content, '$.trans_type')) = 'Refund'
        THEN CAST(JSON_UNQUOTE(JSON_EXTRACT(jo.content, '$.amount')) AS DECIMAL(10, 2))
        ELSE 0
        END
    ) AS refund,
    SUM(
        CAST(JSON_UNQUOTE(JSON_EXTRACT(jo.content, '$.amount')) AS DECIMAL(10, 2))
    ) AS amount
FROM jsonOlaPay jo
LEFT JOIN olapay_approvals oa
    ON CAST(JSON_UNQUOTE(JSON_EXTRACT(jo.content, '$.trans_id')) AS CHAR) != oa.olapayApprovalId
JOIN terminals ON terminals.id = jo.serial
JOIN accounts ON terminals.vendors_id = accounts.id
WHERE jo.lastmod >= 1736139398
GROUP BY accounts.id
ORDER BY amount DESC
LIMIT 10
```

===

INSERT INTO phinxlog
(version, migration_name, start_time, end_time, breakpoint)
VALUES
('20240817050935', 'InitMigration', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0),
('20240817072044', 'OrderUuidColumnMigration', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0),
('20240818162541', 'OrderEmployeePinColumnMigration', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0),
('20240818163524', 'OrderItemUuidColumnMigration', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0),
('20240818173921', 'OrderItemsAddColumnEbtMigration', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0),
('20240818174204', 'OrderItemsAddColumnCrvMigration', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0),
('20240818174254', 'OrderItemsAddColumnCrvTaxableMigration', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0),
('20240818174353', 'OrderItemsAddColumnLabelPrintMigration', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0),
('20240818174438', 'OrderItemsAddColumnKitechnPrintMigration', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0),
('20240818174935', 'OrderItemsAddColumnWeightMigration', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0),
('20240821052355', 'MergeOnlineVendorToAccountMigration', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0),
('20240821052754', 'GroupMenuMigration', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0),
('20240821053429', 'InvoiceMigration', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0),
('20240821053859', 'OnlineItemsToItemsMigration', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0),
('20240821072120', 'ItemGroupMigration', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0),
('20240821072229', 'MenuMigration', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0),
('20240821072435', 'MenuStoreMigration', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0),
('20240821072538', 'OnlineOrderGroupMigration', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0),
('20240821073227', 'OrdersMigration', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0),
('20240821073746', 'PendingOrderMigration', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0),
('20240821073933', 'StaggeredStoreHoursMigration', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0),
('20240821074032', 'StoresMigration', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0),
('20240821074129', 'StoreHoursMigration', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0),
('20240822030957', 'OrderPaymentUuidMigration', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0),
('20240831041157', 'PendingOrderAddColumnMerchantIdMigration', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0),
('20240920075545', 'PendingOrderAddColumnStatus', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0),
('20241217050633', 'TerminalPaymentMethodMigration', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0),
('20241217084326', 'TerminalAlterColumnSystemMigration', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, 0);

=== query olapay transactions

```mysql
SELECT
    accounts.id,
    accounts.companyname AS business,
    JSON_UNQUOTE(
            JSON_EXTRACT(jsonOlaPay.content, '$.amount')
        ) as amount,
        JSON_UNQUOTE(
            JSON_EXTRACT(jsonOlaPay.content, '$.trans_type')
        ) as trans_type,
        JSON_UNQUOTE(
            JSON_EXTRACT(jsonOlaPay.content, '$.Status')
        ) as status,
        JSON_UNQUOTE(
            JSON_EXTRACT(jsonOlaPay.content, '$.auth_code')
        ) as auth_code,
        JSON_UNQUOTE(
            JSON_EXTRACT(jsonOlaPay.content, '$.command')
        ) as command,
        JSON_UNQUOTE(
            JSON_EXTRACT(jsonOlaPay.content, '$.orderID')
        ) as orderID,
        JSON_UNQUOTE(
            JSON_EXTRACT(jsonOlaPay.content, '$.processor')
        ) as processor,
        JSON_UNQUOTE(
            JSON_EXTRACT(jsonOlaPay.content, '$.response_message')
        ) as response_message,
        JSON_UNQUOTE(
            JSON_EXTRACT(jsonOlaPay.content, '$.trans_id')
        ) as trans_id
FROM
    jsonOlaPay
JOIN terminals ON terminals.serial = jsonOlaPay.serial
JOIN accounts ON terminals.vendors_id = accounts.id
WHERE
    CAST(jsonOlaPay.lastmod AS UNSIGNED) >= 1736531976 AND CAST(jsonOlaPay.lastmod AS UNSIGNED) <= 1736614776 AND accounts.id IN(134);
```

## Procedure

```mysql
DELIMITER //

CREATE PROCEDURE sync_inventory_from_account(IN p_account_id INT)
BEGIN
    -- Step 1: Query accounts for the given ID
    DECLARE v_account_exists INT;
    SELECT COUNT(*) INTO v_account_exists
    FROM accounts
    WHERE id = p_account_id;

    -- Only proceed if account exists
    IF v_account_exists > 0 THEN
        -- Step 2 & 3: Get items for the account and upsert into inventories
        -- Excluding items with NULL or empty sku, upc, or name
        INSERT INTO inventories (vendors_id, sku, upc, name, enterdate)
        SELECT
            i.vendors_id,
            i.sku,
            i.upc,
            i.name,
            NOW() as enterdate
        FROM items i
        JOIN accounts a ON a.id = i.vendors_id
        WHERE a.id = p_account_id
        AND i.sku IS NOT NULL AND i.sku != ''
        AND i.upc IS NOT NULL AND i.upc != ''
        AND i.name IS NOT NULL AND i.name != ''
        ON DUPLICATE KEY UPDATE
            upc = VALUES(upc),
            name = VALUES(name);

        -- Step 4: Insert inventory logs for each inventory item
        INSERT INTO inventoryLogs (inventoryId, quantity, reason, enterdate)
        SELECT
            id,
            1,
            'BEGINNING_BALANCE',
            NOW() as enterdate
        FROM inventories
        WHERE vendors_id = p_account_id
        AND id NOT IN (
            -- Exclude inventories that already have a BEGINNING_BALANCE log
            SELECT DISTINCT inventoryId
            FROM inventoryLogs
            WHERE reason = 'BEGINNING_BALANCE'
        );
    END IF;

END //

DELIMITER ;
```

```mysql
DELIMITER //

CREATE PROCEDURE upsert_item(
    IN p_agents_id INT,
    IN p_vendors_id INT,
    IN p_terminals_id INT,
    IN p_items_id INT,
    IN p_desc TEXT,
    IN p_sku VARCHAR(255),
    IN p_cost INT,
    IN p_price INT,
    IN p_notes VARCHAR(255),
    IN p_upc VARCHAR(255),
    IN p_taxable INT,
    IN p_taxrate FLOAT,
    IN p_group INT,
    IN p_amount_on_hand INT,
    IN p_enterdate DATETIME,
    IN p_lastmod INT,
    IN p_name VARCHAR(255),
    IN p_uuid VARCHAR(255),
    IN p_price_currency VARCHAR(255),
    IN p_status TINYINT,
    IN p_type_display TINYINT,
    IN p_metadata JSON,
    IN p_group_belong_type SMALLINT UNSIGNED,
    IN p_image_url VARCHAR(255),
    IN p_print_type INT,
    IN p_is_ebt TINYINT(1),
    IN p_is_manual_price TINYINT(1),
    IN p_is_weighted TINYINT(1),
    IN p_crv JSON
)
BEGIN
    DECLARE item_exists INT;

    -- Check if item exists
    SELECT COUNT(*) INTO item_exists
    FROM items
    WHERE uuid = p_uuid;

    IF item_exists > 0 THEN
        -- Update existing record
        UPDATE items
        SET
            agents_id = COALESCE(p_agents_id, -1),
            vendors_id = COALESCE(p_vendors_id, -1),
            terminals_id = COALESCE(p_terminals_id, -1),
            items_id = COALESCE(p_items_id, -1),
            `desc` = p_desc,
            sku = p_sku,
            cost = COALESCE(p_cost, 0),
            price = p_price,
            notes = COALESCE(p_notes, ''),
            upc = p_upc,
            taxable = COALESCE(p_taxable, 0),
            taxrate = COALESCE(p_taxrate, 0.0),
            `group` = COALESCE(p_group, 0),
            amount_on_hand = COALESCE(p_amount_on_hand, 0),
            enterdate = COALESCE(p_enterdate, NOW()),
            lastmod = COALESCE(p_lastmod, UNIX_TIMESTAMP()),
            name = p_name,
            price_currency = COALESCE(p_price_currency, 'USD'),
            status = p_status,
            type_display = p_type_display,
            metadata = p_metadata,
            group_belong_type = COALESCE(p_group_belong_type, 0),
            image_url = p_image_url,
            print_type = COALESCE(p_print_type, 0),
            is_ebt = COALESCE(p_is_ebt, 0),
            is_manual_price = COALESCE(p_is_manual_price, 0),
            is_weighted = COALESCE(p_is_weighted, 0),
            crv = p_crv,
            is_active = 1
        WHERE uuid = p_uuid;
    ELSE
        -- Insert new record
        INSERT INTO items (
            agents_id,
            vendors_id,
            terminals_id,
            items_id,
            `desc`,
            sku,
            cost,
            price,
            notes,
            upc,
            taxable,
            taxrate,
            `group`,
            amount_on_hand,
            enterdate,
            lastmod,
            name,
            uuid,
            price_currency,
            status,
            type_display,
            metadata,
            group_belong_type,
            image_url,
            print_type,
            is_ebt,
            is_manual_price,
            is_weighted,
            crv,
            is_active
        ) VALUES (
            COALESCE(p_agents_id, -1),
            COALESCE(p_vendors_id, -1),
            COALESCE(p_terminals_id, -1),
            COALESCE(p_items_id, -1),
            p_desc,
            p_sku,
            COALESCE(p_cost, 0),
            p_price,
            COALESCE(p_notes, ''),
            p_upc,
            COALESCE(p_taxable, 0),
            COALESCE(p_taxrate, 0.0),
            COALESCE(p_group, 0),
            COALESCE(p_amount_on_hand, 0),
            COALESCE(p_enterdate, NOW()),
            COALESCE(p_lastmod, UNIX_TIMESTAMP()),
            p_name,
            p_uuid,
            COALESCE(p_price_currency, 'USD'),
            p_status,
            p_type_display,
            p_metadata,
            COALESCE(p_group_belong_type, 0),
            p_image_url,
            COALESCE(p_print_type, 0),
            COALESCE(p_is_ebt, 0),
            COALESCE(p_is_manual_price, 0),
            COALESCE(p_is_weighted, 0),
            p_crv,
            1
        );
    END IF;
END //

DELIMITER ;
```

## Sample query #1 - Online platform - no inventory - insert order and order payment in 1 call

{
"serial":"368e6763-94bc-4373-a4d3-20669132d840",
"json":"{\"isOnlinePlatform\":true,\"hasInventory\":false,\n\"orders\":\"[{\\\"employeeId\\\":0,\\\"id\\\":3,\\\"lastMod\\\":1739350175,\\\"notes\\\":\\\"\\\",\\\"uuid\\\":\\\"ec3ba98a-ed2b-4d71-8078-e0114ed6370f\\\",\\\"orderReference\\\":3,\\\"onlineorder_id\\\":\\\"83-213-4\\\",\\\"onlinetrans_id\\\":\\\"385043318209360\\\",\\\"orderDate\\\":\\\"2025-02-12T08:49:35.779Z\\\",\\\"orderName\\\":\\\"\\\",\\\"status\\\":\\\"PAID\\\",\\\"subTotal\\\":0.01,\\\"tax\\\":0,\\\"delivery_fee\\\":0,\\\"delivery_type\\\":\\\"SELF-PICKUP\\\",\\\"total\\\":0.01}]\",\n\"payments\":\"[{\\\"amtPaid\\\":\\\"0.01\\\",\\\"employeeId\\\":0,\\\"id\\\":\\\"4\\\",\\\"lastMod\\\":1739350175,\\\"orderID\\\":3,\\\"orderUUID\\\":\\\"ec3ba98a-ed2b-4d71-8078-e0114ed6370f\\\",\\\"orderReference\\\":3,\\\"payDate\\\":\\\"02/12/2025 08:50:19 AM\\\",\\\"refNumber\\\":\\\"4\\\",\\\"refund\\\":0,\\\"status\\\":\\\"PAID\\\",\\\"techfee\\\":0,\\\"tips\\\":0,\\\"total\\\":\\\"0.01\\\"}]\"}"
}

## Sample query #2 - Online platform - has inventory - no payment - no order id - call #1

{
"serial":"368e6763-94bc-4373-a4d3-20669132d840",
"json":"{\"isOnlinePlatform\":true,\"hasInventory\":true,\n\"orders\":\"[{\\\"employeeId\\\":0,\\\"lastMod\\\":1739350175,\\\"notes\\\":\\\"\\\",\\\"uuid\\\":\\\"ec3ba98a-ed2b-4d71-8078-e0114ed6370f\\\",\\\"orderReference\\\":3,\\\"onlineorder_id\\\":\\\"83-213-4\\\",\\\"onlinetrans_id\\\":\\\"385043318209360\\\",\\\"orderDate\\\":\\\"2025-02-12T08:49:35.779Z\\\",\\\"orderName\\\":\\\"\\\",\\\"status\\\":\\\"PAID\\\",\\\"subTotal\\\":0.01,\\\"tax\\\":0,\\\"delivery_fee\\\":0,\\\"delivery_type\\\":\\\"SELF-PICKUP\\\",\\\"total\\\":0.01}]\"}"
}

## Sample query #3 - Online platform - has inventory - has payment - has order id - call #2

{
"serial":"368e6763-94bc-4373-a4d3-20669132d840",
"json":"{\"isOnlinePlatform\":true,\"hasInventory\":true,\n\"orders\":\"[{\\\"employeeId\\\":0,\\\"id\\\":3,\\\"lastMod\\\":1739350175,\\\"notes\\\":\\\"\\\",\\\"uuid\\\":\\\"ec3ba98a-ed2b-4d71-8078-e0114ed6370f\\\",\\\"orderReference\\\":3,\\\"onlineorder_id\\\":\\\"83-213-4\\\",\\\"onlinetrans_id\\\":\\\"385043318209360\\\",\\\"orderDate\\\":\\\"2025-02-12T08:49:35.779Z\\\",\\\"orderName\\\":\\\"\\\",\\\"status\\\":\\\"PAID\\\",\\\"subTotal\\\":0.01,\\\"tax\\\":0,\\\"delivery_fee\\\":0,\\\"delivery_type\\\":\\\"SELF-PICKUP\\\",\\\"total\\\":0.01}]\",\n\"payments\":\"[{\\\"amtPaid\\\":\\\"0.01\\\",\\\"employeeId\\\":0,\\\"id\\\":\\\"4\\\",\\\"lastMod\\\":1739350175,\\\"orderID\\\":3,\\\"orderUUID\\\":\\\"ec3ba98a-ed2b-4d71-8078-e0114ed6370f\\\",\\\"orderReference\\\":3,\\\"payDate\\\":\\\"02/12/2025 08:50:19 AM\\\",\\\"refNumber\\\":\\\"4\\\",\\\"refund\\\":0,\\\"status\\\":\\\"PAID\\\",\\\"techfee\\\":0,\\\"tips\\\":0,\\\"total\\\":\\\"0.01\\\"}]\"}"
}


### Sample query total sales
```mysql

SELECT op.vendors_id, SUM(op.total) as total_sale FROM `ordersPayments` as op WHERE op.lastMod > 1739987375 GROUP BY op.vendors_id ORDER BY total_sale DESC;
```

### Procedure to delete menu and related data

```mysql

-- Add this procedure after all the table definitions:

DELIMITER //

DROP PROCEDURE IF EXISTS delete_menu_cascade //
CREATE PROCEDURE delete_menu_cascade(IN menu_uuid VARCHAR(255))
BEGIN
    -- Declare variables for debugging
    DECLARE debug_msg TEXT;
    DECLARE affected_rows INT;
    
    -- Declare variables to handle errors
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1
            @sqlstate = RETURNED_SQLSTATE,
            @errno = MYSQL_ERRNO,
            @text = MESSAGE_TEXT;
        
        -- Get the current debug message
        SET debug_msg = CONCAT(IFNULL(debug_msg, ''), 
            '\nError occurred at step: ', IFNULL(@current_step, 'unknown'),
            '\nAffected rows in last operation: ', IFNULL(affected_rows, 0));
            
        -- Rollback the transaction
        ROLLBACK;
        
        -- Create detailed error message
        SET @error_message = CONCAT('{',
            '"sqlstate": "', @sqlstate, '", ',
            '"errno": "', @errno, '", ',
            '"text": "', REPLACE(@text, '"', '\\"'), '", ',
            '"debug_info": "', REPLACE(debug_msg, '"', '\\"'), '",',
            '"menu_uuid": "', menu_uuid, '"',
            '}');
            
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = @error_message;
    END;

    -- Start transaction
    START TRANSACTION;
    
    SET debug_msg = 'Starting delete_menu_cascade procedure';
    SET @current_step = 'initial menu check';
    
    -- Check if menu exists and get its ID
    SELECT id INTO @menu_id FROM menus WHERE uuid = menu_uuid COLLATE utf8mb4_unicode_ci;
    SET affected_rows = ROW_COUNT();
    SET debug_msg = CONCAT(debug_msg, '\nMenu ID found: ', IFNULL(@menu_id, 'null'), ' (affected rows: ', affected_rows, ')');

    -- Create temporary table with explicit collation
    SET @current_step = 'creating temporary table';
    CREATE TEMPORARY TABLE temp_group_uuids (
        group_uuid VARCHAR(255) COLLATE utf8mb4_unicode_ci
    );
    
    -- Store group UUIDs with explicit collation
    SET @current_step = 'storing group UUIDs';
    INSERT INTO temp_group_uuids 
    SELECT DISTINCT first_column 
    FROM group_menu 
    WHERE second_column = menu_uuid COLLATE utf8mb4_unicode_ci;
    SET affected_rows = ROW_COUNT();
    SET debug_msg = CONCAT(debug_msg, '\nGroup UUIDs stored: ', affected_rows);

    -- Delete items linked to groups
    SET @current_step = 'deleting linked items';
    DELETE items 
    FROM items 
    INNER JOIN item_group ON items.uuid COLLATE utf8mb4_unicode_ci = item_group.first_column
    WHERE item_group.second_column IN (SELECT group_uuid FROM temp_group_uuids);
    SET affected_rows = ROW_COUNT();
    SET debug_msg = CONCAT(debug_msg, '\nItems deleted: ', affected_rows);

    -- Delete item_group entries
    SET @current_step = 'deleting item_group entries';
    DELETE FROM item_group 
    WHERE second_column IN (SELECT group_uuid FROM temp_group_uuids);
    SET affected_rows = ROW_COUNT();
    SET debug_msg = CONCAT(debug_msg, '\nItem-group links deleted: ', affected_rows);

    -- Delete groups
    SET @current_step = 'deleting groups';
    DELETE FROM online_order_groups 
    WHERE uuid IN (SELECT group_uuid FROM temp_group_uuids);
    SET affected_rows = ROW_COUNT();
    SET debug_msg = CONCAT(debug_msg, '\nGroups deleted: ', affected_rows);

    -- Delete group_menu entries
    SET @current_step = 'deleting group_menu entries';
    DELETE FROM group_menu 
    WHERE second_column = menu_uuid COLLATE utf8mb4_unicode_ci;
    SET affected_rows = ROW_COUNT();
    SET debug_msg = CONCAT(debug_msg, '\nGroup-menu links deleted: ', affected_rows);

    -- Delete the menu itself
    SET @current_step = 'deleting menu';
    DELETE FROM menus 
    WHERE uuid = menu_uuid COLLATE utf8mb4_unicode_ci;
    SET affected_rows = ROW_COUNT();
    SET debug_msg = CONCAT(debug_msg, '\nMenu deleted: ', affected_rows);

    -- Drop temporary table
    SET @current_step = 'cleanup';
    DROP TEMPORARY TABLE IF EXISTS temp_group_uuids;

    -- Commit transaction
    COMMIT;
    
    -- Return success message with debug info
    SELECT CONCAT('Successfully deleted menu cascade. Debug info: ', debug_msg) AS result;
END //

DROP PROCEDURE IF EXISTS insert_test_menu_data //
CREATE PROCEDURE insert_test_menu_data()
BEGIN
    -- Declare variables to handle errors
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1
            @sqlstate = RETURNED_SQLSTATE,
            @errno = MYSQL_ERRNO,
            @text = MESSAGE_TEXT;
        ROLLBACK;
        -- Format the error details into a JSON-like string
        SET @error_message = CONCAT('{"sqlstate": "', @sqlstate, '", ',
                                  '"errno": "', @errno, '", ',
                                  '"text": "', @text, '"}');
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = @error_message;
    END;

    -- Start transaction
    START TRANSACTION;
    
    -- First ensure test vendor exists
    INSERT IGNORE INTO accounts (
        id,
        accounts_id,
        firstname,
        lastname,
        email,
        password,
        role,
        companyname,
        address,
        landline,
        mobile,
        title,
        enterdate
    ) VALUES (
        1,
        1,
        'Test',
        'Vendor',
        'test@example.com',
        'password123',
        'vendor',
        'Test Company',
        '123 Test St',
        '555-0000',
        '555-1111',
        'Owner',
        NOW()
    );
    
    -- Insert test menu
    INSERT INTO menus (uuid, vendor_id, name, enterdate, lastmod, metadata, num_stores) 
    VALUES (
        'test-menu-uuid-123',
        1,
        'Test Menu',
        NOW(),
        UNIX_TIMESTAMP(),
        '{"description": "Test menu for deletion testing"}',
        1
    );

    -- Insert test groups
    INSERT INTO online_order_groups (
        vendor_id, 
        group_type, 
        name, 
        description, 
        uuid, 
        is_active,
        type_display,
        lastmod,
        enterdate,
        metadata
    ) VALUES 
    (1, 1, 'Beverages', 'Test beverage group', 'test-group-uuid-1', 1, 1, UNIX_TIMESTAMP(), NOW(), '{}'),
    (1, 1, 'Food', 'Test food group', 'test-group-uuid-2', 1, 1, UNIX_TIMESTAMP(), NOW(), '{}');

    -- Link groups to menu
    INSERT INTO group_menu (first_column, second_column, lastmod, enterdate) 
    VALUES 
    ('test-group-uuid-1', 'test-menu-uuid-123', UNIX_TIMESTAMP(), NOW()),
    ('test-group-uuid-2', 'test-menu-uuid-123', UNIX_TIMESTAMP(), NOW());

    -- Insert test items
    INSERT INTO items (
        vendors_id,
        name,
        uuid,
        price,
        cost,
        `desc`,
        is_active,
        enterdate,
        lastmod,
        notes,
        upc,
        taxable,
        taxrate,
        amount_on_hand
    ) VALUES 
    (1, 'Coffee', 'test-item-uuid-1', 399, 100, 'Test coffee item', 1, NOW(), UNIX_TIMESTAMP(), '', '', 0, 0.0, 0),
    (1, 'Tea', 'test-item-uuid-2', 299, 50, 'Test tea item', 1, NOW(), UNIX_TIMESTAMP(), '', '', 0, 0.0, 0),
    (1, 'Burger', 'test-item-uuid-3', 999, 300, 'Test burger item', 1, NOW(), UNIX_TIMESTAMP(), '', '', 0, 0.0, 0),
    (1, 'Fries', 'test-item-uuid-4', 499, 100, 'Test fries item', 1, NOW(), UNIX_TIMESTAMP(), '', '', 0, 0.0, 0);

    -- Link items to groups
    INSERT INTO item_group (first_column, second_column, lastmod, enterdate) 
    VALUES 
    ('test-item-uuid-1', 'test-group-uuid-1', UNIX_TIMESTAMP(), NOW()),
    ('test-item-uuid-2', 'test-group-uuid-1', UNIX_TIMESTAMP(), NOW()),
    ('test-item-uuid-3', 'test-group-uuid-2', UNIX_TIMESTAMP(), NOW()),
    ('test-item-uuid-4', 'test-group-uuid-2', UNIX_TIMESTAMP(), NOW());

    -- Commit transaction
    COMMIT;
    
    -- Output success message
    SELECT 'Test data inserted successfully. Menu UUID: test-menu-uuid-123' AS Result;
END //

DELIMITER ;
```

### Verify the inserted data

```mysql
SELECT * FROM menus WHERE uuid = 'test-menu-uuid-123';
SELECT * FROM online_order_groups WHERE vendor_id = 1;
SELECT * FROM items WHERE vendors_id = 1;
```

### Delete the test data

```mysql
CALL insert_test_menu_data();
CALL delete_menu_cascade('test-menu-uuid-123');
CALL delete_menu_related_data('test-menu-uuid-123');

CALL delete_menu_related_data('5fe690a9-fbe9-477a-8b74-6b28a04cdaab');
CALL delete_menu_related_data('e8e4d4bf-5f81-4553-8f87-e3e27f571541');
CALL delete_menu_related_data('ded0ca29-9a64-40c0-bdb2-519027b6499c');
CALL delete_menu_related_data('f255b64c-80c1-4a05-a040-f1eb8360df64');


CALL delete_menu_cascade('5fe690a9-fbe9-477a-8b74-6b28a04cdaab');
CALL delete_menu_cascade('e8e4d4bf-5f81-4553-8f87-e3e27f571541');
CALL delete_menu_cascade('ded0ca29-9a64-40c0-bdb2-519027b6499c');
CALL delete_menu_cascade('f255b64c-80c1-4a05-a040-f1eb8360df64');
```

### Verify everything was deleted:

```mysql
SELECT * FROM menus WHERE uuid = 'test-menu-uuid-123';
SELECT * FROM online_order_groups WHERE uuid IN ('test-group-uuid-1', 'test-group-uuid-2');
SELECT * FROM items WHERE uuid IN ('test-item-uuid-1', 'test-item-uuid-2', 'test-item-uuid-3', 'test-item-uuid-4');
SELECT * FROM group_menu WHERE second_column = 'test-menu-uuid-123';
SELECT * FROM item_group WHERE second_column IN ('test-group-uuid-1', 'test-group-uuid-2');
```

### Procedure to delete menu and related data

```mysql
DELIMITER //

DROP PROCEDURE IF EXISTS delete_menu_related_data //
CREATE PROCEDURE delete_menu_related_data(IN menu_uuid VARCHAR(255))
BEGIN
    -- Declare variables for debugging
    DECLARE debug_msg TEXT;
    DECLARE affected_rows INT;
    
    -- Declare variables to handle errors
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        GET DIAGNOSTICS CONDITION 1
            @sqlstate = RETURNED_SQLSTATE,
            @errno = MYSQL_ERRNO,
            @text = MESSAGE_TEXT;
        
        -- Get the current debug message
        SET debug_msg = CONCAT(IFNULL(debug_msg, ''), 
            '\nError occurred at step: ', IFNULL(@current_step, 'unknown'),
            '\nAffected rows in last operation: ', IFNULL(affected_rows, 0));
            
        -- Rollback the transaction
        ROLLBACK;
        
        -- Create detailed error message
        SET @error_message = CONCAT('{',
            '"sqlstate": "', @sqlstate, '", ',
            '"errno": "', @errno, '", ',
            '"text": "', REPLACE(@text, '"', '\\"'), '", ',
            '"debug_info": "', REPLACE(debug_msg, '"', '\\"'), '",',
            '"menu_uuid": "', menu_uuid, '"',
            '}');
            
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = @error_message;
    END;

    -- Start transaction
    START TRANSACTION;
    
    SET debug_msg = 'Starting delete_menu_related_data procedure';
    SET @current_step = 'initial menu check';
    
    -- Check if menu exists and get its ID
    SELECT id INTO @menu_id FROM menus WHERE uuid = menu_uuid COLLATE utf8mb4_unicode_ci;
    SET affected_rows = ROW_COUNT();
    SET debug_msg = CONCAT(debug_msg, '\nMenu ID found: ', IFNULL(@menu_id, 'null'), ' (affected rows: ', affected_rows, ')');

    -- Create temporary table with explicit collation
    SET @current_step = 'creating temporary table';
    CREATE TEMPORARY TABLE temp_group_uuids (
        group_uuid VARCHAR(255) COLLATE utf8mb4_unicode_ci
    );
    
    -- Store group UUIDs with explicit collation
    SET @current_step = 'storing group UUIDs';
    INSERT INTO temp_group_uuids 
    SELECT DISTINCT first_column 
    FROM group_menu 
    WHERE second_column = menu_uuid COLLATE utf8mb4_unicode_ci;
    SET affected_rows = ROW_COUNT();
    SET debug_msg = CONCAT(debug_msg, '\nGroup UUIDs stored: ', affected_rows);

    -- Delete items linked to groups
    SET @current_step = 'deleting linked items';
    DELETE items 
    FROM items 
    INNER JOIN item_group ON items.uuid COLLATE utf8mb4_unicode_ci = item_group.first_column
    WHERE item_group.second_column IN (SELECT group_uuid FROM temp_group_uuids);
    SET affected_rows = ROW_COUNT();
    SET debug_msg = CONCAT(debug_msg, '\nItems deleted: ', affected_rows);

    -- Delete item_group entries
    SET @current_step = 'deleting item_group entries';
    DELETE FROM item_group 
    WHERE second_column IN (SELECT group_uuid FROM temp_group_uuids);
    SET affected_rows = ROW_COUNT();
    SET debug_msg = CONCAT(debug_msg, '\nItem-group links deleted: ', affected_rows);

    -- Delete groups
    SET @current_step = 'deleting groups';
    DELETE FROM online_order_groups 
    WHERE uuid IN (SELECT group_uuid FROM temp_group_uuids);
    SET affected_rows = ROW_COUNT();
    SET debug_msg = CONCAT(debug_msg, '\nGroups deleted: ', affected_rows);

    -- Delete group_menu entries
    SET @current_step = 'deleting group_menu entries';
    DELETE FROM group_menu 
    WHERE second_column = menu_uuid COLLATE utf8mb4_unicode_ci;
    SET affected_rows = ROW_COUNT();
    SET debug_msg = CONCAT(debug_msg, '\nGroup-menu links deleted: ', affected_rows);

    -- Drop temporary table
    SET @current_step = 'cleanup';
    DROP TEMPORARY TABLE IF EXISTS temp_group_uuids;

    -- Commit transaction
    COMMIT;
    
    -- Return success message with debug info
    SELECT CONCAT('Successfully deleted menu related data. Menu itself is preserved. Debug info: ', debug_msg) AS result;
END //

DELIMITER ;
```

---
Update jsonOlaPay record by
- serial
- lastmod
- limit

```mysql
DROP PROCEDURE IF EXISTS process_jsonolapay_batch$$
DELIMITER //

CREATE PROCEDURE process_jsonolapay_batch(
    IN p_serial VARCHAR(255),
    IN p_starting_lastmod BIGINT,
    IN p_limit INT
)
BEGIN
    DECLARE id_list TEXT;
    
    -- Query jsonOlaPay table to get IDs based on input parameters
    SELECT GROUP_CONCAT(id SEPARATOR ',') INTO id_list
    FROM jsonOlaPay
    WHERE serial = p_serial
    AND lastmod >= p_starting_lastmod
    ORDER BY id ASC
    LIMIT p_limit;
    
    -- Check if any records were found
    IF id_list IS NOT NULL AND LENGTH(id_list) > 0 THEN
        -- Call the update procedure with the concatenated ID list
        CALL update_jsonolapay_lastmod(id_list);
        
        -- Return success message with the number of IDs processed
        SELECT CONCAT('Successfully processed ', 
                      (LENGTH(id_list) - LENGTH(REPLACE(id_list, ',', ''))) + 1, 
                      ' records with IDs: ', id_list) AS result;
    ELSE
        -- Return message when no records found
        SELECT CONCAT('No matching records found for serial: ', 
                     p_serial, 
                     ' with lastmod >= ', 
                     p_starting_lastmod) AS result;
    END IF;
END //

DELIMITER ;
```

example call:

CALL process_jsonolapay_batch('your_serial_number', 1736139398, 100);


==== Test update tip / techfee / total

php vendor/bin/phpunit tests/UpdateOrderTipsTest.php --filter=testUpdateOrderTipsWithLocalDb


====

Log check ORDER2

sudo tail -f /var/log/apache2/error.log | grep '\[ORDERS2-API\]'

====
update php.ini

[Date]
; Defines the default timezone used by the date functions
; https://php.net/date.timezone
date.timezone = "America/Los_Angeles"

====

DELETE json using vendor id and lastmod

WITH vendor_terminal AS (
    SELECT serial
    FROM `terminals`
    WHERE `vendors_id` = 159
)
DELETE *
FROM `json`
WHERE serial IN (SELECT serial FROM vendor_terminal)
  AND lastmod >= '2025-10-01 00:00:00';

====

Current cron

sudo su -

crontab -l

there is a cron job to reconcile orders

0 * * * * cd /home/olaportal/olaportal/api && TZ=America/Los_Angeles timeout 3600 /usr/bin/php reconcile_orders.php --date=$(TZ=America/Los_Angeles date +\%Y-\%m-\%d) >> /var/log/apache2/reconcile_orders.log 2>&1 || echo "Reconcile failed on $(date)" >> /var/log/apache2/reconcile_errors.log


===

needed indexes

CREATE INDEX idx_items_vendor_uuid ON items(vendors_id, uuid);

CREATE INDEX idx_item_group_mapping ON item_group(first_column, second_column);

CREATE INDEX idx_group_menu_mapping ON group_menu(second_column, first_column);