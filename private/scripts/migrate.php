<?php
require_once __DIR__.'/../database/database.php';

class MigrationManager {
    private $connection;
    private $migrationsDir = __DIR__.'/../database/migrations/';

    public function __construct() {
        $db = new Database();
        $this->connection = $db->getConnection();
        $this->createMigrationsTable();
    }

    private function createMigrationsTable() {
        $this->connection->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(250) UNIQUE,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    public function migrate() {
        $executed = $this->getExecutedMigrations();
        $files = glob($this->migrationsDir.'*.php');
        
        foreach ($files as $file) {
            $migrationName = basename($file);
            if (!in_array($migrationName, $executed)) {
                $this->executeMigration($file, $migrationName);
            }
        }
    }

    private function executeMigration($file, $migrationName) {
        try {
            require_once $file;
            
            $className = $this->getClassNameFromFileName($migrationName);
            $migration = new $className();
            
            $this->connection->beginTransaction();
            $migration->up($this->connection);
            $this->connection->exec("
                INSERT INTO migrations (migration) 
                VALUES ('$migrationName')
            ");
            $this->connection->commit();
            
            echo "\033[32mMigrated: $migrationName\033[0m\n";
        } catch (Exception $e) {
            $this->connection->rollBack();
            echo "\033[31mFailed: $migrationName - ".$e->getMessage()."\033[0m\n";
            exit(1);
        }
    }

    private function getClassNameFromFileName($fileName) {
        $className = str_replace('.php', '', $fileName);
        $className = preg_replace('/^\d+-/', '', $className); // Remove leading numbers and hyphens
        $className = str_replace(['-', '_'], ' ', $className); // Replace hyphens and underscores with spaces
        $className = ucwords($className); // Uppercase each word
        $className = str_replace(' ', '', $className); // Remove spaces
        return $className;
    }

    private function getExecutedMigrations() {
        $stmt = $this->connection->query("SELECT migration FROM migrations");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

// Execute migrations
try {
    $manager = new MigrationManager();
    $manager->migrate();
    echo "\033[34mAll migrations completed successfully!\033[0m\n";
} catch (Exception $e) {
    echo "\033[31mMigration error: ".$e->getMessage()."\033[0m\n";
    exit(1);
}