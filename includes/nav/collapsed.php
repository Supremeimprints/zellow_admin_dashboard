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

<!DOCTYPE html>
<html>
<head>
    <style>
        .sidebar {
            position: fixed;
            left: -2.5rem;
            top: 50%;
            transform: translateY(-50%);
            height: auto;
            width: 3.5rem;
            background-color: #1a202c;
            color: #a0aec0;
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 1000;
            border-radius: 0 15px 15px 0;
            padding: 1rem 0;
            transition: left 0.3s ease;
            box-shadow: 4px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar:hover {
            left: 0;
        }

        .sidebar a {
            width: 2.5rem;
            height: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0.5rem 0;
            border-radius: 8px;
            color: inherit;
            text-decoration: none;
            transition: all 0.2s ease;
            position: relative;
        }

        .sidebar a:hover {
            background-color: #4a5568;
            color: #e2e8f0;
            transform: translateX(3px);
        }

        .sidebar svg {
            width: 1.5rem;
            height: 1.5rem;
        }

        .sidebar-section {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            border-top: 1px solid #4a5568;
            margin-top: 0.75rem;
            padding-top: 0.75rem;
        }

        .active {
            background-color: #4a5568 !important;
            color: #e2e8f0 !important;
        }

        .hover-indicator {
            position: absolute;
            right: 0;
            width: 3px;
            height: 100%;
            background-color: #667eea;
            opacity: 0;
            transition: opacity 0.2s ease;
            border-radius: 3px;
        }

        .sidebar a:hover .hover-indicator {
            opacity: 1;
        }

        .main-content {
            margin-left: 1rem;
        }
    </style>
</head>
<body>

<div class="sidebar">
    <!-- Logo -->
    <a href="index.php" class="logo">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
        </svg>
        <span class="hover-indicator"></span>
    </a>

    <!-- Main Navigation -->
    <div class="sidebar-section">
        <a href="products.php" id="products-link" title="Products">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
            <span class="hover-indicator"></span>
        </a>
        <a href="categories.php" id="categories-link" title="Categories">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
            </svg>
            <span class="hover-indicator"></span>
        </a>
        <a href="orders.php" id="orders-link" title="Orders">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
            </svg>
            <span class="hover-indicator"></span>
        </a>
        <a href="dispatch.php" id="dispatch-link" title="Dispatch">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4" />
            </svg>
            <span class="hover-indicator"></span>
        </a>
        <a href="inventory.php" id="inventory-link" title="Inventory">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            <span class="hover-indicator"></span>
        </a>
        <a href="customers.php" id="customers-link" title="Customers">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            <span class="hover-indicator"></span>
        </a>
        <a href="reports.php" id="reports-link" title="Reports">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span class="hover-indicator"></span>
        </a>
        <a href="notifications.php" id="notifications-link" title="Notifications">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <span class="hover-indicator"></span>
        </a>
    </div>

    <!-- Settings and Logout Section -->
    <div class="sidebar-section">
        <a href="settings.php" id="settings-link" title="Settings">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span class="hover-indicator"></span>
        </a>
        <a href="logout.php" id="logout-link" title="Logout">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            <span class="hover-indicator"></span>
        </a>
    </div>
</div>

<script>
    // Get current page URL
    const currentPage = window.location.pathname;
    
    // Remove any previous active classes
    document.querySelectorAll('.sidebar a').forEach(link => {
        link.classList.remove('active');
    });

    // Add active class based on current page
    const pageMap = {
        'products': 'products-link',
        'categories': 'categories-link',
        'orders': 'orders-link',
        'dispatch': 'dispatch-link',
        'inventory': 'inventory-link',
        'customers': 'customers-link',
        'reports': 'reports-link',
        'notifications': 'notifications-link',
        'settings': 'settings-link'
    };

    // Find and activate the current page link
    Object.keys(pageMap).forEach(page => {
        if (currentPage.includes(page)) {
            document.getElementById(pageMap[page])?.classList.add('active');
        }
    });
</script>
</body>
</html>