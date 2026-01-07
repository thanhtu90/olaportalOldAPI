<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class OnlineOrderGroupMigration extends AbstractMigration
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
        $table = $this->table('online_order_groups');
        $columnsToAdd = [];
        
        if (!$table->hasColumn('group_type')) {
            $columnsToAdd['group_type'] = ['type' => 'smallinteger', 'null' => true, 'signed' => false];
        }
        if (!$table->hasColumn('name')) {
            $columnsToAdd['name'] = ['type' => 'string', 'null' => true];
        }
        if (!$table->hasColumn('description')) {
            $columnsToAdd['description'] = ['type' => 'string', 'null' => true];
        }
        if (!$table->hasColumn('uuid')) {
            $columnsToAdd['uuid'] = ['type' => 'string', 'null' => true];
        }
        if (!$table->hasColumn('is_active')) {
            $columnsToAdd['is_active'] = ['type' => 'smallinteger', 'null' => true];
        }
        if (!$table->hasColumn('type_display')) {
            $columnsToAdd['type_display'] = ['type' => 'smallinteger', 'null' => true, 'signed' => false];
        }
        if (!$table->hasColumn('lastmod')) {
            $columnsToAdd['lastmod'] = ['type' => 'integer', 'null' => true, 'signed' => false];
        }
        if (!$table->hasColumn('enterdate')) {
            $columnsToAdd['enterdate'] = ['type' => 'datetime', 'null' => true];
        }
        if (!$table->hasColumn('metadata')) {
            $columnsToAdd['metadata'] = ['type' => 'json', 'null' => true];
        }
        
        if (!empty($columnsToAdd)) {
            $table->addColumns($columnsToAdd)->update();
        }
    }
}
