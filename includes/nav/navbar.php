<?php
// Check if session has already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Initialize Database connection if $admin is not set
if (!isset($admin)) {
    require_once 'config/database.php';
    $database = new Database();
    $db = $database->getConnection();

    // Get admin info
    $query = "SELECT username, profile_photo FROM users WHERE id = ? AND role = 'admin'";
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

// Generate profile display
if (!empty($admin['profile_photo'])) {
    $profile_photo = htmlspecialchars($admin['profile_photo']);
    $profile_display = "<img src=\"{$profile_photo}\" alt=\"Profile\" class=\"rounded-circle\" width=\"40\" height=\"40\">";
} else {
    $username = isset($admin['username']) ? $admin['username'] : 'A';
    $first_initial = strtoupper(substr($username, 0, 1));
    $profile_display = "<div class=\"rounded-circle d-flex align-items-center justify-content-center bg-secondary text-white\" 
                            style=\"width: 40px; height: 40px; font-size: 20px;\">{$first_initial}</div>";
}
?>

<!-- Navbar Structure -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">Zellow Admin</a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
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
                </li>
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
            
            <!-- Profile and Logout Section -->
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <div class="d-flex align-items-center">
                        <?= $profile_display ?>
                        <a href="logout.php" class="btn btn-outline-light ms-3">Logout</a>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Required Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Add active class to current page nav link
    document.addEventListener('DOMContentLoaded', function() {
        const currentPage = window.location.pathname.split('/').pop() || 'index.php';
        const navLinks = document.querySelectorAll('.nav-link');
        
        navLinks.forEach(link => {
            if (link.getAttribute('href') === currentPage) {
                link.classList.add('active');
            }
        });
    });
</script>