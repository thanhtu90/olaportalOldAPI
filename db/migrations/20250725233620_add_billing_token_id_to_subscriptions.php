<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddBillingTokenIdToSubscriptions extends AbstractMigration
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
        $table = $this->table('subscriptions');
        
        // Add billing_token_id column if it doesn't exist
        if (!$table->hasColumn('billing_token_id')) {
            $table->addColumn('billing_token_id', 'integer', [
                'null' => true,
                'signed' => true,
                'after' => 'payment_method_id'
            ]);
            
            // Add index for better performance
            $table->addIndex(['billing_token_id']);
            
            $table->update();
        }
    }
} 