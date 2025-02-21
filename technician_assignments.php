<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$technicianId = $_GET['id'] ?? null;
if (!$technicianId) {
    header('Location: technicians.php');
    exit();
}

// Fetch technician details
$query = "SELECT * FROM technicians WHERE technician_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$technicianId]);
$technician = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch assignments
$query = "SELECT a.*, o.order_id, o.gift_message, o.customization_details 
          FROM technician_assignments a
          JOIN orders o ON a.order_id = o.order_id
          WHERE a.technician_id = ?
          ORDER BY a.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$technicianId]);
$assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Technician Assignments - Zellow Admin</title>
    <!-- ...existing head content... -->
    <script src="https://unpkg.com/feather-icons"></script>

<!-- Existing stylesheets -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/badges.css">
<link rel="stylesheet" href="assets/css/orders.css">
<link rel="stylesheet" href="assets/css/collapsed.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/nav/collapsed.php'; ?>
        <?php include 'includes/theme.php'; ?>
        
        <div class="main-content">
            <div class="container-fluid">
                <h2 class="mb-4">Assignments for <?= htmlspecialchars($technician['name']) ?></h2>
                
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Assignment Type</th>
                                        <th>Details</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignments as $assignment): ?>
                                    <tr>
                                        <td>#<?= $assignment['order_id'] ?></td>
                                        <td><?= ucfirst($assignment['customization_type']) ?></td>
                                        <td><?= htmlspecialchars($assignment['customization_details']) ?></td>
                                        <td>
                                            <select class="form-select form-select-sm status-select" 
                                                    data-assignment-id="<?= $assignment['assignment_id'] ?>">
                                                <option value="pending" <?= $assignment['status'] === 'pending' ? 'selected' : '' ?>>
                                                    Pending
                                                </option>
                                                <option value="in_progress" <?= $assignment['status'] === 'in_progress' ? 'selected' : '' ?>>
                                                    In Progress
                                                </option>
                                                <option value="completed" <?= $assignment['status'] === 'completed' ? 'selected' : '' ?>>
                                                    Completed
                                                </option>
                                            </select>
                                        </td>
                                        <td><?= date('Y-m-d H:i', strtotime($assignment['created_at'])) ?></td>
                                        <td>
                                            <a href="view_order.php?id=<?= $assignment['order_id'] ?>" 
                                               class="btn btn-sm btn-info">View Order</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Handle status updates
        document.querySelectorAll('.status-select').forEach(select => {
            select.addEventListener('change', function() {
                const assignmentId = this.dataset.assignmentId;
                const newStatus = this.value;
                
                fetch('api/update_assignment_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        assignment_id: assignmentId,
                        status: newStatus
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Status updated successfully');
                    } else {
                        showError('Failed to update status');
                    }
                });
            });
        });
    </script>
</body>
</html>
