<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/api_response.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function authenticate() {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';

    if (empty($authHeader) || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        send_error('No token provided', 401);
    }

    try {
        $token = $matches[1];
        $key = $_ENV['JWT_SECRET'];
        
        if (!$key) {
            throw new Exception('JWT secret not configured');
        }

        $decoded = JWT::decode($token, new Key($key, 'HS256'));
        
        // Verify token hasn't expired
        $now = new \DateTimeImmutable();
        if ($decoded->exp < $now->getTimestamp()) {
            send_error('Token has expired', 401);
        }

        // Get user from database
        $database = new Database();
        $db = $database->getConnection();
        
        $stmt = $db->prepare("SELECT id, username, email, role, status, is_active 
                             FROM users 
                             WHERE id = ? AND status = 'active' AND is_active = 1");
        $stmt->execute([$decoded->sub]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            send_error('User not found or inactive', 401);
        }

        return $user;

    } catch (Exception $e) {
        send_error('Invalid token: ' . $e->getMessage(), 401);
    }
}
