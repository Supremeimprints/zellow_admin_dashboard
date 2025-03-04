<?php
require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Add status column if it doesn't exist
    $query = "ALTER TABLE users 
              ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive', 'suspended') 
              NOT NULL DEFAULT 'active'";
    
    $db->exec($query);

    // Update existing users to be active
    $updateQuery = "UPDATE users SET status = 'active' WHERE status IS NULL";
    $db->exec($updateQuery);

    echo "Successfully added status column to users table\n";

} catch (PDOException $e) {
    die("Error updating database: " . $e->getMessage());
}
