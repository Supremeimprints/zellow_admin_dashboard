<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions/financial_functions.php';
require_once __DIR__ . '/includes/functions/badge_functions.php';

// Authentication check
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Update the date filter initialization section
$today = date('Y-m-d');
$defaultStartDate = date('Y-m-d', strtotime('-6 months'));

$startDate = isset($_GET['start_date']) && strtotime($_GET['start_date']) 
    ? date('Y-m-d', strtotime($_GET['start_date']))
    : $defaultStartDate;

$endDate = isset($_GET['end_date']) && strtotime($_GET['end_date']) 
    ? date('Y-m-d', strtotime($_GET['end_date']))
    : $today;

// Validate date range
if (strtotime($endDate) < strtotime($startDate)) {
    $startDate = $defaultStartDate;
    $endDate = $today;
}

// Get analytics data with error handling
$financialMetrics = getFinancialMetrics($db, $startDate, $endDate) ?? [
    'revenue' => 0,
    'expenses' => 0,
    'refunds' => 0,
    'net_profit' => 0,
    'profit_margin' => 0,
    'revenue_growth' => 0,
    'total_orders' => 0,
    'avg_order_value' => 0
];

$customerMetrics = getCustomerMetrics($db, $startDate, $endDate) ?? [
    'active_customers' => 0,
    'customer_growth' => 0,
    'total_orders' => 0
];

$transactionHistory = getTransactionHistory($db, $startDate, $endDate) ?? [];
$revenueData = getRevenueData($db, $startDate, $endDate) ?? [];
$topProducts = getTopProducts($db, $startDate, $endDate) ?? [];
$categoryPerformance = getCategoryPerformance($db, $startDate, $endDate) ?? [];

// Check for errors in responses
foreach ([$financialMetrics, $customerMetrics, $revenueData, $topProducts, $categoryPerformance] as $metric) {
    if (isset($metric['error']) && $metric['error'] === true) {
        error_log("Analytics error: " . ($metric['message'] ?? 'Unknown error'));
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Analytics - Zellow Admin</title>
    <script src="https://unpkg.com/feather-icons"></script>
    
   
   
   
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   
   
    <link rel="stylesheet" href="assets/css/collapsed.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/analytics.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
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
<body>
<div class="admin-layout">
    <?php include 'includes/theme.php'; ?>
    <nav class="navbar">
        <?php include 'includes/nav/collapsed.php'; ?>
    </nav>

    <div class="main-content">
        <div class="container mt-4">
            <!-- Date Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form id="dateFilterForm" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" 
                                   value="<?= htmlspecialchars($startDate) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control"
                                   value="<?= htmlspecialchars($endDate) ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">Apply Filter</button>
                            <button type="button" id="resetDates" class="btn btn-secondary">Reset</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Metric Cards -->
            <div class="row g-4 mb-4">
                <!-- Revenue Card -->
                <div class="col-md-3">
                    <div class="card h-100 metric-card revenue">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">Total Revenue</h6>
                            <h3 class="card-title mb-3">
                                Ksh <?= number_format($financialMetrics['revenue'], 2) ?>
                            </h3>
                            <p class="mb-0 <?= $financialMetrics['revenue_growth'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                <i class="fas fa-<?= $financialMetrics['revenue_growth'] >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                                <?= abs(round($financialMetrics['revenue_growth'], 1)) ?>%
                            </p>
                            <small class="text-muted">vs previous period</small>
                        </div>
                    </div>
                </div>

                <!-- Net Profit Card -->
                <div class="col-md-3">
                    <div class="card h-100 metric-card profit">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">Net Profit</h6>
                            <h3 class="card-title mb-3">
                                Ksh <?= number_format($financialMetrics['net_profit'], 2) ?>
                            </h3>
                            <p class="mb-0 <?= $financialMetrics['profit_margin'] >= 20 ? 'text-success' : 'text-warning' ?>">
                                <?= round($financialMetrics['profit_margin'], 1) ?>% margin
                            </p>
                            <small class="text-muted">after expenses & refunds</small>
                        </div>
                    </div>
                </div>

                <!-- Customers Card -->
                <div class="col-md-3">
                    <div class="card h-100 metric-card customers">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">Active Customers</h6>
                            <h3 class="card-title mb-3">
                                <?= number_format($customerMetrics['active_customers']) ?>
                            </h3>
                            <p class="mb-0 <?= $customerMetrics['customer_growth'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                <i class="fas fa-<?= $customerMetrics['customer_growth'] >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                                <?= abs(round($customerMetrics['customer_growth'], 1)) ?>%
                            </p>
                            <small class="text-muted">vs previous period</small>
                        </div>
                    </div>
                </div>

                <!-- Average Order Value Card -->
                <div class="col-md-3">
                    <div class="card h-100 metric-card avg-order">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">Avg. Order Value</h6>
                            <h3 class="card-title mb-3">
                                Ksh <?= number_format($financialMetrics['avg_order_value'], 2) ?>
                            </h3>
                            <p class="mb-0">
                                <?= number_format($financialMetrics['total_orders']) ?> orders
                            </p>
                            <small class="text-muted">in selected period</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="row g-4 mb-4">
                <!-- Revenue Chart -->
                <div class="col-md-8">
                    <div class="card h-100">
                        <div class="card-body chart-card">
                            <h5 class="chart-title">Revenue Overview</h5>
                            <div class="chart-container revenue-chart-container">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Category Performance -->
                <div class="col-md-4">
                    <div class="card h-100">
                        <div class="card-body chart-card">
                            <h5 class="chart-title">Sales by Category</h5>
                            <div class="chart-container category-chart-container">
                                <canvas id="categoryChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Replace the Transactions Table section -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Recent Transactions</h5>
                    <div class="btn-group">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportData('csv')">
                            <i class="fas fa-file-csv me-1"></i> CSV
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportData('excel')">
                            <i class="fas fa-file-excel me-1"></i> Excel
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportData('pdf')">
                            <i class="fas fa-file-pdf me-1"></i> PDF
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 transaction-list">
                            <thead>
                                <tr>
                                    <th class="border-top-0">Date</th>
                                    <th class="border-top-0">Type</th>
                                    <th class="border-top-0">Reference</th>
                                    <th class="border-top-0">Description</th>
                                    <th class="border-top-0 text-end">Amount</th>
                                    <th class="border-top-0 text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($transactionHistory) && is_array($transactionHistory)): ?>
                                    <?php foreach ($transactionHistory as $transaction): ?>
                                        <tr>
                                            <td class="align-middle">
                                                <div class="transaction-date">
                                                    <?= date('M d, Y', strtotime($transaction['transaction_date'])) ?>
                                                    <div class="text-muted small">
                                                        <?= date('h:i A', strtotime($transaction['transaction_date'])) ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="align-middle">
                                                <span class="transaction-badge" data-type="<?= strtolower($transaction['transaction_type']) ?>">
                                                    <?= htmlspecialchars($transaction['transaction_type']) ?>
                                                </span>
                                            </td>
                                            <td class="align-middle">
                                                <div class="transaction-reference">
                                                    <?= htmlspecialchars($transaction['reference_id']) ?>
                                                    <?php if (isset($transaction['order_id']) && $transaction['order_id']): ?>
                                                        <div class="text-muted small">
                                                            Order #<?= htmlspecialchars($transaction['order_id']) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="align-middle">
                                                <span class="transaction-details">
                                                    <?= htmlspecialchars($transaction['description'] ?? '-') ?>
                                                </span>
                                            </td>
                                            <td class="align-middle text-end">
                                                <div class="transaction-amount <?= ($transaction['amount'] >= 0) ? 'text-success' : 'text-danger' ?>">
                                                    <?= ($transaction['amount'] >= 0) ? '+' : '-' ?>Ksh <?= number_format(abs($transaction['amount']), 2) ?>
                                                </div>
                                            </td>
                                            <td class="align-middle text-center">
                                                <span class="transaction-badge bg-<?= getStatusBadgeClass($transaction['payment_status'] ?? 'pending', 'payment') ?>">
                                                    <?= htmlspecialchars($transaction['payment_status'] ?? 'Pending') ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">No transactions found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Chart initialization code
document.addEventListener('DOMContentLoaded', function() {
    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: <?= json_encode(array_column($revenueData, 'period') ?: []) ?>,
            datasets: [{
                label: 'Revenue',
                data: <?= json_encode(array_column($revenueData, 'revenue') ?: []) ?>,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Expenses',
                data: <?= json_encode(array_column($revenueData, 'expenses') ?: []) ?>,
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    align: 'end'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        borderDash: [2, 2]
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });

    // Category Chart
    const categoryCtx = document.getElementById('categoryChart').getContext('2d');
    const categoryData = <?= json_encode($categoryPerformance) ?>;

    new Chart(categoryCtx, {
        type: 'doughnut',
        data: {
            labels: categoryData.map(item => item.category),
            datasets: [{
                data: categoryData.map(item => item.total_sales),
                backgroundColor: [
                    '#0d6efd', '#6610f2', '#6f42c1', '#d63384', '#dc3545',
                    '#fd7e14', '#ffc107', '#198754', '#20c997', '#0dcaf0'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        boxWidth: 12,
                        padding: 15,
                        font: {
                            size: 11
                        },
                        generateLabels: function(chart) {
                            const dataset = chart.data.datasets[0];
                            const total = dataset.data.reduce((a, b) => a + b, 0);
                            return chart.data.labels.map((label, i) => ({
                                text: `${label} (${((dataset.data[i] / total) * 100).toFixed(1)}%)`,
                                fillStyle: dataset.backgroundColor[i],
                                hidden: false,
                                index: i
                            }));
                        }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const dataset = context.dataset;
                            const total = dataset.data.reduce((a, b) => a + b, 0);
                            const value = dataset.data[context.dataIndex];
                            const percentage = ((value / total) * 100).toFixed(1);
                            return `Ksh ${value.toLocaleString()} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });
});

// Date filter form handler
document.addEventListener('DOMContentLoaded', function() {
    const dateForm = document.getElementById('dateFilterForm');
    const startDate = document.querySelector('input[name="start_date"]');
    const endDate = document.querySelector('input[name="end_date"]');

    // Set max date to today for both inputs
    const today = new Date().toISOString().split('T')[0];
    startDate.max = today;
    endDate.max = today;

    // Enforce date range validity
    startDate.addEventListener('change', function() {
        endDate.min = this.value;
        if (endDate.value && endDate.value < this.value) {
            endDate.value = this.value;
        }
    });

    endDate.addEventListener('change', function() {
        startDate.max = this.value;
        if (startDate.value && startDate.value > this.value) {
            startDate.value = this.value;
        }
    });

    // Handle form submission
    dateForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const params = new URLSearchParams(formData);
        window.location.href = 'analytics.php?' + params.toString();
    });

    // Handle reset
    document.getElementById('resetDates').addEventListener('click', function() {
        startDate.value = '<?= $defaultStartDate ?>';
        endDate.value = '<?= $today ?>';
        dateForm.submit();
    });
});

// Export function
function exportData(format) {
    const params = new URLSearchParams(window.location.search);
    params.append('export', format);
    window.location.href = 'export_analytics.php?' + params.toString();
}
</script>

<?php include 'includes/nav/footer.php'; ?>
</body>
</html>