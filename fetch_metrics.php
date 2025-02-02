<?php
session_start();
require_once 'config/database.php';

// Authentication check
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Fetch updated metrics
$metrics = [];

// Total Customers
$query = "SELECT COUNT(*) AS total_customers FROM users WHERE role = 'customer'";
$stmt = $db->query($query);
$metrics['total_customers'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_customers'];

// Monthly Sales
$query = "SELECT SUM(total_amount) AS monthly_sales FROM orders WHERE MONTH(order_date) = MONTH(NOW()) AND payment_status = 'Paid'";
$stmt = $db->query($query);
$metrics['monthly_sales'] = $stmt->fetch(PDO::FETCH_ASSOC)['monthly_sales'];

// Low Stock Items
$query = "SELECT COUNT(*) AS low_stock_items FROM inventory WHERE stock_quantity < min_stock_level";
$stmt = $db->query($query);
$metrics['low_stock_items'] = $stmt->fetch(PDO::FETCH_ASSOC)['low_stock_items'];

// Avg Orders per Customer
$query = "SELECT AVG(order_count) AS avg_orders_customer FROM (SELECT COUNT(*) AS order_count FROM orders GROUP BY user_id) AS subquery";
$stmt = $db->query($query);
$metrics['avg_orders_customer'] = $stmt->fetch(PDO::FETCH_ASSOC)['avg_orders_customer'];

// Total Revenue and Net Profit
$query = "SELECT SUM(total_amount) AS total_revenue, SUM(total_amount) - SUM(expenses) AS net_profit FROM orders WHERE payment_status = 'Paid' AND order_status NOT IN ('Cancelled', 'Refunded')";
$stmt = $db->query($query);
$financials = $stmt->fetch(PDO::FETCH_ASSOC);
$metrics['total_revenue'] = $financials['total_revenue'];
$metrics['net_profit'] = $financials['net_profit'];

// Revenue Growth
$query = "SELECT 
            (SUM(IF(MONTH(order_date) = MONTH(NOW()), total_amount, 0)) - 
             SUM(IF(MONTH(order_date) = MONTH(NOW()) - 1, total_amount, 0))) / 
             SUM(IF(MONTH(order_date) = MONTH(NOW()) - 1, total_amount, 0)) * 100 AS revenue_growth
          FROM orders WHERE payment_status = 'Paid' AND order_status NOT IN ('Cancelled', 'Refunded')";
$stmt = $db->query($query);
$metrics['revenue_growth'] = $stmt->fetch(PDO::FETCH_ASSOC)['revenue_growth'];

echo json_encode($metrics);
