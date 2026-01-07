<?php
require 'config/database.php';

$dbService = new DatabaseService();
$connection = $dbService->getConnection();

// List of migration versions to mark as completed
$migrations = [
    '20240817050935',
    '20240817072044',
    '20240818162541',
    '20240818163524',
    '20240818173921',
    '20240818174204',
    '20240818174254',
    '20240818174353',
    '20240818174438',
    '20240818174935',
    '20240821052355',
    '20240821052754',
    '20240821053429',
    '20240821053859',
    '20240821072120',
    '20240821072229',
    '20240821072435',
    '20240821072538',
    '20240821073227',
    '20240821073746',
    '20240821073933',
    '20240821074032',
    '20240821074129',
    '20240822030957',
    '20240831041157',
    '20240920075545',
    '20241217050633',
    '20241217084326',
    '20250109140405'
];

try {
    foreach ($migrations as $version) {
        $stmt = $connection->prepare("INSERT INTO phinxlog (version, migration_name, start_time, end_time, breakpoint) 
                                    VALUES (:version, :name, NOW(), NOW(), 0)");
        $stmt->execute([
            ':version' => $version,
            ':name' => 'Migration' // Placeholder name since we don't have the actual names
        ]);
        echo "Marked migration $version as completed\n";
    }
    echo "All migrations marked as completed successfully.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
