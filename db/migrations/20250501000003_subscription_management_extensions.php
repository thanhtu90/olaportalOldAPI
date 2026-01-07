<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class SubscriptionManagementExtensions extends AbstractMigration
{
    public function up()
    {
        // Create processing_plans table
        $this->createProcessingPlansTable();
        
        // Create batches table
        $this->createBatchesTable();
        
        // Create batch_items table
        $this->createBatchItemsTable();
        
        // Create transactions table
        $this->createTransactionsTable();
        
        // Create audit_logs table
        $this->createAuditLogsTable();
        
        // Create common query views
        $this->createCommonQueryViews();
        
        // Add performance indexes
        $this->addPerformanceIndexes();
        
        // Create stored procedures - SKIPPED due to privilege restrictions
        // $this->createStoredProcedures();
        
        // Add triggers for compliance - SKIPPED due to privilege restrictions
        // $this->addComplianceTriggers();
        
        // Fix the column naming inconsistency between implementations
        $this->fixColumnNamingInconsistency();
    }

    private function createProcessingPlansTable()
    {
        if (!$this->hasTable('processing_plans')) {
            $this->table('processing_plans', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
                ->addColumn('name', 'string', ['limit' => 100, 'null' => false])
                ->addColumn('description', 'text', ['null' => true])
                ->addColumn('frequency', 'enum', ['values' => ['daily', 'weekly', 'monthly', 'custom'], 'null' => false])
                ->addColumn('is_active', 'boolean', ['null' => false, 'default' => true])
                ->addColumn('created_at', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('processing_window_start', 'time', ['null' => true])
                ->addColumn('processing_window_end', 'time', ['null' => true])
                ->addColumn('max_batch_size', 'integer', ['limit' => MysqlAdapter::INT_REGULAR, 'signed' => false, 'null' => false, 'default' => 1000])
                ->addColumn('retry_strategy', 'json', ['null' => true])
                ->addIndex(['is_active'], ['name' => 'idx_processing_active'])
                ->create();
                
            // Add ON UPDATE trigger for updated_at manually
            $this->execute("ALTER TABLE processing_plans MODIFY updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        }
    }

    private function createBatchesTable()
    {
        if (!$this->hasTable('batches')) {
            $this->table('batches', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
                ->addColumn('uuid', 'string', ['limit' => 36, 'null' => false])
                ->addColumn('processing_plan_id', 'biginteger', ['signed' => false, 'null' => false])
                ->addColumn('status', 'enum', ['values' => ['pending', 'processing', 'completed', 'failed', 'canceled'], 'null' => false, 'default' => 'pending'])
                ->addColumn('created_at', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('scheduled_time', 'timestamp', ['null' => false])
                ->addColumn('started_at', 'timestamp', ['null' => true])
                ->addColumn('completed_at', 'timestamp', ['null' => true])
                ->addColumn('total_items', 'integer', ['limit' => MysqlAdapter::INT_REGULAR, 'signed' => false, 'null' => false, 'default' => 0])
                ->addColumn('processed_items', 'integer', ['limit' => MysqlAdapter::INT_REGULAR, 'signed' => false, 'null' => false, 'default' => 0])
                ->addColumn('success_items', 'integer', ['limit' => MysqlAdapter::INT_REGULAR, 'signed' => false, 'null' => false, 'default' => 0])
                ->addColumn('failed_items', 'integer', ['limit' => MysqlAdapter::INT_REGULAR, 'signed' => false, 'null' => false, 'default' => 0])
                ->addColumn('error_message', 'text', ['null' => true])
                ->addColumn('metadata', 'json', ['null' => true])
                ->addIndex(['uuid'], ['unique' => true, 'name' => 'uk_batch_uuid'])
                ->addIndex(['status'], ['name' => 'idx_batch_status'])
                ->addIndex(['scheduled_time'], ['name' => 'idx_batch_scheduled'])
                ->addIndex(['processing_plan_id'], ['name' => 'idx_batch_processing_plan'])
                ->addForeignKey('processing_plan_id', 'processing_plans', 'id', ['delete' => 'RESTRICT', 'update' => 'NO_ACTION'])
                ->create();
                
            // Add ON UPDATE trigger for updated_at manually
            $this->execute("ALTER TABLE batches MODIFY updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        }
    }

    private function createBatchItemsTable()
    {
        if (!$this->hasTable('batch_items')) {
            $this->table('batch_items', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
                ->addColumn('batch_id', 'biginteger', ['signed' => false, 'null' => false])
                ->addColumn('subscription_id', 'integer', ['signed' => false, 'null' => false])
                ->addColumn('status', 'enum', ['values' => ['pending', 'processing', 'success', 'failed', 'skipped'], 'null' => false, 'default' => 'pending'])
                ->addColumn('created_at', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('processed_at', 'timestamp', ['null' => true])
                ->addColumn('retry_count', 'integer', ['limit' => MysqlAdapter::INT_REGULAR, 'signed' => false, 'null' => false, 'default' => 0])
                ->addColumn('error_message', 'text', ['null' => true])
                ->addColumn('result_data', 'json', ['null' => true])
                ->addIndex(['batch_id', 'subscription_id'], ['unique' => true, 'name' => 'uk_batch_subscription'])
                ->addIndex(['batch_id'], ['name' => 'idx_batch_item_batch'])
                ->addIndex(['subscription_id'], ['name' => 'idx_batch_item_subscription'])
                ->addIndex(['status'], ['name' => 'idx_batch_item_status'])
                ->addForeignKey('batch_id', 'batches', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
                ->addForeignKey('subscription_id', 'subscriptions', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
                ->create();
                
            // Add ON UPDATE trigger for updated_at manually
            $this->execute("ALTER TABLE batch_items MODIFY updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        }
    }

    private function createTransactionsTable()
    {
        if (!$this->hasTable('transactions')) {
            $this->table('transactions', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
                ->addColumn('uuid', 'string', ['limit' => 36, 'null' => false])
                ->addColumn('subscription_id', 'integer', ['signed' => false, 'null' => false])
                ->addColumn('batch_item_id', 'biginteger', ['signed' => false, 'null' => true])
                ->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2, 'null' => false])
                ->addColumn('currency', 'string', ['limit' => 3, 'null' => false, 'default' => 'USD'])
                ->addColumn('status', 'enum', ['values' => ['pending', 'processing', 'success', 'failed', 'refunded', 'partially_refunded'], 'null' => false, 'default' => 'pending'])
                ->addColumn('transaction_type', 'enum', ['values' => ['charge', 'refund', 'credit'], 'null' => false, 'default' => 'charge'])
                ->addColumn('payment_processor', 'string', ['limit' => 50, 'null' => false])
                ->addColumn('processor_transaction_id', 'string', ['limit' => 255, 'null' => true])
                ->addColumn('processor_response_code', 'string', ['limit' => 50, 'null' => true])
                ->addColumn('processor_response_message', 'text', ['null' => true])
                ->addColumn('created_at', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('completed_at', 'timestamp', ['null' => true])
                ->addColumn('metadata', 'json', ['null' => true])
                ->addIndex(['uuid'], ['unique' => true, 'name' => 'uk_transaction_uuid'])
                ->addIndex(['subscription_id'], ['name' => 'idx_transaction_subscription'])
                ->addIndex(['batch_item_id'], ['name' => 'idx_transaction_batch_item'])
                ->addIndex(['status'], ['name' => 'idx_transaction_status'])
                ->addIndex(['created_at'], ['name' => 'idx_transaction_created'])
                ->addIndex(['transaction_type'], ['name' => 'idx_transaction_type'])
                ->addForeignKey('subscription_id', 'subscriptions', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION'])
                ->addForeignKey('batch_item_id', 'batch_items', 'id', ['delete' => 'SET_NULL', 'update' => 'NO_ACTION'])
                ->create();
                
            // Add ON UPDATE trigger for updated_at manually
            $this->execute("ALTER TABLE transactions MODIFY updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        }
    }

    private function createAuditLogsTable()
    {
        if (!$this->hasTable('audit_logs')) {
            $this->table('audit_logs', ['id' => false, 'primary_key' => ['id']])
                ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
                ->addColumn('entity_type', 'string', ['limit' => 50, 'null' => false])
                ->addColumn('entity_id', 'string', ['limit' => 36, 'null' => false])
                ->addColumn('user_id', 'string', ['limit' => 36, 'null' => true])
                ->addColumn('action', 'string', ['limit' => 50, 'null' => false])
                ->addColumn('old_values', 'json', ['null' => true])
                ->addColumn('new_values', 'json', ['null' => true])
                ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => true])
                ->addColumn('created_at', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
                ->addIndex(['entity_type', 'entity_id'], ['name' => 'idx_audit_entity'])
                ->addIndex(['user_id'], ['name' => 'idx_audit_user'])
                ->addIndex(['action'], ['name' => 'idx_audit_action'])
                ->addIndex(['created_at'], ['name' => 'idx_audit_created'])
                ->create();
        }
    }

    private function createCommonQueryViews()
    {
        // Drop existing views first
        $this->execute("DROP VIEW IF EXISTS vw_subscriptions_due_next_24h");
        $this->execute("DROP VIEW IF EXISTS vw_customer_subscription_summary");
        
        // Create view for subscriptions due in the next 24 hours
        $this->execute("
            CREATE VIEW vw_subscriptions_due_next_24h AS
            SELECT 
                s.id,
                s.uuid,
                s.customer_id,
                s.subscription_plan_id,
                s.status,
                s.started_at,
                s.next_billing_date,
                s.ended_at,
                s.payment_method_id,
                s.created_at,
                s.updated_at
            FROM subscriptions s
            WHERE s.status = 'active'
            AND s.next_billing_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
        ");
        
        // Create view for customer subscription summary
        $this->execute("
            CREATE VIEW vw_customer_subscription_summary AS
            SELECT 
                c.id AS customer_id,
                c.email,
                c.first_name,
                c.last_name,
                COUNT(s.id) AS total_subscriptions,
                SUM(CASE WHEN s.status = 'active' THEN 1 ELSE 0 END) AS active_subscriptions
            FROM customer c
            LEFT JOIN subscriptions s ON c.id = s.customer_id
            GROUP BY c.id, c.email, c.first_name, c.last_name
        ");
    }

    private function addPerformanceIndexes()
    {
        try {
            // Add indexes directly with execute to avoid pending actions issues
            try {
                $this->execute("
                    ALTER TABLE subscriptions 
                    ADD INDEX idx_subscription_date_range (next_billing_date, status),
                    ADD INDEX idx_subscription_updated (updated_at)
                ");
            } catch (\Exception $e) {
                // Indexes might already exist, ignore error
            }
            
            // Add composite index for batch items
            try {
                $this->execute("
                    ALTER TABLE batch_items 
                    ADD INDEX idx_batch_item_composite (batch_id, status, subscription_id)
                ");
            } catch (\Exception $e) {
                // Index might already exist, ignore error
            }
            
            // Add index for transaction reporting
            try {
                $this->execute("
                    ALTER TABLE transactions 
                    ADD INDEX idx_transaction_date_amount (created_at, amount, status)
                ");
            } catch (\Exception $e) {
                // Index might already exist, ignore error
            }
        } catch (\Exception $e) {
            // Log the exception but continue with migration
            error_log('Error adding performance indexes: ' . $e->getMessage());
        }
    }

    private function createStoredProcedures()
    {
        // Drop existing procedures first
        $this->execute("DROP PROCEDURE IF EXISTS sp_maintain_transaction_partitions");
        $this->execute("DROP PROCEDURE IF EXISTS sp_export_customer_data");
        $this->execute("DROP FUNCTION IF EXISTS fn_mask_pii");
        
        // Transaction partition maintenance procedure
        $this->execute("
            CREATE PROCEDURE sp_maintain_transaction_partitions()
            BEGIN
                DECLARE current_year INT;
                DECLARE current_month INT;
                DECLARE next_partition_value INT;
                DECLARE partition_exists INT;
                
                -- Calculate current year-month and next partition value
                SET current_year = YEAR(CURDATE());
                SET current_month = MONTH(CURDATE());
                SET next_partition_value = (current_year * 100 + current_month + 1);
                
                -- Check if the next month partition already exists
                SELECT COUNT(*) INTO partition_exists
                FROM information_schema.partitions 
                WHERE table_schema = DATABASE()
                  AND table_name = 'transactions'
                  AND partition_name = CONCAT('p_transactions_', next_partition_value);
                
                -- If next month partition doesn't exist, create it
                IF partition_exists = 0 THEN
                    SET @sql = CONCAT('ALTER TABLE transactions REORGANIZE PARTITION p_transactions_future INTO (
                        PARTITION p_transactions_', next_partition_value, ' VALUES LESS THAN (', next_partition_value + 1, '),
                        PARTITION p_transactions_future VALUES LESS THAN MAXVALUE
                    )');
                    PREPARE stmt FROM @sql;
                    EXECUTE stmt;
                    DEALLOCATE PREPARE stmt;
                END IF;
            END
        ");

        // Data masking function for PCI compliance
        $this->execute("
            CREATE FUNCTION fn_mask_pii(input_text VARCHAR(255), mask_char CHAR(1), visible_chars INT)
            RETURNS VARCHAR(255)
            DETERMINISTIC
            BEGIN
                DECLARE result VARCHAR(255);
                DECLARE text_length INT;
                
                IF input_text IS NULL THEN
                    RETURN NULL;
                END IF;
                
                SET text_length = CHAR_LENGTH(input_text);
                
                IF text_length <= visible_chars THEN
                    RETURN input_text;
                END IF;
                
                SET result = CONCAT(
                    LEFT(input_text, visible_chars),
                    REPEAT(mask_char, text_length - visible_chars)
                );
                
                RETURN result;
            END
        ");

        // GDPR data export procedure
        $this->execute("
            CREATE PROCEDURE sp_export_customer_data(IN customer_uuid VARCHAR(36))
            BEGIN
                -- Declare variables to hold customer ID
                DECLARE customer_id BIGINT UNSIGNED;
                
                -- Get customer ID from UUID
                SELECT id INTO customer_id
                FROM customer
                WHERE uuid = customer_uuid;
                
                -- Return customer personal data
                SELECT
                    'customer_data' AS data_section,
                    c.uuid,
                    c.email,
                    c.name,
                    c.created_at,
                    c.updated_at
                FROM customer c
                WHERE c.id = customer_id;
                
                -- Return subscriptions
                SELECT
                    'subscriptions' AS data_section,
                    s.uuid,
                    s.status,
                    s.started_at,
                    s.next_billing_date,
                    s.ended_at,
                    s.created_at,
                    s.updated_at
                FROM subscriptions s
                WHERE s.customer_id = customer_id;
                
                -- Return transaction history (without sensitive data)
                SELECT
                    'transactions' AS data_section,
                    t.uuid,
                    t.amount,
                    t.currency,
                    t.status,
                    t.transaction_type,
                    t.created_at,
                    t.updated_at,
                    t.completed_at
                FROM transactions t
                JOIN subscriptions s ON t.subscription_id = s.id
                WHERE s.customer_id = customer_id;
            END
        ");
    }

    private function addComplianceTriggers()
    {
        // Drop existing triggers first
        $this->execute("DROP TRIGGER IF EXISTS trg_transactions_audit");
        
        // Add transaction audit trigger
        $this->execute("
            CREATE TRIGGER trg_transactions_audit
            AFTER UPDATE ON transactions
            FOR EACH ROW
            BEGIN
                INSERT INTO audit_logs (entity_type, entity_id, action, old_values, new_values, created_at)
                VALUES (
                    'transactions', 
                    NEW.uuid, 
                    'update', 
                    JSON_OBJECT(
                        'status', OLD.status,
                        'amount', OLD.amount,
                        'processor_response_code', OLD.processor_response_code,
                        'completed_at', OLD.completed_at
                    ),
                    JSON_OBJECT(
                        'status', NEW.status,
                        'amount', NEW.amount,
                        'processor_response_code', NEW.processor_response_code,
                        'completed_at', NEW.completed_at
                    ),
                    CURRENT_TIMESTAMP
                );
            END
        ");
    }

    private function fixColumnNamingInconsistency()
    {
        // Check if the subscription_plans table exists
        if (!$this->hasTable('subscription_plans')) {
            return;
        }
            
        // Check if the subscription_plans table has an 'interval' column
        if ($this->getAdapter()->hasColumn('subscription_plans', 'interval')) {
            // Rename 'interval' to 'billing_interval' to match the expected column name
            $this->execute("
                ALTER TABLE subscription_plans 
                CHANGE COLUMN `interval` `billing_interval` VARCHAR(20) NOT NULL COMMENT 'monthly, annual, etc.'
            ");
            
            // Update the data types to match the implemented schema
            $this->execute("
                ALTER TABLE subscription_plans 
                MODIFY `billing_interval` ENUM('daily', 'weekly', 'monthly', 'quarterly', 'annually') NOT NULL
            ");
            
            // Update existing values to match the new enum
            $this->execute("
                UPDATE subscription_plans 
                SET `billing_interval` = 'monthly' 
                WHERE `billing_interval` IN ('monthly', 'Monthly')
            ");
            
            $this->execute("
                UPDATE subscription_plans 
                SET `billing_interval` = 'annually' 
                WHERE `billing_interval` IN ('annual', 'Annual', 'yearly', 'Yearly')
            ");
            
            $this->execute("
                UPDATE subscription_plans 
                SET `billing_interval` = 'quarterly' 
                WHERE `billing_interval` IN ('quarterly', 'Quarterly')
            ");
            
            $this->execute("
                UPDATE subscription_plans 
                SET `billing_interval` = 'weekly' 
                WHERE `billing_interval` IN ('weekly', 'Weekly')
            ");
            
            $this->execute("
                UPDATE subscription_plans 
                SET `billing_interval` = 'daily' 
                WHERE `billing_interval` IN ('daily', 'Daily')
            ");
        }
    }

    public function down()
    {
        // Drop view dependencies first
        $this->execute('DROP VIEW IF EXISTS vw_subscriptions_due_next_24h');
        $this->execute('DROP VIEW IF EXISTS vw_customer_subscription_summary');
        
        // Drop triggers
        $this->execute('DROP TRIGGER IF EXISTS trg_transactions_audit');
        
        // Drop stored procedures and functions
        $this->execute('DROP PROCEDURE IF EXISTS sp_maintain_transaction_partitions');
        $this->execute('DROP PROCEDURE IF EXISTS sp_export_customer_data');
        $this->execute('DROP FUNCTION IF EXISTS fn_mask_pii');
        
        // Drop tables in reverse order to handle foreign key constraints
        $this->table('transactions')->drop()->save();
        $this->table('batch_items')->drop()->save();
        $this->table('batches')->drop()->save();
        $this->table('processing_plans')->drop()->save();
        $this->table('audit_logs')->drop()->save();
        
        // Revert column name changes if needed
        if ($this->hasTable('subscription_plans') && $this->getAdapter()->hasColumn('subscription_plans', 'billing_interval')) {
            $this->execute("
                ALTER TABLE subscription_plans 
                CHANGE COLUMN `billing_interval` `interval` VARCHAR(20) NOT NULL COMMENT 'monthly, annual, etc.'
            ");
        }
    }
} 