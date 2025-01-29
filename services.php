<?php
session_start();

// Ensure the admin is logged in
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services - Zellow Enterprises</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/nav/navbar.php'; ?>
<div class="container mt-4">
    <h1>Services</h1>
    <p>This is a placeholder for Services page.</p>
</div>
</body>
<?php include 'includes/nav/footer.php'; ?>
</html>
