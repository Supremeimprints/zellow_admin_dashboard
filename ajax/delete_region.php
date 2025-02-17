<?php
require_once '../config/database.php';
require_once '../includes/functions/shipping_functions.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['region_id'])) {
        throw new Exception("Region ID is required");
    }

    $database = new Database();
    $db = $database->getConnection();

    if (deleteShippingRegion($db, $data['region_id'])) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Failed to delete region");
    }

} catch (Exception $e) {
    error_log("Delete region error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
