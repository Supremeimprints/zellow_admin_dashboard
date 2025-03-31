<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/api_response.php';

$database = new Database();
$db = $database->getConnection();

if (!isset($_GET['order_id'])) {
    send_error("Order ID is required", 400);
}

try {
    // Get token from headers
    $headers = getallheaders();
    $token = str_replace('Bearer ', '', $headers['Authorization'] ?? $headers['authorization'] ?? '');
    
    // Check user token
    $userCheck = $db->prepare("
        SELECT id, role 
        FROM users
        WHERE api_token = ? 
        AND is_active = 1 
        AND status = 'active'
    ");
    $userCheck->execute([$token]);
    $user = $userCheck->fetch(PDO::FETCH_OBJ);

    if (!$user) {
        send_error("Invalid or expired token", 401);
    }

    // For regular users, verify they own the order
    if ($user->role !== 'admin') {
        $orderCheck = $db->prepare("SELECT order_id FROM orders WHERE order_id = ? AND id = ?");
        $orderCheck->execute([$_GET['order_id'], $user->id]);
        if (!$orderCheck->fetch()) {
            send_error("Access denied", 403);
        }
    }

    // First check if order exists
    $orderCheck = $db->prepare("SELECT order_id, status FROM orders WHERE order_id = ?");
    $orderCheck->execute([$_GET['order_id']]);
    $order = $orderCheck->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        send_error("Order not found", 404);
    }

    // Get order tracking information
    $query = "
        SELECT 
            ot.*,
            u.username as updated_by_name,
            o.status as order_status,
            o.payment_status,
            o.order_date
        FROM orders o
        LEFT JOIN order_tracking ot ON o.order_id = ot.order_id
        LEFT JOIN users u ON ot.updated_by = u.id
        WHERE o.order_id = ?
        ORDER BY ot.created_at ASC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([$_GET['order_id']]);
    $tracking = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Even if no tracking entries exist, return order status
    $response = [
        'order_id' => $order['order_id'],
        'current_status' => $order['status'],
        'tracking_history' => $tracking ?: [],
        'last_updated' => $tracking ? end($tracking)['created_at'] : null
    ];

    send_success("Order tracking retrieved successfully", $response);

} catch (Exception $e) {
    error_log("Order Tracking Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    send_error("Server error: " . $e->getMessage(), 500);
}
