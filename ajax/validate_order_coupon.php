<?php
session_start();
require_once '../config/database.php';
require_once '../includes/classes/CouponValidator.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    $input = file_get_contents('php://input');
    if (!$input) {
        throw new Exception("No input data received");
    }

    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON data: " . json_last_error_msg());
    }
    
    if (!isset($data['couponCode'])) {
        throw new Exception("Coupon code is required");
    }
    
    $orderTotal = isset($data['orderTotal']) ? (float)$data['orderTotal'] : 0;
    
    // Validate the order total
    if ($orderTotal <= 0) {
        throw new Exception("Invalid order total");
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    $validator = new CouponValidator($db);
    $userId = $_SESSION['id'] ?? null;
    
    error_log("Validating coupon request - Code: {$data['couponCode']}, Total: $orderTotal, User: $userId");
    
    $result = $validator->validateCoupon(
        trim($data['couponCode']),
        $userId,
        $orderTotal
    );
    
    if ($result['valid']) {
        $_SESSION['valid_coupon'] = [
            'code' => $data['couponCode'],
            'discount_type' => $result['discount_type'],
            'discount_value' => $result['discount_value'],
            'discount_amount' => $result['discount_amount'],
            'coupon_id' => $result['coupon_id']
        ];
        error_log("Coupon validation successful: " . print_r($_SESSION['valid_coupon'], true));
    } else {
        error_log("Coupon validation failed: " . $result['message']);
    }

    echo json_encode($result);

} catch (Exception $e) {
    error_log("Coupon validation error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'valid' => false,
        'message' => $e->getMessage()
    ]);
}
