<?php
require_once '../../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Add token columns if they don't exist
    $query = "ALTER TABLE users 
              ADD COLUMN IF NOT EXISTS api_token VARCHAR(255) NULL,
              ADD COLUMN IF NOT EXISTS token_expiry DATETIME NULL,
              ADD INDEX idx_api_token (api_token)";
    
    $db->exec($query);

    echo "Successfully added token columns to users table\n";

} catch (PDOException $e) {
    die("Error updating database: " . $e->getMessage());
}
