<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class StaggeredStoreHoursMigration extends AbstractMigration
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
        $table = $this->table('staggered_store_hours');
        $columnsToAdd = [];
        
        if (!$table->hasColumn('uuid')) {
            $columnsToAdd['uuid'] = ['type' => 'char', 'length' => 36, 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('vendor_id')) {
            $columnsToAdd['vendor_id'] = ['type' => 'integer', 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('name')) {
            $columnsToAdd['name'] = [
                'type' => 'string',
                'length' => 255,
                'default' => 'Business Hour',
                'null' => true,
                'collation' => 'utf8mb4_0900_ai_ci',
            ];
        }
        if (!$table->hasColumn('enterdate')) {
            $columnsToAdd['enterdate'] = ['type' => 'date', 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('lastmod')) {
            $columnsToAdd['lastmod'] = ['type' => 'integer', 'signed' => false, 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('monday')) {
            $columnsToAdd['monday'] = ['type' => 'json', 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('tuesday')) {
            $columnsToAdd['tuesday'] = ['type' => 'json', 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('wednesday')) {
            $columnsToAdd['wednesday'] = ['type' => 'json', 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('thursday')) {
            $columnsToAdd['thursday'] = ['type' => 'json', 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('friday')) {
            $columnsToAdd['friday'] = ['type' => 'json', 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('saturday')) {
            $columnsToAdd['saturday'] = ['type' => 'json', 'default' => null, 'null' => true];
        }
        if (!$table->hasColumn('sunday')) {
            $columnsToAdd['sunday'] = ['type' => 'json', 'default' => null, 'null' => true];
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
