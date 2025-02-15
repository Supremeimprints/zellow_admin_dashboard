<?php
session_start();
require_once 'config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    $couponCode = $_POST['coupon_code'] ?? '';
    
    try {
        $stmt = $db->prepare("
            SELECT * FROM coupons 
            WHERE code = ? 
            AND expiration_date >= CURRENT_DATE()
            AND status = 'active'
        ");
        
        $stmt->execute([$couponCode]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($coupon) {
            $expirationDate = new DateTime($coupon['expiration_date']);
            $today = new DateTime();
            
            if ($expirationDate < $today) {
                unset($_SESSION['valid_coupon']);
                echo json_encode([
                    'valid' => false,
                    'message' => 'This coupon has expired',
                    'status' => 'expired'
                ]);
                exit;
            }
            
            $_SESSION['valid_coupon'] = [
                'code' => $coupon['code'],
                'discount_percentage' => $coupon['discount_percentage'],
                'expiration_date' => $coupon['expiration_date']
            ];
            
            echo json_encode([
                'valid' => true,
                'discount' => $coupon['discount_percentage'],
                'message' => 'Coupon applied successfully!',
                'status' => 'active'
            ]);
        } else {
            unset($_SESSION['valid_coupon']);
            echo json_encode([
                'valid' => false,
                'message' => 'Invalid or expired coupon code',
                'status' => 'invalid'
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'valid' => false,
            'message' => 'Error validating coupon',
            'status' => 'error'
        ]);
    }
}
