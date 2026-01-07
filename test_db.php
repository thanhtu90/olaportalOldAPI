<?php
try {
    $pdo = new PDO("mysql:host=localhost;dbname=api2", "api2", "Ukkjh^%trxfD");
    echo "Connection successful!\n";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    print_r($tables);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
