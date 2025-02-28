<?php
session_start();

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions/order_functions.php';
require_once 'includes/functions/badge_functions.php';

$database = new Database();
$db = $database->getConnection();

// Get all service requests
$serviceRequests = getServiceRequests($db);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Requests - Technician Assignments</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/badges.css">
    <link rel="stylesheet" href="assets/css/orders.css">
    <link rel="stylesheet" href="assets/css/collapsed.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/feather-icons"></script>
</head>
<body>
<div class="admin-layout">
    <?php include 'includes/theme.php'; ?>
    <nav class="navbar">
        <?php include 'includes/nav/collapsed.php'; ?>
    </nav>
    
    <div class="main-content">
        <div class="container-fluid p-3">
            <h2>Service Requests</h2>
            
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Request ID</th>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Service Type</th>
                                    <th>Details</th>
                                    <th>Cost</th>
                                    <th>Status</th>
                                    <th>Request Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($serviceRequests as $request): ?>
                                    <tr>
                                        <td>#<?= htmlspecialchars($request['service_request_id']) ?></td>
                                        <td>
                                            <a href="update_order.php?id=<?= $request['order_id'] ?>">
                                                #<?= htmlspecialchars($request['order_id']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($request['username']) ?><br>
                                            <small class="text-muted"><?= htmlspecialchars($request['email']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge <?= $request['customization_type'] === 'engraving' ? 'bg-primary' : 'bg-info' ?>">
                                                <?= ucfirst(htmlspecialchars($request['customization_type'])) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($request['customization_details']) ?></td>
                                        <td>Ksh. <?= number_format($request['customization_cost'], 2) ?></td>
                                        <td>
                                            <select class="form-select form-select-sm status-select" 
                                                    data-request-id="<?= $request['service_request_id'] ?>">
                                                <option value="Pending" <?= $request['status'] === 'Pending' ? 'selected' : '' ?>>
                                                    Pending
                                                </option>
                                                <option value="Processing" <?= $request['status'] === 'Processing' ? 'selected' : '' ?>>
                                                    Processing
                                                </option>
                                                <option value="Completed" <?= $request['status'] === 'Completed' ? 'selected' : '' ?>>
                                                    Completed
                                                </option>
                                            </select>
                                        </td>
                                        <td><?= date('Y-m-d H:i', strtotime($request['request_date'])) ?></td>
                                        <td>
                                            <a href="view_service_request.php?id=<?= $request['service_request_id'] ?>" 
                                               class="btn btn-sm btn-primary">View Details</a>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Initialize Feather icons
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
</script>
</body>
</html>
