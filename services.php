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

$action = $_GET['action'] ?? null;
$service_id = $_GET['id'] ?? null;
$error = '';
$success = '';

// Handle service activation/deactivation
if ($action === 'toggle_status' && $service_id) {
    $stmt = $db->prepare("SELECT status FROM services WHERE id = ?");
    $stmt->execute([$service_id]);
    $service = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($service) {
        $new_status = ($service['status'] === 'active') ? 'inactive' : 'active';
        $stmt = $db->prepare("UPDATE services SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $service_id]);
        $success = "Service status updated to " . strtoupper($new_status) . "!";
    }
}

// Handle service deletion
if ($action === 'delete' && $service_id) {
    $stmt = $db->prepare("DELETE FROM services WHERE id = ?");
    if ($stmt->execute([$service_id])) {
        header('Location: services.php');
        exit();
    } else {
        $error = "Failed to delete service.";
    }
}

// Fetch all services
$stmt = $db->prepare("SELECT * FROM services");
$stmt->execute();
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.8.1/font/bootstrap-icons.min.css"
        rel="stylesheet">
    <link href="assets/css/services.css" rel="stylesheet">
</head>

<body>
    <?php include 'includes/nav/collapsed.php'; ?>
    <?php include 'includes/theme.php'; ?>

    <div class="container mt-5">
        <h2>Manage Services</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <a href="add_services.php?action=add" class="btn btn-primary mb-3">Add New Service</a>

        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Actions</th>
                    <th></th> <!-- Empty header for delete button -->
                </tr>
            </thead>
            <tbody>
                <?php foreach ($services as $service): ?>
                    <tr>
                        <td><?= htmlspecialchars($service['id']) ?></td>
                        <td><?= htmlspecialchars($service['name']) ?></td>
                        <td><?= htmlspecialchars($service['description']) ?></td>
                        <td>Ksh. <?= htmlspecialchars($service['price']) ?></td>
                        <td>
                            <span class="status-badge <?= $service['status'] === 'active' ? 'active' : 'inactive' ?>">
                                <?= ucfirst($service['status']) ?>
                            </span>
                        </td>
                        <td>
                            <a href="edit_services.php?action=edit&id=<?= $service['id'] ?>"
                                class="btn btn-warning btn-sm">Edit</a>
                            <a href="services.php?action=toggle_status&id=<?= $service['id'] ?>"
                                class="btn <?= $service['status'] === 'active' ? 'btn-danger' : 'btn-success' ?> btn-sm">
                                <?= $service['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                            </a>
                        </td>
                        <td>
                            <form method="POST" action="services.php?action=delete&id=<?= $service['id'] ?>"
                                class="d-inline"
                                onsubmit="return confirm('Are you sure you want to delete this service?');">
                                <a href="services.php?action=delete&id=<?= $service['id'] ?>" class="delete-icon">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <a href="service_requests.php" class="btn btn-secondary mt-3">Return to Service Requests</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php include 'includes/nav/footer.php'; ?>

</html>