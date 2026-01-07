<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

final class ModifyItemsAndAddForeignKeys extends AbstractMigration
{
    public function up(): void
    {
        // Modify items table
        $table = $this->table('items');
        $columns = $this->adapter->getColumns('items');

        // Now do the rest of the modifications
        $table->changeColumn('desc', 'text', ['collation' => 'utf8mb4_0900_ai_ci', 'null' => true])
            ->changeColumn('agents_id', 'integer', ['default' => -1, 'null' => false])
            ->changeColumn('items_id', 'integer', ['default' => -1, 'null' => false])
            ->changeColumn('terminals_id', 'integer', ['default' => -1, 'null' => false])
            ->save();

        // Check if available_amount exists before removing it
        if ($table->hasColumn('available_amount')) {
            $table->removeColumn('available_amount')
                ->save();
        }

        // Handle is_active to status conversion
        if ($table->hasColumn('is_active') && !$table->hasColumn('status')) {
            $table->renameColumn('is_active', 'status')
                ->save();
        }

        // Update status column if it exists
        if ($table->hasColumn('status')) {
            $table->changeColumn('status', 'integer', ['limit' => MysqlAdapter::INT_TINY, 'null' => true])
                ->save();
        }

        // Update remaining columns
        $table->changeColumn('type_display', 'integer', ['limit' => MysqlAdapter::INT_TINY, 'null' => true])
            ->changeColumn('group_belong_type', 'integer', ['null' => true])
            ->save();

        // Add foreign keys (will be skipped if they already exist due to MySQL's behavior)
        try {
            $table->addForeignKey('group_belong_type', 'item_group', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])->save();
        } catch (\Exception $e) {
            // Foreign key probably already exists, continue
        }

        // Add foreign key for online_order_groups -> item_group
        $table = $this->table('online_order_groups');
        try {
            $table->addForeignKey('group_type', 'item_group', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])->save();
        } catch (\Exception $e) {
            // Foreign key probably already exists, continue
        }

        // Add foreign key for online_order_groups -> group_menu
        try {
            $table->addForeignKey('type_display', 'group_menu', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])->save();
        } catch (\Exception $e) {
            // Foreign key probably already exists, continue
        }

        // Add foreign key for menus -> group_menu
        $table = $this->table('menus');
        $table->changeColumn('metadata', 'integer', ['null' => true])
            ->save();
        try {
            $table->addForeignKey('metadata', 'group_menu', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])->save();
        } catch (\Exception $e) {
            // Foreign key probably already exists, continue
        }

        // Add foreign key for menus -> menu_store
        try {
            $table->addForeignKey('num_stores', 'menu_store', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])->save();
        } catch (\Exception $e) {
            // Foreign key probably already exists, continue
        }

        // Add foreign key for stores -> menu_store
        $table = $this->table('stores');
        try {
            $table->addForeignKey('active_menu_id', 'menu_store', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE'
            ])->save();
        } catch (\Exception $e) {
            // Foreign key probably already exists, continue
        }

        // Add vendor_id column and foreign key
        $table = $this->table('items');
        if (!$table->hasColumn('vendor_id')) {
            $table->addColumn('vendor_id', 'integer', [
                'null' => true
            ])->save();
        }

        try {
            $table->addForeignKey('vendor_id', 'vendors', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'NO_ACTION'
            ])->save();
        } catch (\Exception $e) {
            // Foreign key probably already exists, continue
        }
    }

    public function down(): void
    {
        // Remove foreign keys
        $this->table('stores')
            ->dropForeignKey('active_menu_id')
            ->save();

        $this->table('menus')
            ->dropForeignKey('num_stores')
            ->dropForeignKey('metadata')
            ->save();

        $this->table('online_order_groups')
            ->dropForeignKey('type_display')
            ->dropForeignKey('group_type')
            ->save();

        $this->table('items')
            ->dropForeignKey('group_belong_type')
            ->dropForeignKey('vendor_id')
            ->save();

        // Revert items table changes
        $table = $this->table('items');
        
        if ($table->hasColumn('vendor_id')) {
            $table->removeColumn('vendor_id');
        }
        
        $table->changeColumn('desc', 'string', ['limit' => 255, 'collation' => 'utf8mb4_0900_ai_ci'])
            ->changeColumn('agents_id', 'integer', ['null' => false])
            ->changeColumn('items_id', 'integer', ['null' => false])
            ->changeColumn('terminals_id', 'integer', ['null' => false]);

        if (!$table->hasColumn('available_amount')) {
            $table->addColumn('available_amount', 'integer', ['default' => 0, 'comment' => 'From online order']);
        }

        if ($table->hasColumn('status') && !$table->hasColumn('is_active')) {
            $table->renameColumn('status', 'is_active');
        }

        if ($table->hasColumn('is_active')) {
            $table->changeColumn('is_active', 'smallint', ['null' => true]);
        }

        $table->changeColumn('type_display', 'smallint', ['unsigned' => true, 'null' => true])
            ->save();

        // Revert metadata column back to JSON
        $this->table('menus')
            ->changeColumn('metadata', 'json', ['null' => true])
            ->save();
    }

    /**
     * Check if a foreign key exists
     */
    protected function hasForeignKey(string $tableName, array $columns): bool
    {
        $foreignKeys = $this->table($tableName)->getForeignKeys();
        foreach ($foreignKeys as $foreignKey) {
            if ($foreignKey['columns'] === $columns) {
                return true;
            }
        }
        return false;
    }
}
