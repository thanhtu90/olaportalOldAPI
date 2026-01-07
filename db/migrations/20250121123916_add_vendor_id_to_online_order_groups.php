<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddVendorIdToOnlineOrderGroups extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('online_order_groups');
        
        // Only add vendor_id if it doesn't exist
        if (!$table->hasColumn('vendor_id')) {
            $table->addColumn('vendor_id', 'integer', [
                'null' => true,
                'after' => 'id'  // Position the column after the id column
            ])
            ->update();
        }
    }
}
