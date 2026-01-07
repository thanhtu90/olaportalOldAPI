<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class InitMigration extends AbstractMigration
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
    public function up(): void
    {
        // Path to your SQL dump file
        $sqlDumpFile = __DIR__ . '/../schema/api2.sql';

        // Check if the file exists
        if (!file_exists($sqlDumpFile)) {
            throw new \RuntimeException("SQL dump file not found: " . $sqlDumpFile);
        }

        // Read the contents of the file
        $sql = file_get_contents($sqlDumpFile);

        // Split SQL into individual statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        // Execute each statement separately
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                try {
                    $this->execute($statement);
                } catch (\PDOException $e) {
                    // Skip if table already exists (error code 42S01)
                    if ($e->getCode() !== '42S01') {
                        throw $e;
                    }
                }
            }
        }
    }

    public function down(): void
    {
        // Define how to rollback the migration if necessary
    }
}
