<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/api_response.php';
require_once __DIR__ . '/../../includes/middleware/auth_middleware.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // Public access for viewing feedback
            include_once 'list.php';
            break;
            
        case 'POST':
            // Requires user authentication
            authenticate();
            include_once 'create.php';
            break;
            
        case 'PUT':
            // Requires admin authentication
            $user = authenticate();
            if ($user->role !== 'admin') {
                send_error("Admin access required", 403);
            }
            include_once 'reply.php';
            break;
            
        default:
            send_error("Method not allowed", 405);
    }
} catch (Exception $e) {
    error_log("Feedback API Error: " . $e->getMessage());
    send_error("Server error", 500);
}
