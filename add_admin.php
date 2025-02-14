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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = ucwords(strtolower(trim($_POST['username'])));
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $role = $_POST['role'];
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    // Check if password is set
    if (isset($_POST['password']) && !empty($_POST['password'])) {
        $password = $_POST['password'];
    } else {
        $error = "Password is required.";
    }

    // Validate password length
    if (isset($password) && strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    }

    if (!isset($error)) {
        // Hash the password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Set the prefix based on the role
        $prefix = match ($role) {
            'admin' => 'ADM',
            'finance_manager' => 'FIN',
            'supply_manager' => 'SUP',
            'inventory_manager' => 'INV',
            'dispatch_manager' => 'DIS',
            'service_manager' => 'SER',
            default => 'ADM',
        };

        // Fetch the highest employee number for the given prefix
        $query = "SELECT MAX(CAST(SUBSTRING(employee_number, LENGTH(?) + 2) AS UNSIGNED)) AS max_employee_number
                  FROM users 
                  WHERE employee_number LIKE ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$prefix, "$prefix%"]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // If there are no results, the table is empty for that prefix, so start at 1
        $maxEmployeeNumber = $result['max_employee_number'] ?? 0; // Default to 0 if no result
        $newEmployeeNumber = $prefix . '-' . ($maxEmployeeNumber + 1);

        // Now use $newEmployeeNumber when inserting the admin (into users table)
        $insertQuery = "INSERT INTO users (username, email, password, phone, address, role, is_active, employee_number) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $insertStmt = $db->prepare($insertQuery);

        if ($insertStmt->execute([$username, $email, $hashedPassword, $phone, $address, $role, $isActive, $newEmployeeNumber])) {
            header('Location: admins.php');
            exit();
        } else {
            $error = "Failed to add admin.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Admin</title>
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
    </style>
</head>

<body class="admin-layout">
<?php include 'includes/nav/collapsed.php'; ?>
<nav class="navbar">
    <?php include 'includes/nav/collapsed.php'; ?>
    </nav>

    <div class="container mt-5">
        <h2>Add Admin</h2>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>

            <div class="mb-3">
                <label for="phone" class="form-label">Phone</label>
                <input type="text" class="form-control" id="phone" name="phone">
            </div>

            <div class="mb-3">
                <label for="address" class="form-label">Address</label>
                <textarea class="form-control" id="address" name="address" rows="3"></textarea>
            </div>

            <div class="mb-3">
                <label for="role" class="form-label">Role</label>
                <select class="form-select" id="role" name="role" required>
                    <option value="admin">Admin</option>
                    <option value="finance_manager">Finance Manager</option>
                    <option value="supply_manager">Supply Manager</option>
                    <option value="inventory_manager">Inventory Manager</option>
                    <option value="dispatch_manager">Dispatch Manager</option>
                    <option value="service_manager">Service Manager</option>
                </select>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                <label class="form-check-label" for="is_active">
                    Active
                </label>
            </div>

            <div class="button-group">
                <button type="submit" class="btn btn-primary">Add Admin</button>
                <a href="admins.php" class="btn btn-danger">Cancel</a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php include 'includes/nav/footer.php'; ?>
</html>