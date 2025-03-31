<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        // Sanitize input
        $name = trim($_POST['name']);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];
        $specialization = $_POST['specialization'];

        // Validate password length
        if (strlen($password) < 6) {
            throw new Exception("Password must be at least 6 characters long");
        }

        // Generate employee number
        $prefix = 'TECH';
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $randomString = '';
        for ($i = 0; $i < 8; $i++) {
            $randomString .= $characters[random_int(0, strlen($characters) - 1)];
        }
        $employeeNumber = "$prefix-$randomString";

        // Create user account - use name as username
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $userQuery = "INSERT INTO users (username, email, password, role, is_active, employee_number) 
                      VALUES (?, ?, ?, 'technician', 1, ?)";
        $stmt = $db->prepare($userQuery);
        $stmt->execute([$name, $email, $hashedPassword, $employeeNumber]);
        
        $userId = $db->lastInsertId();

        // Create technician record
        $techQuery = "INSERT INTO technicians (name, specialization, user_id, email) 
                     VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($techQuery);
        $stmt->execute([$name, $specialization, $userId, $email]);

        $db->commit();
        $_SESSION['success'] = "Technician added successfully";
        header('Location: technicians.php');
        exit();
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add New Technician</title>
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
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Add New Technician</h3>
                    </div>
                    <div class="card-body">
                        <form action="add_technician.php" method="POST">
                            <div class="mb-3">
                                <label class="form-label">Name</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                            <!-- Remove username field -->
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Password</label>
                                <input type="password" class="form-control" name="password" required minlength="6">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Specialization</label>
                                <select class="form-select" name="specialization" required>
                                    <option value="">Select Specialization</option>
                                    <option value="engraving">Engraving</option>
                                    <option value="printing">Printing</option>
                                    <option value="repair">Repair</option>
                                </select>
                            </div>
                            <div class="d-flex justify-content-between">
                                <a href="technicians.php" class="btn btn-danger">Cancel</a>
                                <button type="submit" class="btn btn-primary">Add Technician</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/nav/footer.php'; ?>
</body>
</html>
