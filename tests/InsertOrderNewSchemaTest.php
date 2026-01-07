<?php

namespace Tests;

use PDO;
use PDOException;

class InsertOrderNewSchemaTest extends BaseTest
{
    private $connection;

    public function getConnection()
    {
        $this->connection = null;
        try {
            $this->connection = new PDO("mysql:host=" . $this->db_host . ";dbname=" . $this->db_name, $this->db_user, $this->db_password);
        } catch (PDOException $exception) {
            echo "Connection failed: " . $exception->getMessage();
        }
        return $this->connection;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->getConnection();
    }

    public function testInsertOrderEmptyUuid()
    {
        $jsonString = file_get_contents(__DIR__ . '/example_payload.json');

        $data = json_decode($jsonString, true);
        $order = $data['orders'][0];
        $payments = $data['payments'];
        $items = $data['items'];

        $stmt = $this->connection->prepare("select * from terminals where serial = ?");
        $serial = "APN7002";
        $stmt->execute([$serial]);
        if ($stmt->rowCount() == 0) {
            // error
        }
        $row = $stmt->fetch();

        #get terminal id, vendor id, and agent id
        $terminals_id = $row["id"];
        $vendors_id = $row["vendors_id"];
        $stmt2 = $this->connection->prepare("select * from accounts where id = ?");
        $stmt2->execute([$vendors_id]);
        $row2 = $stmt2->fetch();
        $agents_id = $row2["accounts_id"];


        $stmt = $this->connection->prepare("insert into orders set uuid = ?, agents_id = ?, vendors_id = ?, terminals_id = ?, orderReference = ?, subTotal = ?, tax = ?, total = ?, notes = ?, lastMod = ?, orderName = ?, employee_id = ?, OrderDate = ?, status = ?");
        $stmt->execute([
            null,
            $agents_id,
            $vendors_id,
            $data['termId'],
            $order['id'],
            $order['subTotal'],
            $order['tax'],
            $order['total'],
            $order['notes'],
            $order['lastMod'],
            'test_mock_order_name',
            1,
            strtotime(date('Y-m-d H:i:s')),
            0
        ]);

        $stmt = $this->connection->query('SELECT COUNT(*) FROM orders');
        $count = $stmt->fetchColumn();

        $this->assertSame(1, (int)$count);
    }


    public function testInsertOrderHasUuid()
    {
        $jsonString = file_get_contents(__DIR__ . '/example_payload.json');

        $data = json_decode($jsonString, true);
        $order = $data['orders'][0];
        $payments = $data['payments'];
        $items = $data['items'];

        $stmt = $this->connection->prepare("select * from terminals where serial = ?");
        $serial = "APN7002";
        $stmt->execute([$serial]);
        if ($stmt->rowCount() == 0) {
            // error
        }
        $row = $stmt->fetch();

        #get terminal id, vendor id, and agent id
        $terminals_id = $row["id"];
        $vendors_id = $row["vendors_id"];
        $stmt2 = $this->connection->prepare("select * from accounts where id = ?");
        $stmt2->execute([$vendors_id]);
        $row2 = $stmt2->fetch();
        $agents_id = $row2["accounts_id"];


        $stmt = $this->connection->prepare("insert into orders set uuid = ?, agents_id = ?, vendors_id = ?, terminals_id = ?, orderReference = ?, subTotal = ?, tax = ?, total = ?, notes = ?, lastMod = ?, orderName = ?, employee_id = ?, OrderDate = ?, status = ?");
        $stmt->execute([
            "sample-uuid",
            $agents_id,
            $vendors_id,
            $data['termId'],
            $order['id'],
            $order['subTotal'],
            $order['tax'],
            $order['total'],
            $order['notes'],
            $order['lastMod'],
            'test_mock_order_name',
            1,
            strtotime(date('Y-m-d H:i:s')),
            0
        ]);

        $stmt = $this->connection->query('SELECT COUNT(*) FROM orders');
        $count = $stmt->fetchColumn();

        $this->assertSame(1, (int)$count);
    }

    protected function tearDown(): void
    {
        $this->connection->exec('DELETE FROM orders');
    }
}
