<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/api_response.php';
require_once __DIR__ . '/../../includes/middleware/auth_middleware.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$database = new Database();
$db = $database->getConnection();

try {
    // Public endpoint for listing categories
    if ($method === 'GET') {
        include_once 'list.php';
        exit();
    }

    // Protected endpoints - require admin authentication
    $userData = authenticate();
    if (!$userData || $userData->role !== 'admin') {
        send_error('Unauthorized access', 401);
    }

    // Admin-only endpoints
    switch ($method) {
        case 'POST':
            include_once 'create.php';
            break;
        case 'PUT':
            include_once 'update.php';
            break;
        case 'DELETE':
            include_once 'delete.php';
            break;
        default:
            send_error('Method not allowed', 405);
    }
} catch (Exception $e) {
    send_error($e->getMessage(), 500);
}
