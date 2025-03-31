<?php
require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Read and execute migration SQL
    $sql = file_get_contents(__DIR__ . '/migrations/create_customer_tables.sql');
    $db->exec($sql);
    
    echo "Migration completed successfully\n";
} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
