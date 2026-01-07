<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class OnlinePendingOrders extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('online_pending_orders', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'encoding' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci'
        ]);

        $table->addColumn('id', 'integer', [
            'null' => false,
            'identity' => true
        ])
            ->addColumn('content', 'json', [
                'null' => false
            ])
            ->addColumn('lastmod', 'biginteger', [
                'null' => true,
                'default' => null
            ])
            ->addColumn('merchant_id', 'biginteger', [
                'null' => true,
                'default' => null
            ])
            ->addColumn('terminal_serial', 'string', [
                'limit' => 255,
                'null' => true,
                'default' => null
            ])
            ->addColumn('uuid', 'string', [
                'limit' => 255,
                'null' => true,
                'default' => null
            ])
            ->addColumn('status', 'string', [
                'limit' => 255,
                'null' => true,
                'default' => null
            ])
            ->addIndex(['id'], [
                'unique' => true,
                'name' => 'idx_id'
            ])
            ->create();
    }
}
