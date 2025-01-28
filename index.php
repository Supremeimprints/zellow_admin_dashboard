<?php
session_start();

// Redirect if not logged in as admin
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';

// Initialize variables with safe defaults
$displayName = 'Administrator';
$orderStats = [];
$customerStats = ['count' => 0];
$inventoryStats = ['total_stock' => 0, 'unique_items' => 0];
$recentOrders = [];
$notifications = [];
$totalRevenue = 0;
$lowStockItems = 0;

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
    date_default_timezone_set('Africa/Nairobi'); // Set your timezone (adjust if necessary)


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
    $inventoryQuery = "SELECT 
                        COALESCE(SUM(stock_quantity), 0) as total_stock,
                        COALESCE(COUNT(id), 0) as unique_items 
                      FROM inventory";
    $inventoryStmt = $db->prepare($inventoryQuery);
    $inventoryStmt->execute();
    $inventoryStats = $inventoryStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_stock' => 0, 'unique_items' => 0];

    // Fetch recent orders
    $recentQuery = "SELECT order_id, status, order_date 
                   FROM orders 
                   ORDER BY order_date DESC 
                   LIMIT 5";
    $recentStmt = $db->prepare($recentQuery);
    $recentStmt->execute();
    $recentOrders = $recentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Fetch notifications
    $notificationQuery = "SELECT id, message, created_at FROM notifications 
                         ORDER BY created_at DESC LIMIT 5";
    $notificationStmt = $db->prepare($notificationQuery);
    $notificationStmt->execute();
    $notifications = $notificationStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Fetch additional stats
    $revenueQuery = "SELECT SUM(total_price) as total_revenue FROM orders 
                    WHERE status = 'Delivered'";
    $revenueStmt = $db->prepare($revenueQuery);
    $revenueStmt->execute();
    $totalRevenue = $revenueStmt->fetchColumn() ?: 0;

    $lowStockQuery = "SELECT COUNT(id) as low_stock FROM inventory 
                     WHERE stock_quantity < 10";
    $lowStockStmt = $db->prepare($lowStockQuery);
    $lowStockStmt->execute();
    $lowStockItems = $lowStockStmt->fetchColumn() ?: 0;

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
}

// Calculate derived values
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .dashboard-header {
            background: linear-gradient(135deg,rgb(11, 121, 231),rgb(25, 21, 91));
            color: white;
            padding: 1rem 0;
            margin-bottom: 1rem;
        }

        .clickable-card {
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .clickable-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
        }

        .stats-icon {
            width: 40px;
            height: 40px;
            padding: 0.5rem;
        }

        .status-indicator {
            width: 10px;
            height: 10px;
            margin-right: 8px;
        }

        .quick-action-card {
            height: 100%;
            min-height: 100px;
        }

        .notification-badge {
            font-size: 0.75rem;
            padding: 0.25em 0.6em;
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="dashboard-header">
        <div class="container">
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
    </div>

    <div class="container">
        <div class="row g-3 mb-3">
            <!-- Stats Cards -->
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
                                <div class="stats-number"><?= $totalStock ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-6 col-md-3">
                <div class="card clickable-card shadow-sm" onclick="window.location='reports.php'">
                    <div class="card-body p-2">
                        <div class="d-flex align-items-center">
                            <div
                                class="bg-info text-white rounded-circle stats-icon me-2 d-flex align-items-center justify-content-center">
                                <i class="fas fa-cube fa-lg"></i>
                            </div>
                            <div>
                                <div class="text-muted small">Unique Items</div>
                                <div class="stats-number"><?= $uniqueItems ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row g-3 mb-3">
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
                                                            <i class="fas fa-users fa-2x mb-2"></i>
                                                            <div class="small">Admins</div>
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
                                                    <a href="reports.php"
                                                        class="card quick-action-card bg-warning text-dark text-center py-2 clickable-card">
                                                        <div class="card-body">
                                                            <i class="fas fa-chart-bar fa-2x mb-2"></i>
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

                            <div class="row g-3">
                                <!-- Order Status -->
                                <div class="col-md-4">
                                    <div class="card shadow-sm h-100">
                                        <div class="card-header bg-white p-2">
                                            <h6 class="mb-0 fw-bold">Order Status</h6>
                                        </div>
                                        <div class="card-body p-2">
                                            <?php if (!empty($orderStats)): ?>
                                                <?php foreach ($orderStats as $stat): ?>
                                                    <?php
                                                    $statusColor = match ($stat['status']) {
                                                        'Pending' => 'warning',
                                                        'Shipped' => 'primary',
                                                        'Delivered' => 'success',
                                                        default => 'secondary'
                                                    }; ?>
                                                    <div class="mb-2">
                                                        <div class="d-flex justify-content-between small mb-1">
                                                            <div>
                                                                <span class="status-indicator bg-<?= $statusColor ?>"></span>
                                                                <?= htmlspecialchars($stat['status']) ?>
                                                            </div>
                                                            <div><?= htmlspecialchars($stat['count']) ?></div>
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
                                        <div
                                            class="card-header bg-white p-2 d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0 fw-bold">Notifications</h6>
                                            <a href="notifications.php" class="btn btn-sm btn-link p-0">View All →</a>
                                        </div>
                                        <div class="card-body p-2">
                                            <?php if (!empty($notifications)): ?>
                                                <div class="list-group list-group-flush">
                                                    <?php foreach ($notifications as $notification): ?>
                                                        <a href="notification.php?id=<?= $notification['id'] ?>"
                                                            class="list-group-item list-group-item-action d-flex justify-content-between align-items-center p-2">
                                                            <div class="text-truncate" style="max-width: 80%">
                                                                <?= htmlspecialchars($notification['message']) ?>
                                                            </div>
                                                            <small class="text-muted notification-badge">
                                                                <?= time_elapsed_string($notification['created_at']) ?>
                                                            </small>
                                                        </a>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="alert alert-info mb-0 p-2 small">
                                                    <i class="fas fa-info-circle me-2"></i>
                                                    No new notifications
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Recent Activities -->
                                <div class="col-md-4">
                                    <div class="card shadow-sm h-100">
                                        <div
                                            class="card-header bg-white p-2 d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0 fw-bold">Recent Activities</h6>
                                            <a href="orders.php" class="btn btn-sm btn-link p-0">View All →</a>
                                        </div>
                                        <div class="card-body p-2">
                                            <?php if (!empty($recentOrders)): ?>
                                                <?php foreach ($recentOrders as $order): ?>
                                                    <?php
                                                    $statusColor = match ($order['status']) {
                                                        'Pending' => 'warning',
                                                        'Shipped' => 'primary',
                                                        'Delivered' => 'success',
                                                        default => 'secondary'
                                                    }; ?>
                                                    <div class="recent-activity-item border-<?= $statusColor ?>">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <div class="small fw-medium">
                                                                    #<?= htmlspecialchars($order['order_id']) ?>
                                                                </div>
                                                                <small class="text-muted">
                                                                    <?= date('M j, H:i', strtotime($order['order_date'])) ?>
                                                                </small>
                                                            </div>
                                                            <span class="badge bg-<?= $statusColor ?> small">
                                                                <?= htmlspecialchars($order['status']) ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <div class="text-center text-muted small py-2">No recent activity</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <script
                            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
                        <script>
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

                            // Clickable card handler
                            document.querySelectorAll('.clickable-card').forEach(card => {
                                card.addEventListener('click', function (e) {
                                    if (!e.target.closest('a')) {
                                        window.location = this.dataset.href || this.querySelector('a')?.href || '#';
                                    }
                                });
                            });
                        </script>

                        <?php
                        function time_elapsed_string($datetime, $full = false)
                        {
                            $now = new DateTime;
                            $ago = new DateTime($datetime);
                            $diff = $now->diff($ago);

                            $weeks = floor($diff->d / 7);
                            $days = $diff->d - $weeks * 7;

                            $string = [
                                'y' => 'year',
                                'm' => 'month',
                                'w' => $weeks > 0 ? $weeks . ' week' . ($weeks > 1 ? 's' : '') : null,
                                'd' => $days > 0 ? $days . ' day' . ($days > 1 ? 's' : '') : null,
                                'h' => 'hour',
                                'i' => 'minute',
                                's' => 'second',
                            ];

                            foreach ($string as $k => &$v) {
                                if ($diff->$k) {
                                    $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
                                } else {
                                    unset($string[$k]);
                                }
                            }

                            if (!$full)
                                $string = array_slice($string, 0, 1);
                            return $string ? implode(', ', $string) . ' ago' : 'just now';
                        }
                        ?>
</body>

</html>