<?php
require_once '../config/database.php';
require_once '../includes/functions/shipping_functions.php';

header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['region'])) {
        throw new Exception('Region ID is required');
    }
    
    $db = (new Database())->getConnection();
    $fee = calculateShippingFee(
        $db,
        $input['method'],
        $input['subtotal'],
        $input['itemCount'],
        $input['region'] // Make sure region is passed
    );
    
    echo json_encode(['fee' => $fee]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
