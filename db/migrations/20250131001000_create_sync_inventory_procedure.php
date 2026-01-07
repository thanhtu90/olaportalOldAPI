<?php

use Phinx\Migration\AbstractMigration;

class CreateSyncInventoryProcedure extends AbstractMigration
{
    public function up()
    {
        $sql = <<<SQL
CREATE PROCEDURE sync_inventory_from_account(IN p_account_id INT)
BEGIN
    DECLARE v_account_exists INT;
    
    -- Step 1: Check if account exists
    SELECT COUNT(*) INTO v_account_exists
    FROM accounts
    WHERE id = p_account_id;
    
    -- Only proceed if account exists
    IF v_account_exists > 0 THEN
        -- Step 2 & 3: Get items for the account and upsert into inventories
        INSERT INTO inventories (vendors_id, sku, upc, name, enterdate)
        SELECT 
            i.vendors_id,
            i.sku,
            i.upc,
            i.name,
            NOW() as enterdate
        FROM items i
        WHERE i.vendors_id = p_account_id
        ON DUPLICATE KEY UPDATE
            upc = VALUES(upc),
            name = VALUES(name);
    END IF;
END;
SQL;
        $this->execute($sql);
    }

    public function down()
    {
        $this->execute("DROP PROCEDURE IF EXISTS sync_inventory_from_account");
    }
}
