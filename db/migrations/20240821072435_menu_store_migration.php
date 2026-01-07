<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class MenuStoreMigration extends AbstractMigration
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
        $table = $this->table('menu_store');
        $columnsToAdd = [];
        
        if (!$table->hasColumn('first_column')) {
            $columnsToAdd['first_column'] = ['type' => 'string', 'null' => true];
        }
        if (!$table->hasColumn('second_column')) {
            $columnsToAdd['second_column'] = ['type' => 'string', 'null' => true];
        }
        if (!$table->hasColumn('lastmod')) {
            $columnsToAdd['lastmod'] = ['type' => 'integer', 'null' => true];
        }
        if (!$table->hasColumn('enterdate')) {
            $columnsToAdd['enterdate'] = ['type' => 'datetime', 'null' => true];
        }
        
        if (!empty($columnsToAdd)) {
            $table->addColumns($columnsToAdd)->update();
        }
    }
}
