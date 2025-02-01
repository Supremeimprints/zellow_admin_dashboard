
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Panel - Zellow Enterprises</title>
    </head>
    <body>
    <div class="sidebar bg-dark text-white vh-100 position-fixed overflow-auto" style="width: 250px; z-index: 1000;">
    <div class="sidebar-header p-3">
        <h4 class="mb-0">Admin Panel</h4>
        <hr class="border-light my-2">
    </div>
    
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
