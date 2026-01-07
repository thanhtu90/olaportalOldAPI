<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class PendingOrderMigration extends AbstractMigration
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
        $table = $this->table('pending_orders');
        $columnsToAdd = [];
        
        if (!$table->hasColumn('content')) {
            $columnsToAdd['content'] = ['type' => 'json', 'null' => false];
        }
        if (!$table->hasColumn('lastmod')) {
            $columnsToAdd['lastmod'] = ['type' => 'biginteger', 'default' => null];
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
