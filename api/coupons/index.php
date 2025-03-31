<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/api_response.php';
require_once __DIR__ . '/../../includes/classes/CouponValidator.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$database = new Database();
$db = $database->getConnection();
$couponValidator = new CouponValidator($db);

// Verify token and get user ID if provided
$headers = getallheaders();
$token = str_replace('Bearer ', '', $headers['Authorization'] ?? $headers['authorization'] ?? '');
$userId = null;

if ($token) {
    $stmt = $db->prepare("SELECT id FROM users WHERE api_token = ? AND is_active = 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $userId = $user ? $user['id'] : null;
}

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if (isset($_GET['code'])) {
                // Validate specific coupon
                $orderTotal = floatval($_GET['order_total'] ?? 0);
                $result = $couponValidator->validateCoupon($_GET['code'], $userId, $orderTotal);
                send_success("Coupon validation result", $result);
            } else if ($userId) {
                // Get user's coupon history
                $stmt = $db->prepare("
                    SELECT 
                        c.code,
                        c.discount_type,
                        c.discount_percentage,
                        c.discount_value,
                        o.order_id,
                        o.order_date,
                        o.total_amount,
                        o.discount_amount,
                        cu.used_at
                    FROM coupon_usage cu
                    JOIN coupons c ON cu.coupon_id = c.coupon_id
                    JOIN orders o ON cu.order_id = o.order_id
                    WHERE cu.user_id = ?
                    ORDER BY cu.used_at DESC
                ");
                $stmt->execute([$userId]);
                $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get available coupons
                $stmt = $db->prepare("
                    SELECT 
                        code,
                        discount_type,
                        CASE 
                            WHEN discount_type = 'percentage' THEN discount_percentage
                            ELSE discount_value 
                        END as discount_amount,
                        min_order_amount,
                        expiration_date,
                        usage_limit_per_user,
                        (
                            SELECT COUNT(*) 
                            FROM coupon_usage 
                            WHERE coupon_id = c.coupon_id 
                            AND user_id = ?
                        ) as times_used
                    FROM coupons c
                    WHERE status = 'active'
                    AND expiration_date >= CURRENT_DATE
                    AND (
                        usage_limit_total = 0 
                        OR usage_limit_total > (
                            SELECT COUNT(*) FROM coupon_usage 
                            WHERE coupon_id = c.coupon_id
                        )
                    )
                    ORDER BY created_at DESC
                ");
                $stmt->execute([$userId]);
                $available = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                send_success("Coupons retrieved", [
                    'history' => $history,
                    'available' => $available
                ]);
            } else {
                // Get public available coupons (limited info)
                $stmt = $db->prepare("
                    SELECT 
                        code,
                        discount_type,
                        CASE 
                            WHEN discount_type = 'percentage' THEN discount_percentage
                            ELSE discount_value 
                        END as discount_amount,
                        min_order_amount,
                        expiration_date
                    FROM coupons
                    WHERE status = 'active'
                    AND expiration_date >= CURRENT_DATE
                    AND is_public = TRUE
                    ORDER BY created_at DESC
                ");
                $stmt->execute();
                send_success("Public coupons retrieved", $stmt->fetchAll(PDO::FETCH_ASSOC));
            }
            break;

        default:
            send_error("Method not allowed", 405);
    }
} catch (Exception $e) {
    error_log("Coupons API Error: " . $e->getMessage());
    send_error("Server error", 500);
}
