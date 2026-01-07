<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class MergeOnlineVendorToAccountMigration extends AbstractMigration
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
        $table = $this->table('accounts');
        $columnsToAdd = [];
        
        if (!$table->hasColumn('logo')) {
            $columnsToAdd['logo'] = ['type' => 'string', 'null' => true];
        }
        if (!$table->hasColumn('processor_info')) {
            $columnsToAdd['processor_info'] = ['type' => 'string', 'null' => true];
        }
        if (!$table->hasColumn('processor_type')) {
            $columnsToAdd['processor_type'] = ['type' => 'string', 'null' => true];
        }
        
        if (!empty($columnsToAdd)) {
            $table->addColumns($columnsToAdd)->update();
        }
    }
}
