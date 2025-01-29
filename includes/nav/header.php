<?php
require_once 'config/constants.php'; // Load settings

$theme = THEME; // Get saved theme from database
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <?php if ($theme === 'dark') : ?>
        <style>
            body { background-color: #121212; color: white; }
            .container, .navbar, .card { background-color: #1e1e1e; color: white; }
            .form-control, .form-select { background-color: #333; color: white; border: 1px solid #555; }
            .btn { background-color: #007bff; color: white; }
        </style>
    <?php endif; ?>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="dashboard.php"><?php echo SITE_NAME; ?></a>
    </div>
</nav>
