<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/api_response.php';
require_once __DIR__ . '/../../includes/middleware/auth_middleware.php';

// Use standardized auth
$user = authorize('admin');

if (!isset($_GET['id'])) {
    send_error("Order ID is required", 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get order details with items
    $query = "SELECT o.*, 
                u.username as customer_name,
                COUNT(oi.id) as total_items,
                SUM(oi.quantity) as total_quantity
             FROM orders o
             LEFT JOIN users u ON o.user_id = u.id
             LEFT JOIN order_items oi ON o.order_id = oi.order_id
             WHERE o.order_id = ?
             GROUP BY o.order_id";
             
    $stmt = $db->prepare($query);
    $stmt->execute([$_GET['id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        send_error("Order not found", 404);
    }

    // Get order items
    $itemsQuery = "SELECT oi.*,
                    p.product_name,
                    p.main_image
                  FROM order_items oi
                  LEFT JOIN products p ON oi.product_id = p.product_id
                  WHERE oi.order_id = ?";
    $itemsStmt = $db->prepare($itemsQuery);
    $itemsStmt->execute([$_GET['id']]);
    $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get order status history
    $historyQuery = "SELECT h.*, u.username as updated_by_user 
                    FROM order_status_history h
                    LEFT JOIN users u ON h.updated_by = u.id
                    WHERE h.order_id = ?
                    ORDER BY h.created_at DESC";
    $historyStmt = $db->prepare($historyQuery);
    $historyStmt->execute([$_GET['id']]);
    $order['status_history'] = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

    send_success("Order retrieved successfully", $order);

} catch (Exception $e) {
    error_log("Order API Error: " . $e->getMessage());
    send_error("Server error: " . $e->getMessage(), 500);
}
