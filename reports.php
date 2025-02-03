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
function getRevenueData($pdo, $startDate = null, $endDate = null)
{
    $query = "SELECT 
        DATE_FORMAT(o.order_date, '%Y-%m') as month,
        SUM(o.total_amount) as revenue,
        SUM(t.expenses) as expenses,
        COUNT(DISTINCT o.order_id) as total_orders
    FROM orders o
    LEFT JOIN transactions t ON o.order_id = t.order_id
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
    
    $query .= " GROUP BY DATE_FORMAT(o.order_date, '%Y-%m')
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

function getRecentTransactions($pdo) {
    try {
        $search = $_GET['search'] ?? '';
        $startDate = $_GET['start_date'] ?? '';
        $endDate = $_GET['end_date'] ?? '';
        
        $query = "
            SELECT * FROM (
                SELECT 
                    reference_id,
                    total_amount,
                    'IN' as transaction_type,
                    payment_status,
                    transaction_date
                FROM transactions 
                WHERE payment_status = 'completed'
                    AND transaction_type = 'Customer Payment'
                
                UNION ALL
                
                SELECT 
                    reference_id,
                    total_amount,
                    transaction_type,
                    payment_status,
                    transaction_date
                FROM transactions
                WHERE transaction_type = 'OUT'
            ) AS combined_transactions 
            WHERE 1=1";
        
        $params = [];
        
        // Add search condition
        if ($search) {
            $query .= " AND reference_id LIKE :search";
            $params[':search'] = "%$search%";
        }
        
        // Add date range conditions
        if ($startDate) {
            $query .= " AND transaction_date >= :start_date";
            $params[':start_date'] = $startDate . ' 00:00:00';
        }
        if ($endDate) {
            $query .= " AND transaction_date <= :end_date";
            $params[':end_date'] = $endDate . ' 23:59:59';
        }
        
        $query .= " ORDER BY transaction_date DESC LIMIT 10";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $results;
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

// Get data for the dashboard
$revenueData = getRevenueData($db, $startDate, $endDate);
$topProducts = getTopProducts($db, $startDate, $endDate);
$recentTransactions = getRecentTransactions($db); // This already has date filtering
$salesByCategory = getSalesByCategory($db, $startDate, $endDate);

// Calculate summary metrics
$currentMonthRevenue = $revenueData[0]['revenue'] ?? 0;
$previousMonthRevenue = $revenueData[1]['revenue'] ?? 0;
$revenueGrowth = $previousMonthRevenue != 0 ?
    (($currentMonthRevenue - $previousMonthRevenue) / $previousMonthRevenue * 100) : 0;

function getTotalOrders($pdo)
{
    if (!$pdo)
        return 0;
    try {
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
    } catch (PDOException $e) {
        error_log("Error in getTotalOrders: " . $e->getMessage());
        return ['total' => 0, 'current' => 0, 'previous' => 0];
    }
}

function getNetProfit($pdo, $startDate = null, $endDate = null) {
    if (!$pdo) return 0;
    try {
        $query = "SELECT 
                SUM(o.total_amount) as total_revenue,
                SUM(t.expenses) as total_expenses,
                SUM(CASE 
                    WHEN o.order_date >= DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH) 
                    THEN o.total_amount ELSE 0 
                END) as current_month_revenue,
                SUM(CASE 
                    WHEN o.order_date >= DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH) 
                    THEN COALESCE(t.expenses, 0) ELSE 0 
                END) as current_month_expenses
            FROM orders o
            LEFT JOIN transactions t ON o.order_id = t.order_id
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

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetch();

        // Get previous period data for comparison
        $prevStartDate = $startDate ? date('Y-m-d', strtotime($startDate . ' -1 month')) : date('Y-m-d', strtotime('-2 month'));
        $prevEndDate = $startDate ? date('Y-m-d', strtotime($startDate . ' -1 day')) : date('Y-m-d', strtotime('-1 month'));
        
        $prevQuery = "SELECT 
                SUM(o.total_amount) as prev_revenue,
                SUM(t.expenses) as prev_expenses
            FROM orders o
            LEFT JOIN transactions t ON o.order_id = t.order_id
            WHERE o.order_date BETWEEN :prev_start AND :prev_end";
        
        $prevStmt = $pdo->prepare($prevQuery);
        $prevStmt->execute([
            ':prev_start' => $prevStartDate . ' 00:00:00',
            ':prev_end' => $prevEndDate . ' 23:59:59'
        ]);
        $prevResult = $prevStmt->fetch();

        $currentProfit = $result['current_month_revenue'] - $result['current_month_expenses'];
        $previousProfit = $prevResult['prev_revenue'] - $prevResult['prev_expenses'];
        $totalProfit = $result['total_revenue'] - $result['total_expenses'];

        return [
            'current' => $currentProfit,
            'previous' => $previousProfit,
            'total' => $totalProfit
        ];
    } catch (PDOException $e) {
        error_log("Error in getNetProfit: " . $e->getMessage());
        return ['current' => 0, 'previous' => 0, 'total' => 0];
    }
}

function getActiveCustomers($pdo)
{
    if (!$pdo)
        return 0;
    try {
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
    } catch (PDOException $e) {
        error_log("Error in getActiveCustomers: " . $e->getMessage());
        return ['total' => 0, 'current' => 0, 'previous' => 0];
    }
}

// Get data for all cards
$orderStats = getTotalOrders($db);
$profitStats = getNetProfit($db, $startDate, $endDate);
$customerStats = getActiveCustomers($db);

// Calculate growth percentages
$orderGrowth = $orderStats['previous'] != 0 ?
    (($orderStats['current'] - $orderStats['previous']) / $orderStats['previous'] * 100) : 0;

$profitGrowth = $profitStats['previous'] != 0 ?
    (($profitStats['current'] - $profitStats['previous']) / $profitStats['previous'] * 100) : 0;

$customerGrowth = $customerStats['previous'] != 0 ?
    (($customerStats['current'] - $customerStats['previous']) / $customerStats['previous'] * 100) : 0;

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/tailwindcss/2.2.19/tailwind.min.css" rel="stylesheet">
    <link href="themes/reports.css" rel="stylesheet"> <!-- Link to reports.css -->
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
    </style>
</head>

<body class="bg-gray-100 p-6 light-mode">
    <div class="container">
        <!-- Date Filter Section -->
        <div class="mb-6 bg-white p-4 rounded-lg shadow">
            <form id="dateFilterForm" class="flex flex-wrap items-end gap-4">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium mb-1 text-gray-700">Date Range</label>
                    <div class="flex gap-2">
                        <input type="date" name="start_date" id="start_date"
                            class="flex-1 px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            value="<?= htmlspecialchars($startDate) ?>">
                        <input type="date" name="end_date" id="end_date"
                            class="flex-1 px-3 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            value="<?= htmlspecialchars($endDate) ?>">
                    </div>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Apply Filter
                    </button>
                    <button type="button" id="resetFilter" class="px-4 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600">
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
                    <p class="metric-value" style="color: var(--metric-value-color);">Ksh.<?= number_format($currentMonthRevenue, 2) ?></p>
                    <p class="growth-indicator <?= $revenueGrowth >= 0 ? 'growth-positive' : 'growth-negative' ?>">
                        <?= $revenueGrowth >= 0 ? '↑' : '↓' ?> <?= abs(round($revenueGrowth, 1)) ?>% from last month
                    </p>
                </div>
            </div>
            <div class="card metric-card" data-metric="orders">
                <div>
                    <h3 class="metric-title">Total Orders</h3>
                    <p class="metric-value" style="color: var(--metric-value-color);"><?= number_format($orderStats['current'] ?? 0) ?></p>
                    <p class="growth-indicator <?= $orderGrowth >= 0 ? 'growth-positive' : 'growth-negative' ?>">
                        <?= $orderGrowth >= 0 ? '↑' : '↓' ?> <?= abs(round($orderGrowth, 1)) ?>% from last month
                    </p>
                </div>
            </div>
            <div class="card metric-card" data-metric="profit">
                <div>
                    <h3 class="metric-title">Net Profit</h3>
                    <p class="metric-value" style="color: var(--metric-value-color);">Ksh.<?= number_format($profitStats['current'] ?? 0, 2) ?></p>
                    <p class="growth-indicator <?= $profitGrowth >= 0 ? 'growth-positive' : 'growth-negative' ?>">
                        <?= $profitGrowth >= 0 ? '↑' : '↓' ?> <?= abs(round($profitGrowth, 1)) ?>% from last month
                    </p>
                </div>
            </div>
            <div class="card metric-card" data-metric="customers">
                <div>
                    <h3 class="metric-title">Active Customers</h3>
                    <p class="metric-value" style="color: var(--metric-value-color);"><?= number_format($customerStats['current'] ?? 0) ?></p>
                    <p class="growth-indicator <?= $customerGrowth >= 0 ? 'growth-positive' : 'growth-negative' ?>">
                        <?= $customerGrowth >= 0 ? '↑' : '↓' ?> <?= abs(round($customerGrowth, 1)) ?>% from last month
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
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
            <!-- Top Products Table -->
            <div class="space-y-4">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="table-heading">Top Products</h2>
                    <div>
                        <a href="export.php?table=topProductsTable&format=csv" class="btn btn-primary btn-sm">Export CSV</a>
                        <a href="export.php?table=topProductsTable&format=excel" class="btn btn-success btn-sm">Export Excel</a>
                        <a href="export.php?table=topProductsTable&format=pdf" class="btn btn-danger btn-sm">Export PDF</a>
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
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($product['product_name']) ?>
                                    </td>
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

            <!-- Recent Transactions Table -->
            <div class="space-y-4">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="table-heading">Recent Transactions</h2>
                    <div>
                        <a href="export.php?table=recentTransactionsTable&format=csv" class="btn btn-primary btn-sm">Export CSV</a>
                        <a href="export.php?table=recentTransactionsTable&format=excel" class="btn btn-success btn-sm">Export Excel</a>
                        <a href="export.php?table=recentTransactionsTable&format=pdf" class="btn btn-danger btn-sm">Export PDF</a>
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
                                        <td><?= htmlspecialchars($transaction['reference_id'] ?? 'N/A') ?></td>
                                        <td class="numeric-cell <?= ($transaction['transaction_type'] ?? '') === 'IN' ? 'text-green-600' : '' ?>">
                                            <?= ($transaction['transaction_type'] ?? '') === 'IN' ? number_format($transaction['total_amount'] ?? 0, 2) : '-' ?>
                                        </td>
                                        <td class="numeric-cell <?= ($transaction['transaction_type'] ?? '') === 'OUT' ? 'text-red-600' : '' ?>">
                                            <?= ($transaction['transaction_type'] ?? '') === 'OUT' ? number_format($transaction['total_amount'] ?? 0, 2) : '-' ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?= strtolower($transaction['payment_status'] ?? 'pending') ?>">
                                                <?= htmlspecialchars($transaction['payment_status'] ?? 'Pending') ?>
                                            </span>
                                        </td>
                                        <td><?= $transaction['transaction_date'] ? date('M d, Y', strtotime($transaction['transaction_date'])) : 'N/A' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

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

        // Toggle dark mode
        function toggleDarkMode() {
            const body = document.body;
            body.classList.toggle('dark-mode');
            body.classList.toggle('light-mode');
        }
    </script>
</body>

</html>