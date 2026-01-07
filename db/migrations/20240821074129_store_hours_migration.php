<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class StoreHoursMigration extends AbstractMigration
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
        $table = $this->table('store_hours');
        $columnsToAdd = [];
        
        if (!$table->hasColumn('vendor_id')) {
            $columnsToAdd['vendor_id'] = ['type' => 'integer', 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('uuid')) {
            $columnsToAdd['uuid'] = ['type' => 'char', 'length' => 36, 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('enterdate')) {
            $columnsToAdd['enterdate'] = ['type' => 'date', 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('lastmod')) {
            $columnsToAdd['lastmod'] = ['type' => 'integer', 'signed' => false, 'null' => false];
        }
        if (!$table->hasColumn('monday_open')) {
            $columnsToAdd['monday_open'] = ['type' => 'time', 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('monday_close')) {
            $columnsToAdd['monday_close'] = ['type' => 'time', 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('tuesday_open')) {
            $columnsToAdd['tuesday_open'] = ['type' => 'time', 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('tuesday_close')) {
            $columnsToAdd['tuesday_close'] = ['type' => 'time', 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('wednesday_open')) {
            $columnsToAdd['wednesday_open'] = ['type' => 'time', 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('wednesday_close')) {
            $columnsToAdd['wednesday_close'] = ['type' => 'time', 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('thursday_open')) {
            $columnsToAdd['thursday_open'] = ['type' => 'time', 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('thursday_close')) {
            $columnsToAdd['thursday_close'] = ['type' => 'time', 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('friday_open')) {
            $columnsToAdd['friday_open'] = ['type' => 'time', 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('friday_close')) {
            $columnsToAdd['friday_close'] = ['type' => 'time', 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('saturday_open')) {
            $columnsToAdd['saturday_open'] = ['type' => 'time', 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('saturday_close')) {
            $columnsToAdd['saturday_close'] = ['type' => 'time', 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('sunday_open')) {
            $columnsToAdd['sunday_open'] = ['type' => 'time', 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('sunday_close')) {
            $columnsToAdd['sunday_close'] = ['type' => 'time', 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('name')) {
            $columnsToAdd['name'] = ['type' => 'string', 'length' => 255, 'default' => 'Business Hour', 'null' => true];
        }
        
        if (!empty($columnsToAdd)) {
            $table->addColumns($columnsToAdd)->update();
        }
        
        // Add index if it doesn't exist
        if (!$table->hasIndex(['id'], ['unique' => true])) {
            $table->addIndex(['id'], ['unique' => true, 'name' => 'idx_id'])
                ->update();
        }
    }
}
