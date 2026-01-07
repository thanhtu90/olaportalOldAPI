<?php

use Phinx\Migration\AbstractMigration;

class CreateOlapayMerchantsRegistry extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
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
    public function change()
    {
        // Create olapay_merchants_registry table
        $table = $this->table('olapay_merchants_registry', [
            'id' => false,  // Use custom primary key
            'primary_key' => 'merchant_id',
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_0900_ai_ci'
        ]);
        
        $table->addColumn('merchant_id', 'integer', [
                'null' => false,
                'signed' => false,
                'comment' => 'Primary key - Reference to accounts.id'
            ])
            ->addColumn('business_name', 'string', [
                'limit' => 255,
                'null' => false,
                'comment' => 'Cached business name from accounts.companyname'
            ])
            ->addColumn('is_olapay_only', 'boolean', [
                'null' => false,
                'default' => true,
                'comment' => 'True if merchant uses only OlaPay (excludes OlaPos merchants)'
            ])
            ->addColumn('last_transaction_date', 'date', [
                'null' => true,
                'comment' => 'Date of last transaction for cleanup purposes'
            ])
            ->addColumn('status', 'enum', [
                'values' => ['active', 'inactive'],
                'null' => false,
                'default' => 'active',
                'comment' => 'Merchant status'
            ])
            ->addColumn('created_at', 'timestamp', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP'
            ])
            ->addColumn('updated_at', 'timestamp', [
                'null' => false,
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP'
            ])
            // Performance indexes
            ->addIndex(['is_olapay_only'], [
                'name' => 'idx_is_olapay_only'
            ])
            ->addIndex(['status'], [
                'name' => 'idx_status'
            ])
            ->addIndex(['last_transaction_date'], [
                'name' => 'idx_last_transaction_date'
            ])
            ->addIndex(['status', 'is_olapay_only'], [
                'name' => 'idx_status_olapay_only'
            ])
            ->create();
    }
} 