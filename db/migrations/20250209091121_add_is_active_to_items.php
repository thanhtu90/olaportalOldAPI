<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddIsActiveToItems extends AbstractMigration
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
        $table = $this->table('items');
        
        // Only add is_active if it doesn't exist and status doesn't exist
        if (!$table->hasColumn('is_active') && !$table->hasColumn('status')) {
            $table->addColumn('is_active', 'boolean', [
                'default' => true,
                'null' => false,
                'after' => 'id'
            ])
            ->update();
        }
    }
}
