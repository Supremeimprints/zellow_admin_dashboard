<?php
session_start();
ob_start(); // Add output buffering

// Redirect if not logged in as admin
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions/badge_functions.php'; // Add this line
require_once 'includes/theme.php'; // Add this line

// Initialize variables with safe defaults
$displayName = 'Administrator';
$orderStats = [];
$customerStats = ['count' => 0];
$inventoryStats = ['total_stock' => 0, 'unique_items' => 0];
$recentOrders = [];
$notifications = [];
$totalRevenue = 0;
$lowStockItems = 0;

date_default_timezone_set('Africa/Nairobi'); // Set timezone

try {
    $database = new Database();
    $db = $database->getConnection();

    // Fetch admin info
    $adminQuery = "SELECT email FROM users WHERE id = ? AND role = 'admin' LIMIT 1";
    $adminStmt = $db->prepare($adminQuery);
    $adminStmt->execute([$_SESSION['id']]);

    if ($admin = $adminStmt->fetch(PDO::FETCH_ASSOC)) {
        function extractNameFromEmail($email)
        {
            $namePart = strstr($email, '@', true) ?: $email;
            return ucwords(str_replace(['.', '_'], ' ', $namePart));
        }
        $displayName = !empty($admin['email']) ? extractNameFromEmail($admin['email']) : 'Administrator';
    }

    // Fetch order statistics
    $orderQuery = "SELECT status, COUNT(id) as count FROM orders GROUP BY status";
    $orderStmt = $db->prepare($orderQuery);
    $orderStmt->execute();
    $orderStats = $orderStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Fetch customer statistics
    $customerQuery = "SELECT COUNT(id) as count FROM users WHERE role = 'customer' AND is_active = 1";
    $customerStmt = $db->prepare($customerQuery);
    $customerStmt->execute();
    $customerStats = $customerStmt->fetch(PDO::FETCH_ASSOC) ?: ['count' => 0];

    // Fetch inventory statistics
    $inventoryQuery = "SELECT COALESCE(SUM(stock_quantity), 0) as total_stock, COALESCE(COUNT(id), 0) as unique_items FROM inventory";
    $inventoryStmt = $db->prepare($inventoryQuery);
    $inventoryStmt->execute();
    $inventoryStats = $inventoryStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_stock' => 0, 'unique_items' => 0];
    // LOW INVENTORY STATS
    $query = "SELECT 
            p.product_name,
            i.stock_quantity,
            i.min_stock_level
          FROM inventory i
          JOIN products p ON i.product_id = p.product_id
          WHERE i.stock_quantity < i.min_stock_level";
    $stmt = $db->query($query);
    $reportData['low_stock'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Fetch recent orders
    $recentQuery = "SELECT order_id, status, order_date FROM orders ORDER BY order_date DESC LIMIT 5";
    $recentStmt = $db->prepare($recentQuery);
    $recentStmt->execute();
    $recentOrders = $recentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Fetch unread notifications
    $notificationQuery = "SELECT m.*, u.username as sender_name FROM messages m 
                          JOIN users u ON m.sender_id = u.id 
                          WHERE (m.recipient_id = ? OR m.recipient_id IS NULL) AND m.is_read = 0 
                          ORDER BY m.created_at DESC LIMIT 5";
    $notificationStmt = $db->prepare($notificationQuery);
    $notificationStmt->execute([$_SESSION['id']]);
    $notifications = $notificationStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $notificationQuery = "SELECT 
                        m.id,
                        m.sender_id,
                        u.username,
                        m.message,
                        m.created_at,
                        m.is_read
                     FROM messages m
                     JOIN users u ON m.sender_id = u.id
                     WHERE m.recipient = 'admin' 
                       AND m.is_read = 0
                     ORDER BY m.created_at DESC 
                     LIMIT 5";
    $notificationStmt = $db->prepare($notificationQuery);
    $notificationStmt->execute();
    $notifications = $notificationStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Fetch total revenue from delivered orders
    $revenueQuery = "SELECT SUM(total_price) as total_revenue FROM orders WHERE status = 'Delivered'";
    $revenueStmt = $db->prepare($revenueQuery);
    $revenueStmt->execute();
    $totalRevenue = $revenueStmt->fetchColumn() ?: 0;

    // Fetch count of low stock items
    $lowStockQuery = "SELECT COUNT(id) as low_stock FROM inventory WHERE stock_quantity < 10";
    $lowStockStmt = $db->prepare($lowStockQuery);
    $lowStockStmt->execute();
    $lowStockItems = $lowStockStmt->fetchColumn() ?: 0;

    // Fetch count of low stock notifications
    $stmt = $db->prepare("SELECT COUNT(*) AS count FROM notifications WHERE type = 'warning'");
    $stmt->execute();
    $lowStockNotifications = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $lowStockNotifications = 0; // Initialize to 0 in case of error
}

// Derived statistics
$totalOrders = array_sum(array_column($orderStats, 'count'));
$activeCustomers = $customerStats['count'];
$totalStock = $inventoryStats['total_stock'];
$uniqueItems = $inventoryStats['unique_items'];


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Zellow Enterprises</title>
    
    <!-- Feather Icons - Add this line -->
    <script src="https://unpkg.com/feather-icons"></script>
    
    <!-- Existing stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/badges.css">
    <link rel="stylesheet" href="assets/css/orders.css">
    <link rel="stylesheet" href="assets/css/collapsed.css">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>

<body>
<div class="admin-layout"> 
<?php include 'includes/theme.php'; ?>
    <nav class="navbar">
    <?php include 'includes/nav/collapsed.php'; ?>
    </nav>
        
        
            <div class="container mt-3">
                 <!-- Reduced gutter from gx-5 to g-3 -->
                    <div class="col-12">
                        <!-- Dashboard header -->
                        <div class="dashboard-header"> <!-- Reduced margin from mb-4 to mb-3 -->
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h2 class="mb-0">
                                        <span id="timeBasedGreeting"></span>,
                                        <span class="fw-light"><?= htmlspecialchars($displayName) ?></span>
                                    </h2>
                                    <p class="lead mb-0 small text-white-80">Dashboard Overview</p>
                                </div>
                                <div class="text-end">
                                    <div class="h5 mb-0"><?= date('M j, Y') ?></div>
                                    <small class="text-white-50"><?= date('H:i') ?> Local time</small>
                                </div>
                            </div>
                        </div>

                        <!-- Stats Cards -->
                        <div class="row g-3"> <!-- Reduced gap from g-4 to g-3 -->
                            <div class="col-6 col-md-3">
                                <div class="card clickable-card shadow-sm" onclick="window.location='orders.php'">
                                    <div class="card-body p-2">
                                        <div class="d-flex align-items-center">
                                            <div
                                                class="bg-primary text-white rounded-circle stats-icon me-2 d-flex align-items-center justify-content-center">
                                                <i class="fas fa-shopping-cart fa-lg"></i>
                                            </div>
                                            <div>
                                                <div class="text-muted small">Total Orders</div>
                                                <div class="stats-number"><?= $totalOrders ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-6 col-md-3">
                                <div class="card clickable-card shadow-sm" onclick="window.location='customers.php'">
                                    <div class="card-body p-2">
                                        <div class="d-flex align-items-center">
                                            <div
                                                class="bg-success text-white rounded-circle stats-icon me-2 d-flex align-items-center justify-content-center">
                                                <i class="fas fa-users fa-lg"></i>
                                            </div>
                                            <div>
                                                <div class="text-muted small">Active Customers</div>
                                                <div class="stats-number"><?= $activeCustomers ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-6 col-md-3">
                                <div class="card clickable-card shadow-sm" onclick="window.location='inventory.php'">
                                    <div class="card-body p-2">
                                        <div class="d-flex align-items-center">
                                            <div
                                                class="bg-warning text-white rounded-circle stats-icon me-2 d-flex align-items-center justify-content-center">
                                                <i class="fas fa-boxes fa-lg"></i>
                                            </div>
                                            <div>
                                                <div class="text-muted small">Total Stock</div>
                                                <div class="stats-number"><?= number_format($totalStock) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-6 col-md-3">
                                <div class="card clickable-card shadow-sm" onclick="window.location='products.php'">
                                    <div class="card-body p-2">
                                        <div class="d-flex align-items-center">
                                            <div
                                                class="bg-info text-white rounded-circle stats-icon me-2 d-flex align-items-center justify-content-center">
                                                <i class="fas fa-cube fa-lg"></i>
                                            </div>
                                            <div>
                                                <div class="text-muted small">Catalogue</div>
                                                <div class="stats-number"><?= $uniqueItems ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="card clickable-card shadow-sm" onclick="window.location='services.php'">
                                    <div class="card-body p-2">
                                        <div class="d-flex align-items-center">
                                            <div
                                                class="bg-secondary text-white rounded-circle stats-icon me-2 d-flex align-items-center justify-content-center">
                                                <i class="fas fa-concierge-bell fa-lg"></i>
                                            </div>
                                            <div>
                                                <div class="text-muted small">Manage Services</div>
                                                <div class="stats-number">Services</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="card clickable-card shadow-sm" onclick="window.location='suppliers.php'">
                                    <div class="card-body p-2">
                                        <div class="d-flex align-items-center">
                                            <div
                                                class="bg-dark text-white rounded-circle stats-icon me-2 d-flex align-items-center justify-content-center">
                                                <i class="fas fa-truck-loading fa-lg"></i>
                                            </div>
                                            <div>
                                                <div class="text-muted small">Manage Suppliers</div>
                                                <div class="stats-number">Suppliers</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="card clickable-card shadow-sm" onclick="window.location='invoices.php'">
                                    <div class="card-body p-2">
                                        <div class="d-flex align-items-center">
                                            <div
                                                class="bg-info text-white rounded-circle stats-icon me-2 d-flex align-items-center justify-content-center">
                                                <i class="fas fa-receipt fa-lg"></i>
                                            </div>
                                            <div>
                                                <div class="text-muted small">Transactions</div>
                                                <div class="stats-number">Invoices</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="card clickable-card shadow-sm" onclick="window.location='maintenance.php'">
                                    <div class="card-body p-2">
                                        <div class="d-flex align-items-center">
                                            <div
                                                class="bg-danger text-white rounded-circle stats-icon me-2 d-flex align-items-center justify-content-center">
                                                <i class="fas fa-wrench fa-lg"></i>
                                            </div>
                                            <div>
                                                <div class="text-muted small">Maintenance</div>
                                                <div class="stats-number">System</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="row g-3 my-3"> <!-- Reduced margins -->
                            <div class="col-12">
                                <div class="card shadow-sm">
                                    <div class="card-body p-2">
                                        <div class="row g-2">
                                            <div class="col-6 col-md-2">
                                                <a href="create_order.php"
                                                    class="card quick-action-card bg-primary text-white text-center py-2 clickable-card">
                                                    <div class="card-body">
                                                        <i class="fas fa-plus-circle fa-2x mb-2"></i>
                                                        <div class="small">New Order</div>
                                                    </div>
                                                </a>
                                            </div>
                                            <div class="col-6 col-md-2">
                                                <a href="inventory.php"
                                                    class="card quick-action-card bg-success text-white text-center py-2 clickable-card">
                                                    <div class="card-body">
                                                        <i class="fas fa-box-open fa-2x mb-2"></i>
                                                        <div class="small">Inventory</div>
                                                    </div>
                                                </a>
                                            </div>
                                            <div class="col-6 col-md-2">
                                                <a href="admins.php"
                                                    class="card quick-action-card bg-danger text-white text-center py-2 clickable-card">
                                                    <div class="card-body">
                                                    <i class="fas fa-user-tie fa-2x mb-2"></i>



                                                        <div class="small">Employees</div>
                                                    </div>
                                                </a>
                                            </div>
                                            <div class="col-6 col-md-2">
                                                <a href="customers.php"
                                                    class="card quick-action-card bg-info text-white text-center py-2 clickable-card">
                                                    <div class="card-body">
                                                        <i class="fas fa-users fa-2x mb-2"></i>
                                                        <div class="small">Customers</div>
                                                    </div>
                                                </a>
                                            </div>
                                            <div class="col-6 col-md-2">
                                                <a href="analytics.php"
                                                    class="card quick-action-card bg-warning text-white text-center py-2 clickable-card">
                                                    <div class="card-body">
                                                    <i class="fas fa-pie-chart fa-2x mb-2"></i>
                                                        <div class="small">Reports</div>
                                                    </div>
                                                </a>
                                            </div>
                                            <div class="col-6 col-md-2">
                                                <a href="notifications.php"
                                                    class="card quick-action-card bg-secondary text-white text-center py-2 clickable-card">
                                                    <div class="card-body">
                                                        <i class="fas fa-bell fa-2x mb-2"></i>
                                                        <div class="small">Notifications</div>
                                                    </div>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Three Column Section -->
                        <div class="row g-3"> <!-- Reduced gap -->
                            <!-- Order Status -->
                            <div class="col-md-4">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header bg-white p-2">
                                        <h6 class="mb-0 fw-bold order-status-title">Order Status</h6>
                                    </div>
                                    <div class="card-body p-2">
                                        <?php if (!empty($orderStats)): ?>
                                            <?php foreach ($orderStats as $stat): ?>
                                                <?php
                                                $statusColor = match (strtolower($stat['status'])) {
                                                    'pending' => 'order-status-pending',
                                                    'processing' => 'order-status-processing',
                                                    'shipped' => 'order-status-shipped',
                                                    'delivered' => 'order-status-delivered',
                                                    'cancelled' => 'order-status-cancelled',
                                                    default => 'order-status-pending'
                                                };
                                                $textColors = match (strtolower($stat['status'])) {
                                                    'pending' => '#874d00',     // Darker orange
                                                    'processing' => '#055160',   // Dark cyan
                                                    'shipped' => '#084298',      // Dark blue
                                                    'delivered' => '#0a3622',    // Dark green
                                                    'cancelled' => '#842029',    // Dark red
                                                    default => '#495057'         // Dark gray
                                                };
                                                ?>
                                                <div class="mb-2">
                                                    <div class="d-flex justify-content-between small mb-1">
                                                        <div>
                                                            <span class="status-indicator <?= $statusColor ?>"></span>
                                                            <span class="status-text" style="color: var(--status-text-color, <?= $textColors ?>)">
                                                                <?= htmlspecialchars($stat['status']) ?>
                                                            </span>
                                                        </div>
                                                        <div class="status-count" style="color: var(--status-text-color, <?= $textColors ?>)">
                                                            <?= htmlspecialchars($stat['count']) ?>
                                                        </div>
                                                    </div>
                                                    <div class="progress" style="height: 6px;">
                                                        <div class="progress-bar bg-<?= $statusColor ?>"
                                                            style="width: <?= ($stat['count'] / $totalOrders) * 100 ?>%">
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="text-center text-muted small py-2">No order data</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Notifications -->
                            <div class="col-md-4">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header bg-white p-2 d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0 fw-bold">Notifications</h6>
                                        <a href="notifications.php" class="btn btn-sm btn-link p-0">View All →</a>
                                    </div>
                                    <div class="card-body p-2">
                                        <?php if (!empty($notifications)): ?>
                                            <div class="notifications-list">
                                                <?php foreach ($notifications as $notification): ?>
                                                    <div class="notifications-item">
                                                        <div class="d-flex justify-content-between align-items-start">
                                                            <div class="flex-grow-1">
                                                                <span class="badge bg-<?= match ($notification['type'] ?? 'Message') {
                                                                    'Task' => 'warning',
                                                                    'Alert' => 'danger',
                                                                    'Message' => 'info',
                                                                    default => 'secondary'
                                                                } ?> mb-2">
                                                                    <?= $notification['type'] ?? 'Message' ?>
                                                                </span>
                                                                <div class="sender-name">
                                                                    <?= htmlspecialchars($notification['sender_name']) ?>
                                                                    <?php if (!$notification['is_read']): ?>
                                                                        <span class="badge bg-primary ms-2">New</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="timestamp">
                                                                    <i class="fas fa-clock me-1"></i>
                                                                    <?= time_elapsed_string($notification['created_at']) ?>
                                                                </div>
                                                            </div>
                                                            <div class="action-buttons d-flex">
                                                                <form method="GET" action="mark_read.php" class="d-flex gap-2">
                                                                    <input type="hidden" name="id" value="<?= $notification['id'] ?>">
                                                                    <button type="submit" name="mark_read" class="btn btn-sm btn-outline-secondary">
                                                                        <i class="fas fa-check"></i>
                                                                    </button>
                                                                    <button type="submit" name="delete" class="btn btn-sm btn-outline-danger">
                                                                        <i class="fas fa-trash"></i>
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-info mb-0">
                                                <i class="fas fa-info-circle me-2"></i>No new notifications
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($reportData['low_stock'])): ?>
                                            <div class="notifications-item mt-3">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-1">Low Stock Alerts</h6>
                                                        <p class="mb-0 small">You have <?= count($reportData['low_stock']) ?> low stock items</p>
                                                    </div>
                                                    <a href="inventory.php" class="btn btn-sm btn-warning">View</a>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Recent Activities -->
                            <div class="col-md-4">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header bg-white p-2 d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0 fw-bold">Recent Activities</h6>
                                        <a href="orders.php" class="btn btn-sm btn-link p-0">View All →</a>
                                    </div>
                                    <div class="card-body">
                                        <?php if (!empty($recentOrders)): ?>
                                            <?php foreach ($recentOrders as $order): ?>
                                                <div class="recent-activity-item">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <div class="fw-medium">Order #<?= htmlspecialchars($order['order_id']) ?></div>
                                                            <small class="text-muted">
                                                                <i class="fas fa-calendar me-1"></i>
                                                                <?= date('M j, H:i', strtotime($order['order_date'])) ?>
                                                            </small>
                                                        </div>
                                                        <div>
                                                            <?= renderStatusBadge($order['status'], 'order', 'sm') ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="alert alert-info mb-0">
                                                <i class="fas fa-info-circle me-2"></i>No recent activity
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        

                        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
                        <script>
                            // Initialize Feather Icons - Add this block before other scripts
                            document.addEventListener('DOMContentLoaded', function() {
                                feather.replace();
                            });
                            
                            function updateGreeting() {
                                const hour = new Date().getHours();
                                let greeting = "Welcome";
                                if (hour >= 5 && hour < 12) greeting = "Good Morning";
                                else if (hour >= 12 && hour < 18) greeting = "Good Afternoon";
                                else greeting = "Good Evening";
                                document.getElementById('timeBasedGreeting').textContent = greeting;
                            }
                            updateGreeting();
                            setInterval(updateGreeting, 60000);

                            document.querySelectorAll('.clickable-card').forEach(card => {
                                card.addEventListener('click', function (e) {
                                    if (!e.target.closest('a')) {
                                        window.location = this.dataset.href || this.querySelector('a')?.href || '#';
                                    }
                                });
                            });

                            function toggleSidebar() {
                                const sidebar = document.querySelector('.sidebar');
                                const mainWrapper = document.querySelector('.main-wrapper');
                                const body = document.querySelector('body');

                                if (window.innerWidth < 768) {
                                    sidebar.classList.toggle('show');
                                } else {
                                    body.classList.toggle('sidebar-collapsed');
                                }
                            }

                            document.addEventListener('click', function (event) {
                                const sidebar = document.querySelector('.sidebar');
                                const toggleBtn = document.querySelector('.btn-dark');

                                if (window.innerWidth < 768 &&
                                    !sidebar.contains(event.target) &&
                                    !toggleBtn.contains(event.target) &&
                                    sidebar.classList.contains('show')) {
                                    sidebar.classList.remove('show');
                                }
                            });

                            window.addEventListener('resize', function () {
                                const sidebar = document.querySelector('.sidebar');
                                if (window.innerWidth >= 768 && sidebar.classList.contains('show')) {
                                    sidebar.classList.remove('show');
                                }
                            });
                        </script>

                        <?php
                        function time_elapsed_string($datetime, $full = false)
                        {
                            $now = new DateTime;
                            $ago = new DateTime($datetime);
                            $diff = $now->diff($ago);

                            $weeks = floor($diff->d / 7);
                            $remaining_days = $diff->d % 7;

                            $string = array();

                            if ($diff->y)
                                $string[] = $diff->y . ' year' . ($diff->y > 1 ? 's' : '');
                            if ($diff->m)
                                $string[] = $diff->m . ' month' . ($diff->m > 1 ? 's' : '');
                            if ($weeks)
                                $string[] = $weeks . ' week' . ($weeks > 1 ? 's' : '');
                            if ($remaining_days)
                                $string[] = $remaining_days . ' day' . ($remaining_days > 1 ? 's' : '');
                            if ($diff->h)
                                $string[] = $diff->h . ' hour' . ($diff->h > 1 ? 's' : '');
                            if ($diff->i)
                                $string[] = $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
                            if ($diff->s)
                                $string[] = $diff->s . ' second' . ($diff->s > 1 ? 's' : '');

                            if (!$full)
                                $string = array_slice($string, 0, 1);

                            return $string ? implode(', ', $string) . ' ago' : 'just now';
                        }
                        ?>

                        
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Feather Icons - Add this block before other scripts
        document.addEventListener('DOMContentLoaded', function() {
            feather.replace();
        });
        
        function updateGreeting() {
            const hour = new Date().getHours();
            let greeting = "Welcome";
            if (hour >= 5 && hour < 12) greeting = "Good Morning";
            else if (hour >= 12 && hour < 18) greeting = "Good Afternoon";
            else greeting = "Good Evening";
            document.getElementById('timeBasedGreeting').textContent = greeting;
        }
        updateGreeting();
        setInterval(updateGreeting, 60000);

        document.querySelectorAll('.clickable-card').forEach(card => {
            card.addEventListener('click', function (e) {
                if (!e.target.closest('a')) {
                    window.location = this.dataset.href || this.querySelector('a')?.href || '#';
                }
            });
        });

        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const mainWrapper = document.querySelector('.main-wrapper');
            const body = document.querySelector('body');

            if (window.innerWidth < 768) {
                sidebar.classList.toggle('show');
            } else {
                body.classList.toggle('sidebar-collapsed');
            }
        }

        document.addEventListener('click', function (event) {
            const sidebar = document.querySelector('.sidebar');
            const toggleBtn = document.querySelector('.btn-dark');

            if (window.innerWidth < 768 &&
                !sidebar.contains(event.target) &&
                !toggleBtn.contains(event.target) &&
                sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
        });

        window.addEventListener('resize', function () {
            const sidebar = document.querySelector('.sidebar');
            if (window.innerWidth >= 768 && sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
        });
    </script>
    
</body>
<?php include 'includes/nav/footer.php'; ?>
</html>
