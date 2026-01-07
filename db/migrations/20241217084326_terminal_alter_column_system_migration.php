<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class TerminalAlterColumnSystemMigration extends AbstractMigration
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
        $table = $this->table('terminal_payment_methods');
        
        // First check if the old column exists
        if ($table->hasColumn('system_id')) {
            // Rename system_id to payment_method_id if it exists
            $table->renameColumn('system_id', 'payment_method_id')
                  ->update();
        } else if (!$table->hasColumn('payment_method_id')) {
            // If neither column exists, add payment_method_id
            $table->addColumn('payment_method_id', 'integer', ['null' => true])
                  ->update();
        }
    }
}
