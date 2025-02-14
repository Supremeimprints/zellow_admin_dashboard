<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set default page title if not defined
$pageTitle = $pageTitle ?? 'Zellow Enterprises Admin';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= $_COOKIE['theme'] ?? 'light' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <!-- Core CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Navigation CSS -->
    <link rel="stylesheet" href="/zellow_admin/assets/css/collapsed.css">
    
    <!-- Feather Icons -->
    <script src="https://unpkg.com/feather-icons"></script>
    
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Your custom styles -->
    <link rel="stylesheet" href="assets/css/styles.css">
    
    <!-- Optional: Configure Tailwind theme -->
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: '#406ff3',
                    }
                }
            }
        }
    </script>
</head>
<body class="admin-layout">
    <!-- Navbar -->
    <?php include 'includes/nav/navbar.php'; ?> 
    
    <!-- Mobile Menu Toggle -->
    <button class="btn btn-dark d-md-none mobile-menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/nav/sidebar.php'; ?>