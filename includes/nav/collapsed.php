<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get admin info without redirecting
require_once __DIR__ . '/../../config/database.php';
$database = new Database();
$db = $database->getConnection();

// Check authentication here instead of in individual pages
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

// Start HTML output only after all logic is complete
?>
<!DOCTYPE html>
<html>
<head>
    <style>
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 3.5rem;  /* Reduced from 4rem */
            background-color: #1a202c;
            color: #a0aec0;
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 1000;
            padding: 1rem 0;
            transition: width 0.3s ease;
        }

        .sidebar:hover {
            width: 14rem;  /* Reduced from 16rem */
        }

        .sidebar a {
            width: 100%;
            height: 2.25rem;  /* Reduced from 2.5rem */
            display: flex;
            align-items: center;
            padding: 0 1rem;
            margin: 0.25rem 0;
            color: inherit;
            text-decoration: none;
            transition: all 0.2s ease, background-color 0.2s ease;
            position: relative;
            white-space: nowrap;
            font-size: 0.875rem;  /* Match table text size */
        }

        .nav-text {
            margin-left: 0.75rem;  /* Reduced from 1rem */
            opacity: 0;
            transition: opacity 0.2s ease;
            font-size: 0.875rem;  /* Match table text size */
            color: #a0aec0;  /* Default color */
            display: none;  /* Hide by default */
        }

        .sidebar:hover .nav-text {
            opacity: 0.75;  /* Show text at reduced opacity when sidebar is hovered */
            transition: opacity 0.2s ease;
            display: block;  /* Show when sidebar is hovered */
        }

        .sidebar a:hover .nav-text {
            opacity: 1;     /* Full opacity when specific link is hovered */
            color: #fff;    /* Brighter color on specific hover */
            transition: opacity 0.2s ease;
        }

        .sidebar svg {
            width: 1.15rem;  /* Reduced from 1.25rem */
            height: 1.15rem;  /* Reduced from 1.25rem */
            stroke-width: 1.5;
            min-width: 1.15rem;
        }

        /* Adjust spacing for main navigation */
        .main-nav {
            flex: 1;
            width: 100%;
            display: flex;
            flex-direction: column;
            padding: 1rem 0;
        }

        /* Bottom section styling */
        .bottom-nav {
            width: 100%;
            padding: 1rem 0;
            border-top: 1px solid #4a5568;
        }

        /* Update profile container */
        .sidebar-profile-container {
            width: 100%;
            padding: 0.5rem 1rem;
            display: flex;
            align-items: center;
        }

        .profile-info {
            margin-left: 1rem;
            opacity: 0;
            transition: opacity 0.2s ease;
            white-space: nowrap;
            color: #a0aec0;
            display: none;  /* Hide by default */
        }

        .sidebar:hover .profile-info {
            opacity: 0.75;
            display: block;  /* Show when sidebar is hovered */
        }

        .sidebar-profile-container:hover .profile-info {
            opacity: 1;
            color: #fff;
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

        .sidebar .profile-photo {
            width: 28px;  /* Reduced from 35px to match icon size */
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-color);
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
            margin: 0.25rem 0;
        }

        .profile-initial {
            width: 28px;  /* Match new profile photo size */
            height: 28px;
            font-size: 14px;  /* Reduced from 16px */
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--primary-accent);
            color: white;
            font-weight: 600;
            border: 2px solid var(--border-color);
        }

        /* Active state highlighting */
        .sidebar a.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
        }

        .sidebar a.active svg {
            color: #667eea;  /* Highlight icon color */
        }

        /* Remove the permanent opacity for active nav-text */
        .sidebar a.active .nav-text {
            opacity: 0; /* Start hidden like other nav items */
            color: #fff;
             /* Hide by default even when active */
        }

        /* Show text only when sidebar is hovered, regardless of active state */
        .sidebar:hover .nav-text {
            opacity: 0.75;
            display: block;
        }

        .sidebar:hover a:hover .nav-text {
            opacity: 1;
        }

        .sidebar:hover a.active .nav-text {
            opacity: 1;
            color: #fff;
        }

        /* Update hover effect */
        .sidebar a:hover {
            background-color: rgba(255, 255, 255, 0.05);
            color: #fff;
        }

        /* Active state adjustments */
        .sidebar a.active .nav-text {
            color: #fff;
            opacity: 1;
            text-ease: 0.2s ease;
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
        <span class="nav-text">Dashboard</span>
    </a>

    <!-- Main Navigation -->
    <div class="main-nav">
        <a href="products.php" id="products-link" title="Products">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
            <span class="nav-text">Products</span>
        </a>
        <a href="categories.php" id="categories-link" title="Categories">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
            </svg>
            <span class="nav-text">Categories</span>
        </a>
        <a href="orders.php" id="orders-link" title="Orders">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            <span class="nav-text">Orders</span>
        </a>
        <a href="dispatch.php" id="dispatch-link" title="Dispatch">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
            </svg>
            <span class="nav-text">Dispatch</span>
        </a>
        <a href="inventory.php" id="inventory-link" title="Inventory">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
            <span class="nav-text">Inventory</span>
        </a>
        <a href="customers.php" id="customers-link" title="Customers">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
            </svg>
            <span class="nav-text">Customers</span>
        </a>
        <a href="reports.php" id="reports-link" title="Reports">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <span class="nav-text">Reports</span>
        </a>
        <a href="notifications.php" id="notifications-link" title="Notifications">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <span class="nav-text">Notifications</span>
        </a>
    </div>

    <!-- Settings and Logout Section -->
    <div class="bottom-nav">
        <!-- User Profile Display -->
        <div class="sidebar-profile-container">
            <?php if (!empty($_SESSION['profile_photo'])): ?>
                <img src="<?= htmlspecialchars($_SESSION['profile_photo']) ?>" 
                     alt="Profile" 
                     class="profile-photo"
                     onerror="this.onerror=null; this.src='assets/images/default-profile.png';">
                <span class="profile-info"><?= htmlspecialchars($_SESSION['username'] ?? 'Profile') ?></span>
            <?php else: ?>
                <div class="profile-initial">
                    <?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?>
                </div>
                <span class="profile-info"><?= htmlspecialchars($_SESSION['username'] ?? 'Profile') ?></span>
            <?php endif; ?>
        </div>
        <a href="settings.php" id="settings-link">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
            <span class="nav-text">Settings</span>
        </a>
        <a href="logout.php" id="logout-link">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            <span class="nav-text">Logout</span>
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