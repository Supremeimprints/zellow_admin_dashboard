<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/api_response.php';
require_once __DIR__ . '/../../includes/functions/auth_functions.php'; // Updated path

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Verify admin token
    $headers = getallheaders();
    if (!isset($headers['Authorization'])) {
        send_error("No authorization token provided", 401);
    }
    
    $token = $headers['Authorization'];
    if (!verify_admin_token($token)) {
        send_error("Invalid or expired token", 401);
    }

    $database = new Database();
    $db = $database->getConnection();

    // Fetch all dashboard data
    $dashboardData = [
        'orderStats' => [],
        'customerStats' => [],
        'inventoryStats' => [],
        'recentOrders' => [],
        'notifications' => [],
        'lowStockItems' => [],
        'revenueStats' => []
    ];

    // Order statistics
    $orderQuery = "SELECT status, COUNT(id) as count FROM orders GROUP BY status";
    $stmt = $db->query($orderQuery);
    $dashboardData['orderStats'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Customer statistics
    $customerQuery = "SELECT 
        COUNT(id) as total_customers,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_customers,
        SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as active_customers
    FROM users 
    WHERE role = 'customer'";
    $stmt = $db->query($customerQuery);
    $dashboardData['customerStats'] = $stmt->fetch(PDO::FETCH_ASSOC);

    // Inventory statistics
    $inventoryQuery = "SELECT 
        COUNT(id) as total_items,
        SUM(stock_quantity) as total_stock,
        COUNT(CASE WHEN stock_quantity <= min_stock_level THEN 1 END) as low_stock_count
    FROM inventory";
    $stmt = $db->query($inventoryQuery);
    $dashboardData['inventoryStats'] = $stmt->fetch(PDO::FETCH_ASSOC);

    // Recent orders (last 5)
    $recentOrdersQuery = "SELECT 
        o.order_id,
        o.status,
        o.order_date,
        o.total_price,
        u.username as customer_name
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    ORDER BY o.order_date DESC 
    LIMIT 5";
    $stmt = $db->query($recentOrdersQuery);
    $dashboardData['recentOrders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent notifications
    $notificationsQuery = "SELECT 
        id,
        type,
        message,
        created_at,
        is_read
    FROM notifications
    WHERE recipient_role = 'admin'
    ORDER BY created_at DESC
    LIMIT 5";
    $stmt = $db->query($notificationsQuery);
    $dashboardData['notifications'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Low stock items
    $lowStockQuery = "SELECT 
        i.id,
        p.product_name,
        i.stock_quantity,
        i.min_stock_level
    FROM inventory i
    JOIN products p ON i.product_id = p.id
    WHERE i.stock_quantity <= i.min_stock_level
    LIMIT 10";
    $stmt = $db->query($lowStockQuery);
    $dashboardData['lowStockItems'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Revenue statistics
    $revenueQuery = "SELECT 
        SUM(total_price) as total_revenue,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
        AVG(total_price) as average_order_value
    FROM orders
    WHERE order_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $stmt = $db->query($revenueQuery);
    $dashboardData['revenueStats'] = $stmt->fetch(PDO::FETCH_ASSOC);

    send_success("Dashboard data retrieved successfully", $dashboardData);

} catch (Exception $e) {
    error_log("Dashboard API Error: " . $e->getMessage());
    send_error("Server error occurred", 500);
}
