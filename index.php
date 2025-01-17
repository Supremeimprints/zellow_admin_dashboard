<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['id']) || !isset($_SESSION['role'])) {
    header('Location: login.php');
    exit();
}

// Validate admin access
if ($_SESSION['role'] !== 'admin') {
    session_destroy(); 
    header('Location: login.php');
    exit();
}

// Initialize Database connection
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Fetch admin info
$query = "SELECT email FROM users WHERE id = ? AND role = 'admin'";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// If admin not found, logout
if (!$admin) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Function to extract name from email
function extractNameFromEmail($email) {
    if (empty($email)) {
        return 'Admin';
    }
    
    // First, get everything before the @ symbol
    $namePart = strstr($email, '@', true);
    
    if ($namePart === false) {
        // If no @ symbol found, use the whole string
        $namePart = $email;
    }
    
    // Replace dots and underscores with spaces
    $namePart = str_replace(['.', '_'], ' ', $namePart);
    
    // Capitalize each word
    $namePart = ucwords($namePart);
    
    return $namePart;
}

// Extract name from admin's email
$displayName = extractNameFromEmail($admin['email']);


// Fetch order stats (Pending, Shipped, Delivered)
$orderStatsQuery = "SELECT status, COUNT(id) as count FROM orders GROUP BY status";
$orderStatsStmt = $db->prepare($orderStatsQuery);
$orderStatsStmt->execute();
$orderStats = $orderStatsStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch active customer stats
$customerStatsQuery = "SELECT COUNT(id) as count FROM users WHERE role = 'customer' AND is_active = 1";
$customerStatsStmt = $db->prepare($customerStatsQuery);
$customerStatsStmt->execute();
$customerStats = $customerStatsStmt->fetch(PDO::FETCH_ASSOC);


// Fetch inventory stats (Total stock quantity and number of unique items)
$inventoryStatsQuery = "SELECT SUM(stock_quantity) as total_stock, COUNT(id) as unique_items FROM inventory";
$inventoryStatsStmt = $db->prepare($inventoryStatsQuery);
$inventoryStatsStmt->execute();
$inventoryStats = $inventoryStatsStmt->fetch(PDO::FETCH_ASSOC);

// Handle cases where inventory might be empty
$totalStock = $inventoryStats['total_stock'] ?? 0; // Total stock quantity
$uniqueItems = $inventoryStats['unique_items'] ?? 0; // Unique items in inventory

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
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --light-bg: rgb(28, 79, 130);
            --dark-text: #2b2d42;
        }

        .greeting-text {
            font-size: 2.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .user-name {
            font-weight: 300;
            color: #2b2d42;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="dashboard-header">
        <div class="container">
            <h1 class="greeting-text mb-0">
                <span id="timeBasedGreeting"></span>, 
                <span class="user-name"><?php echo htmlspecialchars($displayName); ?></span>
            </h1>
            <p class="lead">Manage your business operations efficiently</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateGreeting() {
            const hour = new Date().getHours();
            let greeting;
            
            if (hour >= 5 && hour < 12) {
                greeting = "Good Morning";
            } else if (hour >= 12 && hour < 18) {
                greeting = "Good Afternoon";
            } else {
                greeting = "Good Evening";
            }
            
            document.getElementById('timeBasedGreeting').textContent = greeting;
        }

        // Update greeting immediately
        updateGreeting();
        
        // Update greeting every minute
        setInterval(updateGreeting, 60000);
    </script>
       <div class="container">
        <div class="row g-4">
            <!-- Quick Actions Widget -->
            <div class="col-md-4">
                <div class="card quick-actions">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                        <ul class="list-unstyled">
                            <li><a href="customers.php" class="btn btn-link"><i class="fas fa-users me-2"></i>Manage Customers</a></li>
                            <li><a href="admins.php" class="btn btn-link"><i class="fas fa-user-shield me-2"></i>Manage Admins</a></li>
                            <li><a href="inventory.php" class="btn btn-link"><i class="fas fa-boxes me-2"></i>Manage Inventory</a></li>
                            <li><a href="reports.php" class="btn btn-link"><i class="fas fa-chart-bar me-2"></i>View Reports</a></li>
                            <li><a href="settings.php" class="btn btn-link"><i class="fas fa-cog me-2"></i>System Settings</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Order Stats Widget -->
            <div class="col-md-4">
                <div class="card stats-widget">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-shopping-cart me-2"></i>Order Stats</h5>
                        <ul class="list-unstyled">
                            <?php foreach ($orderStats as $stat): ?>
                                <li class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-circle me-2 text-primary"></i><?php echo htmlspecialchars($stat['status']); ?></span>
                                        <span class="stats-number"><?php echo htmlspecialchars($stat['count']); ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Customer Stats Widget -->
            <div class="col-md-4">
                <div class="card stats-widget">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-user-friends me-2"></i>Customer Stats</h5>
                        <div class="d-flex justify-content-between align-items-center">
                            <span>Active Customers</span>
                            <span class="stats-number"><?php echo htmlspecialchars($customerStats['count']); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inventory Stats Widget -->
            <div class="col-md-4">
                <div class="card stats-widget">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-warehouse me-2"></i>Inventory Stats</h5>
                        <div class="row">
                            <div class="col-4">
                                <p class="mb-1">Total Stock</p>
                                <p class="stats-number"><?php echo htmlspecialchars($totalStock); ?></p>
                            </div>
                            <div class="col-4">
                                <p class="mb-1">Unique Items</p>
                                <p class="stats-number"><?php echo htmlspecialchars($uniqueItems); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activities Widget -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-history me-2"></i>Recent Activity</h5>
                        <ul class="list-unstyled recent-activity">
                            <li>
                                <i class="fas fa-check-circle text-success status-icon"></i>
                                <strong>Order ID 101:</strong> Shipped on 2025-01-10
                            </li>
                            <li>
                                <i class="fas fa-truck text-primary status-icon"></i>
                                <strong>Order ID 102:</strong> Delivered on 2025-01-12
                            </li>
                            <li>
                                <i class="fas fa-clock text-warning status-icon"></i>
                                <strong>Order ID 103:</strong> Pending
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
