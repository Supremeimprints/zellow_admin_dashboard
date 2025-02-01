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

// Get driver data with vehicle information (updated query)
$driverQuery = "SELECT d.*, 
                v.vehicle_id,
                v.vehicle_type, 
                v.vehicle_model, 
                v.registration_number, 
                v.vehicle_status 
                FROM drivers d 
                LEFT JOIN vehicles v ON d.driver_id = v.driver_id
                ORDER BY d.driver_id DESC";
$driverStmt = $db->prepare($driverQuery);
$driverStmt->execute();
$drivers = $driverStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Employees</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/orders.css">
</head>
<body>
<?php include 'includes/nav/collapsed.php'; ?>
<?php include 'includes/theme.php'; ?>
<div class="container mt-5">
    <h2>Employee Management</h2>

    <a href="add_admin.php" class="btn btn-primary mb-3">Add New Employee</a>

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
 <!-- Enhanced Drivers Table -->
 <div class="container mt-5">
    <h2>Manage Drivers </h2>
        <div>
            <a href="create_driver.php" class="btn btn-primary mb-2">
                <i class="bi bi-person-plus"></i> Create New Driver
            </a>
        </div>
        <div class="table table-striped">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Driver ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Driver Status</th>
                        <th>Vehicle Type</th>
                        <th>Vehicle Status</th>
                        <th>Vehicle Model</th>
                        <th>Registration</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($drivers)): ?>
                        <tr>
                            <td colspan="10" class="text-center">No drivers available</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($drivers as $driver): ?>
                            <tr>
                                <td><?= $driver['driver_id'] ?></td>
                                <td><?= htmlspecialchars($driver['name']) ?></td>
                                <td><?= htmlspecialchars($driver['email']) ?></td>
                                <td><?= htmlspecialchars($driver['phone_number']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $driver['status'] === 'Active' ? 'success' : 'danger' ?>">
                                        <?= $driver['status'] ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($driver['vehicle_type'] ?? 'N/A') ?></td>
                                <td>
                                    <?php if ($driver['vehicle_status'] ?? false): ?>
                                        <span class="badge bg-<?= getVehicleStatusColor($driver['vehicle_status']) ?>">
                                            <?= $driver['vehicle_status'] ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($driver['vehicle_model'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($driver['registration_number'] ?? 'N/A') ?></td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-link btn-sm p-0 opacity-75" type="button"
                                            data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-three-dots-vertical fs-5"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border">
                                            <li>
                                                <a class="dropdown-item py-2 px-3"
                                                    href="edit_driver.php?driver_id=<?= $driver['driver_id'] ?>">
                                                    <i class="bi bi-pencil me-2"></i>Edit Driver
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item py-2 px-3"
                                                    href="update_vehicle.php?driver_id=<?= $driver['driver_id'] ?>">
                                                    <i class="bi bi-truck me-2"></i>Update Vehicle
                                                </a>
                                            </li>
                                            <li>
                                                <form method="POST" action="update_driver.php" class="dropdown-item p-0">
                                                    <input type="hidden" name="driver_id" value="<?= $driver['driver_id'] ?>">
                                                    <button type="submit" class="dropdown-item py-2 px-3">
                                                        <i class="bi bi-arrow-repeat me-2"></i>Driver Status
                                                    </button>
                                                </form>
                                            </li>
                                            <li>
                                                <hr class="dropdown-divider my-1">
                                            </li>
                                            <li>
                                                <form method="POST"
                                                    onsubmit="return confirm('Are you sure you want to delete this driver?');">
                                                    <input type="hidden" name="driver_id" value="<?= $driver['driver_id'] ?>">
                                                    <button type="submit" class="bi bi-trash"
                                                        name="delete_driver">Delete</button>
                                                </form>

                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
            function deleteDriver(driverId) {
                if (confirm("Are you sure you want to delete this driver?")) {
                    fetch('delete_driver_ajax.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'driver_id=' + driverId
                    })
                        .then(response => response.text())
                        .then(data => {
                            if (data.trim() === 'success') {
                                document.getElementById("row-" + driverId).remove();
                                alert("Driver deleted successfully.");
                            } else {
                                alert("Error deleting driver.");
                            }
                        });
                }
            }
        </script>

        <?php 
         function getVehicleStatusColor($status)
         {
             return match ($status) {
                 'Available' => 'success',
                 'In Use' => 'warning',
                 'Under Maintenance' => 'danger',
                 default => 'secondary'
             };
         }
         ?>
</body>
<?php include 'includes/nav/footer.php'; ?>
</html>