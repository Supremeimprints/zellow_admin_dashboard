<?php
require_once '../config/database.php';
require_once '../includes/functions/shipping_functions.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['methodId'], $data['regionId'], $data['itemCount'], $data['subtotal'])) {
        throw new Exception("Missing required parameters");
    }

    $database = new Database();
    $db = $database->getConnection();

    // Validate inputs
    $methodId = filter_var($data['methodId'], FILTER_VALIDATE_INT);
    $regionId = filter_var($data['regionId'], FILTER_VALIDATE_INT);
    $itemCount = max(1, filter_var($data['itemCount'], FILTER_VALIDATE_INT));
    $subtotal = filter_var($data['subtotal'], FILTER_VALIDATE_FLOAT);

    if (!$methodId || !$regionId) {
        throw new Exception("Invalid method or region ID");
    }

    $shippingCost = calculateShippingCost($db, $methodId, $regionId, $itemCount, $subtotal);
    
    if ($shippingCost === null) {
        throw new Exception("Could not calculate shipping cost");
    }

    echo json_encode([
        'success' => true,
        'fee' => $shippingCost
    ]);

} catch (Exception $e) {
    error_log("Shipping calculation error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
