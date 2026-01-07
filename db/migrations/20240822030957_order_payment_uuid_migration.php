<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class OrderPaymentUuidMigration extends AbstractMigration
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
        $table = $this->table('ordersPayments');
        $columnsToAdd = [];
        
        if (!$table->hasColumn('paymentUuid')) {
            $columnsToAdd['paymentUuid'] = ['type' => 'string', 'length' => 36, 'null' => true];
        }
        if (!$table->hasColumn('orderUuid')) {
            $columnsToAdd['orderUuid'] = ['type' => 'string', 'length' => 36, 'null' => true];
        }
        if (!$table->hasColumn('olapayApprovalId')) {
            $columnsToAdd['olapayApprovalId'] = ['type' => 'string', 'length' => 255, 'null' => true];
        }
        if (!$table->hasColumn('employee_pin')) {
            $columnsToAdd['employee_pin'] = ['type' => 'string', 'length' => 255, 'default' => 'NONE', 'null' => true];
        }
        
        if (!empty($columnsToAdd)) {
            $table->addColumns($columnsToAdd)->update();
        }
    }
}
