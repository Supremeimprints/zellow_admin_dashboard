<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
$database = new Database();
$db = $database->getConnection();

if (!isset($_SESSION['id'])) {
    header('Location: /zellow_admin/login.php');
    exit();
}

$query = "SELECT username, profile_photo FROM users WHERE id = ? AND role = 'admin'";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// Set default values if admin not found
if (!$admin) {
    $admin = [
        'username' => 'Unknown',
        'profile_photo' => ''
    ];
}

$profile_photo = !empty($admin['profile_photo']) 
    ? $admin['profile_photo'] 
    : 'assets/images/default-avatar.png';

$current_page = basename($_SERVER['PHP_SELF']);
function isActive($page) {
    global $current_page;
    return $current_page === $page ? 'active' : '';
}
?>

<!-- Just the navbar component -->
<nav class="navbar">
    <ul class="navbar__menu">
        <li class="navbar__item">
            <a href="index.php" class="navbar__link <?= isActive('index.php') ?>" aria-label="Home">
                <i data-feather="home"></i>
                <span>Home</span>
            </a>
        </li>
        <li class="navbar__item">
            <a href="products.php" class="navbar__link <?= isActive('products.php') ?>" aria-label="Products">
                <i data-feather="package"></i>
                <span>Products</span>
            </a>
        </li>
        <li class="navbar__item">
            <a href="categories.php" class="navbar__link <?= isActive('categories.php') ?>" aria-label="Categories">
                <i data-feather="grid"></i>
                <span>Categories</span>
            </a>
        </li>
        <li class="navbar__item">
            <a href="inventory.php" class="navbar__link <?= isActive('inventory.php') ?>" aria-label="Inventory">
                <i data-feather="box"></i>
                <span>Inventory</span>
            </a>
        </li>
        <li class="navbar__item">
            <a href="orders.php" class="navbar__link <?= isActive('orders.php') ?>" aria-label="Orders">
                <i data-feather="shopping-cart"></i>
                <span>Orders</span>
            </a>
        </li>
        <li class="navbar__item">
            <a href="dispatch.php" class="navbar__link <?= isActive('dispatch.php') ?>" aria-label="Dispatch">
                <i data-feather="truck"></i>
                <span>Dispatch</span>
            </a>
        </li>
        <li class="navbar__item">
            <a href="customers.php" class="navbar__link <?= isActive('customers.php') ?>" aria-label="Customers">
                <i data-feather="users"></i>
                <span>Customers</span>
            </a>
        </li>
        <li class="navbar__item">
            <a href="promotions.php" class="navbar__link <?= isActive('promotions.php') ?>" aria-label="Promotions">
                <i data-feather="tag"></i>
                <span>Promotions</span>
            </a>
        </li>
        <li class="navbar__item">
            <a href="reports.php" class="navbar__link <?= isActive('reports.php') ?>" aria-label="Analytics">
            <i data-feather="pie-chart"></i>
                <span>Reports</span>
            </a>
        </li>
    </ul>

    <!-- Help and Settings Section -->
    <ul class="navbar__menu">
        <li class="navbar__item">
            <a href="help.php" class="navbar__link <?= isActive('help.php') ?>" aria-label="Help">
                <i data-feather="help-circle"></i>
                <span>Help</span>
            </a>
        </li>
        <li class="navbar__item">
            <a href="settings.php" class="navbar__link <?= isActive('settings.php') ?>" aria-label="Settings">
                <i data-feather="settings"></i>
                <span>Settings</span>
            </a>
        </li>
        <!-- Profile and Logout Section -->
        <div class="navbar__profile">
            <a href="profile_settings.php" class="profile-link" aria-label="Profile Settings">
                <div class="profile-pic">
                    <img src="<?= htmlspecialchars($profile_photo) ?>" 
                         alt="Profile"
                         width="24"
                         height="24"
                         loading="lazy">
                </div>
            </a>
            <a href="logout.php" class="logout-btn" aria-label="Logout">
                <i data-feather="log-out"></i>
            </a>
        </div>
    </ul>
</nav>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        feather.replace();
        const currentPage = window.location.pathname;
        document.querySelectorAll('.navbar__link').forEach(link => {
            if (link.getAttribute('href') && currentPage.includes(link.getAttribute('href'))) {
                link.classList.add('active');
            }
        });
    });
</script>


