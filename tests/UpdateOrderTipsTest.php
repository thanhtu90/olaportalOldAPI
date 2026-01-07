<?php

namespace Tests;

use PDO;
use Faker\Factory;

class UpdateOrderTipsTest extends BaseTest
{
    private $baseUrl = 'http://localhost:8888/json.php';
    private $testSerial = 'dea7b0c9-5e18-40a4-85f8-517d473a402f';
    private $terminalId;
    private $pdo;
    private $faker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->faker = Factory::create();
        
        // Set up database connection
        $this->pdo = new PDO(
            'mysql:host=127.0.0.1;port=3306;dbname=app_db',
            'app_user',
            'app_user_password',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        // Create test terminal
        $stmt = $this->pdo->prepare("
            INSERT INTO terminals (
                vendors_id,
                serial,
                description,
                onlinestorename,
                tech_fee,
                address,
                phone,
                enterdate,
                lastmod
            ) VALUES (
                1,
                ?,
                'Test Terminal',
                'Test Store',
                0,
                '',
                '',
                NOW(3),
                NOW(3)
            )
        ");
        $stmt->execute([$this->testSerial]);
        $this->terminalId = $this->pdo->lastInsertId();

        // Double check that we have the terminal ID
        $stmt = $this->pdo->prepare("SELECT id FROM terminals WHERE serial = ?");
        $stmt->execute([$this->testSerial]);
        $result = $stmt->fetch();
        if ($result) {
            $this->terminalId = $result['id'];
        }
    }

    public function testUpdateOrderTips()
    {
        $pdo = $this->getConnection();

        // Get current order data
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE terminals_id = ? AND orderReference = ?");
        $stmt->execute([$this->terminalId, $this->faker->numberBetween(10000, 99999)]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);

        // Prepare update payload with new tips and higher lastMod
        $newTip = 10.00;
        $newLastMod = time();

        // Create payment data
        $payment = [
            'amtPaid' => 110.00,
            'employeeId' => 0,
            'id' => 1,
            'lastMod' => $newLastMod,
            'orderID' => (string)$order['orderReference'],
            'orderReference' => (string)$order['orderReference'],
            'payDate' => date('M d, Y h:i:s A'),
            'refNumber' => 'TEST123',
            'refund' => 0,
            'status' => 'PAID',
            'techfee' => 0,
            'tips' => $newTip,
            'total' => 110.00
        ];

        // Create the inner JSON string with JSON-encoded fields
        $jsonString = [
            'payments' => json_encode([$payment]),
            'orders' => json_encode([]),
            'items' => json_encode([]),
            'groups' => json_encode([]),
            'itemdata' => json_encode([]),
            'termId' => json_encode('TEST123')
        ];

        // Create request data with double-encoded JSON
        $requestData = [
            'serial' => $this->testSerial,
            'json' => json_encode($jsonString)
        ];

        // Make the POST request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/json.php');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Test-Mode: true'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Debug output
        echo "\nResponse: " . $response . "\n";
        echo "HTTP Code: " . $httpCode . "\n";
        echo "Raw JSON: " . json_encode($jsonString) . "\n";
        echo "Request Data: " . json_encode($requestData) . "\n";

        // Assert response
        $this->assertEquals(200, $httpCode, "Expected 200 OK response");

        // Verify database update
        $stmt = $pdo->prepare("SELECT tip FROM orders WHERE terminals_id = ? AND orderReference = ?");
        $stmt->execute([$this->terminalId, $order['orderReference']]);
        $updatedTip = $stmt->fetchColumn();

        $this->assertEquals($newTip, $updatedTip, "Tip should be updated to new value");
    }

    public function testUpdateOrderTipsWithLocalDb()
    {
        // Create a test order with initial values
        $initialTip = 5.00;
        $initialTotal = 100.00;
        $initialTechFee = 2.00;
        $orderRef = mt_rand(10000, 99999);
        $stmt = $this->pdo->prepare("
            INSERT INTO orders (
                terminals_id,
                orderReference,
                tip,
                tech_fee,
                vendors_id,
                agents_id,
                subTotal,
                tax,
                total,
                notes,
                orderName,
                employee_id,
                OrderDate,
                delivery_type,
                delivery_fee,
                status,
                lastMod
            ) VALUES (
                ?,
                ?,
                ?,
                ?,
                1,
                1,
                100.00,
                10.00,
                ?,
                '',
                '',
                0,
                NOW(),
                0,
                0,
                0,
                ?
            )
        ");
        $stmt->execute([
            $this->terminalId,
            $orderRef,
            $initialTip,
            $initialTechFee,
            $initialTotal,
            time() - 3600 // lastMod 1 hour ago
        ]);

        // Verify initial values
        $stmt = $this->pdo->prepare("SELECT tip, tech_fee, total FROM orders WHERE terminals_id = ? AND orderReference = ?");
        $stmt->execute([$this->terminalId, $orderRef]);
        $result = $stmt->fetch();
        $this->assertEquals($initialTip, $result['tip'], "Initial tip should be set");
        $this->assertEquals($initialTechFee, $result['tech_fee'], "Initial tech fee should be set");
        $this->assertEquals($initialTotal, $result['total'], "Initial total should be set");

        // Set up new values
        $newTip = 15.00;
        $newTotal = 110.00;
        $newTechFee = 5.00;
        $newLastMod = time() + 60 * 60 * 24 * 365; // One year in the future

        // Create payment data
        $payment = [
            'amtPaid' => $newTotal,
            'employeeId' => 0,
            'id' => 1,
            'lastMod' => $newLastMod,
            'orderID' => (string)$orderRef,
            'orderReference' => (string)$orderRef,
            'payDate' => date('M d, Y h:i:s A'),
            'refNumber' => 'TEST123',
            'refund' => 0,
            'status' => 'PAID',
            'techfee' => $newTechFee,
            'tips' => $newTip,
            'total' => $newTotal
        ];

        // Create the inner JSON string with JSON-encoded fields
        $jsonString = [
            'payments' => json_encode([$payment]),
            'orders' => json_encode([]),
            'items' => json_encode([]),
            'groups' => json_encode([]),
            'itemdata' => json_encode([]),
            'termId' => json_encode('TEST123')
        ];

        // Create request data with double-encoded JSON
        $requestData = [
            'serial' => $this->testSerial,
            'json' => json_encode($jsonString)
        ];

        // Make the POST request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Test-Mode: true'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Debug output
        echo "\nResponse: " . $response . "\n";
        echo "HTTP Code: " . $httpCode . "\n";
        echo "Raw JSON: " . json_encode($jsonString) . "\n";
        echo "Request Data: " . json_encode($requestData) . "\n";

        // Assert response
        $this->assertEquals(200, $httpCode, "Expected 200 OK response");

        // Verify the database changes
        $stmt = $this->pdo->prepare("SELECT tip, tech_fee, total FROM orders WHERE terminals_id = ? AND orderReference = ?");
        $stmt->execute([$this->terminalId, $orderRef]);
        $result = $stmt->fetch();
        $this->assertEquals($newTip, $result['tip'], "Order tip should be updated");
        $this->assertEquals($newTechFee, $result['tech_fee'], "Order tech fee should be updated");
        $this->assertEquals($newTotal, $result['total'], "Order total should be updated");
    }

    protected function tearDown(): void
    {
        if ($this->pdo) {
            // Clean up test data
            $stmt = $this->pdo->prepare("DELETE FROM orders WHERE terminals_id = ?");
            $stmt->execute([$this->terminalId]);

            $stmt = $this->pdo->prepare("DELETE FROM terminals WHERE id = ?");
            $stmt->execute([$this->terminalId]);

            $this->pdo = null;
        }

        parent::tearDown();
    }
}