<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddLanIpToTerminalsMigration extends AbstractMigration
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
        $table = $this->table('terminals');
        
        // Check if lan_ip column exists before adding it
        if (!$table->hasColumn('lan_ip')) {
            $table->addColumn('lan_ip', 'string', [
                'limit' => 255,
                'null' => true,
                'after' => 'lastmod'
            ])->update();
        }
    }
}
