<?php


use Phinx\Seed\AbstractSeed;

class OrderSeeder extends AbstractSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeders is available here:
     * https://book.cakephp.org/phinx/0/en/seeding.html
     */
    public function run(): void
    {
        // if environment is production, do not seed data
        if (getenv('APP_ENV') === 'production') {
            return;
        }
        $this->table('orders')->truncate();
        $this->table('orderItems')->truncate();
        // Seed data for orders
        $ordersData = [
            [
                'id' => 1,
                'orderReference' => 1001,
                'agents_id' => 1,
                'vendors_id' => 1,
                'terminals_id' => 1,
                'subTotal' => 100.00,
                'tax' => 10.00,
                'total' => 110.00,
                'notes' => 'First order',
                'orderName' => 'Order One',
                'employee_id' => 1,
                'OrderDate' => time(),
                'delivery_type' => 'standard',
                'delivery_fee' => 5.00,
                'status' => 1,
                'lastMod' => time(),
                'uuid' => '114ede46-ad84-48ac-86f1-1972a12187bf',
                'employee_pin' => null
            ],
            [
                'id' => 2,
                'orderReference' => 1002,
                'agents_id' => 2,
                'vendors_id' => 2,
                'terminals_id' => 2,
                'subTotal' => 200.00,
                'tax' => 20.00,
                'total' => 220.00,
                'notes' => 'Second order',
                'orderName' => 'Order Two',
                'employee_id' => 2,
                'OrderDate' => time(),
                'delivery_type' => 'express',
                'delivery_fee' => 10.00,
                'status' => 1,
                'lastMod' => time(),
                'uuid' => null,
                'employee_pin' => null
            ],
            // Add more orders as needed
        ];

        // Insert data into orders table
        $ordersTable = $this->table('orders');
        $ordersTable->insert($ordersData)->save();

        // Seed data for orderItems
        $orderItemsData = [
            [
                'id' => 1,
                'agents_id' => 1,
                'vendors_id' => 1,
                'terminals_id' => 1,
                'group_name' => 'Group A',
                'orders_id' => 1,
                'items_id' => 101,
                'description' => 'Item 101 Description',
                'cost' => 25.00,
                'price' => 30.00,
                'notes' => 'Note for item 101',
                'taxable' => 1,
                'taxamount' => 3.00,
                'group_id' => 1,
                'itemid' => 101,
                'discount' => 0.00,
                'orderReference' => 1001,
                'itemsAddedDateTime' => time(),
                'qty' => 1,
                'lastMod' => time(),
                'status' => 1,
                'itemUuid' => null,
                'orderUuid' => '114ede46-ad84-48ac-86f1-1972a12187bf'
            ],
            [
                'id' => 2,
                'agents_id' => 2,
                'vendors_id' => 2,
                'terminals_id' => 2,
                'group_name' => 'Group B',
                'orders_id' => 2,
                'items_id' => 102,
                'description' => 'Item 102 Description',
                'cost' => 50.00,
                'price' => 60.00,
                'notes' => 'Note for item 102',
                'taxable' => 1,
                'taxamount' => 6.00,
                'group_id' => 2,
                'itemid' => 102,
                'discount' => 5.00,
                'orderReference' => 1002,
                'itemsAddedDateTime' => time(),
                'qty' => 2,
                'lastMod' => time(),
                'status' => 1,
                'itemUuid' => null,
                'orderUuid' => null
            ],
            // Add more orderItems as needed
        ];

        // Insert data into orderItems table
        $orderItemsTable = $this->table('orderItems');
        $orderItemsTable->insert($orderItemsData)->save();
    }
}
