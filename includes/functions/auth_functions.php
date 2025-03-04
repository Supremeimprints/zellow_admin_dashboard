<?php
require_once __DIR__ . '/../../config/database.php';

function verify_admin_token($token) {
    if (empty($token)) {
        return false;
    }

    // Remove 'Bearer ' if present
    $token = str_replace('Bearer ', '', $token);

    try {
        $database = new Database();
        $db = $database->getConnection();

        $stmt = $db->prepare("
            SELECT u.id 
            FROM users u
            WHERE u.api_token = ? 
            AND u.role = 'admin' 
            AND u.token_expiry > NOW()
            AND u.is_active = 1
        ");
        
        $stmt->execute([$token]);
        return $stmt->rowCount() > 0;

    } catch (Exception $e) {
        error_log("Token verification error: " . $e->getMessage());
        return false;
    }
}

function generate_admin_token($userId) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $stmt = $db->prepare("
            UPDATE users 
            SET api_token = ?, token_expiry = ? 
            WHERE id = ? AND role = 'admin'
        ");
        
        $stmt->execute([$token, $expiry, $userId]);
        return $token;
        
    } catch (Exception $e) {
        error_log("Token generation error: " . $e->getMessage());
        return false;
    }
}

function invalidate_admin_token($token) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $stmt = $db->prepare("
            UPDATE users 
            SET api_token = NULL, token_expiry = NULL 
            WHERE api_token = ? AND role = 'admin'
        ");
        
        return $stmt->execute([$token]);
        
    } catch (Exception $e) {
        error_log("Token invalidation error: " . $e->getMessage());
        return false;
    }
}
