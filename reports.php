<?php
session_start();
require_once 'config/database.php';
require_once 'includes/nav/collapsed.php'; // Include collapsed.php for the header
require_once 'includes/theme.php'; // Include themes

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
    $query = "SELECT 
        DATE_FORMAT(t.transaction_date, '%Y-%m') as month,
        SUM(CASE 
            WHEN t.transaction_type = 'Customer Payment' AND t.payment_status = 'completed' 
            THEN t.total_amount 
            ELSE 0 
        END) as revenue,
        SUM(CASE 
            WHEN t.transaction_type IN ('Expense', 'Refund') 
            THEN t.total_amount 
            ELSE 0 
        END) as expenses,
        SUM(CASE 
            WHEN t.transaction_type = 'Refund' 
            THEN t.total_amount 
            ELSE 0 
        END) as refunds,
        COUNT(DISTINCT CASE 
            WHEN t.transaction_type = 'Customer Payment' 
            THEN t.order_id 
        END) as total_orders,
        COUNT(DISTINCT CASE 
            WHEN t.transaction_type = 'Refund' 
            THEN t.order_id 
        END) as refunded_orders
    FROM transactions t
    WHERE 1=1";
    
    $params = [];
    if ($startDate) {
        $query .= " AND t.transaction_date >= :start_date";
        $params[':start_date'] = $startDate . ' 00:00:00';
    }
    if ($endDate) {
        $query .= " AND t.transaction_date <= :end_date";
        $params[':end_date'] = $endDate . ' 23:59:59';
    }
    
    $query .= " GROUP BY DATE_FORMAT(t.transaction_date, '%Y-%m')
                ORDER BY month DESC LIMIT 6";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
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

function getRecentTransactions($pdo, $search = '', $startDate = null, $endDate = null) {
    try {
        $query = "
            SELECT 
                t.reference_id,
                t.total_amount,
                t.order_id,
                t.transaction_type,
                t.payment_status,
                t.transaction_date,
                t.payment_method,
                o.total_amount as original_amount,
                CASE 
                    WHEN t.transaction_type = 'Customer Payment' 
                         AND t.payment_status = 'completed' THEN 'IN'
                    WHEN t.transaction_type = 'Refund' THEN 'REFUND'
                    ELSE 'OUT'
                END as flow_type,
                CASE 
                    WHEN t.transaction_type = 'Customer Payment' 
                         AND t.payment_status = 'completed' THEN 'text-success'
                    WHEN t.transaction_type = 'Refund' THEN 'text-warning'
                    ELSE 'text-danger'
                END as amount_class
            FROM transactions t
            LEFT JOIN orders o ON t.order_id = o.order_id
            WHERE 1=1";
        
        $params = [];
        
        if ($search) {
            $query .= " AND (t.reference_id LIKE :search OR t.payment_method LIKE :search)";
            $params[':search'] = "%$search%";
        }
        
        if ($startDate) {
            $query .= " AND t.transaction_date >= :start_date";
            $params[':start_date'] = $startDate . ' 00:00:00';
        }
        
        if ($endDate) {
            $query .= " AND t.transaction_date <= :end_date";
            $params[':end_date'] = $endDate . ' 23:59:59';
        }
        
        $query .= " ORDER BY t.transaction_date DESC LIMIT 10";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error in getRecentTransactions: " . $e->getMessage());
        return [];
    }
}

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
            SUM(CASE 
                WHEN refunded_amount = 0 THEN original_amount
                ELSE original_amount - refunded_amount
            END) as net_revenue,
            SUM(refunded_amount) as total_refunds
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

        // Get previous period data
        $prevStartDate = $startDate ? 
            date('Y-m-d', strtotime($startDate . ' -1 month')) : 
            date('Y-m-d', strtotime('-2 month'));
        $prevEndDate = $startDate ? 
            date('Y-m-d', strtotime($startDate . ' -1 day')) : 
            date('Y-m-d', strtotime('-1 month'));

        $prevQuery = str_replace([':start_date', ':end_date'], 
            ['"' . $prevStartDate . ' 00:00:00"', '"' . $prevEndDate . ' 23:59:59"'], 
            $currentQuery);
        
        $prevStmt = $pdo->query($prevQuery);
        $prevResult = $prevStmt->fetch(PDO::FETCH_ASSOC);

        // Get expenses
        $expenseQuery = "SELECT 
            SUM(CASE 
                WHEN transaction_type = 'Expense' 
                THEN total_amount 
                ELSE 0 
            END) as current_expenses,
            SUM(CASE 
                WHEN transaction_type = 'Expense' 
                AND transaction_date BETWEEN :prev_start AND :prev_end
                THEN total_amount 
                ELSE 0 
            END) as previous_expenses
        FROM transactions
        WHERE transaction_date <= :end_date";

        $expenseStmt = $pdo->prepare($expenseQuery);
        $expenseStmt->execute([
            ':prev_start' => $prevStartDate . ' 00:00:00',
            ':prev_end' => $prevEndDate . ' 23:59:59',
            ':end_date' => ($endDate ?? date('Y-m-d')) . ' 23:59:59'
        ]);
        $expenseResult = $expenseStmt->fetch(PDO::FETCH_ASSOC);

        // Calculate final results
        $currentNetProfit = ($currentResult['net_revenue'] ?? 0) - ($expenseResult['current_expenses'] ?? 0);
        $previousNetProfit = ($prevResult['net_revenue'] ?? 0) - ($expenseResult['previous_expenses'] ?? 0);

        return [
            'current' => $currentNetProfit,
            'previous' => $previousNetProfit,
            'net_revenue' => $currentResult['net_revenue'] ?? 0,
            'expenses' => $expenseResult['current_expenses'] ?? 0,
            'refunds' => $currentResult['total_refunds'] ?? 0,
            'growth' => $previousNetProfit != 0 ? 
                (($currentNetProfit - $previousNetProfit) / $previousNetProfit * 100) : 0
        ];
    } catch (PDOException $e) {
        error_log("Error in getNetProfit: " . $e->getMessage());
        return [
            'current' => 0,
            'previous' => 0,
            'net_revenue' => 0,
            'expenses' => 0,
            'refunds' => 0,
            'growth' => 0
        ];
    }
}

function getActiveCustomers($pdo, $startDate = null, $endDate = null)
{
    if (!$pdo) return 0;
    try {
        $query = "SELECT 
                COUNT(DISTINCT c.customer_id) as total_customers,
                SUM(CASE 
                    WHEN c.last_activity >= COALESCE(:current_start, DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH))
                    THEN 1 ELSE 0 
                END) as current_month_customers,
                (
                    SELECT COUNT(DISTINCT c2.customer_id)
                    FROM customers c2
                    WHERE c2.last_activity BETWEEN 
                        COALESCE(:prev_start, DATE_SUB(CURRENT_DATE, INTERVAL 2 MONTH))
                        AND COALESCE(:prev_end, DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH))
                    AND c2.status = 'active'
                ) as previous_month_customers
            FROM customers c
            WHERE c.status = 'active'
            AND (
                c.last_activity >= COALESCE(:start_date, DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH))
                OR EXISTS (
                    SELECT 1 FROM orders o 
                    WHERE o.id = c.customer_id
                    AND o.order_date >= COALESCE(:start_date, DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH))
                )
                OR EXISTS (
                    SELECT 1 FROM customer_activity ca 
                    WHERE ca.customer_id = c.customer_id
                    AND ca.activity_date >= COALESCE(:start_date, DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH))
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
    'current' => $profitStats['net_revenue'] ?? 0,
    'previous' => 0,
    'growth' => 0
];

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
    <link href="assets/css/reports.css" rel="stylesheet"> <!-- Link to reports.css -->
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
                    <small class="text-muted">Before deductions & refunds</small>
                    <p class="growth-indicator <?= $profitStats['growth'] >= 0 ? 'growth-positive' : 'growth-negative' ?>">
                        <?= $profitStats['growth'] >= 0 ? '↑' : '↓' ?> <?= abs(round($profitStats['growth'], 1)) ?>% 
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
                    <p class="metric-value">Ksh.<?= number_format($profitStats['current'], 2) ?></p>
                    <small class="text-muted">After expenses & refunds</small>
                    <div class="mt-2">
                        <small class="d-block text-muted">
                            Expenses: Ksh.<?= number_format($profitStats['expenses'], 2) ?>
                        </small>
                        <small class="d-block text-muted">
                            Refunds: Ksh.<?= number_format($profitStats['refunds'], 2) ?>
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
                            <tbody class="divide-y divide-[var(--border-color)]">
                                <?php if (empty($recentTransactions)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">No transactions found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentTransactions as $transaction): ?>
                                        <tr>
                                            <td class="px-6 py-4">
                                                <div class="flex items-center">
                                                    <span class="font-medium"><?= htmlspecialchars($transaction['reference_id']) ?></span>
                                                    <?php if ($transaction['transaction_type'] === 'Refund'): ?>
                                                        <span class="ml-2 px-2 py-1 text-xs font-medium bg-warning-soft text-warning rounded-full">
                                                            Refund
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <!-- Money In Column -->
                                            <td class="numeric-cell">
                                                <?php if ($transaction['flow_type'] === 'IN'): ?>
                                                    <span class="text-success">
                                                        +Ksh. <?= number_format($transaction['total_amount'], 2) ?>
                                                    </span>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <!-- Money Out Column -->
                                            <td class="numeric-cell">
                                                <?php if ($transaction['flow_type'] === 'OUT'): ?>
                                                    <span class="text-danger">
                                                        -Ksh. <?= number_format($transaction['total_amount'], 2) ?>
                                                    </span>
                                                <?php elseif ($transaction['flow_type'] === 'REFUND'): ?>
                                                    <span class="text-warning">
                                                        <?php
                                                        $refundPercent = 0;
                                                        if (!empty($transaction['original_amount']) && $transaction['original_amount'] > 0) {
                                                            $refundPercent = ($transaction['total_amount'] / $transaction['original_amount']) * 100;
                                                            echo "-Ksh. " . number_format($transaction['total_amount'], 2);
                                                            echo " <small>(" . number_format($refundPercent, 1) . "%)</small>";
                                                        } else {
                                                            echo "-Ksh. " . number_format($transaction['total_amount'], 2);
                                                        }
                                                        ?>
                                                    </span>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="status-badge status-<?= strtolower($transaction['payment_status']) ?>">
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
        // Add this before the existing chart initialization code
        document.getElementById('dateFilterForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            window.location.href = `reports.php?start_date=${startDate}&end_date=${endDate}`;
        });

        document.getElementById('resetFilter').addEventListener('click', function() {
            window.location.href = 'reports.php';
        });

        // Prepare data for charts
        const revenueData = <?= json_encode($revenueData) ?>;
        const salesByCategoryData = <?= json_encode($salesByCategory) ?>;

        // Revenue vs Expenses Chart
        const revenueChart = new Chart(
            document.getElementById('revenueChart'),
            {
                type: 'line',
                data: {
                    labels: revenueData.map(d => d.month),
                    datasets: [
                        {
                            label: 'Revenue',
                            data: revenueData.map(d => d.revenue),
                            borderColor: '#4F46E5',
                            tension: 0.1
                        },
                        {
                            label: 'Expenses',
                            data: revenueData.map(d => d.expenses),
                            borderColor: '#EF4444',
                            tension: 0.1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    }
                }
            }
        );

        // Sales by Category Chart
        const categoryChart = new Chart(
            document.getElementById('categoryChart'),
            {
                type: 'bar',
                data: {
                    labels: salesByCategoryData.map(d => d.category),
                    datasets: [
                        {
                            label: 'Revenue',
                            data: salesByCategoryData.map(d => d.total_revenue),
                            backgroundColor: '#4F46E5'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    }
                }
            }
        );

        // Add this after your existing chart initializations
        const ordersPerCustomerData = <?= json_encode($ordersPerCustomer) ?>;
        
        // Orders per Customer Pie Chart
        const ordersPerCustomerChart = new Chart(
            document.getElementById('ordersPerCustomerChart'),
            {
                type: 'doughnut',
                data: {
                    labels: ordersPerCustomerData.map(d => d.order_group),
                    datasets: [{
                        data: ordersPerCustomerData.map(d => d.customer_count),
                        backgroundColor: [
                            '#4F46E5', // Indigo
                            '#10B981', // Green
                            '#F59E0B', // Yellow
                            '#EF4444', // Red
                            '#8B5CF6'  // Purple
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '60%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: {
                                    family: "'Montserrat', sans-serif",
                                    size: 11
                                },
                                padding: 10
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value * 100) / total).toFixed(1);
                                    const avgItems = ordersPerCustomerData[context.dataIndex].avg_items;
                                    return [
                                        `${label}: ${value} orders`,
                                        `${percentage}% of total`,
                                        `Avg items: ${avgItems}`
                                    ];
                                }
                            }
                        }
                    }
                }
            }
        );

        // Toggle dark mode
        function toggleDarkMode() {
            const body = document.body;
            body.classList.toggle('dark-mode');
            body.classList.toggle('light-mode');
        }

        // Update chart options for better visibility
        const chartOptions = {
            responsive: true,
            plugins: {
                legend: {                    position: 'top',
                    labels: {
                        color: getComputedStyle(document.documentElement).getPropertyValue('--chart-text'),
                        font: {
                            family: "'Montserrat', sans-serif",
                            weight: '500'
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        color: getComputedStyle(document.documentElement).getPropertyValue('--chart-grid')
                    },
                    ticks: {
                        color: getComputedStyle(document.documentElement).getPropertyValue('--chart-text'),
                        font: {
                            family: "'Montserrat', sans-serif"
                        }
                    }
                },
                y: {
                    grid: {
                        color: getComputedStyle(document.documentElement).getPropertyValue('--chart-grid')
                    },
                    ticks: {
                        color: getComputedStyle(document.documentElement).getPropertyValue('--chart-text'),
                        font: {
                            family: "'Montserrat', sans-serif"
                        }
                    }
                }
            }
        };

        // Apply options to both charts
        revenueChart.options = { ...revenueChart.options, ...chartOptions };
        categoryChart.options = { ...categoryChart.options, ...chartOptions };

        // Update charts when theme changes
        const updateChartsTheme = () => {
            const isDark = document.body.classList.contains('dark-mode');
            document.documentElement.setAttribute('data-bs-theme', isDark ? 'dark' : 'light');
            revenueChart.update();
            categoryChart.update();
            ordersPerCustomerChart.update();
        };

        // Add theme change listener
        document.addEventListener('themeChanged', updateChartsTheme);

        // Add real-time customer activity updates
        function updateActiveCustomers() {
            fetch('ajax/get_active_customers.php')
                .then(response => response.json())
                .then(data => {
                    const customerCard = document.querySelector('[data-metric="customers"]');
                    const valueElement = customerCard.querySelector('.metric-value');
                    const growthElement = customerCard.querySelector('.growth-indicator');
                    
                    valueElement.textContent = new Intl.NumberFormat().format(data.current);
                    
                    const growth = data.previous !== 0 ? 
                        ((data.current - data.previous) / data.previous * 100) : 0;
                    
                    growthElement.className = `growth-indicator ${growth >= 0 ? 'growth-positive' : 'growth-negative'}`;
                    growthElement.textContent = `${growth >= 0 ? '↑' : '↓'} ${Math.abs(growth.toFixed(1))}% from last month`;
                })
                .catch(error => console.error('Error updating active customers:', error));
        }

        // Update active customers count every 5 minutes
        setInterval(updateActiveCustomers, 300000);

        // Update chart colors for better visualization
        const chartColors = {
            revenue: '#10B981',  // Green for revenue
            expenses: '#EF4444', // Red for expenses
            refunds: '#F59E0B'   // Orange for refunds
        };

        // Update revenue chart options
        revenueChart.data.datasets[0].borderColor = chartColors.revenue;
        revenueChart.data.datasets[1].borderColor = chartColors.expenses;
    </script>
</body>

</html>