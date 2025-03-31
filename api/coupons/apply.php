<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/api_response.php';
require_once __DIR__ . '/../../includes/classes/CouponValidator.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error("Method not allowed", 405);
}

// Get JSON input
$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['code']) || !isset($data['order_total'])) {
    send_error("Missing required fields: code and order_total", 400);
}

// Verify token and get user ID if provided
$headers = getallheaders();
$token = str_replace('Bearer ', '', $headers['Authorization'] ?? $headers['authorization'] ?? '');
$userId = null;

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if ($token) {
        $stmt = $db->prepare("SELECT id FROM users WHERE api_token = ? AND is_active = 1");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $userId = $user ? $user['id'] : null;
    }

    $couponValidator = new CouponValidator($db);
    $result = $couponValidator->validateCoupon(
        $data['code'],
        $userId,
        floatval($data['order_total'])
    );

    if (!$result['valid']) {
        send_error($result['message'], 400);
    }

    // Calculate discount amount
    $discountAmount = 0;
    if ($result['discount_type'] === 'percentage') {
        $discountAmount = $data['order_total'] * ($result['discount_value'] / 100);
    } else {
        $discountAmount = min($result['discount_value'], $data['order_total']);
    }

    // Round to 2 decimal places
    $discountAmount = round($discountAmount, 2);
    $finalAmount = round($data['order_total'] - $discountAmount, 2);

    send_success("Coupon applied successfully", [
        'original_amount' => $data['order_total'],
        'discount_amount' => $discountAmount,
        'final_amount' => $finalAmount,
        'discount_type' => $result['discount_type'],
        'discount_value' => $result['discount_value'],
        'coupon_id' => $result['coupon_id'],
        'code' => $data['code']
    ]);

} catch (Exception $e) {
    error_log("Coupon Apply API Error: " . $e->getMessage());
    send_error("Server error", 500);
}
