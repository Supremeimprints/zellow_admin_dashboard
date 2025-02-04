<?php
session_start();
require_once 'config/database.php';
require_once 'includes/nav/collapsed.php'; // Include collapsed.php for the header
require_once 'includes/theme.php'; // Include themes
require_once 'includes/functions/transaction_functions.php'; // Add this line
require_once 'includes/functions/chart_functions.php'; // Add near the top after other requires

// Authentication check
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}
$database = new Database();
$db = $database->getConnection();

$whereClause = [];
$params = [];
// Initialize filter parameters
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$paymentStatusFilter = $_GET['payment_status'] ?? '';
$paymentMethodFilter = $_GET['payment_method'] ?? '';
$shippingMethodFilter = $_GET['shipping_method'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

// Get report data
$reportData = [];

// Helper functions for data retrieval
function getRevenueData($pdo, $startDate = null, $endDate = null) {
    $query = "WITH monthly_stats AS (
        SELECT 
            DATE_FORMAT(o.order_date, '%Y-%m') as month,
            SUM(o.total_amount) as total_revenue,
            COALESCE(SUM(r.refund_amount), 0) as refunds,
            COALESCE(SUM(e.expense_amount), 0) as expenses
        FROM orders o
        LEFT JOIN (
            SELECT order_id, SUM(total_amount) as refund_amount, transaction_date
            FROM transactions 
            WHERE transaction_type = 'Refund'
            GROUP BY order_id
        ) r ON o.order_id = r.order_id
        LEFT JOIN (
            SELECT DATE_FORMAT(transaction_date, '%Y-%m') as exp_month, 
                   SUM(total_amount) as expense_amount
            FROM transactions
            WHERE transaction_type = 'Expense'
            GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
        ) e ON DATE_FORMAT(o.order_date, '%Y-%m') = e.exp_month
        WHERE 1=1";

    if ($startDate) {
        $query .= " AND o.order_date >= :start_date";
    }
    if ($endDate) {
        $query .= " AND o.order_date <= :end_date";
    }
    
    $query .= " GROUP BY DATE_FORMAT(o.order_date, '%Y-%m')
                ORDER BY month DESC
                LIMIT 6)
                SELECT 
                    month,
                    total_revenue as revenue,
                    refunds,
                    expenses,
                    (total_revenue - refunds - expenses) as net_profit
                FROM monthly_stats
                ORDER BY month ASC";

    $stmt = $pdo->prepare($query);
    if ($startDate) {
        $stmt->bindValue(':start_date', $startDate . ' 00:00:00');
    }
    if ($endDate) {
        $stmt->bindValue(':end_date', $endDate . ' 23:59:59');
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTopProducts($pdo, $startDate = null, $endDate = null)
{
    $query = "SELECT 
        p.product_name as product_name,
        SUM(oi.quantity) as total_quantity,
        SUM(oi.quantity * oi.unit_price) as total_revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.order_id
    JOIN products p ON oi.product_id = p.product_id
    WHERE 1=1";
    
    $params = [];
    if ($startDate) {
        $query .= " AND o.order_date >= :start_date";
        $params[':start_date'] = $startDate . ' 00:00:00';
    }
    if ($endDate) {
        $query .= " AND o.order_date <= :end_date";
        $params[':end_date'] = $endDate . ' 23:59:59';
    }
    
    $query .= " GROUP BY p.product_name
                ORDER BY total_revenue DESC
                LIMIT 5";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Remove the getRecentTransactions() function definition since it's now in transaction_functions.php

function getSalesByCategory($pdo, $startDate = null, $endDate = null)
{
    $query = "SELECT 
        c.category_name as category,
        SUM(oi.quantity * oi.unit_price) as total_revenue
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.order_id
    JOIN products p ON oi.product_id = p.product_id
    JOIN categories c ON p.category_id = c.category_id
    WHERE 1=1";
    
    $params = [];
    if ($startDate) {
        $query .= " AND o.order_date >= :start_date";
        $params[':start_date'] = $startDate . ' 00:00:00';
    }
    if ($endDate) {
        $query .= " AND o.order_date <= :end_date";
        $params[':end_date'] = $endDate . ' 23:59:59';
    }
    
    $query .= " GROUP BY c.category_name
                ORDER BY total_revenue DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Add this new function after your existing functions
function getOrdersPerCustomer($pdo, $startDate = null, $endDate = null) {
    try {
        $query = "WITH CustomerOrders AS (
            SELECT 
                o.id,
                COUNT(DISTINCT o.order_id) as order_count,
                SUM(oi.quantity) as total_items,
                COUNT(oi.id) as items_per_order,
                SUM(oi.subtotal) as total_spent
            FROM orders o
            JOIN order_items oi ON o.order_id = oi.order_id
            WHERE 1=1 ";
        
        $params = [];
        if ($startDate) {
            $query .= " AND o.order_date >= :start_date";
            $params[':start_date'] = $startDate;
        }
        if ($endDate) {
            $query .= " AND o.order_date <= :end_date";
            $params[':end_date'] = $endDate;
        }
        
        $query .= " GROUP BY o.id
        )
        SELECT 
            CASE 
                WHEN items_per_order <= 2 THEN '1-2 items'
                WHEN items_per_order <= 5 THEN '3-5 items'
                WHEN items_per_order <= 10 THEN '6-10 items'
                WHEN items_per_order <= 20 THEN '11-20 items'
                ELSE '20+ items'
            END as order_group,
            COUNT(*) as customer_count,
            ROUND(AVG(total_items), 1) as avg_items,
            ROUND(AVG(items_per_order), 1) as avg_items_per_order,
            ROUND(AVG(total_spent), 2) as avg_spent
        FROM CustomerOrders
        GROUP BY 
            CASE 
                WHEN items_per_order <= 2 THEN '1-2 items'
                WHEN items_per_order <= 5 THEN '3-5 items'
                WHEN items_per_order <= 10 THEN '6-10 items'
                WHEN items_per_order <= 20 THEN '11-20 items'
                ELSE '20+ items'
            END
        ORDER BY 
            MIN(items_per_order) ASC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error in getOrdersPerCustomer: " . $e->getMessage());
        return [];
    }
}

// Get data for the dashboard
$revenueData = getRevenueData($db, $startDate, $endDate);
$topProducts = getTopProducts($db, $startDate, $endDate);
$recentTransactions = getRecentTransactions($db, $search, $startDate, $endDate); // This already has date filtering
$salesByCategory = getSalesByCategory($db, $startDate, $endDate);
$ordersPerCustomer = getOrdersPerCustomer($db, $startDate, $endDate);

// Calculate summary metrics
$currentMonthRevenue = $revenueData[0]['revenue'] ?? 0;
$previousMonthRevenue = $revenueData[1]['revenue'] ?? 0;
$revenueGrowth = $previousMonthRevenue != 0 ?
    (($currentMonthRevenue - $previousMonthRevenue) / $previousMonthRevenue * 100) : 0;

function getTotalOrders($pdo, $startDate = null, $endDate = null)
{
    if (!$pdo) return 0;
    try {
        $query = "SELECT 
                COUNT(*) as total_orders,
                COUNT(*) as current_month_orders,
                (
                    SELECT COUNT(*) 
                    FROM orders 
                    WHERE order_date BETWEEN 
                        COALESCE(:prev_start, DATE_SUB(CURRENT_DATE, INTERVAL 2 MONTH))
                        AND COALESCE(:prev_end, DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH))
                ) as previous_month_orders
            FROM orders 
            WHERE 1=1";
        
        $params = [
            ':prev_start' => $startDate ? date('Y-m-d', strtotime($startDate . ' -1 month')) : null,
            ':prev_end' => $startDate ? date('Y-m-d', strtotime($startDate . ' -1 day')) : null
        ];

        if ($startDate) {
            $query .= " AND order_date >= :start_date";
            $params[':start_date'] = $startDate . ' 00:00:00';
        }
        if ($endDate) {
            $query .= " AND order_date <= :end_date";
            $params[':end_date'] = $endDate . ' 23:59:59';
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return [
            'total' => $result['total_orders'],
            'current' => $result['current_month_orders'],
            'previous' => $result['previous_month_orders']
        ];
    } catch (PDOException $e) {
        error_log("Error in getTotalOrders: " . $e->getMessage());
        return ['total' => 0, 'current' => 0, 'previous' => 0];
    }
}

function getNetProfit($pdo, $startDate = null, $endDate = null) {
    try {
        // Get current period data
        $currentQuery = "WITH OrderStats AS (
            SELECT 
                o.order_id,
                o.total_amount as original_amount,
                COALESCE(SUM(CASE 
                    WHEN t.transaction_type = 'Refund' 
                    THEN t.total_amount 
                    ELSE 0 
                END), 0) as refunded_amount
            FROM orders o
            LEFT JOIN transactions t ON o.order_id = t.order_id
            WHERE 1=1 ";
        
        if ($startDate) {
            $currentQuery .= " AND o.order_date >= :start_date";
        }
        if ($endDate) {
            $currentQuery .= " AND o.order_date <= :end_date";
        }
        
        $currentQuery .= " GROUP BY o.order_id
        )
        SELECT 
            SUM(original_amount) as total_revenue,
            SUM(refunded_amount) as total_refunds,
            SUM(original_amount - refunded_amount) as net_revenue
        FROM OrderStats";

        $stmt = $pdo->prepare($currentQuery);
        if ($startDate) {
            $stmt->bindValue(':start_date', $startDate . ' 00:00:00');
        }
        if ($endDate) {
            $stmt->bindValue(':end_date', $endDate . ' 23:59:59');
        }
        $stmt->execute();
        $currentResult = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get expenses
        $expenseQuery = "SELECT 
            SUM(CASE 
                WHEN transaction_type = 'Expense' 
                THEN total_amount 
                ELSE 0 
            END) as current_expenses
        FROM transactions
        WHERE 1=1";

        if ($startDate) {
            $expenseQuery .= " AND transaction_date >= :start_date";
        }
        if ($endDate) {
            $expenseQuery .= " AND transaction_date <= :end_date";
        }

        $expenseStmt = $pdo->prepare($expenseQuery);
        if ($startDate) {
            $expenseStmt->bindValue(':start_date', $startDate . ' 00:00:00');
        }
        if ($endDate) {
            $expenseStmt->bindValue(':end_date', $endDate . ' 23:59:59');
        }
        $expenseStmt->execute();
        $expenseResult = $expenseStmt->fetch(PDO::FETCH_ASSOC);

        // Calculate final net profit
        $totalRevenue = $currentResult['total_revenue'] ?? 0;
        $totalRefunds = $currentResult['total_refunds'] ?? 0;
        $totalExpenses = $expenseResult['current_expenses'] ?? 0;
        $netProfit = $currentResult['net_revenue'] - $totalExpenses;

        return [
            'total_revenue' => $totalRevenue,
            'refunds' => $totalRefunds,
            'expenses' => $totalExpenses,
            'net_profit' => $netProfit,
            'net_revenue' => $currentResult['net_revenue'] ?? 0
        ];
    } catch (PDOException $e) {
        error_log("Error in getNetProfit: " . $e->getMessage());
        return [
            'total_revenue' => 0,
            'refunds' => 0,
            'expenses' => 0,
            'net_profit' => 0,
            'net_revenue' => 0
        ];
    }
}

function getActiveCustomers($pdo, $startDate = null, $endDate = null)
{
    if (!$pdo) return 0;
    try {
        $query = "SELECT 
                COUNT(DISTINCT u.id) as total_customers,
                SUM(CASE 
                    WHEN o.order_date >= COALESCE(:current_start, DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH))
                    THEN 1 ELSE 0 
                END) as current_month_customers,
                (
                    SELECT COUNT(DISTINCT u2.id)
                    FROM users u2
                    JOIN orders o2 ON u2.id = o2.id
                    WHERE o2.order_date BETWEEN 
                        COALESCE(:prev_start, DATE_SUB(CURRENT_DATE, INTERVAL 2 MONTH))
                        AND COALESCE(:prev_end, DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH))
                    AND u2.role = 'customer'
                    AND u2.is_active = 1
                ) as previous_month_customers
            FROM users u
            LEFT JOIN orders o ON u.id = o.id
            WHERE u.role = 'customer'
            AND u.is_active = 1
            AND (
                o.order_date >= COALESCE(:start_date, DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH))
                OR EXISTS (
                    SELECT 1 FROM orders o3 
                    WHERE o3.id = u.id
                    AND o3.order_date >= COALESCE(:start_date, DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH))
                )
            )";
        
        $params = [
            ':current_start' => $startDate ?? date('Y-m-d', strtotime('-1 month')),
            ':prev_start' => $startDate ? date('Y-m-d', strtotime($startDate . ' -1 month')) : date('Y-m-d', strtotime('-2 month')),
            ':prev_end' => $startDate ? date('Y-m-d', strtotime($startDate . ' -1 day')) : date('Y-m-d', strtotime('-1 month')),
            ':start_date' => $startDate ? $startDate . ' 00:00:00' : date('Y-m-d', strtotime('-1 month')),
        ];

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'total' => $result['total_customers'] ?? 0,
            'current' => $result['current_month_customers'] ?? 0,
            'previous' => $result['previous_month_customers'] ?? 0
        ];
    } catch (PDOException $e) {
        error_log("Error in getActiveCustomers: " . $e->getMessage());
        return ['total' => 0, 'current' => 0, 'previous' => 0];
    }
}

function getOrderDistribution($pdo, $startDate = null, $endDate = null) {
    try {
        $query = "WITH OrderCounts AS (
            SELECT 
                o.order_id,
                COUNT(oi.id) as items_count,
                SUM(oi.quantity) as total_quantity,
                o.total_amount,
                COALESCE(r.refunded_amount, 0) as refunded_amount
            FROM orders o
            JOIN order_items oi ON o.order_id = oi.order_id
            LEFT JOIN (
                SELECT order_id, SUM(total_amount) as refunded_amount
                FROM transactions 
                WHERE transaction_type = 'Refund'
                GROUP BY order_id
            ) r ON o.order_id = r.order_id
            WHERE 1=1";

        if ($startDate) {
            $query .= " AND o.order_date >= :start_date";
            $params[':start_date'] = $startDate . ' 00:00:00';
        }
        if ($endDate) {
            $query .= " AND o.order_date <= :end_date";
            $params[':end_date'] = $endDate . ' 23:59:59';
        }

        $query .= " GROUP BY o.order_id
        )
        SELECT 
            CASE 
                WHEN refunded_amount > 0 THEN 'Refunded Orders'
                WHEN items_count = 1 THEN 'Single Item'
                WHEN items_count <= 3 THEN '2-3 Items'
                WHEN items_count <= 5 THEN '4-5 Items'
                ELSE '6+ Items'
            END as category,
            COUNT(*) as count,
            AVG(total_quantity) as avg_quantity,
            AVG(total_amount) as avg_amount,
            SUM(total_amount) as total_amount
        FROM OrderCounts
        GROUP BY CASE 
            WHEN refunded_amount > 0 THEN 'Refunded Orders'
            WHEN items_count = 1 THEN 'Single Item'
            WHEN items_count <= 3 THEN '2-3 Items'
            WHEN items_count <= 5 THEN '4-5 Items'
            ELSE '6+ Items'
        END
        ORDER BY 
            CASE category
                WHEN 'Refunded Orders' THEN 1
                WHEN 'Single Item' THEN 2
                WHEN '2-3 Items' THEN 3
                WHEN '4-5 Items' THEN 4
                ELSE 5
            END";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params ?? []);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error in getOrderDistribution: " . $e->getMessage());
        return [];
    }
}

// Get data for all cards
$orderStats = getTotalOrders($db, $startDate, $endDate);
$profitStats = getNetProfit($db, $startDate, $endDate);
$customerStats = getActiveCustomers($db, $startDate, $endDate);  // Add this line

// Calculate total revenue (all income before deductions)
$revenueStats = [
    'current' => $profitStats['total_revenue'] ?? 0,  // Changed from net_revenue to total_revenue
    'previous' => $profitStats['previous'] ?? 0,
    'growth' => 0
];

// Calculate revenue growth
if ($revenueStats['previous'] != 0) {
    $revenueStats['growth'] = (($revenueStats['current'] - $revenueStats['previous']) / $revenueStats['previous']) * 100;
}

// Calculate growth percentages
$orderGrowth = $orderStats['previous'] != 0 ?
    (($orderStats['current'] - $orderStats['previous']) / $orderStats['previous'] * 100) : 0;

// Add null check for customerStats
$customerGrowth = ($customerStats && $customerStats['previous'] != 0) ?
    (($customerStats['current'] - $customerStats['previous']) / $customerStats['previous'] * 100) : 0;

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="reports.css" rel="stylesheet"> <!-- Link to reports.css -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Link to Montserrat font -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet"> <!-- Link to Bootstrap -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.0/chart.min.js"></script>
    <script src="assets/js/reports.js"></script> <!-- Link to reports.js -->
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--background);
            color: var(--text-color);
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 20px;
        }

        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            text-align: center;
            border-radius: 8px;
            box-shadow: var(--box-shadow);
            background: var(--container-bg);
            transition: transform 0.2s;
        }

        .card:hover {
            transform: translateY(-5px);
        }

        .metric-title {
            font-size: 1rem;
            color: var(--text-muted);
            font-weight: 500;
            margin-bottom: 8px;
        }

        .metric-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--metric-value-color);
        }

        .growth-indicator {
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 0.9rem;
            font-weight: 500;
            margin-top: 8px;
        }

        .growth-positive {
            color: var(--priority-low);
        }

        .growth-negative {
            color: var(--priority-high);
        }

        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: var(--box-shadow);
            background-color: var(--container-bg);
            border: 1px solid var(--border-color);
        }

        table {
            width: 100%;
            color: var(--text-color);
            font-family: var(--font-family);
        }

        th {
            padding: 12px 24px;
            text-align: left;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            background-color: var(--table-header-bg);
            color: var(--text-muted);
            border-bottom: 2px solid var(--border-color);
        }

        td {
            padding: 12px 24px;
            white-space: nowrap;
            font-size: 0.875rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-color);
        }

        tr:hover {
            background-color: var(--feedback-bg);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-completed {
            background-color: var(--priority-low);
            color: white;
        }

        .status-pending {
            background-color: var(--priority-medium);
            color: black;
        }

        .status-failed {
            background-color: var(--priority-high);
            color: white;
        }

        .numeric-cell {
            text-align: right;
            font-family: monospace;
            color: var(--text-color) !important;
        }

        .table-heading {
            font-size: 1.125rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        body.dark-mode {
            --background: #15202B;
            --card-bg: #1F2937;
            --text-color: #F9FAFB;
            --table-header-bg: #374151;
            --metric-value-color: #F9FAFB !important;
            --border-color: #4B5563;
            --text-muted: #9CA3AF;
            --priority-low: #10B981;
            --priority-medium: #F59E0B;
            --priority-high: #EF4444;
            --container-bg: #1F2937;
            --box-shadow: rgba(0, 0, 0, 0.1) 0px 4px 6px -1px, rgba(0, 0, 0, 0.06) 0px 2px 4px -1px;
            --feedback-bg: #2D3748;
        }

        body.dark-mode .metric-value,
        body.dark-mode .numeric-cell {
            color: #ffffff !important;
        }

        body.light-mode {
            --background: #ffffff;
            --card-bg: white;
            --text-color: #1F2937;
            --table-header-bg: #F9FAFB;
            --metric-value-color: #1F2937;
        }

        @media (max-width: 768px) {
            .grid-container {
                grid-template-columns: repeat(auto-fit, minmax(100%, 1fr));
            }
        }

        /* Base styles */
        :root {
            --chart-text: #1F2937;
            --chart-grid: #E5E7EB;
        }

        [data-bs-theme="dark"] {
            --chart-text: #F9FAFB;
            --chart-grid: #4B5563;
            --background: #1F2937;
        }

        /* Search filter styling to match orders page */
        .filter-form {
            background-color: var(--container-bg);
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .filter-form label {
            color: var(--text-muted);
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .filter-form input[type="date"] {
            background-color: var(--input-bg);
            border: 1px solid var(--border-color);
            color: var(--text-color);
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            width: 100%;
        }

        .filter-form input[type="date"]:focus {
            border-color: var(--primary-accent);
            outline: none;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.25);
        }

        /* Chart container styling */
        .chart-container {
            background-color: var(--container-bg);
            border-radius: 0.5rem;
            padding: 1rem;
        }

        /* Metric card improvements */
        .metric-card .metric-value {
            color: var(--metric-value-color) !important;
            font-weight: 700;
        }

        .metric-card .metric-title {
            color: var(--text-muted);
            font-weight: 500;
        }

        /* Dark mode specific overrides */
        [data-bs-theme="dark"] .table td,
        [data-bs-theme="dark"] .metric-value,
        [data-bs-theme="dark"] .numeric-cell {
            color: var(--text-color) !important;
        }

        [data-bs-theme="dark"] .chart-container {
            background-color: var(--container-bg);
        }

        [data-bs-theme="dark"] canvas {
            filter: none !important;
        }

        .nav-text {
            margin-left: 0.75rem;
            opacity: 0;
            transition: opacity 0.2s ease;
            font-size: 0.875rem;
            color: #a0aec0;
            display: none;
            text-decoration: none !important;  /* Add this line */
        }

        .sidebar a {
            text-decoration: none !important;  /* Add this line */
        }

        .sidebar a:hover {
            text-decoration: none !important;  /* Add this line */
        }

        /* Add to existing styles */
        .text-success {
            color: #10B981 !important;
        }
        
        .text-danger {
            color: #EF4444 !important;
        }
        
        .text-warning {
            color: #F59E0B !important;
        }
        
        .bg-warning-soft {
            background-color: rgba(245, 158, 11, 0.1);
        }
        
        .numeric-cell {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.9rem;
        }
        
        .numeric-cell span {
            font-weight: 500;
        }

        /* Add to the existing styles section */
        .bg-success-soft { background-color: rgba(16, 185, 129, 0.1); }
        .bg-warning-soft { background-color: rgba(245, 158, 11, 0.1); }
        .bg-danger-soft { background-color: rgba(239, 68, 68, 0.1); }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-completed { 
            background-color: var(--priority-low); 
            color: white;
        }

        .status-pending {
            background-color: var(--priority-medium);
            color: black;
        }

        .status-failed {
            background-color: var(--priority-high);
            color: white;
        }
    </style>
</head>

<body> <!-- Remove bg-gray-100 class -->
    <div class="container">
        <!-- Date Filter Section -->
        <div class="filter-form">
            <form id="dateFilterForm" class="flex flex-wrap items-end gap-4">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium mb-1">Date Range</label>
                    <div class="flex gap-2">
                        <input type="date" name="start_date" id="start_date"
                            class="flex-1"
                            value="<?= htmlspecialchars($startDate) ?>">
                        <input type="date" name="end_date" id="end_date"
                            class="flex-1"
                            value="<?= htmlspecialchars($endDate) ?>">
                    </div>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn btn-primary px-4 py-2">
                        Apply Filter
                    </button>
                    <button type="button" id="resetFilter" class="btn btn-secondary px-4 py-2">
                        Reset
                    </button>
                </div>
            </form>
        </div>

        <!-- Summary Cards -->
        <div class="grid-container">
            <div class="card metric-card" data-metric="revenue">
                <div>
                    <h3 class="metric-title">Total Revenue</h3>
                    <p class="metric-value">Ksh.<?= number_format($revenueStats['current'], 2) ?></p>
                    <small class="text-muted">Gross revenue before any deductions</small>
                    <div class="mt-2">
                        <small class="d-block text-muted">
                            Total Sales: Ksh.<?= number_format($profitStats['total_revenue'], 2) ?>
                        </small>
                    </div>
                    <p class="growth-indicator <?= $revenueStats['growth'] >= 0 ? 'growth-positive' : 'growth-negative' ?>">
                        <?= $revenueStats['growth'] >= 0 ? '↑' : '↓' ?> <?= abs(round($revenueStats['growth'], 1)) ?>% 
                        <?= $startDate ? 'vs previous period' : 'from last month' ?>
                    </p>
                </div>
            </div>
            <div class="card metric-card" data-metric="orders">
                <div>
                    <h3 class="metric-title">Total Orders</h3>
                    <p class="metric-value"><?= number_format($orderStats['current'] ?? 0) ?></p>
                    <small class="text-muted">Including cancelled orders</small>
                    <p class="growth-indicator <?= $orderGrowth >= 0 ? 'growth-positive' : 'growth-negative' ?>">
                        <?= $orderGrowth >= 0 ? '↑' : '↓' ?> <?= abs(round($orderGrowth, 1)) ?>% 
                        <?= $startDate ? 'vs previous period' : 'from last month' ?>
                    </p>
                </div>
            </div>
            <div class="card metric-card" data-metric="profit">
                <div>
                    <h3 class="metric-title">Net Profit</h3>
                    <p class="metric-value">Ksh.<?= number_format($profitStats['net_profit'], 2) ?></p>
                    <small class="text-muted">After expenses & refunds</small>
                    <div class="mt-2">
                        <small class="d-block text-muted">
                            Revenue: Ksh.<?= number_format($profitStats['total_revenue'], 2) ?>
                        </small>
                        <small class="d-block text-danger">
                            - Refunds: Ksh.<?= number_format($profitStats['refunds'], 2) ?>
                        </small>
                        <small class="d-block text-danger">
                            - Expenses: Ksh.<?= number_format($profitStats['expenses'], 2) ?>
                        </small>
                        <hr class="my-1">
                        <small class="d-block text-success fw-bold">
                            = Net Profit: Ksh.<?= number_format($profitStats['net_profit'], 2) ?>
                        </small>
                    </div>
                </div>
            </div>
            <div class="card metric-card" data-metric="customers">
                <div>
                    <h3 class="metric-title">Active Customers</h3>
                    <p class="metric-value"><?= number_format($customerStats['current'] ?? 0) ?></p>
                    <small class="text-muted">Last 30 days activity</small>
                    <p class="growth-indicator <?= $customerGrowth >= 0 ? 'growth-positive' : 'growth-negative' ?>">
                        <?= $customerGrowth >= 0 ? '↑' : '↓' ?> <?= abs(round($customerGrowth, 1)) ?>% 
                        <?= $startDate ? 'vs previous period' : 'from last month' ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid-container">
            <div class="card">
                <h2 class="text-lg font-semibold mb-4">Revenue vs Expenses</h2>
                <canvas id="revenueChart"></canvas>
            </div>
            
            <div class="card">
                <h2 class="text-lg font-semibold mb-4">Sales by Category</h2>
                <canvas id="categoryChart"></canvas>
            </div>
        </div>

        <!-- Tables Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">
            <!-- Left Column - Top Products and Orders per Customer -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Top Products Table -->
                <div class="space-y-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="table-heading">Top Products</h2>
                        <div>
                            <a href="export.php?table=topProductsTable&format=csv" class="btn btn-primary btn-sm">CSV</a>
                            <a href="export.php?table=topProductsTable&format=excel" class="btn btn-success btn-sm">Excel</a>
                            <a href="export.php?table=topProductsTable&format=pdf" class="btn btn-danger btn-sm">PDF</a>
                        </div>
                    </div>
                    <div class="table-container">
                        <table class="min-w-full divide-y divide-[var(--border-color)]">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Product Name
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Units Sold
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Revenue (KES)
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[var(--border-color)]">
                                <?php foreach ($topProducts as $product): ?>
                                    <tr class="hover:bg-opacity-10 hover:bg-gray-500">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <?= htmlspecialchars($product['product_name']) ?>
                                        </td></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900">
                                            <?= number_format($product['total_quantity']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900">
                                            <?= number_format($product['total_revenue'], 2) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Orders per Customer Chart -->
                <div class="card">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="text-lg font-semibold">Items per Order Distribution</h2>
                        <div class="d-flex gap-2"> <!-- Added d-flex and gap-2 classes -->
                            <a href="export.php?table=ordersPerCustomer&format=csv" class="btn btn-primary btn-sm">CSV</a>
                            <a href="export.php?table=ordersPerCustomer&format=excel" class="btn btn-success btn-sm">Excel</a>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-4" style="height: 300px;">
                        <canvas id="ordersPerCustomerChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Right Column - Recent Transactions -->
            <div class="lg:col-span-2">
                <div class="space-y-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <h2 class="table-heading">Recent Transactions</h2>
                        <div>
                            <a href="export.php?table=recentTransactionsTable&format=csv" class="btn btn-primary btn-sm">CSV</a>
                            <a href="export.php?table=recentTransactionsTable&format=excel" class="btn btn-success btn-sm">Excel</a>
                            <a href="export.php?table=recentTransactionsTable&format=pdf" class="btn btn-danger btn-sm">PDF</a>
                        </div>
                    </div>
                    <div class="table-container">
                        <table class="min-w-full divide-y divide-[var(--border-color)]">
                            <thead>
                                <tr>
                                    <th class="w-1/4">Reference</th>
                                    <th class="w-1/4">Money In</th>
                                    <th class="w-1/4">Money Out</th>
                                    <th class="w-1/4">Status</th>
                                    <th class="w-1/4">Date</th>
                                </tr>
                            </thead>
                            <!-- In the transactions table section -->
                            <tbody class="divide-y divide-[var(--border-color)]">
                                <?php if (empty($recentTransactions)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">No transactions found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentTransactions as $transaction): ?>
                                        <tr>
                                            <td class="px-6 py-4">
                                                <div class="flex flex-col">
                                                    <span class="font-medium mb-1"><?= htmlspecialchars($transaction['reference_id']) ?></span>
                                                    <span class="text-xs font-medium <?= $transaction['badge_class'] ?> rounded-full px-2 py-1 self-start">
                                                        <?= htmlspecialchars($transaction['transaction_type']) ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 numeric-cell">
                                                <?php if ($transaction['money_in'] > 0): ?>
                                                    <span class="text-success">
                                                        +Ksh. <?= number_format($transaction['money_in'], 2) ?>
                                                    </span>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 numeric-cell">
                                                <?php if ($transaction['money_out'] > 0): ?>
                                                    <div class="flex flex-col items-end">
                                                        <div class="flex flex-col">
                                                            <span class="<?= $transaction['transaction_type'] === 'Refund' ? 'text-warning' : 'text-danger' ?>">
                                                                -Ksh. <?= number_format($transaction['money_out'], 2) ?>
                                                            </span>
                                                            <?php if ($transaction['transaction_type'] === 'Refund' && $transaction['original_amount']): ?>
                                                                <span class="text-xs font-medium <?= $transaction['badge_class'] ?> rounded-full px-2 py-1 self-start mt-1">
                                                                    <?php
                                                                    $refundPercentage = ($transaction['money_out'] / $transaction['original_amount']) * 100;
                                                                    echo $refundPercentage >= 99 ? 'Full Refund' : 'Partial Refund';
                                                                    ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="status-badge <?= strtolower($transaction['payment_status']) ?>">
                                                    <?= htmlspecialchars($transaction['payment_status']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('M d, Y', strtotime($transaction['transaction_date'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div> <!-- closing container div -->

    <script>
        // Form handlers
        document.getElementById('dateFilterForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            window.location.href = `reports.php?start_date=${startDate}&end_date=${endDate}`;
        });

        document.getElementById('resetFilter').addEventListener('click', function() {
            window.location.href = 'reports.php';
        });

        // Prepare formatted data for charts
        const chartData = {
            revenueData: <?= json_encode(array_map(function($month) {
                return [
                    'month' => date('M Y', strtotime($month['month'] . '-01')),
                    'revenue' => floatval($month['revenue']),
                    'expenses' => floatval($month['expenses']),
                    'refunds' => floatval($month['refunds']),
                    'net_profit' => floatval($month['net_profit'])
                ];
            }, array_reverse($revenueData))) ?>,
            categoriesData: <?= json_encode(array_map(function($cat) {
                return [
                    'category' => $cat['category'],
                    'revenue' => floatval($cat['total_revenue'])
                ];
            }, $salesByCategory)) ?>,
            ordersData: <?= json_encode($ordersPerCustomer) ?>
        };

        // Initialize charts when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            initializeCharts(chartData);
        });
    </script>

</body>

</html>