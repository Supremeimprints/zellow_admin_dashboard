<?php
session_start();

// Ensure the admin is logged in
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

require_once 'navbar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Zellow Enterprises</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <h1>Notifications</h1>
    <p>This is a placeholder for Notifications page.</p>
</div>
</body>
</html>
