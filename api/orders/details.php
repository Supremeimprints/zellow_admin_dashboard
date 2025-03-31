<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/api_response.php';

$database = new Database();
$db = $database->getConnection();

if (!isset($_GET['id'])) {
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
        AND (token_expiry IS NULL OR token_expiry > NOW())
    ");
    $userCheck->execute([$token]);
    $user = $userCheck->fetch(PDO::FETCH_OBJ);

    if (!$user) {
        send_error("Invalid or expired token", 401);
    }

    // For regular users, verify they own the order
    if ($user->role !== 'admin') {
        $orderCheck = $db->prepare("SELECT order_id FROM orders WHERE order_id = ? AND id = ?");
        $orderCheck->execute([$_GET['id'], $user->id]);
        if (!$orderCheck->fetch()) {
            send_error("Access denied", 403);
        }
    }

    // Get detailed order items with product and service details
    $query = "
        SELECT 
            oi.*,
            p.product_name,
            p.main_image,
            p.description as product_description,
            s.name as service_name,
            s.description as service_description,
            s.price as service_base_price,
            (oi.subtotal + COALESCE(oi.customization_cost, 0) + COALESCE(oi.service_cost, 0)) as total_cost
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.product_id
        LEFT JOIN services s ON oi.service_type = s.name
        WHERE oi.order_id = ?
        ORDER BY oi.created_at ASC";

    $stmt = $db->prepare($query);
    $stmt->execute([$_GET['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        send_error("No items found for this order", 404);
    }

    // Calculate totals
    $totals = [
        'items_count' => count($items),
        'total_quantity' => array_sum(array_column($items, 'quantity')),
        'subtotal' => array_sum(array_column($items, 'subtotal')),
        'customization_total' => array_sum(array_map(function($item) {
            return $item['customization_cost'] ?? 0;
        }, $items)),
        'service_total' => array_sum(array_map(function($item) {
            return $item['service_cost'] ?? 0;
        }, $items))
    ];
    $totals['grand_total'] = $totals['subtotal'] + $totals['customization_total'] + $totals['service_total'];

    // Format response
    $formattedItems = array_map(function($item) {
        return [
            'id' => $item['id'],
            'product' => [
                'id' => $item['product_id'],
                'name' => $item['product_name'],
                'description' => $item['product_description'],
                'image' => $item['main_image']
            ],
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'subtotal' => $item['subtotal'],
            'customization' => $item['customization_type'] ? [
                'type' => $item['customization_type'],
                'details' => $item['customization_details'],
                'cost' => $item['customization_cost']
            ] : null,
            'service' => $item['service_type'] ? [
                'type' => $item['service_type'],
                'name' => $item['service_name'],
                'description' => $item['service_description'],
                'details' => $item['service_details'],
                'cost' => $item['service_cost']
            ] : null,
            'total_cost' => $item['total_cost'],
            'status' => $item['status'],
            'created_at' => $item['created_at']
        ];
    }, $items);

    send_success("Order items retrieved successfully", [
        'items' => $formattedItems,
        'totals' => $totals
    ]);

} catch (Exception $e) {
    error_log("Order Details API Error: " . $e->getMessage());
    send_error("Server error: " . $e->getMessage(), 500);
}
