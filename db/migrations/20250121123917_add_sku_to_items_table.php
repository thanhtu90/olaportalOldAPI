<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSkuToItemsTable extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    addCustomColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Any other destructive changes will result in an error when trying to
     * rollback the migration.
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change(): void
    {
        $table = $this->table('items');
        
        // Only add sku if it doesn't exist
        if (!$table->hasColumn('sku')) {
            $table->addColumn('sku', 'string', [
                'limit' => 255,
                'null' => true,
                'after' => 'id'
            ])
            ->update();
        }
    }
}
