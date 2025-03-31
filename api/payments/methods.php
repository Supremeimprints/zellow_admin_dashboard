<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/api_response.php';
require_once __DIR__ . '/../../includes/functions/auth_functions.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verify token and get user ID
$headers = getallheaders();
$token = str_replace('Bearer ', '', $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '');

if (!$token) {
    send_error("No authorization token provided", 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get user ID from token
    $stmt = $db->prepare("SELECT id FROM users WHERE api_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        send_error("Invalid token", 401);
    }
    
    $userId = $user['id'];

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // List payment methods with usage info
            $stmt = $db->prepare("
                SELECT 
                    pm.*,
                    CASE 
                        WHEN pm.method_type = 'Credit Card' THEN CONCAT('**** **** **** ', pm.card_last_four)
                        WHEN pm.method_type IN ('Mpesa', 'Airtel money') THEN CONCAT('**** ', RIGHT(pm.phone_number, 4))
                        ELSE 'Cash On Delivery'
                    END as display_number,
                    COUNT(o.order_id) as orders_count,
                    MAX(o.order_date) as last_order_date,
                    SUM(CASE WHEN o.payment_status = 'Paid' THEN o.total_amount ELSE 0 END) as total_paid
                FROM payment_methods pm
                LEFT JOIN orders o ON pm.id = o.payment_method_id
                JOIN users u ON pm.user_id = u.id
                WHERE pm.user_id = ? 
                    AND u.is_active = 1 
                    AND u.status = 'active'
                GROUP BY pm.id
                ORDER BY pm.is_default DESC, pm.created_at DESC");
            $stmt->execute([$userId]);
            $methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Add recent orders for each method
            foreach ($methods as &$method) {
                $stmt = $db->prepare("
                    SELECT order_id, order_date, total_amount, status 
                    FROM orders 
                    WHERE payment_method_id = ? 
                    ORDER BY order_date DESC LIMIT 3");
                $stmt->execute([$method['id']]);
                $method['recent_orders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            send_success("Payment methods retrieved", $methods);
            break;

        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);
            
            if (!isset($data['method_type'])) {
                send_error("Method type is required", 400);
            }

            // Validate method type
            $validMethods = ['Mpesa', 'Airtel money', 'Credit Card', 'Cash On Delivery'];
            if (!in_array($data['method_type'], $validMethods)) {
                send_error("Invalid payment method", 400);
            }

            // Insert new payment method
            $stmt = $db->prepare("
                INSERT INTO payment_methods 
                (user_id, method_type, phone_number, card_last_four, is_default) 
                VALUES (?, ?, ?, ?, ?)");

            $isDefault = isset($data['is_default']) && $data['is_default'];
            if ($isDefault) {
                // Reset other default methods
                $resetStmt = $db->prepare("
                    UPDATE payment_methods 
                    SET is_default = FALSE 
                    WHERE user_id = ?");
                $resetStmt->execute([$userId]);
            }

            $stmt->execute([
                $userId,
                $data['method_type'],
                $data['phone_number'] ?? null,
                $data['card_last_four'] ?? null,
                $isDefault
            ]);

            send_success("Payment method added", [
                'id' => $db->lastInsertId(),
                'method_type' => $data['method_type']
            ]);
            break;

        case 'PUT':
            if (!isset($_GET['id'])) {
                send_error("Payment method ID is required", 400);
            }

            $data = json_decode(file_get_contents("php://input"), true);

            // Validate method type
            $validMethods = ['Mpesa', 'Airtel money', 'Credit Card', 'Cash On Delivery'];
            if (!in_array($data['method_type'], $validMethods)) {
                send_error("Invalid payment method", 400);
            }

            // Update payment method
            if (isset($data['is_default']) && $data['is_default']) {
                $resetStmt = $db->prepare("
                    UPDATE payment_methods 
                    SET is_default = FALSE 
                    WHERE user_id = ?");
                $resetStmt->execute([$userId]);
            }

            $stmt = $db->prepare("
                UPDATE payment_methods 
                SET method_type = ?, phone_number = ?, card_last_four = ?, is_default = ? 
                WHERE id = ? AND user_id = ?");
            
            $stmt->execute([
                $data['method_type'],
                $data['phone_number'] ?? null,
                $data['card_last_four'] ?? null,
                $data['is_default'] ?? false,
                $_GET['id'],
                $userId
            ]);

            send_success("Payment method updated");
            break;

        case 'DELETE':
            if (!isset($_GET['id'])) {
                send_error("Payment method ID is required", 400);
            }

            $stmt = $db->prepare("
                DELETE FROM payment_methods 
                WHERE id = ? AND user_id = ?");
            
            $stmt->execute([$_GET['id'], $userId]);

            send_success("Payment method deleted");
            break;

        default:
            send_error("Method not allowed", 405);
    }

} catch (Exception $e) {
    error_log("Payment Methods API Error: " . $e->getMessage());
    send_error("Server error: " . $e->getMessage(), 500);
}
