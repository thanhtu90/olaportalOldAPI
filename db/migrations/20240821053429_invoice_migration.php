<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InvoiceMigration extends AbstractMigration
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
        $table = $this->table('invoice');
        $columnsToAdd = [];
        
        if (!$table->hasColumn('ref')) {
            $columnsToAdd['ref'] = ['type' => 'string', 'null' => false];
        }
        if (!$table->hasColumn('profile_id')) {
            $columnsToAdd['profile_id'] = ['type' => 'string', 'null' => false];
        }
        if (!$table->hasColumn('amount')) {
            $columnsToAdd['amount'] = ['type' => 'float', 'null' => false];
        }
        if (!$table->hasColumn('status')) {
            $columnsToAdd['status'] = ['type' => 'smallinteger', 'null' => true];
        }
        
        if (!empty($columnsToAdd)) {
            $table->addColumns($columnsToAdd)->update();
        }
    }
}
