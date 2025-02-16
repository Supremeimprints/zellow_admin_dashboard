<?php
session_start();
require_once 'config/database.php';
require_once 'includes/classes/CouponValidator.php';

$database = new Database();
$db = $database->getConnection();

$response = ['valid' => false, 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['coupon_code'])) {
    $validator = new CouponValidator($db);
    $orderTotal = isset($_POST['order_total']) ? floatval($_POST['order_total']) : 0;
    $userId = isset($_SESSION['id']) ? $_SESSION['id'] : null;
    
    $result = $validator->validateCoupon($_POST['coupon_code'], $userId, $orderTotal);
    
    if ($result['valid']) {
        $_SESSION['valid_coupon'] = [
            'code' => $_POST['coupon_code'],
            'discount_type' => $result['discount_type'],
            'discount_value' => $result['discount_value'],
            'coupon_id' => $result['coupon_id']
        ];
    }
    
    $response = $result;
}

header('Content-Type: application/json');
echo json_encode($response);
