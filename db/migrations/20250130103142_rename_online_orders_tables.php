<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RenameOnlineOrdersTables extends AbstractMigration
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
    public function up(): void
    {
        $exists = $this->hasTable('ol_orders');
        
        if (!$exists) {
            // Only rename if target table doesn't exist and source table does
            if ($this->hasTable('online_orders')) {
                $this->table('online_orders')
                    ->rename('ol_orders')
                    ->save();
            }
        }

        $exists = $this->hasTable('ol_order_items');
        
        if (!$exists) {
            // Only rename if target table doesn't exist and source table does
            if ($this->hasTable('online_order_items')) {
                $this->table('online_order_items')
                    ->rename('ol_order_items')
                    ->save();
            }
        }
    }

    public function down(): void
    {
        if ($this->hasTable('ol_orders')) {
            $this->table('ol_orders')
                ->rename('online_orders')
                ->save();
        }

        if ($this->hasTable('ol_order_items')) {
            $this->table('ol_order_items')
                ->rename('online_order_items')
                ->save();
        }
    }
}
