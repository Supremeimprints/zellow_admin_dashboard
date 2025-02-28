<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Fetch all technicians
$query = "SELECT * FROM technicians ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch assignments for each technician
$query = "SELECT a.*, o.order_id, o.gift_message, o.customization_details 
          FROM technician_assignments a
          JOIN orders o ON a.order_id = o.order_id
          WHERE a.technician_id = ?";
$assignmentStmt = $db->prepare($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Technicians Management - Zellow Admin</title>
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
        <div class="container mt-5">
        <div class="d-flex align-items-center mb-4">
                    <a href="index.php" class="btn btn-light me-2">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                <h2 class="mb-0">Technicians Management</h2>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Add New Technician</h5>
                            </div>
                            <div class="card-body">
                                <form action="api/add_technician.php" method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Name</label>
                                        <input type="text" class="form-control" name="name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Specialization</label>
                                        <select class="form-select" name="specialization" required>
                                            <option value="engraving">Engraving</option>
                                            <option value="printing">Printing</option>
                                            <option value="both">Both</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Add Technician</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Technicians List</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Specialization</th>
                                                <th>Active Assignments</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($technicians as $tech): 
                                                $assignmentStmt->execute([$tech['technician_id']]);
                                                $assignments = $assignmentStmt->fetchAll(PDO::FETCH_ASSOC);
                                            ?>
                                            <tr>
                                                <td><?= $tech['technician_id'] ?></td>
                                                <td><?= htmlspecialchars($tech['name']) ?></td>
                                                <td><?= ucfirst($tech['specialization']) ?></td>
                                                <td><?= count($assignments) ?></td>
                                                <td>
                                                    <a href="technician_assignments.php?id=<?= $tech['technician_id'] ?>" 
                                                       class="btn btn-sm btn-info">View Assignments</a>
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
        </div>
    </div>
</body>
</html>
