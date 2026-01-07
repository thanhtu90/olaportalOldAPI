<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddStateAndCountryToCustomer extends AbstractMigration
{
    /**
     * Change Method.
     */
    public function change(): void
    {
        $table = $this->table('customer');

        // Add 'state' column if it does not exist
        if (!$table->hasColumn('state')) {
            $table->addColumn('state', 'string', [
                'limit' => 255,
                'null' => true,
            ]);
        }

        // Add 'country' column if it does not exist
        if (!$table->hasColumn('country')) {
            $table->addColumn('country', 'string', [
                'limit' => 255,
                'null' => true,
            ]);
        }

        $table->update();
    }
} 