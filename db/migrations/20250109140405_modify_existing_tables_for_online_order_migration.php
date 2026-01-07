<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ModifyExistingTablesForOnlineOrderMigration extends AbstractMigration
{
    /*
     * Fields that online order needs to change when using api2:
     * 
     * orders table:
     * - terminal_id -> terminals_id (api2 uses terminals_id)
     * - enterdate -> OrderDate (api2 uses OrderDate)
     * - lastmod -> needs to handle int instead of bigint
     * - delivery_type -> needs to handle varchar(32) instead of tinyint
     * - onlineorder_id -> already exists
     * - onlinetrans_id -> already exists
     * 
     * stores table:
     * - name -> needs to handle 25 char limit instead of 255
     * - lastmod -> needs to handle int UNSIGNED instead of int
     * 
     * items table:
     * - lastmod -> needs to handle int UNSIGNED instead of int
     * - group_belong_type -> needs to handle tinyint UNSIGNED instead of tinyint
     * - type_display -> needs to handle tinyint UNSIGNED instead of tinyint
     * 
     * online_order_groups table:
     * - group_type -> needs to handle tinyint UNSIGNED instead of tinyint
     * - type_display -> needs to handle tinyint UNSIGNED instead of tinyint
     * - lastmod -> needs to handle int UNSIGNED instead of int
     * 
     * menu_store table:
     * - first_column -> needs to handle varchar(255) instead of char(36)
     * - second_column -> needs to handle varchar(255) instead of char(36)
     * 
     * group_menu table:
     * - enterdate -> needs to handle datetime instead of date
     * - lastmod -> needs to handle int instead of int UNSIGNED
     * 
     * item_group table:
     * - enterdate -> needs to handle datetime instead of date
     * - lastmod -> needs to handle int instead of int UNSIGNED
     */

    public function up(): void
    {
        // Modify orders table
        $table = $this->table('orders');
        if ($table->hasColumn('OrderDate')) {
            $table->changeColumn('OrderDate', 'datetime', ['null' => true]);
        }
        if ($table->hasColumn('delivery_type')) {
            $table->changeColumn('delivery_type', 'integer', ['limit' => \Phinx\Db\Adapter\MysqlAdapter::INT_TINY, 'null' => false]);
        }
        $table->update();

        // Modify stores table
        $table = $this->table('stores');
        $needsUpdate = false;
        
        if (!$table->hasColumn('delivery_feature')) {
            $table->addColumn('delivery_feature', 'json', ['null' => true, 'comment' => 'From online order']);
            $needsUpdate = true;
        }
        if (!$table->hasColumn('delivery_settings')) {
            $table->addColumn('delivery_settings', 'json', ['null' => true]);
            $needsUpdate = true;
        }
        if (!$table->hasColumn('online_order_settings')) {
            $table->addColumn('online_order_settings', 'json', ['null' => true]);
            $needsUpdate = true;
        }
        if (!$table->hasColumn('online_payment_settings')) {
            $table->addColumn('online_payment_settings', 'json', ['null' => true]);
            $needsUpdate = true;
        }
        
        if ($table->hasColumn('name')) {
            $table->changeColumn('name', 'string', ['limit' => 255, 'null' => true]);
            $needsUpdate = true;
        }
        
        if ($needsUpdate) {
            $table->update();
        }

        // Modify items table
        $table = $this->table('items');
        $needsUpdate = false;
        
        if (!$table->hasColumn('available_amount')) {
            $table->addColumn('available_amount', 'integer', ['default' => 0, 'null' => true, 'comment' => 'From online order']);
            $needsUpdate = true;
        }
        if (!$table->hasColumn('print_type')) {
            $table->addColumn('print_type', 'integer', ['default' => 0, 'null' => true, 'comment' => 'From online order']);
            $needsUpdate = true;
        }
        if (!$table->hasColumn('is_ebt')) {
            $table->addColumn('is_ebt', 'boolean', ['default' => 0, 'null' => true, 'comment' => 'From online order']);
            $needsUpdate = true;
        }
        if (!$table->hasColumn('is_manual_price')) {
            $table->addColumn('is_manual_price', 'boolean', ['default' => 0, 'null' => true, 'comment' => 'From online order']);
            $needsUpdate = true;
        }
        if (!$table->hasColumn('is_weighted')) {
            $table->addColumn('is_weighted', 'boolean', ['default' => 0, 'null' => true, 'comment' => 'From online order']);
            $needsUpdate = true;
        }
        if (!$table->hasColumn('crv')) {
            $table->addColumn('crv', 'json', ['null' => true, 'comment' => 'From online order']);
            $needsUpdate = true;
        }
        
        if ($needsUpdate) {
            $table->update();
        }
    }

    public function down(): void
    {
        // Remove columns from items table
        $table = $this->table('items');
        $columnsToRemove = ['crv', 'is_weighted', 'is_manual_price', 'is_ebt', 'print_type', 'available_amount'];
        foreach ($columnsToRemove as $column) {
            if ($table->hasColumn($column)) {
                $table->removeColumn($column);
            }
        }
        $table->update();

        // Revert stores table changes
        $table = $this->table('stores');
        $storeColumnsToRemove = ['delivery_feature', 'delivery_settings', 'online_order_settings', 'online_payment_settings'];
        foreach ($storeColumnsToRemove as $column) {
            if ($table->hasColumn($column)) {
                $table->removeColumn($column);
            }
        }
        if ($table->hasColumn('name')) {
            $table->changeColumn('name', 'string', ['limit' => 25, 'null' => true]);
        }
        $table->update();

        // Revert orders table changes
        $table = $this->table('orders');
        if ($table->hasColumn('OrderDate')) {
            $table->changeColumn('OrderDate', 'integer', ['null' => true]);
        }
        if ($table->hasColumn('delivery_type')) {
            $table->changeColumn('delivery_type', 'string', ['limit' => 32, 'null' => false, 'default' => '']);
        }
        $table->update();
    }
}
