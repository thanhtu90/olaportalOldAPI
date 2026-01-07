<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUniqueOlapayTransactions extends AbstractMigration
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
        $table = $this->table('unique_olapay_transactions', [
            'id' => false,
            'primary_key' => ['id']
        ]);
        
        $table->addColumn('id', 'integer', [
            'identity' => true,
            'signed' => false,
        ])
        ->addColumn('serial', 'string', [
            'limit' => 100,
            'null' => false,
        ])
        ->addColumn('content', 'text', [
            'null' => false,
        ])
        ->addColumn('lastmod', 'biginteger', [
            'null' => false,
        ])
        ->addColumn('order_id', 'string', [
            'limit' => 50,
            'null' => true,
        ])
        ->addColumn('trans_date', 'string', [
            'limit' => 50,
            'null' => true,
        ])
        ->addColumn('trans_id', 'string', [
            'limit' => 100,
            'null' => true,
        ])
        ->addColumn('created_at', 'datetime', [
            'default' => 'CURRENT_TIMESTAMP',
        ])
        ->addIndex(['serial', 'order_id', 'trans_date', 'trans_id'], [
            'unique' => true,
            'name' => 'unique_transaction'
        ])
        ->addIndex(['serial'], [
            'name' => 'idx_serial'
        ])
        ->addIndex(['lastmod'], [
            'name' => 'idx_lastmod'
        ])
        ->addIndex(['trans_id'], [
            'name' => 'idx_trans_id'
        ])
        ->create();
    }
} 