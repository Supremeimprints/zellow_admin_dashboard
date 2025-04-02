<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../controllers/OrderController.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../../includes/functions/shipping_functions.php';
require_once __DIR__ . '/../../includes/functions/auth_functions.php';
require_once __DIR__ . '/../../includes/functions/order_functions.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Get token from Authorization header
    $headers = getallheaders();
    $token = null;

    if (isset($headers['Authorization'])) {
        $token = str_replace('Bearer ', '', $headers['Authorization']);
    } elseif (isset($headers['authorization'])) {
        $token = str_replace('Bearer ', '', $headers['authorization']);
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
    }

    if (!$token) {
        send_error("No authorization token provided", 401);
    }

    // Verify token using the same method as list API
    if (!verify_token($token)) {
        send_error("Invalid or expired token", 401);
    }

    $database = new Database();
    $db = $database->getConnection();

    // Get user from token
    $stmt = $db->prepare("
        SELECT id, username, email, role 
        FROM users 
        WHERE api_token = ? 
        AND is_active = 1 
        AND status = 'active'
        AND token_expiry > NOW()
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        send_error('User not found or inactive', 401);
    }

    // Get posted data
    $data = json_decode(file_get_contents("php://input"), true);
    
    // Validate required fields
    if (!isset($data['items']) || empty($data['items'])) {
        send_error("Order must contain at least one item", 400);
    }

    // Calculate order totals
    $subtotal = 0;
    foreach ($data['items'] as $item) {
        if (!isset($item['product_id'], $item['quantity'])) {
            send_error("Each item must have product_id and quantity", 400);
        }
        // Get product price from database to prevent tampering
        $stmt = $db->prepare("SELECT price FROM products WHERE product_id = ?");
        $stmt->execute([$item['product_id']]);
        $price = $stmt->fetchColumn();
        if (!$price) {
            send_error("Invalid product ID: " . $item['product_id'], 400);
        }
        $subtotal += $price * $item['quantity'];
    }

    // Calculate shipping fee if shipping method and region provided
    $shipping_fee = 0.00; // Set default value
    $shipping_method_id = null;
    $shipping_region_id = null;

    if (isset($data['shipping_method_id'], $data['shipping_region_id'])) {
        // First validate that both shipping method and region exist and are active
        $validateMethodRegionStmt = $db->prepare("
            SELECT sm.id as method_id, sr.id as region_id
            FROM shipping_methods sm 
            CROSS JOIN shipping_regions sr
            WHERE sm.id = ? AND sr.id = ?
            AND sm.is_active = 1 AND sr.is_active = 1"
        );
        $validateMethodRegionStmt->execute([
            $data['shipping_method_id'],
            $data['shipping_region_id']
        ]);
        
        if ($validateMethodRegionStmt->fetch()) {
            // Now validate that there's an active rate for this combination
            $validateShippingStmt = $db->prepare("
                SELECT base_rate, per_item_fee
                FROM region_shipping_rates 
                WHERE shipping_method_id = ? 
                AND region_id = ? 
                AND is_active = 1"
            );
            $validateShippingStmt->execute([
                $data['shipping_method_id'],
                $data['shipping_region_id']
            ]);
            
            $shippingRate = $validateShippingStmt->fetch(PDO::FETCH_ASSOC);

            if ($shippingRate) {
                $shipping_method_id = $data['shipping_method_id'];
                $shipping_region_id = $data['shipping_region_id'];
                
                // Calculate shipping fee based on actual rates from database
                $itemCount = array_sum(array_column($data['items'], 'quantity'));
                $shipping_fee = $shippingRate['base_rate'] + ($shippingRate['per_item_fee'] * max(0, $itemCount - 1));
            } else {
                send_error("No active shipping rate found for this method and region combination", 400);
            }
        } else {
            send_error("Invalid or inactive shipping method or region", 400);
        }
    }

    $total_amount = $subtotal + $shipping_fee - ($data['discount_amount'] ?? 0);

    $db->beginTransaction();

    try {
        // Generate tracking number
        $tracking_number = generateTrackingNumber();

        // Insert main order with explicit default values and nullable shipping fields
        $stmt = $db->prepare("
            INSERT INTO orders (
                id, email, username, total_amount,
                status, shipping_address, payment_status,
                payment_method, shipping_fee,
                shipping_method_id, shipping_region_id,
                tracking_number
            ) VALUES (
                :id, :email, :username, :total_amount,
                'Pending', :shipping_address, 'Pending',
                :payment_method, :shipping_fee,
                NULLIF(:shipping_method_id, ''), NULLIF(:shipping_region_id, ''),
                :tracking_number
            )");
        
        $stmt->execute([
            ':id' => $user['id'],
            ':email' => $user['email'],
            ':username' => $user['username'],
            ':total_amount' => $total_amount,
            ':shipping_address' => $data['shipping_address'],
            ':payment_method' => $data['payment_method'] ?? 'Mpesa',
            ':shipping_fee' => $shipping_fee,
            ':shipping_method_id' => $shipping_method_id,
            ':shipping_region_id' => $shipping_region_id,
            ':tracking_number' => $tracking_number
        ]);
        
        $orderId = $db->lastInsertId();

        // Insert order items
        $itemStmt = $db->prepare("
            INSERT INTO order_items (
                order_id, product_id, quantity, 
                unit_price, subtotal, status
            ) VALUES (?, ?, ?, ?, ?, 'purchased')
        ");

        foreach ($data['items'] as $item) {
            $stmt = $db->prepare("SELECT price FROM products WHERE product_id = ?");
            $stmt->execute([$item['product_id']]);
            $price = $stmt->fetchColumn();
            
            $itemSubtotal = $price * $item['quantity'];
            $itemStmt->execute([
                $orderId,
                $item['product_id'],
                $item['quantity'],
                $price,
                $itemSubtotal
            ]);
        }

        // Update payment method usage if provided
        if (isset($data['payment_method_id'])) {
            $stmt = $db->prepare("
                UPDATE payment_methods 
                SET usage_count = usage_count + 1,
                    last_used = NOW()
                WHERE id = ?");
            $stmt->execute([$data['payment_method_id']]);
        }

        $db->commit();
        send_success("Order created successfully", [
            'order_id' => $orderId,
            'tracking_number' => $tracking_number,
            'total_amount' => $total_amount,
            'subtotal' => $subtotal,
            'shipping_fee' => $shipping_fee
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("Order Creation Error: " . $e->getMessage());
    send_error("Server error: " . $e->getMessage(), 500);
}
