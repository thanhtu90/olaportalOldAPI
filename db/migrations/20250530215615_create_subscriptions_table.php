<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateSubscriptionsTable extends AbstractMigration
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
        // Create the subscriptions table if it doesn't exist
        if (!$this->hasTable('subscriptions')) {
            $table = $this->table('subscriptions', ['id' => true, 'primary_key' => 'id']);
            
            // Add new fields
            $table
                ->addColumn('store_id', 'integer', ['signed' => false, 'null' => false])
                ->addColumn('start_date', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('end_date', 'timestamp', ['null' => true])
                ->addColumn('last_billing_date', 'timestamp', ['null' => true])
                ->addColumn('billing_period_start', 'timestamp', ['null' => false])
                ->addColumn('billing_period_end', 'timestamp', ['null' => true])
                ->addColumn('cancellation_date', 'timestamp', ['null' => true])
                ->addColumn('cancellation_reason', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('payment_method_id', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('status', 'string', ['limit' => 10, 'null' => false, 'default' => 'pending'])
                ->addColumn('quantity', 'integer', ['signed' => false, 'null' => false, 'default' => 1])
                ->addColumn('current_price', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => false])
                ->addColumn('billing_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => false, 'default' => 0])
                ->addColumn('metadata', 'json', ['null' => true])
                ->addColumn('billing_cycle', 'string', ['limit' => 20, 'null' => true])
                ->addColumn('is_prorated_first_payment', 'boolean', ['null' => false, 'default' => false])
                ->addColumn('has_processed_first_payment', 'boolean', ['null' => false, 'default' => false])
                ->addColumn('created_at', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->create();
                
            // Add indexes
            $table
                ->addIndex(['store_id'])
                ->addIndex(['status'])
                ->addIndex(['payment_method_id'])
                ->update();
        } else {
            // If the table exists, we need to update it
            $table = $this->table('subscriptions');
            
            // Rename columns
            if ($table->hasColumn('vendor_id')) {
                $table->renameColumn('vendor_id', 'store_id');
            }
            
            if ($table->hasColumn('started_at')) {
                $table->renameColumn('started_at', 'start_date');
            }
            
            if ($table->hasColumn('ended_at')) {
                $table->renameColumn('ended_at', 'end_date');
            }
            
            // Change payment_method_id type if it exists
            if ($table->hasColumn('payment_method_id')) {
                $table->changeColumn('payment_method_id', 'string', ['limit' => 255, 'null' => true]);
            }
            
            // Change status size if it exists
            if ($table->hasColumn('status')) {
                $table->changeColumn('status', 'string', ['limit' => 10, 'null' => false, 'default' => 'pending']);
            }
            
            // Add new columns if they don't exist
            if (!$table->hasColumn('last_billing_date')) {
                $table->addColumn('last_billing_date', 'timestamp', ['null' => true]);
            }
            
            if (!$table->hasColumn('billing_period_start')) {
                $table->addColumn('billing_period_start', 'timestamp', ['null' => false]);
            }
            
            if (!$table->hasColumn('billing_period_end')) {
                $table->addColumn('billing_period_end', 'timestamp', ['null' => true]);
            }
            
            if (!$table->hasColumn('cancellation_date')) {
                $table->addColumn('cancellation_date', 'timestamp', ['null' => true]);
            }
            
            if (!$table->hasColumn('cancellation_reason')) {
                $table->addColumn('cancellation_reason', 'string', ['limit' => 255, 'null' => true]);
            }
            
            if (!$table->hasColumn('quantity')) {
                $table->addColumn('quantity', 'integer', ['signed' => false, 'null' => false, 'default' => 1]);
            }
            
            if (!$table->hasColumn('current_price')) {
                $table->addColumn('current_price', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => false]);
            }
            
            if (!$table->hasColumn('billing_amount')) {
                $table->addColumn('billing_amount', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => false, 'default' => 0]);
            }
            
            if (!$table->hasColumn('metadata')) {
                $table->addColumn('metadata', 'json', ['null' => true]);
            }
            
            if (!$table->hasColumn('billing_cycle')) {
                $table->addColumn('billing_cycle', 'string', ['limit' => 20, 'null' => true]);
            }
            
            if (!$table->hasColumn('is_prorated_first_payment')) {
                $table->addColumn('is_prorated_first_payment', 'boolean', ['null' => false, 'default' => false]);
            }
            
            if (!$table->hasColumn('has_processed_first_payment')) {
                $table->addColumn('has_processed_first_payment', 'boolean', ['null' => false, 'default' => false]);
            }
            
            $table->update();
        }
    }
}
