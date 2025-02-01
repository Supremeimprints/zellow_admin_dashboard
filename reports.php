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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Business Analytics Dashboard</title>
    <link href="assets/css/reports.css"  rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <?php include 'includes/nav/collapsed.php'; ?>
    <?php include 'includes/theme.php' ?>

    <div class="container-fluid mt-4">
    <div class="dashboard-content">
        <h2 class="container mt-5">Analytics Dashboard</h2>

        <!-- Row 1: Key Metrics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <h5>Total Customers</h5>
                        <h2><?= $reportData['customer_stats']['total_customers'] ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <h5>Monthly Sales</h5>
                        <h2>Ksh.<?= number_format(end($reportData['sales_trends'])['total_sales'] ?? 0, 2) ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-danger">
                    <div class="card-body">
                        <h5>Low Stock Items</h5>
                        <h2><?= count($reportData['low_stock']) ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <h5>Avg. Orders/Customer</h5>
                        <h2><?= round($reportData['customer_stats']['avg_orders'], 1) ?></h2>
                    </div>
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

        <!-- Row 3: Tables -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5>Low Stock Alerts</h5>
                        <table class="table">
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
    </div>

    <script>
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

        // Customers Chart
        new Chart(document.getElementById('customersChart'), {
            type: 'doughnut',
            data: {
                labels: ['1 Order', '2-5 Orders', '5+ Orders'],
                datasets: [{
                    data: [/* Add actual data from DB */ 65, 24, 12],
                    backgroundColor: ['#36b9cc', '#1cc88a', '#f6c23e']
                }]
            }
        });
    </script>

</body>
<?php include 'includes/nav/footer.php'; ?>

</html>