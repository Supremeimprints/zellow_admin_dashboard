<?php
session_start();

// Check if user is logged in as an admin
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Fetch all service requests
$stmt = $db->prepare("SELECT sr.*, u.username, s.name AS service_name FROM service_requests sr JOIN users u ON sr.user_id = u.id JOIN services s ON sr.service_id = s.id ORDER BY sr.request_date DESC");
$stmt->execute();
$service_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Requests</title>
     <!-- Feather Icons - Add this line -->
     <script src="https://unpkg.com/feather-icons"></script>
    
    <!-- Existing stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/badges.css">
    <link rel="stylesheet" href="assets/css/orders.css">
    <link rel="stylesheet" href="assets/css/collapsed.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/orders.css" rel="stylesheet">
    <link href="assets/css/badges.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/notifications.css" rel="stylesheet">
</head>
<body>
<div class="admin-layout"> 
<?php include 'includes/theme.php'; ?>
    <nav class="navbar">
    <?php include 'includes/nav/collapsed.php'; ?>
    </nav>
<div class="container mt-5">
    <h2>Service Requests</h2>
    <a href="notifications.php" class="btn btn-secondary mb-3">Return to Notifications</a>

    <div class="notification-section">
        <?php if (!empty($service_requests)): ?>
            <?php foreach ($service_requests as $request): ?>
                <div class="card service-request-card mb-3">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="profile-photo">
                                <img src="path/to/profile/photo.jpg" alt="Profile Photo">
                            </div>
                            <div>
                                <h5 class="card-title"><?= htmlspecialchars($request['username']) ?></h5>
                                <p class="card-text"><?= htmlspecialchars($request['service_name']) ?></p>
                                <p class="card-text"><small class="text-muted"><?= htmlspecialchars($request['request_date']) ?></small></p>
                            </div>
                        </div>
                        <div>
                            <span class="badge bg-secondary"><?= htmlspecialchars($request['status']) ?></span>
                            <a href="view_service_request.php?id=<?= $request['id'] ?>" class="btn btn-primary btn-sm">View</a>
                            <a href="update_service_request.php?id=<?= $request['id'] ?>" class="btn btn-warning btn-sm">Update</a>
                            <a href="delete_service_request.php?id=<?= $request['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this request?');">Delete</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-info">No service requests found</div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php include 'includes/nav/footer.php'; ?>
</html>
