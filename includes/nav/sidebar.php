
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Panel - Zellow Enterprises</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="assets/css/sidebar.css">
    </head>
    <body>
    <div class="sidebar bg-dark text-white vh-100 position-fixed overflow-auto" style="width: 250px; z-index: 1000;">
    <div class="sidebar-header p-3">
        <h4 class="mb-0">Admin Panel</h4>
        <hr class="border-light my-2">
    </div>
    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="index.php" class="nav-link text-white active">
                    <i class="fas fa-home me-2"></i> Overview
                </a>
            </li>
            <h6 class="sidebar-heading px-3 mt-4 mb-2">
                <span class="custom-line">Catalog</span>
            </h6>


            <li class="nav-item">
                <a href="products.php" class="nav-link text-white">
                    <i class="fas fa-box me-2"></i> Products
                </a>
            </li>
            <li class="nav-item">
                <a href="categories.php" class="nav-link text-white">
                    <i class="fas fa-box me-2"></i> Categories
                </a>
            </li>
            <li class="nav-item">
                <a href="inventory.php" class="nav-link text-white">
                    <i class="fas fa-box me-2"></i> Inventory
                </a>
            </li>

            <h6 class="sidebar-heading px-3 mt-4 mb-2">
                <span class="custom-line">Sales</span>
            </h6>
            <li class="nav-item">
                <a href="orders.php" class="nav-link text-white">
                    <i class="fas fa-shopping-cart me-2"></i> Orders
                </a>
            </li>

            <li class="nav-item">
                <a href="dispatch.php" class="nav-link text-white">
                    <i class="fas fa-shopping-cart me-2"></i> Dispatch
                </a>
            </li>
            <h6 class="sidebar-heading px-3 mt-4 mb-2">
                <span class="custom-line">Users</span>
            </h6>

            <li class="nav-item">
                <a href="customers.php" class="nav-link text-white">
                    <i class="fas fa-users me-2"></i> Customers
                </a>
            </li>
            <li class="nav-item">
                <a href="admins.php" class="nav-link text-white">
                    <i class="fas fa-warehouse me-2"></i> Admins
                </a>
            </li>
            <h6 class="sidebar-heading px-3 mt-4 mb-2">
                <span class="custom-line">Analytics</span>
            </h6>
            <li class="nav-item">
                <a href="reports.php" class="nav-link text-white">
                    <i class="fas fa-chart-bar me-2"></i> Reports
                </a>
            </li>
            <h6 class="sidebar-heading px-3 mt-4 mb-2">
                <span class="custom-line">Preferences</span>
            </h6>
            <li class="nav-item">
                <a href="messages.php" class="nav-link text-white">
                    <i class="fas fa-bell me-2"></i> Notifications
                </a>
                <li class="nav-item">
                    <a href="settings.php" class="nav-link text-white">
                        <i class="fas fa-cog me-2"></i> Settings
                    </a>
                    </li>
        </ul>
    </nav>
</div>
<script>
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        sidebar.classList.toggle('mobile-open');
    }

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function (event) {
        const sidebar = document.querySelector('.sidebar');
        const sidebarToggle = document.querySelector('[onclick="toggleSidebar()"]');

        if (window.innerWidth < 768) {
            if (!sidebar.contains(event.target) && !sidebarToggle.contains(event.target)) {
                sidebar.classList.remove('mobile-open');
            }
        }
    });

    // Adjust main content margin on resize
    window.addEventListener('resize', function () {
        const sidebar = document.querySelector('.sidebar');
        if (window.innerWidth >= 768) {
            sidebar.classList.remove('mobile-open');
        }
    });
</script>
</body>
</html>
