<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddStatusColumnsToAccounts extends AbstractMigration
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
        $table = $this->table('accounts');
        
        // Check if onboarding_status column exists before adding it
        if (!$table->hasColumn('onboarding_status')) {
            $table->addColumn('onboarding_status', 'string', [
                'limit' => 255,
                'null' => true,
            ]);
        }
        
        // Check if reward_status column exists before adding it
        if (!$table->hasColumn('reward_status')) {
            $table->addColumn('reward_status', 'string', [
                'limit' => 255,
                'null' => true,
            ]);
        }
        
        $table->update();
    }
}
