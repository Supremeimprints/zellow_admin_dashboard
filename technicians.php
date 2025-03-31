<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_technician') {
        try {
            $db->beginTransaction();

            // Sanitize input
            $username = ucwords(strtolower(trim($_POST['username'])));
            $name = trim($_POST['name']);
            $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
            $password = $_POST['password'];
            $specialization = $_POST['specialization'];

            // Validate password length
            if (strlen($password) < 6) {
                throw new Exception("Password must be at least 6 characters long");
            }

            // Generate unique employee number
            $employeeNumber = "TECH-" . strtoupper(substr(md5(uniqid()), 0, 8));

            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Insert into users table
            $userQuery = "INSERT INTO users (username, email, password, role, is_active, employee_number) 
                          VALUES (?, ?, ?, 'technician', 1, ?)";
            $stmt = $db->prepare($userQuery);
            $stmt->execute([$username, $email, $hashedPassword, $employeeNumber]);
            $userId = $db->lastInsertId();

            // Insert into technicians table
            $techQuery = "INSERT INTO technicians (name, specialization, user_id, email) 
                          VALUES (?, ?, ?, ?)";
            $stmt = $db->prepare($techQuery);
            $stmt->execute([$name, $specialization, $userId, $email]);

            $db->commit();
            $_SESSION['success'] = "Technician added successfully";
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: technicians.php');
        exit();
    }
}

// Fetch technicians with assignments
$techQuery = "
    SELECT t.*, u.email, u.username, u.employee_number, u.is_active,
           (SELECT COUNT(*) 
            FROM technician_assignments 
            WHERE technician_id = t.technician_id 
            AND status = 'in_progress') as active_jobs
    FROM technicians t
    LEFT JOIN users u ON t.user_id = u.id
    ORDER BY t.technician_id DESC";
$stmt = $db->prepare($techQuery);
$stmt->execute();
$technicians = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Technicians</title>
    
    <!-- Bootstrap CSS (Ensure only one instance) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom Styles -->
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
        
        <div class="content-wrapper">
            <div class="container mt-4">
                <!-- Flash Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Manage Technicians</h2>
                    <a href="add_technician.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Technician
                    </a>
                </div>

                <!-- Technicians Table -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Employee ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Specialization</th>
                                        <th>Status</th>
                                        <th>Active Jobs</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($technicians as $tech): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($tech['employee_number']) ?></td>
                                            <td><?= htmlspecialchars($tech['name']) ?></td>
                                            <td><?= htmlspecialchars($tech['email']) ?></td>
                                            <td><?= htmlspecialchars($tech['specialization']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $tech['is_active'] ? 'success' : 'danger' ?>">
                                                    <?= $tech['is_active'] ? 'Active' : 'Inactive' ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?= $tech['active_jobs'] ?> jobs
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-primary" onclick="editTechnician(<?= $tech['technician_id'] ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
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

    <!-- Bootstrap JS (Required for modal functionality) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
