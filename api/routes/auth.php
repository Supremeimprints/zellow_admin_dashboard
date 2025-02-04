<?php
require_once __DIR__ . '/../config/api_config.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        
        if ($_SERVER['REQUEST_URI'] === '/api/auth/login') {
            // Login logic
            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';
            
            $stmt = $db->prepare("SELECT id, username, email, role, password FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                $token = JWT::encode([
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'exp' => time() + JWT_EXPIRES
                ], JWT_SECRET, 'HS256');
                
                ApiResponse::success([
                    'token' => $token,
                    'user' => [
                        'id' => $user['id'],
                        'email' => $user['email'],
                        'role' => $user['role']
                    ]
                ]);
            }
            
            ApiResponse::error('Invalid credentials', 401);
        }
        break;
}
