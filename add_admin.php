<?php
session_start();

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        $username = ucwords(strtolower(trim($_POST['username'])));
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $role = $_POST['role'];
        $password = $_POST['password'];

        // Validate inputs
        if (strlen($password) < 6) {
            throw new Exception("Password must be at least 6 characters long");
        }

        // Check if username or email already exists
        $checkStmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $checkStmt->execute([$username, $email]);
        if ($checkStmt->fetch()) {
            throw new Exception("Username or email already exists");
        }

        // Generate employee number based on role
        $prefix = match ($role) {
            'admin' => 'ADM',
            'finance_manager' => 'FIN',
            'supply_manager' => 'SUP',
            'inventory_manager' => 'INV',
            'dispatch_manager' => 'DIS',
            'service_manager' => 'SER',
            default => 'EMP',
        };

        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $randomString = '';
        for ($i = 0; $i < 8; $i++) {
            $randomString .= $characters[random_int(0, strlen($characters) - 1)];
        }
        $employeeNumber = "$prefix-$randomString";

        // Insert new employee
        $stmt = $db->prepare("
            INSERT INTO users (
                username, 
                email, 
                password, 
                role, 
                is_active, 
                employee_number,
                phone,
                address,
                notification_enabled
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");

        $stmt->execute([
            $username,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $role,
            isset($_POST['is_active']) ? 1 : 0,
            $employeeNumber,
            $_POST['phone'] ?? null,
            $_POST['address'] ?? null
        ]);

        $db->commit();
        $success = "Employee added successfully!";
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Employee</title>
    <!-- Feather Icons - Add this line -->
    <script src="https://unpkg.com/feather-icons"></script>
    
    <!-- Existing stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/badges.css">
    <link rel="stylesheet" href="assets/css/orders.css">
    <link rel="stylesheet" href="assets/css/collapsed.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
        }
        h2, h3, h4 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
        }
        .form-label {
            font-weight: 500;
        }
        .form-control, .form-select {
            font-family: 'Montserrat', sans-serif;
        }
        .button-group {
            display: flex;
            justify-content: space-between;
        }
        
        .content-wrapper {
            padding: 1rem;
            display: flex;
            justify-content: center;
            margin-left: 78px;
            width: calc(100% - 78px);
            transition: all 0.3s ease;
        }

        .form-card {
            width: 100%;
            max-width: 800px;
            margin: 20px auto;
            background: none;
            border: none;
        }

        .card-header {
            background: none;
            border-bottom: 1px solid var(--form-border);
            padding: 1rem 0;
        }

        :root {
            --form-border: #dee2e6;
            --input-bg: #ffffff;
            --input-border: #ced4da;
        }

        [data-bs-theme="dark"] {
            --form-border: #444;
            --input-bg: #1a1d20;
            --input-border: #444;
        }

        .form-control {
            background-color: var(--input-bg);
            border-color: var(--input-border);
        }
    
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include 'includes/theme.php'; ?>
   <nav class="navbar">
   <?php include 'includes/nav/collapsed.php'; ?>
   </nav> 

    <div class="content-wrapper">
        <div class="form-card card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Add New Employee</h5>
                    <a href="admins.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <form method="POST" class="needs-validation" novalidate>
                    <div class="row g-3">
                        <!-- Existing fields -->
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>

                        <!-- New fields -->
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control" 
                                   pattern="[0-9+\-\s]+" 
                                   title="Please enter a valid phone number">
                            <div class="form-text">Optional - Enter a valid phone number</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Password</label>
                            <input type="password" name="password" class="form-control" required minlength="6">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="3" 
                                      placeholder="Optional - Enter the employee's address"></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" required>
                                <option value="">Select a role</option>
                                <option value="admin">Admin</option>
                                <option value="finance_manager">Finance Manager</option>
                                <option value="supply_manager">Supply Manager</option>
                                <option value="inventory_manager">Inventory Manager</option>
                                <option value="dispatch_manager">Dispatch Manager</option>
                                <option value="service_manager">Service Manager</option>
                            </select>
                        </div>

                        <div class="col-md-6 d-flex align-items-center">
                            <div class="form-check form-switch">
                                <input type="checkbox" 
                                       name="is_active" 
                                       class="form-check-input" 
                                       id="is_active" 
                                       checked>
                                <label class="form-check-label" for="is_active">Active Account</label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 d-flex justify-content-end gap-2">
                        <a href="admins.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Add Employee</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ... your existing scripts ... -->
</body>
</html>