<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../controllers/OrderController.php';
require_once __DIR__ . '/../utils/auth.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Verify JWT token and get user data
    $user = authenticate();
    
    $database = new Database();
    $db = $database->getConnection();
    
    $controller = new OrderController($db);
    
    // Get posted data
    $data = json_decode(file_get_contents("php://input"), true);
    
    // Validate payment method exists and is valid for user
    $paymentMethodId = $data['payment_method_id'] ?? null;
    if ($paymentMethodId) {
        $stmt = $db->prepare("
            SELECT * FROM payment_methods 
            WHERE id = ? AND user_id = ? AND is_verified = 1");
        $stmt->execute([$paymentMethodId, $user['id']]);
        
        if (!$stmt->fetch()) {
            send_error("Invalid or unverified payment method", 400);
        }
    } else if ($data['payment_type'] !== 'Cash On Delivery') {
        send_error("Payment method is required", 400);
    }

    // Start transaction
    $db->beginTransaction();

    try {
        // Insert order with payment method
        $stmt = $db->prepare("
            INSERT INTO orders (
                id, email, total_amount, discount_amount, 
                status, shipping_address, payment_status,
                payment_method, shipping_fee, customization_type,
                customization_details, customization_cost,
                payment_method_id
            ) VALUES (
                ?, ?, ?, ?, 
                'Pending', ?, 'Pending',
                ?, ?, ?, 
                ?, ?, ?
            )");
        
        $stmt->execute([
            $user['id'],
            $user['email'],
            $data['total_amount'],
            $data['discount_amount'] ?? 0.00,
            $data['shipping_address'],
            $data['payment_method'] ?? 'Mpesa',
            $data['shipping_fee'] ?? 0.00,
            $data['customization_type'] ?? null,
            $data['customization_details'] ?? null,
            $data['customization_cost'] ?? 0.00,
            $paymentMethodId
        ]);
        
        $orderId = $db->lastInsertId();

        // Update payment method usage
        if (isset($data['payment_method_id'])) {
            $stmt = $db->prepare("
                UPDATE payment_methods 
                SET usage_count = usage_count + 1,
                    last_used = NOW()
                WHERE id = ?");
            $stmt->execute([$data['payment_method_id']]);
        }

        $db->commit();
        send_success("Order created successfully", ['order_id' => $orderId]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Order Creation Error: " . $e->getMessage());
    send_error("Server error: " . $e->getMessage(), 500);
}
