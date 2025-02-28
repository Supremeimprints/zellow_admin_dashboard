<?php
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

try {
    // Check if columns already exist to prevent errors
    $checkColumns = $db->query("SHOW COLUMNS FROM order_items LIKE 'service_cost'");
    if ($checkColumns->fetch()) {
        echo "Service columns already exist. Migration skipped.\n";
        exit;
    }

    // Start transaction
    $db->beginTransaction();

    // Add service-related columns to order_items table
    $alterTableQuery = "ALTER TABLE order_items 
        ADD COLUMN IF NOT EXISTS service_type ENUM('engraving', 'printing') NULL,
        ADD COLUMN IF NOT EXISTS service_details TEXT NULL,
        ADD COLUMN IF NOT EXISTS service_cost DECIMAL(10,2) DEFAULT 0.00";
    
    $db->exec($alterTableQuery);

    // Update existing records to have 0 service cost
    $updateQuery = "UPDATE order_items SET service_cost = 0.00 WHERE service_cost IS NULL";
    $db->exec($updateQuery);

    // Create indexes for better performance
    $indexQueries = [
        "CREATE INDEX IF NOT EXISTS idx_service_type ON order_items (service_type)",
        "CREATE INDEX IF NOT EXISTS idx_service_cost ON order_items (service_cost)"
    ];

    foreach ($indexQueries as $query) {
        $db->exec($query);
    }

    // Commit the transaction
    $db->commit();
    echo "Migration completed successfully!\n";

} catch (PDOException $e) {
    // Only try to rollback if a transaction is active
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
