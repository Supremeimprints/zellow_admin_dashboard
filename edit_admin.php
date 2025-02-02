<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

// Check if user has admin access
if ($_SESSION['role'] !== 'admin') {
    echo "Access denied. You do not have permission to view this page.";
    exit();
}

// Initialize Database connection
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Check if the admin ID is provided
if (!isset($_GET['id'])) {
    echo "No admin ID specified.";
    exit();
}

$id = $_GET['id'];

// Fetch admin details
$query = "SELECT id, username, email, role, is_active FROM users WHERE id = ? AND role IN ('admin', 'finance_manager', 'supply_manager', 'inventory_manager', 'dispatch_manager', 'service_manager')";
$stmt = $db->prepare($query);
$stmt->execute([$id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    echo "Admin not found.";
    exit();
}

// Initialize variables for form submission handling
$username = $email = $role = $isActive = $hashedPassword = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Capture POST data
    $username = $_POST['username'] ?? $admin['username'];  // Default to current value if not posted
    $username = ucwords(strtolower(trim($_POST['username'])));
    $email = $_POST['email'] ?? $admin['email'];
    $role = $_POST['role'] ?? $admin['role'];
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    // Email format validation based on role
    switch ($role) {
        case 'admin':
            if (!preg_match('/^[a-zA-Z0-9._%+-]+@zellow\.com$/', $email)) {
                $error = "Invalid email format. The email must be in the format 'xyz@zellow.com' for admin roles.";
            }
            break;
        default:
            if (!preg_match('/^[a-zA-Z0-9._%+-]+@admin\.com$/', $email)) {
                $error = "Invalid email format. The email must be in the format 'xyz@admin.com' for other admin roles.";
            }
            break;
    }

    // If no error, proceed with updating the admin
    if (!isset($error)) {
        // Check if password is set for update
        $hashedPassword = (!empty($_POST['password'])) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;

        // Update query
        $updateQuery = "UPDATE users SET username = ?, email = ?, password = ?, role = ?, is_active = ? WHERE id = ?";
        $updateStmt = $db->prepare($updateQuery);

        // Bind values for update query
        if ($updateStmt->execute([$username, $email, $hashedPassword, $role, $isActive, $id])) {
            header('Location: admins.php');
            exit();
        } else {
            $error = "Failed to update admin.";
        }
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
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
        }
        .form-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }
        h2, h4 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
        }
        .form-label {
            font-weight: 500;
        }
        .form-control, .form-select {
            font-family: 'Montserrat', sans-serif;
        }
    </style>
</head>
<body>
<?php include 'includes/nav/collapsed.php'; ?>
<?php include 'includes/theme.php'; ?>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h2>Edit Admin</h2>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-section">
                    <h4 class="mb-3"><i class="bi bi-person-badge"></i> Admin Information</h4>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($admin['username']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="role" class="form-label">Role</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="admin" <?php echo $admin['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="finance_manager" <?php echo $admin['role'] === 'finance_manager' ? 'selected' : ''; ?>>Finance Manager</option>
                            <option value="supply_manager" <?php echo $admin['role'] === 'supply_manager' ? 'selected' : ''; ?>>Supply Manager</option>
                            <option value="inventory_manager" <?php echo $admin['role'] === 'inventory_manager' ? 'selected' : ''; ?>>Inventory Manager</option>
                            <option value="dispatch_manager" <?php echo $admin['role'] === 'dispatch_manager' ? 'selected' : ''; ?>>Dispatch Manager</option>
                            <option value="service_manager" <?php echo $admin['role'] === 'service_manager' ? 'selected' : ''; ?>>Service Manager</option>
                        </select>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" <?php echo $admin['is_active'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">
                            Active
                        </label>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password (Leave blank to keep current)</label>
                        <input type="password" class="form-control" id="password" name="password">
                    </div>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-save"></i> Save Changes
                    </button>
                    <a href="admins.php" class="btn btn-danger btn-lg">
                        <i class="bi bi-x-circle"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php include 'includes/nav/footer.php'; ?>
</html>
