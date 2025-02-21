<?php
require_once '../config/database.php';
require_once '../includes/functions/order_functions.php';

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents('php://input'), true);

try {
    // Validate input
    if (empty($data['order_id']) || empty($data['customizations'])) {
        throw new Exception("Missing required fields");
    }

    $result = processOrderCustomizations($db, $data['order_id'], $data['customizations']);
    
    if (!$result['success']) {
        throw new Exception($result['error']);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Customizations updated successfully'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
