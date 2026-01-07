<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AlterCustomerTable extends AbstractMigration
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
        $table = $this->table('customer');
        
        // Remove fiserv_security_token column and add billing_token_id column
        $table->removeColumn('fiserv_security_token')
              ->addColumn('billing_token_id', 'integer', [
                  'signed' => false,
                  'null' => true,
                  'default' => null
              ])
              ->update();
    }
}
