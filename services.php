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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.8.1/font/bootstrap-icons.min.css"
        rel="stylesheet">
    <link href="assets/css/services.css" rel="stylesheet">

    <!-- Add this CSS in the head section -->
    <style>
        .btn-link {
            padding: 0.25rem;
            text-decoration: none;
        }
        .btn-link:hover {
            background-color: rgba(0,0,0,0.05);
            border-radius: 4px;
        }
        .fas {
            transition: transform 0.2s;
        }
        .btn-link:hover .fas {
            transform: scale(1.1);
        }
        .description-cell {
            max-width: 300px; /* Adjust width as needed */
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        .table td {
            vertical-align: middle;
        }
    </style>
</head>

<body>
<div class="admin-layout"> 
<?php include 'includes/theme.php'; ?>
    <nav class="navbar">
    <?php include 'includes/nav/collapsed.php'; ?>
    </nav>

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
                    <th style="width: 35%">Description</th> <!-- Set fixed width -->
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
                        <td class="description-cell"><?= htmlspecialchars($service['description']) ?></td>
                        <td>Ksh. <?= htmlspecialchars($service['price']) ?></td>
                        <td>
                            <span class="status-badge <?= $service['status'] === 'active' ? 'active' : 'inactive' ?>">
                                <?= ucfirst($service['status']) ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="d-flex gap-2 justify-content-end">
                                <a href="edit_services.php?id=<?= $service['id'] ?>" 
                                   class="btn btn-sm btn-link text-primary" 
                                   title="Edit Service">
                                    <i class="fas fa-edit fs-5"></i>
                                </a>
                                
                                <button type="button" 
                                        class="btn btn-sm btn-link <?= $service['status'] === 'active' ? 'text-danger' : 'text-success' ?>"
                                        onclick="toggleServiceStatus(<?= $service['id'] ?>, '<?= $service['status'] ?>')"
                                        title="<?= $service['status'] === 'active' ? 'Deactivate' : 'Activate' ?> Service">
                                    <i class="fas <?= $service['status'] === 'active' ? 'fa-ban' : 'fa-check-circle' ?> fs-5"></i>
                                </button>
                            </div>
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
    <!-- Update the JavaScript function if needed -->
    <script>
    function toggleServiceStatus(id, currentStatus) {
        const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
        
        fetch('update_service_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `id=${encodeURIComponent(id)}&status=${encodeURIComponent(newStatus)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                console.error('Error:', data.message);
            }
        })
        .catch(error => {
            console.error('Network error:', error);
        });
    }
    </script>
</body>
<?php include 'includes/nav/footer.php'; ?>

</html>