<?php
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = $_REQUEST['user']->id;

        if ($_SERVER['REQUEST_URI'] === '/api/cart/add') {
            // Check stock
            $stmt = $db->prepare("SELECT stock_quantity FROM inventory WHERE product_id = ?");
            $stmt->execute([$data['product_id']]);
            $stock = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($stock['stock_quantity'] < $data['quantity']) {
                ApiResponse::error('Insufficient stock', 400);
            }

            // Add/Update cart item
            $stmt = $db->prepare("
                INSERT INTO cart (user_id, product_id, quantity) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
            ");
            $stmt->execute([$userId, $data['product_id'], $data['quantity']]);
            
            ApiResponse::success(null, 'Added to cart');
        }
        
        if ($_SERVER['REQUEST_URI'] === '/api/cart/checkout') {
            try {
                $db->beginTransaction();
                
                // Create order
                $stmt = $db->prepare("
                    INSERT INTO orders (id, username, status, payment_status, 
                    payment_method, shipping_address, shipping_method)
                    VALUES (?, ?, 'Pending', ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $userId,
                    $_REQUEST['user']->email,
                    $data['payment_status'],
                    $data['payment_method'],
                    $data['shipping_address'],
                    $data['shipping_method']
                ]);
                $orderId = $db->lastInsertId();

                // Get cart items
                $stmt = $db->prepare("
                    SELECT c.*, p.price 
                    FROM cart c 
                    JOIN products p ON c.product_id = p.product_id 
                    WHERE c.user_id = ?
                ");
                $stmt->execute([$userId]);
                $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Create order items and update inventory
                foreach ($cartItems as $item) {
                    // Insert order item
                    $stmt = $db->prepare("
                        INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $subtotal = $item['quantity'] * $item['price'];
                    $stmt->execute([
                        $orderId,
                        $item['product_id'],
                        $item['quantity'],
                        $item['price'],
                        $subtotal
                    ]);

                    // Update inventory
                    $stmt = $db->prepare("
                        UPDATE inventory 
                        SET stock_quantity = stock_quantity - ? 
                        WHERE product_id = ?
                    ");
                    $stmt->execute([$item['quantity'], $item['product_id']]);
                }

                // Clear cart
                $stmt = $db->prepare("DELETE FROM cart WHERE user_id = ?");
                $stmt->execute([$userId]);

                $db->commit();
                ApiResponse::success(['order_id' => $orderId], 'Order placed successfully');
            } catch (Exception $e) {
                $db->rollBack();
                ApiResponse::error('Error processing order: ' . $e->getMessage(), 500);
            }
        }
        break;

    case 'GET':
        // Get cart items
        $userId = $_REQUEST['user']->id;
        $stmt = $db->prepare("
            SELECT c.*, p.product_name, p.price, p.main_image 
            FROM cart c
            JOIN products p ON c.product_id = p.product_id
            WHERE c.user_id = ?
        ");
        $stmt->execute([$userId]);
        $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        ApiResponse::success($cartItems);
        break;
}
