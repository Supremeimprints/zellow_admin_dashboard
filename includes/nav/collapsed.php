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

// Get unread notifications count
$unreadQuery = "SELECT COUNT(*) FROM messages 
                WHERE (recipient_id = ? OR recipient_id IS NULL) 
                AND is_read = 0";
$stmt = $db->prepare($unreadQuery);
$stmt->execute([$_SESSION['id']]);
$unreadCount = $stmt->fetchColumn();

// Add this query for unread alerts
$alertsQuery = "SELECT COUNT(*) FROM inventory i 
                JOIN products p ON i.product_id = p.product_id 
                WHERE i.stock_quantity < i.min_stock_level";
$alertStmt = $db->query($alertsQuery);
$alertCount = $alertStmt->fetchColumn();

// Combine total unread items
$unreadCount = $unreadCount + $alertCount;
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
                <i data-feather="shopping-bag"></i>
                <span>Products</span>
            </a>
        </li>
        <li class="navbar__item">
            <a href="categories.php" class="navbar__link <?= isActive('categories.php') ?>" aria-label="Categories">
                <i data-feather="menu"></i>
                <span>Categories</span>
            </a>
        </li>
        <li class="navbar__item">
            <a href="inventory.php" class="navbar__link <?= isActive('inventory.php') ?>" aria-label="Inventory">
                <i data-feather="archive"></i>
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
            <a href="notifications.php" class="navbar__link <?= isActive('notifications.php') ?>" aria-label="Notifications">
                <div class="notification-icon-wrapper">
                    <i data-feather="bell"></i>
                    <?php if ($unreadCount > 0): ?>
                        <div class="notification-dot" data-count="<?= $unreadCount ?>"></div>
                    <?php endif; ?>
                </div>
                <span>Notifications</span>
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

<style>
.notification-icon-wrapper {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.notification-dot {
    position: absolute;
    top: -2px;
    right: -2px;
    width: 8px;
    height: 8px;
    background-color: #dc3545;
    border-radius: 50%;
    border: 2px solid var(--bs-body-bg);
    animation: pulse 2s infinite;
}

.notification-dot::after {
    content: attr(data-count);
    position: absolute;
    top: -12px;
    right: -12px;
    background-color: #dc3545;
    color: white;
    font-size: 10px;
    min-width: 16px;
    height: 16px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--bs-body-bg);
    font-weight: 600;
    transform: scale(0);
    transition: transform 0.2s ease-out;
}

.notification-icon-wrapper:hover .notification-dot::after {
    transform: scale(1);
}

@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4);
    }
    70% {
        box-shadow: 0 0 0 6px rgba(220, 53, 69, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
    }
}

[data-bs-theme="dark"] .notification-dot {
    border-color: var(--bs-dark);
}

[data-bs-theme="dark"] .notification-dot::after {
    border-color: var(--bs-dark);
}
</style>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        feather.replace();
        const currentPage = window.location.pathname;
        document.querySelectorAll('.navbar__link').forEach(link => {
            if (link.getAttribute('href') && currentPage.includes(link.getAttribute('href'))) {
                link.classList.add('active');
            }
        });

        // Initialize notification dot
        const notificationDot = document.querySelector('.notification-dot');
        if (notificationDot) {
            const count = parseInt(notificationDot.dataset.count);
            if (count > 99) {
                notificationDot.dataset.count = '99+';
            }
            
            // Add hover effect handlers
            const notificationWrapper = notificationDot.closest('.notification-icon-wrapper');
            notificationWrapper.addEventListener('mouseenter', () => {
                notificationDot.classList.add('show-count');
            });
            notificationWrapper.addEventListener('mouseleave', () => {
                notificationDot.classList.remove('show-count');
            });
        }
    });
</script>


