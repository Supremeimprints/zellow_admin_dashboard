<?php
require_once __DIR__ . '/../config/api_config.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT;

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['email']) || !isset($data['password'])) {
        ApiResponse::error('Email and password are required', 400);
        exit;
    }

    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($data['password'], $user['password'])) {
            $payload = [
                'id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role'],
                'exp' => time() + (60 * 60) // 1 hour expiration
            ];

            $jwt = JWT::encode($payload, JWT_SECRET, 'HS256');
            
            ApiResponse::success([
                'token' => $jwt,
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ]
            ]);
        } else {
            ApiResponse::error('Invalid credentials', 401);
        }
    } catch (Exception $e) {
        ApiResponse::error('Login failed: ' . $e->getMessage(), 500);
    }
}
