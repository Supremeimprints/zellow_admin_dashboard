<?php
header('Content-Type: application/json');
require_once 'config/database.php';

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

// Helper functions for data retrieval
function getRevenueData($pdo) {
    $query = "SELECT 
        DATE_FORMAT(o.order_date, '%Y-%m') as month,
        SUM(o.total_amount) as revenue,
        SUM(t.expenses) as expenses,
        COUNT(DISTINCT o.order_id) as total_orders
    FROM orders o
    LEFT JOIN transactions t ON o.order_id = t.order_id
    GROUP BY DATE_FORMAT(o.order_date, '%Y-%m')
    ORDER BY month DESC LIMIT 6";
    
    return $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
}

function getTotalOrders($pdo) {
    $query = "SELECT 
        COUNT(*) as total_orders,
        COUNT(CASE WHEN order_date >= DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH) THEN 1 END) as current_month_orders,
        COUNT(CASE WHEN order_date >= DATE_SUB(CURRENT_DATE, INTERVAL 2 MONTH) 
                  AND order_date < DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH) THEN 1 END) as previous_month_orders
    FROM orders";
    
    $result = $pdo->query($query)->fetch();
    return [
        'total' => $result['total_orders'],
        'current' => $result['current_month_orders'],
        'previous' => $result['previous_month_orders']
    ];
}

function getNetProfit($pdo) {
    $query = "SELECT 
        SUM(o.total_amount) as total_revenue,
        SUM(t.expenses) as total_expenses,
        SUM(CASE WHEN o.order_date >= DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH) 
            THEN o.total_amount ELSE 0 END) as current_month_revenue,
        SUM(CASE WHEN o.order_date >= DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH) 
            THEN COALESCE(t.expenses, 0) ELSE 0 END) as current_month_expenses,
        SUM(CASE WHEN o.order_date >= DATE_SUB(CURRENT_DATE, INTERVAL 2 MONTH) 
                 AND o.order_date < DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH)
            THEN o.total_amount ELSE 0 END) as previous_month_revenue,
        SUM(CASE WHEN o.order_date >= DATE_SUB(CURRENT_DATE, INTERVAL 2 MONTH) 
                 AND o.order_date < DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH)
            THEN COALESCE(t.expenses, 0) ELSE 0 END) as previous_month_expenses
    FROM orders o
    LEFT JOIN transactions t ON o.order_id = t.order_id";
    
    $result = $pdo->query($query)->fetch();
    $currentProfit = $result['current_month_revenue'] - $result['current_month_expenses'];
    $previousProfit = $result['previous_month_revenue'] - $result['previous_month_expenses'];
    
    return [
        'current' => $currentProfit,
        'previous' => $previousProfit,
        'total' => $result['total_revenue'] - $result['total_expenses']
    ];
}

function getActiveCustomers($pdo) {
    $query = "SELECT 
        COUNT(DISTINCT email) as total_customers,
        COUNT(DISTINCT CASE WHEN order_date >= DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH) 
            THEN email END) as current_month_customers,
        COUNT(DISTINCT CASE WHEN order_date >= DATE_SUB(CURRENT_DATE, INTERVAL 2 MONTH) 
                           AND order_date < DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH)
            THEN email END) as previous_month_customers
    FROM orders";
    
    $result = $pdo->query($query)->fetch();
    return [
        'total' => $result['total_customers'],
        'current' => $result['current_month_customers'],
        'previous' => $result['previous_month_customers']
    ];
}

// Gather data
$revenueData = getRevenueData($db);
$orderStats = getTotalOrders($db);
$profitStats = getNetProfit($db);
$customerStats = getActiveCustomers($db);

// Calculate growth percentages
$orderGrowth = $orderStats['previous'] != 0 ? 
    (($orderStats['current'] - $orderStats['previous']) / $orderStats['previous'] * 100) : 0;

$profitGrowth = $profitStats['previous'] != 0 ? 
    (($profitStats['current'] - $profitStats['previous']) / $profitStats['previous'] * 100) : 0;

$customerGrowth = $customerStats['previous'] != 0 ? 
    (($customerStats['current'] - $customerStats['previous']) / $customerStats['previous'] * 100) : 0;

// Return JSON data
echo json_encode([
    'revenue' => [
        'current' => $revenueData[0]['revenue'] ?? 0,
        'growth' => $revenueGrowth
    ],
    'orders' => [
        'current' => $orderStats['current'],
        'growth' => $orderGrowth
    ],
    'profit' => [
        'current' => $profitStats['current'],
        'growth' => $profitGrowth
    ],
    'customers' => [
        'current' => $customerStats['current'],
        'growth' => $customerGrowth
    ]
]);
?>