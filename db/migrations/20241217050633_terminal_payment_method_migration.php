<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class TerminalPaymentMethodMigration extends AbstractMigration
{
    public function change(): void
    {
        // Check if table exists before creating
        if (!$this->hasTable('payment_methods')) {
            $this->table('payment_methods')
                ->addColumn('name', 'string', ['limit' => 255])
                ->addColumn('description', 'text', ['null' => true])
                ->addColumn('is_active', 'boolean', ['default' => true])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->create();
        }

        // Check if table exists before creating
        if (!$this->hasTable('terminal_payment_methods')) {
            $this->table('terminal_payment_methods')
                ->addColumn('terminal_id', 'integer')
                ->addColumn('payment_method_id', 'integer')
                ->addColumn('is_active', 'boolean', ['default' => true])
                ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['terminal_id', 'payment_method_id'], ['unique' => true])
                ->create();
        }
    }
}
