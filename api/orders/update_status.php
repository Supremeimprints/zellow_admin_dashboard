<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../controllers/OrderController.php';
require_once __DIR__ . '/../utils/auth.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $user = authenticate();
    $database = new Database();
    $db = $database->getConnection();
    $controller = new OrderController($db);
    
    $data = json_decode(file_get_contents("php://input"), true);
    $orderId = $data['order_id'] ?? null;
    
    switch($data['action']) {
        case 'approve':
            $controller->approveOrder($orderId, $user['id'], $user['role']);
            break;
        case 'assign_technician':
            $controller->assignTechnician($orderId, $data['technician_id'], $user['id'], $user['role']);
            break;
        case 'complete':
            $controller->markCompleted($orderId, $user['id'], $user['role']);
            break;
        case 'assign_driver':
            $controller->assignDriver($orderId, $data['driver_id'], $user['id'], $user['role']);
            break;
        default:
            send_error("Invalid action", 400);
    }
    
} catch (Exception $e) {
    send_error($e->getMessage(), 500);
}
