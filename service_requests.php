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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/service_requests.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/nav/navbar.php'; ?>
<?php include 'includes/theme.php'; ?>
<div class="container mt-5">
    <h2>Service Requests</h2>

    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Customer</th>
                <th>Service</th>
                <th>Request Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($service_requests as $request): ?>
                <tr>
                    <td><?= htmlspecialchars($request['id']) ?></td>
                    <td><?= htmlspecialchars($request['username']) ?></td>
                    <td><?= htmlspecialchars($request['service_name']) ?></td>
                    <td><?= htmlspecialchars($request['request_date']) ?></td>
                    <td><?= htmlspecialchars($request['status']) ?></td>
                    <td>
                        <a href="view_service_request.php?id=<?= $request['id'] ?>" class="btn btn-primary btn-sm">View</a>
                        <a href="update_service_request.php?id=<?= $request['id'] ?>" class="btn btn-warning btn-sm">Update</a>
                        <a href="delete_service_request.php?id=<?= $request['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this request?');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php include 'includes/nav/footer.php'; ?>
</html>
