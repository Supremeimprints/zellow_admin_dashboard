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

// Initialize variables
$action = $_GET['action'] ?? null; // Get action from query string if set

// Action handlers for adding, editing, deleting, or toggling status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add Admin
    if ($action === 'add') {
        $username = $_POST['username'];
        $username = ucwords(strtolower(trim($_POST['username'])));
        $email = $_POST['email'];
        $role = $_POST['role'];
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $password = $_POST['password'];

        if (strlen($password) < 6) {
            $error = "Password must be at least 6 characters long.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT); 

            // Get all users who are admins or managers
            $query = "
            SELECT id, role FROM users 
            WHERE role IN ('admin', 'finance_manager', 'supply_manager', 'inventory_manager', 'dispatch_manager', 'service_manager')";
            $stmt = $db->prepare($query);
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Loop through users and update the employee_number if it's missing
            foreach ($users as $user) {
            $role = $user['role'];
            $userId = $user['id'];

            // Generate employee number
           // Generate employee number similar to Safaricom transaction codes
$prefix = match ($role) {
    'admin' => 'ADM',
    'finance_manager' => 'FIN',
    'supply_manager' => 'SUP',
    'inventory_manager' => 'INV',
    'dispatch_manager' => 'DIS',
    'service_manager' => 'SER',
    default => 'ADM',
};

// Get the current timestamp in milliseconds
//$timestamp = round(microtime(true) * 1000);

// Generate a random alphanumeric string (e.g., "5GB72PLK9")
$characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
$randomString = '';
for ($i = 0; $i < 8; $i++) {
    $randomString .= $characters[random_int(0, strlen($characters) - 1)];
}

// Combine the prefix, timestamp, and random string
$newEmployeeNumber = "$prefix-$randomString";

// Update user record with generated employee number
$updateQuery = "UPDATE users SET employee_number = ? WHERE id = ?";
$stmt = $db->prepare($updateQuery);
$stmt->execute([$newEmployeeNumber, $userId]);

echo "Employee numbers have been successfully updated for all admins and managers.";

// Insert into both `users` and `admins` tables
$insertQuery = "INSERT INTO users (username, email, password, role, is_active, employee_number)
                VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $db->prepare($insertQuery);
$stmt->execute([$username, $email, $hashedPassword, $role, $isActive, $newEmployeeNumber]);

header('Location: admins.php');
exit();
}
    }
}

} // End of if ($action === 'add')

// Delete Admin
if ($action === 'delete') {
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        echo "Invalid admin ID.";
        exit();
    }
    $id = (int)$_GET['id'];

    $deleteQuery = "DELETE FROM users WHERE id = ?";
    $stmt = $db->prepare($deleteQuery);
    $stmt->execute([$id]);

    header('Location: admins.php');
    exit();
}

// Fetch only admin users
$query = "
    SELECT id, employee_number, username, email, role, is_active, created_at
    FROM users
    WHERE role IN ('admin', 'finance_manager', 'supply_manager', 'inventory_manager', 'dispatch_manager', 'service_manager')
    ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admins</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container mt-5">
    <h2>Admin Management</h2>

    <a href="add_admin.php" class="btn btn-primary mb-3">Add New Admin</a>

    <table class="table table-striped">
        <thead>
            <tr>
                <th>Employee Number</th>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Created At</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($admins as $admin): ?>
                <tr>
                    <td><?php echo htmlspecialchars($admin['employee_number']); ?></td>
                    <td><?php echo htmlspecialchars($admin['username']); ?></td>
                    <td><?php echo htmlspecialchars($admin['email']); ?></td>
                    <td><?php echo htmlspecialchars($admin['role']); ?></td>
                    <td><?php echo $admin['is_active'] ? 'Active' : 'Inactive'; ?></td>
                    <td><?php echo htmlspecialchars($admin['created_at']); ?></td>
                    <td>
                        <a href="edit_admin.php?action=edit&id=<?php echo $admin['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                        <a href="admins.php?action=delete&id=<?php echo $admin['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this admin?');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>