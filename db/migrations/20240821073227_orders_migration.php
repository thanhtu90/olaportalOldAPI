<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class OrdersMigration extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        $table = $this->table('orders');
        $columnsToAdd = [];
        
        if (!$table->hasColumn('store_uuid')) {
            $columnsToAdd['store_uuid'] = ['type' => 'string', 'length' => 36, 'null' => true];
        }
        if (!$table->hasColumn('terminal_id')) {
            $columnsToAdd['terminal_id'] = ['type' => 'integer', 'null' => true];
        }
        if (!$table->hasColumn('tip')) {
            $columnsToAdd['tip'] = ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => '0.00'];
        }
        if (!$table->hasColumn('tech_fee')) {
            $columnsToAdd['tech_fee'] = ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => '0.00'];
        }
        if (!$table->hasColumn('delivery_info')) {
            $columnsToAdd['delivery_info'] = ['type' => 'json', 'default' => null];
        }
        if (!$table->hasColumn('customer_info')) {
            $columnsToAdd['customer_info'] = ['type' => 'json', 'default' => null];
        }
        if (!$table->hasColumn('payment_info')) {
            $columnsToAdd['payment_info'] = ['type' => 'json', 'default' => null];
        }
        if (!$table->hasColumn('order_items')) {
            $columnsToAdd['order_items'] = ['type' => 'json', 'default' => null];
        }
        if (!$table->hasColumn('discount_amount')) {
            $columnsToAdd['discount_amount'] = ['type' => 'decimal', 'precision' => 10, 'scale' => 2, 'default' => '0.00'];
        }
        if (!$table->hasColumn('delivery_status')) {
            $columnsToAdd['delivery_status'] = ['type' => 'string', 'length' => 255, 'default' => null];
        }
        if (!$table->hasColumn('delivery_service_update_payload')) {
            $columnsToAdd['delivery_service_update_payload'] = ['type' => 'json', 'default' => null];
        }
        if (!$table->hasColumn('prep_time')) {
            $columnsToAdd['prep_time'] = ['type' => 'integer', 'default' => '30'];
        }
        if (!$table->hasColumn('pending_order_id')) {
            $columnsToAdd['pending_order_id'] = ['type' => 'integer', 'default' => null];
        }
        if (!$table->hasColumn('new_order_timestamp')) {
            $columnsToAdd['new_order_timestamp'] = ['type' => 'integer', 'default' => null];
        }
        
        if (!empty($columnsToAdd)) {
            $table->addColumns($columnsToAdd)->update();
        }
    }
}
