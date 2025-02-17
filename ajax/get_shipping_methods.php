<?php
require_once '../config/database.php';
require_once '../includes/functions/shipping_functions.php';

header('Content-Type: application/json');

try {
    $regionId = filter_input(INPUT_GET, 'region_id', FILTER_VALIDATE_INT);
    if (!$regionId) {
        throw new Exception("Invalid region ID");
    }

    $database = new Database();
    $db = $database->getConnection();

    $methods = getRegionShippingMethods($db, $regionId);

    if (empty($methods)) {
        echo json_encode([
            'success' => true,
            'methods' => [],
            'message' => 'No shipping methods available for this region'
        ]);
        exit;
    }

    // Format the methods for display
    $formattedMethods = array_map(function($method) {
        return [
            'id' => $method['id'],
            'name' => $method['name'],
            'display_name' => $method['display_name'],
            'base_rate' => $method['base_rate'],
            'per_item_fee' => $method['per_item_fee'],
            'estimated_days' => $method['estimated_days'],
            'free_shipping_threshold' => $method['free_shipping_threshold']
        ];
    }, $methods);

    echo json_encode([
        'success' => true,
        'methods' => $formattedMethods
    ]);

} catch (Exception $e) {
    error_log("Shipping methods error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
