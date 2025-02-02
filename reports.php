<?php
session_start();
require_once 'config/database.php';

// Authentication check
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get report data
$reportData = [];

// 1. Sales Trends (Last 6 Months)
$query = "SELECT 
            DATE_FORMAT(order_date, '%Y-%m') AS month,
            SUM(total_amount) AS total_sales
          FROM orders
          WHERE order_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
          GROUP BY month
          ORDER BY month DESC";
$stmt = $db->query($query);
$reportData['sales_trends'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Product Performance
$query = "SELECT 
            p.product_name,
            SUM(o.quantity) AS total_units,
            SUM(o.total_amount) AS total_revenue
          FROM orders o
          JOIN products p ON o.product_id = p.product_id
          GROUP BY p.product_name
          ORDER BY total_revenue DESC
          LIMIT 10";
$stmt = $db->query($query);
$reportData['top_products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);


// 3. Inventory Status (Corrected)
$query = "SELECT 
            p.product_name,
            i.stock_quantity,
            i.min_stock_level
          FROM inventory i
          JOIN products p ON i.product_id = p.product_id
          WHERE i.stock_quantity < i.min_stock_level";
$stmt = $db->query($query);
$reportData['low_stock'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

/// 4. Customer Metrics (Improved)
$query = "SELECT 
COUNT(*) AS total_customers,
AVG(co.order_count) AS avg_orders,
MAX(co.order_count) AS max_orders
FROM (
SELECT u.id, COUNT(o.order_id) AS order_count
FROM users u
LEFT JOIN orders o ON u.id = o.id
WHERE u.role = 'customer'
GROUP BY u.id
) AS co";
$stmt = $db->query($query);
$reportData['customer_stats'] = $stmt->fetch(PDO::FETCH_ASSOC);

// New Key Metrics & Insights
// Total Revenue and Net Profit (excluding refunded and cancelled orders)
$query = "SELECT 
            SUM(total_amount) AS total_revenue,
            (SUM(total_amount)) AS net_profit
          FROM orders
          WHERE payment_status = 'Paid' AND status NOT IN ('Cancelled', 'Refunded')";
$stmt = $db->query($query);
$reportData['financials'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Revenue Growth % (compared to last month)
$query = "SELECT 
            (SUM(IF(MONTH(order_date) = MONTH(NOW()), total_amount, 0)) - 
             SUM(IF(MONTH(order_date) = MONTH(NOW()) - 1, total_amount, 0))) / 
             SUM(IF(MONTH(order_date) = MONTH(NOW()) - 1, total_amount, 0)) * 100 AS revenue_growth
          FROM orders
          WHERE payment_status = 'Paid' AND status NOT IN ('Cancelled', 'Refunded')";
$stmt = $db->query($query);
$reportData['revenue_growth'] = $stmt->fetch(PDO::FETCH_ASSOC)['revenue_growth'];

// Peak Sales Hours
$query = "SELECT 
            HOUR(order_date) AS hour,
            COUNT(*) AS order_count
          FROM orders
          GROUP BY hour
          ORDER BY order_count DESC
          LIMIT 1";
$stmt = $db->query($query);
$reportData['peak_sales_hour'] = $stmt->fetch(PDO::FETCH_ASSOC);

// Best-Performing Product Categories
$query = "SELECT 
            c.category_name,
            SUM(o.total_amount) AS total_revenue
          FROM orders o
          JOIN products p ON o.product_id = p.product_id
          JOIN categories c ON p.category_id = c.category_id
          GROUP BY c.category_name
          ORDER BY total_revenue DESC";
$stmt = $db->query($query);
$reportData['top_categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Business Analytics Dashboard</title>
    <link href="assets/css/reports.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.0/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.0/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.1.0/js/buttons.print.min.js"></script>
    <script src="assets/js/datatables.js"></script>
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/reports.js"></script>
</head>

<body>
    <?php include 'includes/nav/collapsed.php'; ?>
    <?php include 'includes/theme.php' ?>

    <div class="container-fluid mt-4">
        <div class="dashboard-content">
            <h2 class="container mt-5">Analytics Dashboard</h2>

            <!-- Date Range Filter -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <select id="dateFilter" class="form-select">
                        <option value="month">This Month</option>
                        <option value="week">This Week</option>
                        <option value="year">This Year</option>
                        <option value="custom">Custom Date Range</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" id="dateRange" class="form-control" />
                    </div>
                </div>
                <div class="col-md-2">
                    <button id="filterBtn" class="btn btn-primary">Apply Filter</button>
                </div>
            </div>

            <!-- Row 1: Key Metrics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <h5>Total Customers</h5>
                            <h2 id="totalCustomers"><?= $reportData['customer_stats']['total_customers'] ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h5>Monthly Sales</h5>
                            <h2 id="monthlySales">
                                Ksh.<?= number_format(end($reportData['sales_trends'])['total_sales'] ?? 0, 2) ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-danger">
                        <div class="card-body">
                            <h5>Low Stock Items</h5>
                            <h2 id="lowStockItems"><?= count($reportData['low_stock']) ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <h5>Avg. Orders/Customer</h5>
                            <h2 id="avgOrdersCustomer"><?= round($reportData['customer_stats']['avg_orders'], 1) ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- New Key Metrics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <h5>Total Revenue</h5>
                            <h2 id="totalRevenue">
                                Ksh.<?= number_format($reportData['financials']['total_revenue'], 2) ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-dark">
                        <div class="card-body">
                            <h5>Net Profit</h5>
                            <h2 id="netProfit">Ksh.<?= number_format($reportData['financials']['net_profit'], 2) ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <h5>Revenue Growth</h5>
                            <h2 id="revenueGrowth"><?= round($reportData['revenue_growth'], 2) ?>%</h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 2: Main Charts -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5>Sales Trend (Last 6 Months)</h5>
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5>Top Performing Products</h5>
                            <canvas id="productsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- New Charts -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5>Income vs Expenditures</h5>
                            <canvas id="incomeExpenditureChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5>Category-Wise Revenue</h5>
                            <canvas id="categoryRevenueChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Row 3: Tables -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5>Low Stock Alerts</h5>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-secondary export-csv"
                                    data-table="lowStockTable">CSV</button>
                                <button class="btn btn-sm btn-outline-secondary export-excel"
                                    data-table="lowStockTable">Excel</button>
                                <button class="btn btn-sm btn-outline-secondary export-pdf"
                                    data-table="lowStockTable">PDF</button>
                            </div>
                            <table id="lowStockTable" class="table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Current Stock</th>
                                        <th>Minimum Required</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData['low_stock'] as $item): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($item['product_name']) ?></td>
                                            <td><?= $item['stock_quantity'] ?></td>
                                            <td><?= $item['min_stock_level'] ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5>Customer Order Distribution</h5>
                            <canvas id="customersChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- New Tables -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5>Recent Transactions</h5>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-secondary export-csv"
                                    data-table="lowStockTable">CSV</button>
                                <button class="btn btn-sm btn-outline-secondary export-excel"
                                    data-table="lowStockTable">Excel</button>
                                <button class="btn btn-sm btn-outline-secondary export-pdf"
                                    data-table="lowStockTable">PDF</button>
                            </div>
                            <table id="recentTransactionsTable" class="table">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Populate with recent transactions data -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5>Top Spending Customers</h5>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-secondary export-csv"
                                    data-table="lowStockTable">CSV</button>
                                <button class="btn btn-sm btn-outline-secondary export-excel"
                                    data-table="lowStockTable">Excel</button>
                                <button class="btn btn-sm btn-outline-secondary export-pdf"
                                    data-table="lowStockTable">PDF</button>
                            </div>
                            <table id="topCustomersTable" class="table">
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Total Spent</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Populate with top spending customers data -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function () {
            // Date Range Picker
            $('#dateRange').daterangepicker({
                locale: {
                    format: 'YYYY-MM-DD'
                }
            });

            $('#dateFilter').change(function () {
                if ($(this).val() === 'custom') {
                    $('#customDateRange').show();
                } else {
                    $('#customDateRange').hide();
                }
            });

            $('#filterBtn').click(function () {
                const startDate = $('input[name="start_date"]').val();
                const endDate = $('input[name="end_date"]').val();
                updateMetrics({
                    start: startDate,
                    end: endDate
                });
            });

            // Sales Trend Chart
            new Chart(document.getElementById('salesChart'), {
                type: 'line',
                data: {
                    labels: <?= json_encode(array_column($reportData['sales_trends'], 'month')) ?>,
                    datasets: [{
                        label: 'Monthly Sales',
                        data: <?= json_encode(array_column($reportData['sales_trends'], 'total_sales')) ?>,
                        borderColor: '#4e73df',
                        tension: 0.3
                    }]
                }
            });

            // Products Chart
            new Chart(document.getElementById('productsChart'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode(array_column($reportData['top_products'], 'product_name')) ?>,
                    datasets: [{
                        label: 'Revenue',
                        data: <?= json_encode(array_column($reportData['top_products'], 'total_revenue')) ?>,
                        backgroundColor: '#1cc88a'
                    }]
                }
            });

            // Income vs Expenditures Chart
            new Chart(document.getElementById('incomeExpenditureChart'), {
                type: 'bar',
                data: {
                    labels: ['Income', 'Expenditures'],
                    datasets: [{
                        label: 'Amount',
                        data: [<?= $reportData['financials']['total_revenue'] ?>, 0], // Assuming no expenses
                        backgroundColor: ['#4e73df', '#e74a3b']
                    }]
                }
            });

            // Category-Wise Revenue Chart
            new Chart(document.getElementById('categoryRevenueChart'), {
                type: 'pie',
                data: {
                    labels: <?= json_encode(array_column($reportData['top_categories'], 'category_name')) ?>,
                    datasets: [{
                        data: <?= json_encode(array_column($reportData['top_categories'], 'total_revenue')) ?>,
                        backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b']
                    }]
                }
            });

            // Customers Chart
            new Chart(document.getElementById('customersChart'), {
                type: 'doughnut',
                data: {
                    labels: ['1 Order', '2-5 Orders', '5+ Orders'],
                    datasets: [{
                        data: [65, 24, 12], // Example data, replace with actual data
                        backgroundColor: ['#36b9cc', '#1cc88a', '#f6c23e']
                    }]
                }
            });

            // DataTables Initialization
            $('#lowStockTable').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    'csv', 'excel', 'pdf'
                ]
            });

            $('#recentTransactionsTable').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    'csv', 'excel', 'pdf'
                ]
            });

            $('#topCustomersTable').DataTable({
                dom: 'Bfrtip',
                buttons: [
                    'csv', 'excel', 'pdf'
                ]
            });

            // Real-Time Data Updates
            setInterval(function () {
                // Fetch and update metrics via AJAX
            }, 30000);
        });
    </script>

</body>
<?php include 'includes/nav/footer.php'; ?>

</html>