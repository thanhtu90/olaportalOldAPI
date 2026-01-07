<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class SubscriptionManagementImplementation extends AbstractMigration
{
    public function up()
    {
        // Create customer table
        if (!$this->hasTable('customer')) {
            $table = $this->table('customer', ['id' => true, 'primary_key' => 'id', 'signed' => false, 'id_type' => 'biginteger']);
            $table
                ->addColumn('uuid', 'string', ['limit' => 36, 'null' => false])
                ->addColumn('email', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('phone', 'string', ['limit' => 20, 'null' => true])
                ->addColumn('status', 'string', ['limit' => 20, 'default' => 'active'])
                ->addColumn('fivserv_security_token', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('created_at', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['uuid'], ['unique' => true])
                ->addIndex(['email'], ['unique' => true, 'name' => 'uk_customer_email'])
                ->addIndex(['status'], ['name' => 'idx_customer_status'])
                ->addIndex(['fivserv_security_token'], ['name' => 'idx_customer_fivserv_token'])
                ->create();
        }

        // Create subscription_plans table
        if (!$this->hasTable('subscription_plans')) {
            $table = $this->table('subscription_plans', ['id' => true, 'primary_key' => 'id', 'signed' => false, 'id_type' => 'biginteger']);
            $table
                ->addColumn('uuid', 'string', ['limit' => 36, 'null' => false])
                ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
                ->addColumn('description', 'text', ['null' => true])
                ->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => false])
                ->addColumn('interval', 'string', ['limit' => 20, 'null' => false, 'comment' => 'monthly, annual, etc.'])
                ->addColumn('interval_count', 'integer', ['limit' => MysqlAdapter::INT_SMALL, 'default' => 1])
                ->addColumn('is_active', 'boolean', ['default' => true])
                ->addColumn('created_at', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['uuid'], ['unique' => true])
                ->create();
        }

        // Create subscriptions table without foreign keys first
        if (!$this->hasTable('subscriptions')) {
            $table = $this->table('subscriptions', ['id' => true, 'primary_key' => 'id', 'signed' => false, 'id_type' => 'biginteger']);
            $table
                ->addColumn('uuid', 'string', ['limit' => 36, 'null' => false])
                ->addColumn('customer_id', 'biginteger', ['null' => false, 'signed' => false])
                ->addColumn('subscription_plan_id', 'biginteger', ['null' => false, 'signed' => false])
                ->addColumn('store_id', 'biginteger', ['null' => false, 'signed' => false])
                ->addColumn('status', 'string', ['limit' => 20, 'default' => 'active'])
                ->addColumn('started_at', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('next_billing_date', 'timestamp', ['null' => true])
                ->addColumn('ended_at', 'timestamp', ['null' => true])
                ->addColumn('payment_method_id', 'biginteger', ['null' => true, 'signed' => false])
                ->addColumn('created_at', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['uuid'], ['unique' => true])
                ->addIndex(['customer_id'], ['name' => 'idx_subscriptions_customer_id'])
                ->addIndex(['subscription_plan_id'], ['name' => 'idx_subscriptions_plan_id'])
                ->addIndex(['store_id'], ['name' => 'idx_subscriptions_store_id'])
                ->addIndex(['status'], ['name' => 'idx_subscriptions_status'])
                ->create();
        }

        // Create subscription_payments table without foreign keys first
        if (!$this->hasTable('subscription_payments')) {
            $table = $this->table('subscription_payments', ['id' => true, 'primary_key' => 'id', 'signed' => false, 'id_type' => 'biginteger']);
            $table
                ->addColumn('uuid', 'string', ['limit' => 36, 'null' => false])
                ->addColumn('subscription_id', 'biginteger', ['null' => false, 'signed' => false])
                ->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => false])
                ->addColumn('status', 'string', ['limit' => 20, 'default' => 'pending'])
                ->addColumn('payment_date', 'timestamp', ['null' => true])
                ->addColumn('payment_method', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('transaction_id', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('created_at', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['uuid'], ['unique' => true])
                ->addIndex(['subscription_id'], ['name' => 'idx_subscription_payments_subscription_id'])
                ->addIndex(['status'], ['name' => 'idx_subscription_payments_status'])
                ->addIndex(['payment_date'], ['name' => 'idx_subscription_payments_date'])
                ->create();
        }

        // Now add the foreign keys
        if ($this->hasTable('subscriptions') && $this->hasTable('customer') && $this->hasTable('subscription_plans')) {
            $this->table('subscriptions')
                ->addForeignKey('customer_id', 'customer', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
                ->addForeignKey('subscription_plan_id', 'subscription_plans', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
                ->save();
        }

        // Add foreign key to stores if the table exists
        if ($this->hasTable('stores') && $this->hasTable('subscriptions')) {
            // Check if the id column in stores table is bigint
            $columns = $this->adapter->getColumns('stores');
            $storeIdFound = false;
            $storeIdType = null;
            foreach ($columns as $column) {
                if ($column->getName() === 'id') {
                    $storeIdFound = true;
                    $storeIdType = $column->getType();
                    break;
                }
            }

            if ($storeIdFound && $storeIdType === 'biginteger') {
                $this->table('subscriptions')
                    ->addForeignKey('store_id', 'stores', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
                    ->save();
            } else {
                echo "Warning: Not adding foreign key to stores table because id column type is not biginteger or not found.\n";
            }
        }

        // Add foreign key for subscription_payments
        if ($this->hasTable('subscription_payments') && $this->hasTable('subscriptions')) {
            $this->table('subscription_payments')
                ->addForeignKey('subscription_id', 'subscriptions', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
                ->save();
        }
    }

    public function down()
    {
        // Drop tables in reverse order to handle foreign key constraints properly
        if ($this->hasTable('subscription_payments')) {
            // First remove foreign keys
            $table = $this->table('subscription_payments');
            $foreignKeys = $table->getForeignKeys();
            foreach ($foreignKeys as $foreignKey) {
                $table->dropForeignKey($foreignKey['columns'][0])->save();
            }
            // Then drop the table
            $table->drop()->save();
        }
        
        if ($this->hasTable('subscriptions')) {
            // First remove foreign keys
            $table = $this->table('subscriptions');
            $foreignKeys = $table->getForeignKeys();
            foreach ($foreignKeys as $foreignKey) {
                $table->dropForeignKey($foreignKey['columns'][0])->save();
            }
            // Then drop the table
            $table->drop()->save();
        }
        
        if ($this->hasTable('subscription_plans')) {
            $this->table('subscription_plans')->drop()->save();
        }
        
        if ($this->hasTable('customer')) {
            $this->table('customer')->drop()->save();
        }
    }
} 