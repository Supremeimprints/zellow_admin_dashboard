<?php
require_once __DIR__ . '/../../config/database.php';

class AuthManager {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $db = $database->getConnection();
    }

    public function setDatabase($db) {
        $this->db = $db;
    }

    public function validateToken($token) {
        if (!$this->db) {
            throw new Exception('Database connection not set');
        }

        if (empty($token)) {
            return false;
        }

        try {
            $stmt = $this->db->prepare("
                SELECT id, username, role, is_active, customer_type
                FROM users 
                WHERE api_token = ? 
                AND is_active = 1
                AND status = 'active'
                AND (token_expiry IS NULL OR token_expiry > NOW())
            ");
            
            $stmt->execute([$token]);
            return $stmt->fetch(PDO::FETCH_OBJ);
            
        } catch (Exception $e) {
            error_log("Token validation error: " . $e->getMessage());
            return false;
        }
    }

    public function isAdmin($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT role FROM users 
                WHERE id = ? AND role = 'admin' 
                AND is_active = 1
                AND status = 'active'
            ");
            
            $stmt->execute([$userId]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Admin check error: " . $e->getMessage());
            return false;
        }
    }
}
