<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class StoresMigration extends AbstractMigration
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
        $table = $this->table('stores');
        $columnsToAdd = [];
        
        if (!$table->hasColumn('uuid')) {
            $columnsToAdd['uuid'] = ['type' => 'string', 'length' => 36, 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('vendor_id')) {
            $columnsToAdd['vendor_id'] = ['type' => 'integer', 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('active_menu_id')) {
            $columnsToAdd['active_menu_id'] = ['type' => 'integer', 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('active_storehour_id')) {
            $columnsToAdd['active_storehour_id'] = ['type' => 'integer', 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('enterdate')) {
            $columnsToAdd['enterdate'] = ['type' => 'date', 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('lastmod')) {
            $columnsToAdd['lastmod'] = ['type' => 'integer', 'signed' => false, 'null' => false];
        }
        if (!$table->hasColumn('address')) {
            $columnsToAdd['address'] = ['type' => 'text', 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('name')) {
            $columnsToAdd['name'] = ['type' => 'string', 'length' => 25, 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('phone')) {
            $columnsToAdd['phone'] = ['type' => 'string', 'length' => 15, 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('prepare_time')) {
            $columnsToAdd['prepare_time'] = ['type' => 'integer', 'signed' => false, 'default' => 30, 'null' => true];
        }
        if (!$table->hasColumn('timezone')) {
            $columnsToAdd['timezone'] = ['type' => 'string', 'length' => 255, 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('close_at_once')) {
            $columnsToAdd['close_at_once'] = ['type' => 'json', 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('banner')) {
            $columnsToAdd['banner'] = ['type' => 'json', 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('techfee_rate')) {
            $columnsToAdd['techfee_rate'] = ['type' => 'decimal', 'precision' => 10, 'scale' => 4, 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('logo')) {
            $columnsToAdd['logo'] = ['type' => 'json', 'default' => null, 'null' => true];
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
