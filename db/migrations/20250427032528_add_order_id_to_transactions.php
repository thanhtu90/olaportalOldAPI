<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddOrderIdToTransactions extends AbstractMigration
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
        $this->table('transactions')
            ->addColumn('order_id', 'biginteger', [
                'signed' => false,
                'null' => true,
                'after' => 'id'
            ])
            ->addIndex(['order_id'], [
                'name' => 'idx_transaction_order',
            ])
            ->update();
    }
}
