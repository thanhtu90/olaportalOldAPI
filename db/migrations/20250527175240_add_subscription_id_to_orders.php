<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSubscriptionIdToOrders extends AbstractMigration
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
        
        // Check if subscription_id column exists before adding it
        if (!$table->hasColumn('subscription_id')) {
            $table->addColumn('subscription_id', 'integer', [
                'null' => true,
                'after' => 'payment_link'
            ])->update();
        }
    }
}
