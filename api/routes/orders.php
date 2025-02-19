<?php
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions/order_functions.php';

$database = new Database();
$db = $database->getConnection();

// Extract order ID from URL
$urlParts = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
$orderId = null;
if (count($urlParts) > 3 && is_numeric($urlParts[3])) {
    $orderId = (int)$urlParts[3];
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if ($orderId) {
            // Get single order with items
            $stmt = $db->prepare("
                SELECT o.*, 
                       GROUP_CONCAT(
                           DISTINCT CONCAT(
                               oi.product_name, 
                               ' (', oi.quantity, ' x ', oi.unit_price, ')'
                           ) SEPARATOR ', '
                       ) as products,
                       GROUP_CONCAT(DISTINCT c.name) as categories
                FROM orders o
                LEFT JOIN order_items_with_category oi ON o.order_id = oi.order_id
                LEFT JOIN categories c ON oi.category_id = c.id
                WHERE o.order_id = ?
                GROUP BY o.order_id
            ");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($order) {
                ApiResponse::success($order);
            } else {
                ApiResponse::error("Order not found", 404);
            }
        } else {
            // List orders with pagination and filters
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
            $offset = ($page - 1) * $limit;
            
            $where = [];
            $params = [];
            
            if (!empty($_GET['status'])) {
                $where[] = "o.status = ?";
                $params[] = $_GET['status'];
            }
            
            if (!empty($_GET['payment_status'])) {
                $where[] = "o.payment_status = ?";
                $params[] = $_GET['payment_status'];
            }
            
            $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
            
            // Fix the LIMIT and OFFSET syntax
            $stmt = $db->prepare("
                SELECT o.*,
                       GROUP_CONCAT(
                           DISTINCT CONCAT(
                               oi.product_name, 
                               ' (', oi.quantity, ' x ', oi.unit_price, ')'
                           ) SEPARATOR ', '
                       ) as products
                FROM orders o
                LEFT JOIN order_items_with_category oi ON o.order_id = oi.order_id
                $whereClause
                GROUP BY o.order_id
                ORDER BY o.order_date DESC
                LIMIT ?, ?
            ");
            
            // Bind limit parameters as integers
            $params[] = (int)$offset;
            $params[] = (int)$limit;
            
            $stmt->execute($params);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ApiResponse::success([
                'page' => $page,
                'limit' => $limit,
                'orders' => $orders
            ]);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            $db->beginTransaction();
            
            // Required fields validation with detailed error messages
            $requiredFields = [
                'id' => 'Customer ID',
                'email' => 'Email Address',
                'shipping_address' => 'Shipping Address',
                'items' => 'Order Items',
                'payment_method' => 'Payment Method'
            ];
            
            $missingFields = [];
            foreach ($requiredFields as $field => $label) {
                if (empty($data[$field])) {
                    $missingFields[] = $label;
                }
            }
            
            if (!empty($missingFields)) {
                throw new Exception("Missing required fields: " . implode(", ", $missingFields));
            }

            // Validate items array
            if (!is_array($data['items']) || empty($data['items'])) {
                throw new Exception("Order must contain at least one item");
            }

            foreach ($data['items'] as $index => $item) {
                if (!isset($item['product_id'], $item['quantity'], $item['unit_price'])) {
                    throw new Exception("Item #" . ($index + 1) . " is missing required fields (product_id, quantity, unit_price)");
                }
            }

            // Verify user exists
            $userCheck = $db->prepare("SELECT id FROM users WHERE id = ?");
            $userCheck->execute([$data['id']]);
            if (!$userCheck->fetch()) {
                throw new Exception("Invalid customer ID");
            }

            // Create order
            $stmt = $db->prepare("
                INSERT INTO orders (
                    id,
                    email,
                    shipping_address,
                    shipping_method,
                    payment_method,
                    status,
                    payment_status,
                    total_amount,
                    shipping_fee,
                    discount_amount,
                    order_date
                ) VALUES (
                    :id,
                    :email,
                    :shipping_address,
                    :shipping_method,
                    :payment_method,
                    'Pending',
                    'Pending',
                    :total_amount,
                    :shipping_fee,
                    :discount_amount,
                    NOW()
                )
            ");

            // Calculate totals
            $subtotal = array_sum(array_map(function($item) {
                return $item['quantity'] * $item['unit_price'];
            }, $data['items']));

            $shippingFee = $data['shipping_fee'] ?? 0.00;
            $discountAmount = $data['discount_amount'] ?? 0.00;
            $totalAmount = $subtotal + $shippingFee - $discountAmount;

            $orderData = [
                ':id' => $data['id'],
                ':email' => $data['email'],
                ':shipping_address' => $data['shipping_address'],
                ':shipping_method' => $data['shipping_method'] ?? 'Standard',
                ':payment_method' => $data['payment_method'],
                ':total_amount' => $totalAmount,
                ':shipping_fee' => $shippingFee,
                ':discount_amount' => $discountAmount
            ];

            $stmt->execute($orderData);
            $orderId = $db->lastInsertId();

            // Insert order items
            $itemStmt = $db->prepare("
                INSERT INTO order_items (
                    order_id,
                    product_id,
                    quantity,
                    unit_price,
                    subtotal,
                    status
                ) VALUES (
                    :order_id,
                    :product_id,
                    :quantity,
                    :unit_price,
                    :subtotal,
                    'purchased'
                )
            ");

            foreach ($data['items'] as $item) {
                $subtotal = $item['quantity'] * $item['unit_price'];
                $itemStmt->execute([
                    ':order_id' => $orderId,
                    ':product_id' => $item['product_id'],
                    ':quantity' => $item['quantity'],
                    ':unit_price' => $item['unit_price'],
                    ':subtotal' => $subtotal
                ]);
            }

            $db->commit();
            ApiResponse::success([
                'order_id' => $orderId,
                'message' => 'Order created successfully'
            ]);

        } catch (Exception $e) {
            $db->rollBack();
            ApiResponse::error('Error creating order: ' . $e->getMessage());
        }
        break;

    case 'PUT':
        if (!$orderId) {
            ApiResponse::error("Order ID required", 400);
            break;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $updates = [];
            $params = [];

            // Allow updating specific fields
            $allowedFields = ['status', 'payment_status', 'tracking_number', 'driver_id', 'delivery_date'];
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (empty($updates)) {
                throw new Exception("No valid fields to update");
            }

            $params[] = $orderId;
            $query = "UPDATE orders SET " . implode(", ", $updates) . " WHERE order_id = ?";
            
            $stmt = $db->prepare($query);
            if ($stmt->execute($params)) {
                ApiResponse::success(['message' => 'Order updated successfully']);
            } else {
                throw new Exception("Failed to update order");
            }
        } catch (Exception $e) {
            ApiResponse::error('Error updating order: ' . $e->getMessage());
        }
        break;

    case 'DELETE':
        if (!isset($_GET['id'])) {
            ApiResponse::error("Order ID required", 400);
            break;
        }

        try {
            $stmt = $db->prepare("DELETE FROM orders WHERE order_id = ?");
            if ($stmt->execute([$_GET['id']])) {
                ApiResponse::success(['message' => 'Order deleted successfully']);
            } else {
                throw new Exception("Failed to delete order");
            }
        } catch (Exception $e) {
            ApiResponse::error('Error deleting order: ' . $e->getMessage());
        }
        break;
}
