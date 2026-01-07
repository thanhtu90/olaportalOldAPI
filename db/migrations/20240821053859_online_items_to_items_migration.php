<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class OnlineItemsToItemsMigration extends AbstractMigration
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
        $table = $this->table('items');
        $columnsToAdd = [];
        
        if (!$table->hasColumn('name')) {
            $columnsToAdd['name'] = ['type' => 'string', 'null' => true];
        }
        if (!$table->hasColumn('uuid')) {
            $columnsToAdd['uuid'] = ['type' => 'string', 'null' => true];
        }
        if (!$table->hasColumn('price_currency')) {
            $columnsToAdd['price_currency'] = ['type' => 'string', 'null' => true, 'default' => 'USD'];
        }
        if (!$table->hasColumn('is_active')) {
            $columnsToAdd['is_active'] = ['type' => 'smallinteger', 'null' => true];
        }
        if (!$table->hasColumn('type_display')) {
            $columnsToAdd['type_display'] = ['type' => 'smallinteger', 'null' => true, 'signed' => false];
        }
        if (!$table->hasColumn('metadata')) {
            $columnsToAdd['metadata'] = ['type' => 'json', 'null' => true];
        }
        if (!$table->hasColumn('group_belong_type')) {
            $columnsToAdd['group_belong_type'] = ['type' => 'smallinteger', 'null' => true, 'signed' => false];
        }
        if (!$table->hasColumn('image_url')) {
            $columnsToAdd['image_url'] = ['type' => 'string', 'null' => true];
        }
        
        if (!empty($columnsToAdd)) {
            foreach ($columnsToAdd as $columnName => $options) {
                $columnType = $options['type'];
                unset($options['type']);
                $table->addColumn($columnName, $columnType, $options);
            }
            $table->update();
        }
        // Note:
        // description = desc
        // price_val = price
        // is_taxable = taxable
        // vendor_id = vendors_id
        // tax_rate = taxrate
        // available_amount = amount_on_hand
    }
}
