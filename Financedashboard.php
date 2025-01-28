<?php
// Start the session
session_start();

// Check if the user is logged in
if (!isset($_SESSION['id'])) {
    // Redirect to login page if not logged in
    header('Location: login.php');
    exit();
}

//Restrict access to specific roles
$allowed_roles = ['admin','finance_manager', 'supply_manager', 'dispatch_manager', 'service_manager', 'inventory_manager'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    echo "You do not have permission to view this page.";
    exit();
}

// Display the placeholder content
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Placeholder</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-5 text-center">
        <h1>Dashboard Under Construction</h1>
        <p>We're working hard to get this dashboard ready. Please check back later!</p>
        <a href="logout.php" class="btn btn-danger">Log Out</a>
    </div>
</body>
<?php include 'footer.php'; ?>
</html>