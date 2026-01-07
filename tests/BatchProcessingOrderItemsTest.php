<?php

use PHPUnit\Framework\TestCase;

/**
 * Tests for the batch processing fix in json.php
 * 
 * This test validates that the critical bug where hasInventory=true caused 
 * ALL orderItems to be written to ALL orders has been fixed.
 * 
 * The fix ensures orderItems are always properly filtered by their associated order_id.
 */
class BatchProcessingOrderItemsTest extends TestCase
{
    private $baseUrl = 'http://localhost:8000/json.php';
    private $testSerial = 'BATCH_TEST_TERMINAL_456';
    private $db_host = "127.0.0.1";
    private $db_name = "app_db";
    private $db_user = "app_user";
    private $db_password = "app_user_password";
    private $terminalId;

    protected function getConnection()
    {
        return new PDO(
            "mysql:host=" . $this->db_host . ";port=3306;dbname=" . $this->db_name,
            $this->db_user,
            $this->db_password
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure we have a test terminal in the database
        $pdo = $this->getConnection();

        // Create test vendor and account first
        $stmt = $pdo->prepare("INSERT IGNORE INTO accounts (id) VALUES (1)");
        $stmt->execute();

        // Create test terminal if it doesn't exist
        $stmt = $pdo->prepare("INSERT IGNORE INTO terminals (serial, vendors_id) VALUES (?, 1)");
        $stmt->execute([$this->testSerial]);
        
        // Get terminal ID for cleanup
        $stmt = $pdo->prepare("SELECT id FROM terminals WHERE serial = ?");
        $stmt->execute([$this->testSerial]);
        $this->terminalId = $stmt->fetchColumn();
    }

    /**
     * Test the critical fix: hasInventory=true with multiple orders should only 
     * associate orderItems with their correct orders, not all orders.
     */
    public function testHasInventoryTrueWithMultipleOrdersFiltersCorrectly()
    {
        $currentTime = time();
        $orderRef1 = 11111;
        $orderRef2 = 22222;
        $orderUuid1 = "test-order-uuid-1";
        $orderUuid2 = "test-order-uuid-2";
        
        // Create payload with 2 orders and 2 orderItems - each item should only go to its matching order
        $payload = [
            "serial" => $this->testSerial,
            "json" => [
                "payments" => null,
                "hasInventory" => true,  // This was the problematic condition
                "orders" => [
                    [
                        "uuid" => $orderUuid1,
                        "id" => $orderRef1,
                        "subTotal" => 10.99,
                        "tax" => 0,
                        "total" => 10.99,
                        "notes" => "",
                        "lastMod" => $currentTime,
                        "orderName" => "Order 1",
                        "employeeId" => 1,
                        "employeePIN" => "1234",
                        "orderDate" => date('Y-m-d H:i:s', $currentTime),
                        "delivery_type" => "0",
                        "delivery_fee" => "0",
                        "status" => 0,
                        "oUUID" => $orderUuid1
                    ],
                    [
                        "uuid" => $orderUuid2,
                        "id" => $orderRef2,
                        "subTotal" => 5.99,
                        "tax" => 0,
                        "total" => 5.99,
                        "notes" => "",
                        "lastMod" => $currentTime,
                        "orderName" => "Order 2",
                        "employeeId" => 1,
                        "employeePIN" => "1234",
                        "orderDate" => date('Y-m-d H:i:s', $currentTime),
                        "delivery_type" => "0",
                        "delivery_fee" => "0",
                        "status" => 0,
                        "oUUID" => $orderUuid2
                    ]
                ],
                "groups" => [],
                "items" => [
                    // Item 1 belongs to Order 1
                    [
                        "item" => [
                            "name" => "Item for Order 1",
                            "description" => "Item 1 description",
                            "group" => 37966,
                            "id" => 42255,
                            "uuid" => "item-1-uuid",
                            "lastMod" => $currentTime,
                            "price" => 10.99,
                            "priceInt" => 1099,
                            "taxRate" => 0,
                            "taxable" => 0,
                            "upc" => "",
                            "amountOnHand" => 0,
                            "cost" => 0,
                            "notes" => "",
                            "isEbt" => 0,
                            "isManualPrice" => 0,
                            "isWeighted" => 0,
                            "crv" => 0,
                            "crv_taxable" => false,
                            "printType" => 0
                        ],
                        "mods" => [],
                        "orderItem" => [
                            "name" => "Item for Order 1",
                            "description" => "Item 1 description",
                            "group" => 37966,
                            "id" => 42255,
                            "uuid" => "item-1-uuid",
                            "lastMod" => $currentTime,
                            "price" => 10.99,
                            "priceInt" => 1099,
                            "taxRate" => 0,
                            "taxable" => 0,
                            "upc" => "",
                            "amountOnHand" => 0,
                            "cost" => 0,
                            "notes" => "",
                            "isEbt" => 0,
                            "isManualPrice" => 0,
                            "isWeighted" => 0,
                            "crv" => 0,
                            "crv_taxable" => false,
                            "printType" => 0,
                            "orderReference" => $orderRef1,  // Belongs to Order 1
                            "discount" => 0,
                            "status" => "REQUEST_INVENTORY_LOCK",
                            "qty" => 1,
                            "taxAmount" => "0.00"
                        ]
                    ],
                    // Item 2 belongs to Order 2
                    [
                        "item" => [
                            "name" => "Item for Order 2",
                            "description" => "Item 2 description",
                            "group" => 37966,
                            "id" => 42256,
                            "uuid" => "item-2-uuid",
                            "lastMod" => $currentTime,
                            "price" => 5.99,
                            "priceInt" => 599,
                            "taxRate" => 0,
                            "taxable" => 0,
                            "upc" => "",
                            "amountOnHand" => 0,
                            "cost" => 0,
                            "notes" => "",
                            "isEbt" => 0,
                            "isManualPrice" => 0,
                            "isWeighted" => 0,
                            "crv" => 0,
                            "crv_taxable" => false,
                            "printType" => 0
                        ],
                        "mods" => [],
                        "orderItem" => [
                            "name" => "Item for Order 2",
                            "description" => "Item 2 description",
                            "group" => 37966,
                            "id" => 42256,
                            "uuid" => "item-2-uuid",
                            "lastMod" => $currentTime,
                            "price" => 5.99,
                            "priceInt" => 599,
                            "taxRate" => 0,
                            "taxable" => 0,
                            "upc" => "",
                            "amountOnHand" => 0,
                            "cost" => 0,
                            "notes" => "",
                            "isEbt" => 0,
                            "isManualPrice" => 0,
                            "isWeighted" => 0,
                            "crv" => 0,
                            "crv_taxable" => false,
                            "printType" => 0,
                            "orderReference" => $orderRef2,  // Belongs to Order 2
                            "discount" => 0,
                            "status" => "REQUEST_INVENTORY_LOCK",
                            "qty" => 1,
                            "taxAmount" => "0.00"
                        ]
                    ]
                ],
                "termId" => "TERM123",
                "itemdata" => []
            ]
        ];

        // Make the POST request
        $response = $this->makePostRequest($payload);
        
        // Assert response is successful
        $this->assertEquals(200, $response['httpCode'], "Expected 200 OK response");
        $responseData = json_decode($response['body'], true);
        $this->assertNotNull($responseData, "Response should be valid JSON");

        // Verify database state - this is the critical test
        $pdo = $this->getConnection();

        // Get order IDs from database
        $stmt = $pdo->prepare("SELECT id, uuid FROM orders WHERE terminals_id = ? ORDER BY id");
        $stmt->execute([$this->terminalId]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->assertCount(2, $orders, "Should have exactly 2 orders");
        
        $order1_db_id = null;
        $order2_db_id = null;
        
        foreach ($orders as $order) {
            if ($order['uuid'] == $orderUuid1) {
                $order1_db_id = $order['id'];
            } elseif ($order['uuid'] == $orderUuid2) {
                $order2_db_id = $order['id'];
            }
        }
        
        $this->assertNotNull($order1_db_id, "Order 1 should exist in database");
        $this->assertNotNull($order2_db_id, "Order 2 should exist in database");

        // Critical test: Check that orderItems are correctly associated with their respective orders
        $stmt = $pdo->prepare("SELECT * FROM orderItems WHERE orders_id = ?");
        
        // Check Order 1 items
        $stmt->execute([$order1_db_id]);
        $order1_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $order1_items, "Order 1 should have exactly 1 item");
        $this->assertEquals("Item for Order 1", $order1_items[0]['description'], "Order 1 should have its correct item");
        $this->assertEquals(10.99, $order1_items[0]['price'], "Order 1 item should have correct price");

        // Check Order 2 items
        $stmt->execute([$order2_db_id]);
        $order2_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $order2_items, "Order 2 should have exactly 1 item");
        $this->assertEquals("Item for Order 2", $order2_items[0]['description'], "Order 2 should have its correct item");
        $this->assertEquals(5.99, $order2_items[0]['price'], "Order 2 item should have correct price");

        // Verify that items are NOT cross-contaminated (the bug we fixed)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orderItems WHERE terminals_id = ?");
        $stmt->execute([$this->terminalId]);
        $totalItems = $stmt->fetchColumn();
        $this->assertEquals(2, $totalItems, "Total orderItems should be exactly 2 (no duplication across orders)");
    }

    /**
     * Test hasInventory=false maintains existing behavior
     */
    public function testHasInventoryFalseMaintainsExistingBehavior()
    {
        $currentTime = time();
        $orderRef = 33333;
        $orderUuid = "test-order-uuid-3";
        
        $payload = [
            "serial" => $this->testSerial,
            "json" => [
                "payments" => [
                    [
                        "amount" => 7.99,
                        "type" => "CASH",
                        "lastMod" => $currentTime
                    ]
                ],
                "hasInventory" => false,  // Should not affect filtering
                "orders" => [
                    [
                        "uuid" => $orderUuid,
                        "id" => $orderRef,
                        "subTotal" => 7.99,
                        "tax" => 0,
                        "total" => 7.99,
                        "notes" => "",
                        "lastMod" => $currentTime,
                        "orderName" => "Order 3",
                        "employeeId" => 1,
                        "employeePIN" => "1234",
                        "orderDate" => date('Y-m-d H:i:s', $currentTime),
                        "delivery_type" => "0",
                        "delivery_fee" => "0",
                        "status" => 0,
                        "oUUID" => $orderUuid
                    ]
                ],
                "groups" => [],
                "items" => [
                    [
                        "item" => [
                            "name" => "Item for Order 3",
                            "description" => "Item 3 description",
                            "group" => 37966,
                            "id" => 42257,
                            "uuid" => "item-3-uuid",
                            "lastMod" => $currentTime,
                            "price" => 7.99,
                            "priceInt" => 799,
                            "taxRate" => 0,
                            "taxable" => 0
                        ],
                        "mods" => [],
                        "orderItem" => [
                            "name" => "Item for Order 3",
                            "description" => "Item 3 description",
                            "group" => 37966,
                            "id" => 42257,
                            "uuid" => "item-3-uuid",
                            "lastMod" => $currentTime,
                            "price" => 7.99,
                            "priceInt" => 799,
                            "taxRate" => 0,
                            "taxable" => 0,
                            "orderReference" => $orderRef,
                            "discount" => 0,
                            "status" => "COMPLETE",
                            "qty" => 1,
                            "taxAmount" => "0.00"
                        ]
                    ]
                ],
                "termId" => "TERM123",
                "itemdata" => []
            ]
        ];

        // Make the POST request
        $response = $this->makePostRequest($payload);
        
        // Assert response is successful
        $this->assertEquals(200, $response['httpCode'], "Expected 200 OK response");

        // Verify the item was inserted correctly
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare("SELECT oi.* FROM orderItems oi JOIN orders o ON oi.orders_id = o.id WHERE o.uuid = ?");
        $stmt->execute([$orderUuid]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->assertCount(1, $items, "Should have exactly 1 item for hasInventory=false");
        $this->assertEquals("Item 3 description", $items[0]['description'], "Item should be correctly inserted");
    }

    /**
     * Test batch processing with multiple orders having different numbers of orderItems
     */
    public function testBatchProcessingWithDifferentItemCounts()
    {
        $currentTime = time();
        $orderRef1 = 44444;
        $orderRef2 = 55555;
        $orderUuid1 = "test-order-uuid-4";
        $orderUuid2 = "test-order-uuid-5";
        
        $payload = [
            "serial" => $this->testSerial,
            "json" => [
                "payments" => null,
                "hasInventory" => true,
                "orders" => [
                    [
                        "uuid" => $orderUuid1,
                        "id" => $orderRef1,
                        "subTotal" => 15.98,
                        "tax" => 0,
                        "total" => 15.98,
                        "notes" => "",
                        "lastMod" => $currentTime,
                        "orderName" => "Order with 2 items",
                        "employeeId" => 1,
                        "employeePIN" => "1234",
                        "orderDate" => date('Y-m-d H:i:s', $currentTime),
                        "delivery_type" => "0",
                        "delivery_fee" => "0",
                        "status" => 0,
                        "oUUID" => $orderUuid1
                    ],
                    [
                        "uuid" => $orderUuid2,
                        "id" => $orderRef2,
                        "subTotal" => 3.99,
                        "tax" => 0,
                        "total" => 3.99,
                        "notes" => "",
                        "lastMod" => $currentTime,
                        "orderName" => "Order with 1 item",
                        "employeeId" => 1,
                        "employeePIN" => "1234",
                        "orderDate" => date('Y-m-d H:i:s', $currentTime),
                        "delivery_type" => "0",
                        "delivery_fee" => "0",
                        "status" => 0,
                        "oUUID" => $orderUuid2
                    ]
                ],
                "groups" => [],
                "items" => [
                    // Order 1 gets 2 items
                    [
                        "item" => ["name" => "Item A", "description" => "Item A desc", "group" => 37966, "id" => 42258, "uuid" => "item-a-uuid", "lastMod" => $currentTime, "price" => 7.99],
                        "mods" => [],
                        "orderItem" => ["name" => "Item A", "description" => "Item A desc", "group" => 37966, "id" => 42258, "uuid" => "item-a-uuid", "lastMod" => $currentTime, "price" => 7.99, "orderReference" => $orderRef1, "discount" => 0, "status" => "REQUEST_INVENTORY_LOCK", "qty" => 1, "taxAmount" => "0.00"]
                    ],
                    [
                        "item" => ["name" => "Item B", "description" => "Item B desc", "group" => 37966, "id" => 42259, "uuid" => "item-b-uuid", "lastMod" => $currentTime, "price" => 7.99],
                        "mods" => [],
                        "orderItem" => ["name" => "Item B", "description" => "Item B desc", "group" => 37966, "id" => 42259, "uuid" => "item-b-uuid", "lastMod" => $currentTime, "price" => 7.99, "orderReference" => $orderRef1, "discount" => 0, "status" => "REQUEST_INVENTORY_LOCK", "qty" => 1, "taxAmount" => "0.00"]
                    ],
                    // Order 2 gets 1 item
                    [
                        "item" => ["name" => "Item C", "description" => "Item C desc", "group" => 37966, "id" => 42260, "uuid" => "item-c-uuid", "lastMod" => $currentTime, "price" => 3.99],
                        "mods" => [],
                        "orderItem" => ["name" => "Item C", "description" => "Item C desc", "group" => 37966, "id" => 42260, "uuid" => "item-c-uuid", "lastMod" => $currentTime, "price" => 3.99, "orderReference" => $orderRef2, "discount" => 0, "status" => "REQUEST_INVENTORY_LOCK", "qty" => 1, "taxAmount" => "0.00"]
                    ]
                ],
                "termId" => "TERM123",
                "itemdata" => []
            ]
        ];

        $response = $this->makePostRequest($payload);
        $this->assertEquals(200, $response['httpCode'], "Expected 200 OK response");

        // Verify database state
        $pdo = $this->getConnection();
        
        // Get order IDs
        $stmt = $pdo->prepare("SELECT id, uuid FROM orders WHERE terminals_id = ? ORDER BY id");
        $stmt->execute([$this->terminalId]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $order1_id = null;
        $order2_id = null;
        foreach ($orders as $order) {
            if ($order['uuid'] == $orderUuid1) $order1_id = $order['id'];
            if ($order['uuid'] == $orderUuid2) $order2_id = $order['id'];
        }

        // Check Order 1 has exactly 2 items
        $stmt = $pdo->prepare("SELECT description FROM orderItems WHERE orders_id = ? ORDER BY description");
        $stmt->execute([$order1_id]);
        $order1_items = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->assertCount(2, $order1_items, "Order 1 should have exactly 2 items");
        $this->assertEquals(['Item A desc', 'Item B desc'], $order1_items, "Order 1 should have correct items");

        // Check Order 2 has exactly 1 item
        $stmt->execute([$order2_id]);
        $order2_items = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $this->assertCount(1, $order2_items, "Order 2 should have exactly 1 item");
        $this->assertEquals(['Item C desc'], $order2_items, "Order 2 should have correct item");
    }

    /**
     * Test edge case: Order with no orderItems
     */
    public function testOrderWithNoOrderItems()
    {
        $currentTime = time();
        $orderRef = 66666;
        $orderUuid = "test-order-uuid-6";
        
        $payload = [
            "serial" => $this->testSerial,
            "json" => [
                "payments" => null,
                "hasInventory" => true,
                "orders" => [
                    [
                        "uuid" => $orderUuid,
                        "id" => $orderRef,
                        "subTotal" => 0,
                        "tax" => 0,
                        "total" => 0,
                        "notes" => "Empty order",
                        "lastMod" => $currentTime,
                        "orderName" => "Empty Order",
                        "employeeId" => 1,
                        "employeePIN" => "1234",
                        "orderDate" => date('Y-m-d H:i:s', $currentTime),
                        "delivery_type" => "0",
                        "delivery_fee" => "0",
                        "status" => 0,
                        "oUUID" => $orderUuid
                    ]
                ],
                "groups" => [],
                "items" => [],  // No items
                "termId" => "TERM123",
                "itemdata" => []
            ]
        ];

        $response = $this->makePostRequest($payload);
        $this->assertEquals(200, $response['httpCode'], "Expected 200 OK response");

        // Verify order exists but has no items
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare("SELECT id FROM orders WHERE uuid = ?");
        $stmt->execute([$orderUuid]);
        $order_id = $stmt->fetchColumn();
        $this->assertNotFalse($order_id, "Order should exist in database");

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orderItems WHERE orders_id = ?");
        $stmt->execute([$order_id]);
        $itemCount = $stmt->fetchColumn();
        $this->assertEquals(0, $itemCount, "Order should have no items");
    }

    /**
     * Test edge case: Single order (no batch processing)
     */
    public function testSingleOrderProcessing()
    {
        $currentTime = time();
        $orderRef = 77777;
        $orderUuid = "test-order-uuid-7";
        
        $payload = [
            "serial" => $this->testSerial,
            "json" => [
                "payments" => null,
                "hasInventory" => true,
                "orders" => [
                    [
                        "uuid" => $orderUuid,
                        "id" => $orderRef,
                        "subTotal" => 12.99,
                        "tax" => 0,
                        "total" => 12.99,
                        "notes" => "",
                        "lastMod" => $currentTime,
                        "orderName" => "Single Order",
                        "employeeId" => 1,
                        "employeePIN" => "1234",
                        "orderDate" => date('Y-m-d H:i:s', $currentTime),
                        "delivery_type" => "0",
                        "delivery_fee" => "0",
                        "status" => 0,
                        "oUUID" => $orderUuid
                    ]
                ],
                "groups" => [],
                "items" => [
                    [
                        "item" => ["name" => "Single Item", "description" => "Single item desc", "group" => 37966, "id" => 42261, "uuid" => "single-item-uuid", "lastMod" => $currentTime, "price" => 12.99],
                        "mods" => [],
                        "orderItem" => ["name" => "Single Item", "description" => "Single item desc", "group" => 37966, "id" => 42261, "uuid" => "single-item-uuid", "lastMod" => $currentTime, "price" => 12.99, "orderReference" => $orderRef, "discount" => 0, "status" => "REQUEST_INVENTORY_LOCK", "qty" => 1, "taxAmount" => "0.00"]
                    ]
                ],
                "termId" => "TERM123",
                "itemdata" => []
            ]
        ];

        $response = $this->makePostRequest($payload);
        $this->assertEquals(200, $response['httpCode'], "Expected 200 OK response");

        // Verify single order and item work correctly
        $pdo = $this->getConnection();
        $stmt = $pdo->prepare("SELECT oi.description FROM orderItems oi JOIN orders o ON oi.orders_id = o.id WHERE o.uuid = ?");
        $stmt->execute([$orderUuid]);
        $items = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $this->assertCount(1, $items, "Should have exactly 1 item");
        $this->assertEquals("Single item desc", $items[0], "Item should be correctly processed");
    }

    /**
     * Helper method to make POST requests
     */
    private function makePostRequest($payload)
    {
        $ch = curl_init($this->baseUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'X-Test-Mode: true'
        ));

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['body' => $body, 'httpCode' => $httpCode];
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up test data
        $pdo = $this->getConnection();

        if ($this->terminalId) {
            // Delete test data in reverse order of foreign key dependencies
            $stmt = $pdo->prepare("DELETE FROM orderItems WHERE terminals_id = ?");
            $stmt->execute([$this->terminalId]);

            $stmt = $pdo->prepare("DELETE FROM ordersPayments WHERE terminals_id = ?");
            $stmt->execute([$this->terminalId]);

            $stmt = $pdo->prepare("DELETE FROM orders WHERE terminals_id = ?");
            $stmt->execute([$this->terminalId]);

            $stmt = $pdo->prepare("DELETE FROM json WHERE serial = ?");
            $stmt->execute([$this->testSerial]);

            $stmt = $pdo->prepare("DELETE FROM terminals WHERE serial = ?");
            $stmt->execute([$this->testSerial]);
        }
    }
}