<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/api_response.php';
require_once __DIR__ . '/../../includes/functions/shipping_functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Method not allowed', 405);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['methodId'], $data['regionId'], $data['itemCount'])) {
        send_error('Missing required parameters', 400);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    $shippingCost = calculateShippingCost(
        $db,
        $data['methodId'],
        $data['regionId'],
        $data['itemCount'],
        $data['subtotal'] ?? 0
    );
    
    if ($shippingCost === null) {
        send_error('Could not calculate shipping cost', 400);
        exit;
    }

    send_success('Shipping cost calculated', [
        'fee' => $shippingCost,
        'currency' => 'KES',
        'formatted' => 'Ksh. ' . number_format($shippingCost, 2)
    ]);

} catch (Exception $e) {
    error_log("Shipping calculation error: " . $e->getMessage());
    send_error('Error calculating shipping cost', 500);
}
