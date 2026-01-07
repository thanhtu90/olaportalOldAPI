<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddFiservSecurityTokenToCustomer extends AbstractMigration
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
        
        // Check if fiserv_security_token column exists before adding it
        if (!$table->hasColumn('fiserv_security_token')) {
            $table->addColumn('fiserv_security_token', 'string', [
                'limit' => 255,
                'null' => true,
            ]);
        }
        
        $table->update();
    }
}
