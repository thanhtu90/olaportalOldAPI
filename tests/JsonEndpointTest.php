<?php

use PHPUnit\Framework\TestCase;

class JsonEndpointTest extends TestCase
{
    private $baseUrl = 'http://localhost:8000/json.php';
    private $testSerial = 'TEST_TERMINAL_123';
    private $db_host = "127.0.0.1";
    private $db_name = "app_db";
    private $db_user = "app_user";
    private $db_password = "app_user_password";

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
    }

    public function testInventoryLockRequest()
    {
        $orderRef = 12345; // Using numeric order reference
        $orderUuid = "8d1c9bc1-8b1e-4351-9137-b0f4d380d21b";
        $currentTime = time();

        $payload = [
            "serial" => $this->testSerial,
            "json" => [
                "payments" => null,
                "hasInventory" => true,
                "orders" => [
                    [
                        "uuid" => $orderUuid,
                        "id" => $orderRef,
                        "subTotal" => 1.99,
                        "tax" => 0,
                        "total" => 1.99,
                        "notes" => "",
                        "lastMod" => $currentTime,
                        "orderName" => "Test Order",
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
                            "name" => "TestingDbMergeItem1",
                            "description" => "testing",
                            "group" => 37966,
                            "id" => 42255,
                            "uuid" => "a1a1def7-ac15-4a2f-9b24-d3def9f56f58",
                            "lastMod" => $currentTime,
                            "price" => 1.99,
                            "priceInt" => 199,
                            "taxRate" => 0,
                            "taxable" => 0,
                            "upc" => "",
                            "amountOnHand" => 0,
                            "cost" => 0,
                            "notes" => "",
                            "smallImage" => "",
                            "largeImage" => "",
                            "isEbt" => 0,
                            "isManualPrice" => 0,
                            "isWeighted" => 0,
                            "crv" => 0,
                            "crv_taxable" => false,
                            "printType" => 0
                        ],
                        "mods" => [],
                        "orderItem" => [
                            "name" => "TestingDbMergeItem1",
                            "description" => "TestingDbMergeItem1",
                            "group" => 37966,
                            "id" => 42255,
                            "uuid" => "a1a1def7-ac15-4a2f-9b24-d3def9f56f58",
                            "lastMod" => $currentTime,
                            "price" => 1.99,
                            "priceInt" => 199,
                            "taxRate" => 0,
                            "taxable" => 0,
                            "upc" => "",
                            "amountOnHand" => 0,
                            "cost" => 0,
                            "notes" => "",
                            "smallImage" => "",
                            "largeImage" => "",
                            "isEbt" => 0,
                            "isManualPrice" => 0,
                            "isWeighted" => 0,
                            "crv" => 0,
                            "crv_taxable" => false,
                            "printType" => 0,
                            "orderReference" => $orderRef,
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
        $ch = curl_init($this->baseUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'X-Test-Mode: true'
        ));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Debug output
        echo "\nResponse:\n" . $response . "\n";
        echo "HTTP Code: " . $httpCode . "\n";

        // Assert response
        $this->assertEquals(200, $httpCode, "Expected 200 OK response");

        // Decode response
        $responseData = json_decode($response, true);
        $this->assertNotNull($responseData, "Response should be valid JSON");

        // Verify the success message
        $this->assertEquals("Data was successfully inserted.", $responseData['message'] ?? null);

        // Verify database state
        $pdo = $this->getConnection();

        // Check if order was inserted
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE terminals_id = (SELECT id FROM terminals WHERE serial = ?)");
        $stmt->execute([$this->testSerial]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertNotNull($order, "Order should be inserted in database");
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up test data
        $pdo = $this->getConnection();

        // Get terminal ID
        $stmt = $pdo->prepare("SELECT id FROM terminals WHERE serial = ?");
        $stmt->execute([$this->testSerial]);
        $terminalId = $stmt->fetchColumn();

        if ($terminalId) {
            // Delete test data in reverse order of foreign key dependencies
            $stmt = $pdo->prepare("DELETE FROM orderItems WHERE terminals_id = ?");
            $stmt->execute([$terminalId]);

            $stmt = $pdo->prepare("DELETE FROM ordersPayments WHERE terminals_id = ?");
            $stmt->execute([$terminalId]);

            $stmt = $pdo->prepare("DELETE FROM orders WHERE terminals_id = ?");
            $stmt->execute([$terminalId]);

            $stmt = $pdo->prepare("DELETE FROM json WHERE serial = ?");
            $stmt->execute([$this->testSerial]);

            $stmt = $pdo->prepare("DELETE FROM terminals WHERE serial = ?");
            $stmt->execute([$this->testSerial]);
        }
    }
}
