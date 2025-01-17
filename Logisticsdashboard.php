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
    <title>Dispatch Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <h1>Dispatch & Logistics</h1>
    <div class="alert alert-info" role="alert">
        <h4 class="alert-heading">Dashboard Under Construction</h4>
        <p>We're working hard to get this dashboard ready. Please check back later!</p>
    </div>
</div>
</body>
</html>

