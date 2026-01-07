<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class CreateNotificationsTable extends AbstractMigration
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
    public function change()
    {
        $table = $this->table('notifications', ['id' => false, 'primary_key' => ['id']]);
        $table->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
              ->addColumn('uuid', 'string', ['limit' => 36, 'null' => false])
              ->addColumn('type', 'string', ['limit' => 50, 'null' => false, 'comment' => 'Type of notification (e.g., email, sms, webhook)'])
              ->addColumn('status', 'string', ['limit' => 50, 'null' => false, 'comment' => 'Status of notification (e.g., pending, sent, failed)'])
              ->addColumn('vendor_id', 'biginteger', ['signed' => true, 'null' => false]) // Assuming vendor_id can be signed based on int64
              ->addColumn('store_uuid', 'string', ['limit' => 36, 'null' => true])
              ->addColumn('priority', 'string', ['limit' => 50, 'null' => false, 'default' => 'medium', 'comment' => 'Priority (e.g., low, medium, high)'])
              ->addColumn('payload', 'blob', ['null' => false, 'comment' => 'Notification content/data']) // Use blob for []byte
              ->addColumn('sent_at', 'timestamp', ['null' => true])
              ->addColumn('retry_count', 'integer', ['limit' => MysqlAdapter::INT_REGULAR, 'signed' => false, 'default' => 0])
              ->addColumn('max_retries', 'integer', ['limit' => MysqlAdapter::INT_REGULAR, 'signed' => false, 'default' => 3])
              ->addColumn('error_message', 'text', ['null' => true])
              ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('updated_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP', 'update' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['uuid'], ['unique' => true, 'name' => 'idx_notifications_uuid'])
              ->addIndex(['vendor_id'], ['name' => 'idx_notifications_vendor'])
              ->addIndex(['store_uuid'], ['name' => 'idx_notifications_store'])
              ->addIndex(['type'], ['name' => 'idx_notifications_type'])
              ->addIndex(['status'], ['name' => 'idx_notifications_status'])
              ->addIndex(['priority'], ['name' => 'idx_notifications_priority'])
              ->addIndex(['sent_at'], ['name' => 'idx_notifications_sent_at'])
              ->addIndex(['created_at'], ['name' => 'idx_notifications_created_at'])
              ->create();
    }
} 