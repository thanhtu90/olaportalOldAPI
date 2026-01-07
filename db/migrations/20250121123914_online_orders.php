<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class OnlineOrders extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('online_orders', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'encoding' => 'latin1'
        ]);

        $table->addColumn('id', 'integer', [
            'null' => false,
            'identity' => true
        ])
            ->addColumn('orderReference', 'integer', [
                'null' => false
            ])
            ->addColumn('agents_id', 'integer', [
                'null' => false
            ])
            ->addColumn('vendors_id', 'integer', [
                'null' => false
            ])
            ->addColumn('terminals_id', 'integer', [
                'null' => false
            ])
            ->addColumn('subTotal', 'float', [
                'null' => false
            ])
            ->addColumn('tax', 'float', [
                'null' => false
            ])
            ->addColumn('total', 'float', [
                'null' => false
            ])
            ->addColumn('notes', 'text', [
                'null' => false
            ])
            ->addColumn('orderName', 'string', [
                'limit' => 255,
                'null' => false
            ])
            ->addColumn('employee_id', 'integer', [
                'null' => false
            ])
            ->addColumn('OrderDate', 'datetime', [
                'null' => true,
                'default' => null
            ])
            ->addColumn('delivery_type', 'integer', [
                'limit' => MysqlAdapter::INT_TINY,
                'null' => false
            ])
            ->addColumn('delivery_fee', 'float', [
                'null' => false,
                'default' => 0
            ])
            ->addColumn('status', 'integer', [
                'null' => false
            ])
            ->addColumn('lastMod', 'integer', [
                'null' => false
            ])
            ->addColumn('uuid', 'string', [
                'limit' => 255,
                'null' => true,
                'default' => null
            ])
            ->addColumn('employee_pin', 'string', [
                'limit' => 255,
                'null' => true,
                'default' => null
            ])
            ->addColumn('store_uuid', 'string', [
                'limit' => 36,
                'null' => true,
                'default' => null
            ])
            ->addColumn('terminal_id', 'integer', [
                'null' => true,
                'default' => null
            ])
            ->addColumn('tip', 'decimal', [
                'precision' => 10,
                'scale' => 2,
                'null' => true,
                'default' => '0.00'
            ])
            ->addColumn('tech_fee', 'decimal', [
                'precision' => 10,
                'scale' => 2,
                'null' => true,
                'default' => '0.00'
            ])
            ->addColumn('delivery_info', 'json', [
                'null' => true,
                'default' => null
            ])
            ->addColumn('customer_info', 'json', [
                'null' => true,
                'default' => null
            ])
            ->addColumn('payment_info', 'json', [
                'null' => true,
                'default' => null
            ])
            ->addColumn('order_items', 'json', [
                'null' => true,
                'default' => null
            ])
            ->addColumn('discount_amount', 'decimal', [
                'precision' => 10,
                'scale' => 2,
                'null' => true,
                'default' => '0.00'
            ])
            ->addColumn('delivery_status', 'string', [
                'limit' => 255,
                'null' => true,
                'default' => null
            ])
            ->addColumn('delivery_service_update_payload', 'json', [
                'null' => true,
                'default' => null
            ])
            ->addColumn('prep_time', 'integer', [
                'null' => true,
                'default' => 30
            ])
            ->addColumn('pending_order_id', 'integer', [
                'null' => true,
                'default' => null
            ])
            ->addColumn('new_order_timestamp', 'integer', [
                'null' => true,
                'default' => null
            ])
            ->addColumn('onlineorder_id', 'string', [
                'limit' => 255,
                'null' => true,
                'default' => ''
            ])
            ->addColumn('onlinetrans_id', 'string', [
                'limit' => 255,
                'null' => true,
                'default' => null
            ])
            ->create();
    }
}
