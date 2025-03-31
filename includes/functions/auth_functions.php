<?php
require_once __DIR__ . '/../../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function verify_admin_token($token) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Check if token exists and is not expired for an admin user
        $stmt = $db->prepare("
            SELECT id 
            FROM users 
            WHERE api_token = ? 
            AND role = 'admin' 
            AND is_active = 1 
            AND token_expiry > NOW()
        ");
        
        $stmt->execute([$token]);
        
        if ($stmt->fetch()) {
            return true;
        }
        
        error_log('Invalid token or token expired');
        return false;
        
    } catch (Exception $e) {
        error_log('Token verification failed: ' . $e->getMessage());
        return false;
    }
}

function verify_customer_token($token) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $stmt = $db->prepare("
            SELECT id 
            FROM users 
            WHERE api_token = ? 
            AND role = 'customer' 
            AND is_active = 1 
            AND token_expiry > NOW()
        ");
        
        $stmt->execute([$token]);
        return $stmt->fetch() ? true : false;
        
    } catch (Exception $e) {
        error_log('Customer token verification failed: ' . $e->getMessage());
        return false;
    }
}

function verify_token($token) {
    return verify_admin_token($token) || verify_customer_token($token);
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
