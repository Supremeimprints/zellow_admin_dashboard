<?php
// Check if session has already started, if not, start the session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Initialize Database connection if $admin is not set
if (!isset($admin)) {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();

    // Get admin info
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
}
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">Zellow Admin</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
            <li class="nav-item">
                    <a class="nav-link" href="index.php">Overview</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="products.php">Products</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="categories.php">Categories</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="orders.php">Orders</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="dispatch.php">Dispatch</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="inventory.php">Inventory</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="customers.php">Customers</a>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php">Reports</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="notifications.php">Notifications</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="settings.php">Settings</a>
                </li>
            
            </ul>
            <div class="d-flex">
                <span class="navbar-text me-3">
                    <?php echo htmlspecialchars($admin['email']); ?>
                </span>
                <a href="logout.php" class="btn btn-outline-light">Logout</a>
            </div>
        </div>
    </div>
</nav>
