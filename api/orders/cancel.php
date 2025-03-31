<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../controllers/OrderController.php';
require_once __DIR__ . '/../utils/auth.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
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
    
    if (!isset($data['order_id']) || !isset($data['reason'])) {
        send_error("Order ID and reason are required", 400);
    }
    
    $controller->cancelOrder(
        $data['order_id'],
        $user['id'],
        $user['role'],
        $data['reason']
    );
    
} catch (Exception $e) {
    send_error($e->getMessage(), 500);
}
