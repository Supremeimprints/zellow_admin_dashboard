<?php
require_once 'config/database.php';
require_once 'includes/functions/marketing_functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    $couponCode = $_POST['coupon_code'] ?? '';
    
    try {
        $stmt = $db->prepare("SELECT * FROM coupons WHERE code = ? AND expiration_date >= CURDATE()");
        $stmt->execute([$couponCode]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($coupon) {
            echo json_encode([
                'valid' => true,
                'discount' => $coupon['discount_percentage'],
                'message' => 'Coupon applied successfully!'
            ]);
        } else {
            echo json_encode([
                'valid' => false,
                'message' => 'Invalid or expired coupon code'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'valid' => false,
            'message' => 'Error validating coupon'
        ]);
    }
}
