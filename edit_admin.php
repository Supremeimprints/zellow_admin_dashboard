<?php
session_start();

// Authentication check
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Validate and get the admin ID from URL parameter
if (!isset($_GET['id'])) {
    header('Location: admins.php');
    exit();
}

$edit_id = intval($_GET['id']);

// Fetch selected admin's details using prepared statement
try {
    $stmt = $db->prepare("
        SELECT users.* 
        FROM users 
        WHERE users.id = :id 
        AND users.role IN ('admin', 'finance_manager', 'supply_manager', 
                          'inventory_manager', 'dispatch_manager', 'service_manager')
    ");
    
    $stmt->bindValue(':id', $edit_id, PDO::PARAM_INT);
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$admin) {
        $_SESSION['error'] = "Admin not found";
        header('Location: admins.php');
        exit();
    }

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = "Error fetching admin details";
    header('Location: admins.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        // Get the ID from the hidden form field
        $admin_id = $_POST['id'];
        
        // Validate that we're updating the correct record
        if ($admin_id != $edit_id) {
            throw new Exception("Invalid admin ID");
        }
        
        // Sanitize inputs
        $username = trim($_POST['username']);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $role = $_POST['role'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Update the admin
        $stmt = $db->prepare("
            UPDATE users 
            SET username = :username,
                email = :email,
                phone = :phone,
                address = :address,
                role = :role,
                is_active = :is_active
            WHERE id = :id
        ");
        
        $result = $stmt->execute([
            ':username' => $username,
            ':email' => $email,
            ':phone' => $phone,
            ':address' => $address,
            ':role' => $role,
            ':is_active' => $is_active,
            ':id' => $admin_id
        ]);
        
        if (!$result) {
            throw new Exception("Failed to update admin");
        }
        
        $db->commit();
        $_SESSION['success'] = "Admin updated successfully";
        header('Location: admins.php');
        exit();
        
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
    <title>Edit Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/dispatch.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/nav/collapsed.php'; ?>
<?php include 'includes/theme.php'; ?>

    <div class="container mt-5">
        <div class="alert alert-primary" role="alert">
            <h4 class="mb-0">Edit Admin: <?= htmlspecialchars($admin['username'] ?? '') ?></h4>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="admin_id" value="<?= $admin['id'] ?? '' ?>">
            
            <div class="card mb-4">
                <div class="card-header">Admin Information</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" 
                                   value="<?= htmlspecialchars($admin['username'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($admin['email'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" 
                                   value="<?= htmlspecialchars($admin['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" class="form-control" 
                                   value="<?= htmlspecialchars($admin['address'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" required>
                                <option value="admin" <?= ($admin['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                                <option value="finance_manager" <?= ($admin['role'] ?? '') === 'finance_manager' ? 'selected' : '' ?>>Finance Manager</option>
                                <option value="supply_manager" <?= ($admin['role'] ?? '') === 'supply_manager' ? 'selected' : '' ?>>Supply Manager</option>
                                <option value="inventory_manager" <?= ($admin['role'] ?? '') === 'inventory_manager' ? 'selected' : '' ?>>Inventory Manager</option>
                                <option value="dispatch_manager" <?= ($admin['role'] ?? '') === 'dispatch_manager' ? 'selected' : '' ?>>Dispatch Manager</option>
                                <option value="service_manager" <?= ($admin['role'] ?? '') === 'service_manager' ? 'selected' : '' ?>>Service Manager</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <div class="form-check mt-2">
                                <input type="checkbox" name="is_active" class="form-check-input" 
                                       <?= ($admin['is_active'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label">Active</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="admins.php" class="btn btn-danger">Cancel</a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>