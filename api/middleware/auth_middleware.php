<?php
require_once __DIR__ . '/../../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware {
    private $excludedPaths = [
        '/api/auth/login',
        '/api/auth/register',
        '/api/products/list',
        '/api/categories/list'
    ];

    public function handleRequest() {
        $requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Skip authentication for excluded paths
        if (in_array($requestPath, $this->excludedPaths)) {
            return true;
        }

        $token = $this->getBearerToken();
        if (!$token) {
            http_response_code(401);
            echo json_encode(['error' => 'No token provided']);
            exit();
        }

        try {
            $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
            
            // Check if user has permission for this endpoint
            if (!$this->checkPermission($decoded->role, $requestPath)) {
                http_response_code(403);
                echo json_encode(['error' => 'Unauthorized access']);
                exit();
            }
            
            // Add user info to request
            $_REQUEST['user'] = $decoded;
            return true;
            
        } catch(Exception $e) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid token']);
            exit();
        }
    }

    private function getBearerToken() {
        $headers = apache_request_headers();
        if (isset($headers['Authorization'])) {
            if (preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches)) {
                return $matches[1];
            }
        }
        return null;
    }

    private function checkPermission($role, $path) {
        // Extract resource from path (e.g., /api/products/list -> products)
        preg_match('/\/api\/([^\/]+)/', $path, $matches);
        $resource = $matches[1] ?? '';

        if (!isset(PERMISSIONS[$role])) {
            return false;
        }

        return in_array('*', PERMISSIONS[$role]) || 
               in_array($resource, PERMISSIONS[$role]);
    }
}
