<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/api_response.php';

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Verify token and get user
$headers = getallheaders();
$token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');

if (!$token) {
    send_error("No authorization token provided", 401);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get user from token
    $stmt = $db->prepare("SELECT id, email, username FROM users WHERE api_token = ? AND is_active = 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        send_error("Invalid token", 401);
    }

    $data = json_decode(file_get_contents("php://input"), true);

    // Validate required fields
    if (empty($data['items']) || empty($data['shipping_address'])) {
        send_error("Missing required fields", 400);
    }

    $db->beginTransaction();

    try {
        // Validate all products first
        foreach ($data['items'] as $item) {
            if (!isset($item['product_id']) || !isset($item['quantity'])) {
                throw new Exception("Invalid item format - missing product_id or quantity");
            }
            
            $stmt = $db->prepare("
                SELECT product_id, price, stock_quantity 
                FROM products 
                WHERE product_id = ? AND active = 1
            ");
            $stmt->execute([$item['product_id']]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$product) {
                throw new Exception("Product not found: " . $item['product_id']);
            }
            
            // Store validated product data for later use
            $item['validated_price'] = $product['price'];
            $validatedProducts[$item['product_id']] = $product;
        }

        // Calculate totals using validated products
        $subtotal = 0;
        foreach ($data['items'] as $item) {
            $subtotal += $validatedProducts[$item['product_id']]['price'] * $item['quantity'];
        }

        // Validate shipping method and region first
        if (!validateShippingDetails($db, $data['shipping_method'], $data['shipping_region_id'])) {
            throw new Exception("Invalid shipping method or region");
        }

        // Validate occasion if gift order
        if (isset($data['gift_details']) && isset($data['gift_details']['occasion_id'])) {
            $stmt = $db->prepare("SELECT id, name FROM gift_occasions WHERE id = ?");
            $stmt->execute([$data['gift_details']['occasion_id']]);
            $occasion = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$occasion) {
                throw new Exception("Invalid occasion selected");
            }
        }

        // Calculate additional costs
        $giftWrapCost = isset($data['gift_wrap_style']) ? calculateGiftWrapCost($data['gift_wrap_style']) : 0;
        $customizationCost = isset($data['customization_type']) ? calculateCustomizationCost($data['customization_type']) : 0;
        $shippingFee = calculateShippingFee($data['shipping_method'], $data['shipping_region_id']);
        $discountAmount = isset($data['coupon_id']) ? calculateDiscount($data['coupon_id'], $subtotal) : 0;

        $totalAmount = $subtotal + $giftWrapCost + $customizationCost + $shippingFee - $discountAmount;

        // Generate unique tracking number
        $tracking_number = generateTrackingNumber($db);

        // Insert main order
        $stmt = $db->prepare("
            INSERT INTO orders (
                id, email, username, total_amount, shipping_address,
                shipping_method, shipping_region_id, shipping_fee,
                payment_method, payment_method_id, coupon_id, discount_amount,
                is_gift, customization_type, customization_details,
                customization_cost, status, payment_status, tracking_number
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', 'Pending', ?
            )
        ");

        $params = [
            $user['id'],
            $user['email'],
            $user['username'],
            $totalAmount,
            $data['shipping_address'],
            $data['shipping_method'],
            $data['shipping_region_id'],
            $shippingFee,
            $data['payment_method'],
            $data['payment_method_id'] ?? null,
            $data['coupon_id'] ?? null,
            $discountAmount,
            isset($data['gift_details']) ? 1 : 0,
            $data['customization_type'] ?? null,
            $data['customization_details'] ?? null,
            $customizationCost,
            $tracking_number
        ];

        $stmt->execute($params);

        $orderId = $db->lastInsertId();

        // Insert order items using validated products
        $itemStmt = $db->prepare("
            INSERT INTO order_items (
                order_id, product_id, quantity, unit_price,
                subtotal, status
            ) VALUES (?, ?, ?, ?, ?, 'purchased')
        ");

        foreach ($data['items'] as $item) {
            $product = $validatedProducts[$item['product_id']];
            $itemSubtotal = $product['price'] * $item['quantity'];
            
            $itemStmt->execute([
                $orderId,
                $item['product_id'],
                $item['quantity'],
                $product['price'],
                $itemSubtotal
            ]);
        }

        // Insert gift details if applicable
        if (isset($data['gift_details'])) {
            // Validate required gift fields
            $requiredGiftFields = ['recipient_name', 'recipient_email', 'gift_message'];
            foreach ($requiredGiftFields as $field) {
                if (empty($data['gift_details'][$field])) {
                    throw new Exception("Missing required gift field: $field");
                }
            }

            $giftStmt = $db->prepare("
                INSERT INTO order_gifts (
                    order_id, occasion_id, gift_wrap_style_id,
                    gift_message, recipient_name, recipient_email,
                    notify_recipient, gift_status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
            ");

            $giftStmt->execute([
                $orderId,
                $data['gift_details']['occasion_id'] ?? null,
                $data['gift_details']['gift_wrap_style_id'] ?? null,
                $data['gift_details']['gift_message'],
                $data['gift_details']['recipient_name'],
                $data['gift_details']['recipient_email'],
                $data['gift_details']['notify_recipient'] ?? 0
            ]);

            // Update main order with gift details (removed occasion_id)
            $updateOrderStmt = $db->prepare("
                UPDATE orders SET 
                    is_gift = 1,
                    recipient_name = ?,
                    recipient_email = ?,
                    gift_message = ?,
                    notify_recipient = ?
                WHERE order_id = ?
            ");

            $updateOrderStmt->execute([
                $data['gift_details']['recipient_name'],
                $data['gift_details']['recipient_email'],
                $data['gift_details']['gift_message'],
                $data['gift_details']['notify_recipient'] ?? 0,
                $orderId
            ]);
        }

        $db->commit();

        // Return success response with enhanced gift details
        send_success("Order created successfully", [
            'order_id' => $orderId,
            'tracking_number' => $tracking_number,
            'total_amount' => $totalAmount,
            'breakdown' => [
                'subtotal' => $subtotal,
                'gift_wrap_cost' => $giftWrapCost,
                'customization_cost' => $customizationCost,
                'shipping_fee' => $shippingFee,
                'discount_amount' => $discountAmount
            ],
            'gift_details' => isset($data['gift_details']) ? [
                'occasion' => $occasion['name'] ?? null,
                'recipient_name' => $data['gift_details']['recipient_name'],
                'recipient_email' => $data['gift_details']['recipient_email'],
                'notify_recipient' => (bool)$data['gift_details']['notify_recipient']
            ] : null
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Order Creation Error: " . $e->getMessage());
    send_error("Error creating order: " . $e->getMessage(), 500);
}

function validateShippingDetails($db, $shippingMethod, $regionId) {
    $stmt = $db->prepare("
        SELECT sr.base_rate 
        FROM shipping_methods sm
        JOIN region_shipping_rates sr ON sm.id = sr.shipping_method_id
        WHERE sm.name = ? AND sr.region_id = ? AND sm.is_active = 1
    ");
    $stmt->execute([$shippingMethod, $regionId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
}

function calculateGiftWrapCost($style) {
    // Implement gift wrap cost calculation based on style
    $costs = [
        'basic' => 5.00,
        'premium' => 10.00,
        'luxury' => 15.00
    ];
    return $costs[$style] ?? 5.00;
}

function calculateCustomizationCost($type) {
    // Implement customization cost calculation
    $costs = [
        'engraving' => 15.00,
        'printing' => 10.00
    ];
    return $costs[$type] ?? 0;
}

function calculateShippingFee($shippingMethod, $regionId) {
    global $db;
    // Get shipping rate from database using method name instead of ID
    $stmt = $db->prepare("
        SELECT sr.base_rate 
        FROM shipping_methods sm
        JOIN region_shipping_rates sr ON sm.id = sr.shipping_method_id
        WHERE sm.name = ? AND sr.region_id = ? AND sm.is_active = 1
    ");
    $stmt->execute([$shippingMethod, $regionId]);
    return $stmt->fetchColumn() ?: 0;
}

function calculateDiscount($couponId, $subtotal) {
    global $db;
    // Get coupon details and calculate discount
    $stmt = $db->prepare("
        SELECT discount_type, discount_percentage, discount_value 
        FROM coupons 
        WHERE coupon_id = ? 
        AND status = 'active'
        AND expiration_date > NOW()
    ");
    $stmt->execute([$couponId]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$coupon) return 0;

    return $coupon['discount_type'] === 'percentage' 
        ? ($subtotal * $coupon['discount_percentage'] / 100)
        : $coupon['discount_value'];
}

function generateTrackingNumber($db) {
    $prefix = 'TRK-' . date('Ymd');
    $uniqueId = '';
    $isUnique = false;

    while (!$isUnique) {
        // Generate 4 random alphanumeric characters
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $random = '';
        for ($i = 0; $i < 4; $i++) {
            $random .= $chars[rand(0, strlen($chars) - 1)];
        }
        $uniqueId = $prefix . '-' . $random;

        // Check if this tracking number already exists
        $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE tracking_number = ?");
        $stmt->execute([$uniqueId]);
        if ($stmt->fetchColumn() == 0) {
            $isUnique = true;
        }
    }

    return $uniqueId;
}
