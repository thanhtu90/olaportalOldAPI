<?php

use Phinx\Migration\AbstractMigration;

class CreateMerchantDailyOlapayStats extends AbstractMigration
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
        // Create merchant_daily_olapay_stats table
        $table = $this->table('merchant_daily_olapay_stats', [
            'id' => 'id',
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_0900_ai_ci'
        ]);
        
        $table->addColumn('merchant_id', 'integer', [
                'null' => false,
                'signed' => false,
                'comment' => 'Reference to accounts.id'
            ])
            ->addColumn('business_name', 'string', [
                'limit' => 255,
                'null' => false,
                'comment' => 'Cached business name for performance'
            ])
            ->addColumn('date', 'date', [
                'null' => false,
                'comment' => 'Date for which stats are calculated'
            ])
            ->addColumn('transaction_count', 'integer', [
                'null' => false,
                'default' => 0,
                'signed' => false,
                'comment' => 'Number of successful transactions'
            ])
            ->addColumn('total_amount', 'decimal', [
                'precision' => 12,
                'scale' => 2,
                'null' => false,
                'default' => 0.00,
                'comment' => 'Total transaction amount before refunds'
            ])
            ->addColumn('refund_amount', 'decimal', [
                'precision' => 12,
                'scale' => 2,
                'null' => false,
                'default' => 0.00,
                'comment' => 'Total refund amount'
            ])
            ->addColumn('net_amount', 'decimal', [
                'precision' => 12,
                'scale' => 2,
                'null' => false,
                'default' => 0.00,
                'comment' => 'Net amount (total - refunds)'
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
            // Performance-critical indexes
            ->addIndex(['merchant_id', 'date'], [
                'unique' => true,
                'name' => 'idx_merchant_date'
            ])
            ->addIndex(['date', 'net_amount'], [
                'name' => 'idx_date_net_amount'
            ])
            ->addIndex(['merchant_id'], [
                'name' => 'idx_merchant_id'
            ])
            ->addIndex(['created_at'], [
                'name' => 'idx_created_at'
            ])
            ->create();
    }
} 