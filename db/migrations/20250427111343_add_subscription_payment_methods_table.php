<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddSubscriptionPaymentMethodsTable extends AbstractMigration
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
        $table = $this->table('subscription_payment_methods', ['id' => false, 'primary_key' => ['id']]);
        $table->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
              ->addColumn('customer_id', 'biginteger', ['signed' => false, 'null' => false])
              ->addColumn('payment_type', 'string', ['limit' => 20, 'null' => false])
              ->addColumn('payment_token', 'string', ['null' => false])
              ->addColumn('last_four', 'string', ['limit' => 4, 'null' => true])
              ->addColumn('expiry_date', 'string', ['limit' => 7, 'null' => true])
              ->addColumn('card_type', 'string', ['limit' => 50, 'null' => true])
              ->addColumn('billing_address_id', 'biginteger', ['signed' => false, 'null' => true])
              ->addColumn('is_default', 'boolean', ['null' => false, 'default' => false])
              ->addColumn('status', 'string', ['limit' => 10, 'null' => false, 'default' => 'active'])
              ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['customer_id'], ['name' => 'idx_spm_customer_id'])
              ->addIndex(['billing_address_id'], ['name' => 'idx_spm_billing_address_id'])
              ->addIndex(['payment_type'], ['name' => 'idx_spm_payment_type'])
              ->addIndex(['is_default'], ['name' => 'idx_spm_is_default'])
              ->addIndex(['status'], ['name' => 'idx_spm_status'])
              ->addIndex(['created_at'], ['name' => 'idx_spm_created_at'])
              ->create();
    }
}
