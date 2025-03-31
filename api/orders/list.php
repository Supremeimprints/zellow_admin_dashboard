<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/api_response.php';
require_once __DIR__ . '/../../includes/functions/auth_functions.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Token verification
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

try {
    if (!verify_token($token)) {
        send_error("Invalid or expired token", 403);
    }

    $database = new Database();
    $db = $database->getConnection();

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $offset = ($page - 1) * $limit;

    if (verify_customer_token($token)) {
        // For customers, only show their own orders
        $query = "SELECT o.*, 
                    GROUP_CONCAT(oi.quantity) as item_quantities,
                    GROUP_CONCAT(oi.unit_price) as item_prices,
                    GROUP_CONCAT(oi.product_id) as product_ids
                 FROM orders o 
                 LEFT JOIN order_items oi ON o.order_id = oi.order_id
                 JOIN users u ON o.email = u.email 
                 WHERE u.api_token = ? 
                    AND u.is_active = 1 
                    AND u.status = 'active'
                 GROUP BY o.order_id
                 ORDER BY o.order_date DESC LIMIT ? OFFSET ?";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $token);
        $stmt->bindParam(2, $limit, PDO::PARAM_INT);
        $stmt->bindParam(3, $offset, PDO::PARAM_INT);
    } else {
        // For admins, show all orders
        $query = "SELECT o.*, 
                    GROUP_CONCAT(oi.quantity) as item_quantities,
                    GROUP_CONCAT(oi.unit_price) as item_prices,
                    GROUP_CONCAT(oi.product_id) as product_ids,
                    COUNT(DISTINCT oi.id) as total_items
                 FROM orders o
                 LEFT JOIN order_items oi ON o.order_id = oi.order_id
                 GROUP BY o.order_id
                 ORDER BY o.order_date DESC LIMIT ? OFFSET ?";
        $stmt = $db->prepare($query);
        $stmt->bindParam(1, $limit, PDO::PARAM_INT);
        $stmt->bindParam(2, $offset, PDO::PARAM_INT);
    }

    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process each order to include additional details
    foreach ($orders as &$order) {
        // Convert status timestamp
        $order['order_date'] = date('Y-m-d H:i:s', strtotime($order['order_date']));
        $order['delivery_date'] = $order['delivery_date'] ? 
            date('Y-m-d H:i:s', strtotime($order['delivery_date'])) : null;
            
        // Add status history
        $historyQuery = "SELECT * FROM order_status_history 
                        WHERE order_id = ? 
                        ORDER BY created_at DESC LIMIT 1";
        $historyStmt = $db->prepare($historyQuery);
        $historyStmt->execute([$order['order_id']]);
        $order['latest_status'] = $historyStmt->fetch(PDO::FETCH_ASSOC);

        // Calculate order totals
        $order['total_with_tax'] = $order['total_amount'] + $order['shipping_fee'] 
            + ($order['gift_wrap_cost'] ?? 0) + ($order['customization_cost'] ?? 0);
        $order['final_total'] = $order['total_with_tax'] - ($order['discount_amount'] ?? 0);
    }

    // Get total count
    if (verify_customer_token($token)) {
        $countQuery = "SELECT COUNT(DISTINCT o.order_id) 
                      FROM orders o 
                      JOIN users u ON o.email = u.email 
                      WHERE u.api_token = ? 
                        AND u.is_active = 1 
                        AND u.status = 'active'";
        $countStmt = $db->prepare($countQuery);
        $countStmt->execute([$token]);
    } else {
        $countStmt = $db->query("SELECT COUNT(*) FROM orders");
    }
    $totalOrders = $countStmt->fetchColumn();

    send_success("Orders retrieved successfully", [
        'orders' => $orders,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => ceil($totalOrders / $limit),
            'total_records' => $totalOrders,
            'records_per_page' => $limit
        ]
    ]);

} catch (Exception $e) {
    error_log("Orders API Error: " . $e->getMessage());
    send_error("Server error: " . $e->getMessage(), 500);
}
