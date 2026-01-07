<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateEmployeesTable extends AbstractMigration
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
        $table = $this->table('employees', ['id' => false, 'primary_key' => ['id']]);
        
        $table->addColumn('id', 'integer', ['identity' => true, 'signed' => false])
              ->addColumn('name', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('nickname', 'string', ['limit' => 255, 'null' => false])
              ->addColumn('createDate', 'datetime', ['null' => false])
              ->addColumn('role', 'string', ['limit' => 50, 'null' => false])
              ->addColumn('pin', 'string', ['limit' => 50, 'null' => false, 'default' => 'NONE'])
              ->addColumn('status', 'integer', ['null' => false])
              ->addColumn('lastMod', 'biginteger', ['null' => false])
              ->create();
    }
}
