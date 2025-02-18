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
// Fix the for loop syntax
for ($i = 0; $i < 8; $i++) {  // Changed from i++ to $i++
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
        $_SESSION['error'] = "Invalid admin ID.";
        header('Location: admins.php');
        exit();
    }
    $id = (int)$_GET['id'];

    try {
        $db->beginTransaction();
        
        // 1. First handle messages - softly update them
        $messageQueries = [
            "UPDATE messages SET sender_deleted = 1 WHERE sender_id = ?",
            "UPDATE messages SET recipient_deleted = 1 WHERE recipient_id = ?"
        ];
        
        foreach ($messageQueries as $query) {
            $stmt = $db->prepare($query);
            $stmt->execute([$id]);
        }

        // 2. Handle notifications - softly update them
        $notificationQueries = [
            "UPDATE notifications SET is_deleted = 1 WHERE sender_id = ?",
            "UPDATE notifications SET is_deleted = 1 WHERE recipient_id = ?"
        ];

        foreach ($notificationQueries as $query) {
            $stmt = $db->prepare($query);
            $stmt->execute([$id]);
        }

        // 3. Update orders to maintain referential integrity
        $orderQuery = "UPDATE orders SET admin_id = NULL WHERE admin_id = ?";
        $stmt = $db->prepare($orderQuery);
        $stmt->execute([$id]);

        // 4. Update activity logs to maintain history
        $logsQuery = "UPDATE activity_logs SET user_id = NULL WHERE user_id = ?";
        $stmt = $db->prepare($logsQuery);
        $stmt->execute([$id]);

        // 5. Clean up any user sessions
        $sessionQuery = "DELETE FROM user_sessions WHERE user_id = ?";
        $stmt = $db->prepare($sessionQuery);
        $stmt->execute([$id]);

        // 6. Remove user permissions
        $permissionQuery = "DELETE FROM user_permissions WHERE user_id = ?";
        $stmt = $db->prepare($permissionQuery);
        $stmt->execute([$id]);

        // 7. Finally deactivate the user instead of deleting
        $deactivateQuery = "UPDATE users SET 
                            is_active = 0, 
                            status = 'inactive',
                            deactivated_at = CURRENT_TIMESTAMP,
                            deactivated_by = ?
                          WHERE id = ? AND role IN (
                            'admin', 
                            'finance_manager', 
                            'supply_manager', 
                            'inventory_manager', 
                            'dispatch_manager', 
                            'service_manager'
                          )";
        
        $stmt = $db->prepare($deactivateQuery);
        $result = $stmt->execute([$_SESSION['id'], $id]);

        if (!$result) {
            throw new PDOException("Failed to deactivate user");
        }

        $db->commit();
        $_SESSION['success'] = "Admin deactivated successfully";
        
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['error'] = "Error processing request: " . $e->getMessage();
    }
    
    header('Location: admins.php');
    exit();
}

// Fetch only admin users
$query = "
    SELECT id, employee_number, username, email, role, 
           COALESCE(is_active, 0) as is_active, 
           created_at
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
     <!-- Feather Icons - Add this line -->
     <script src="https://unpkg.com/feather-icons"></script>
    
    <!-- Existing stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/themes.css">
    <link rel="stylesheet" href="assets/css/styles.css">
   
    <link rel="stylesheet" href="assets/css/collapsed.css">
    
    <link href="assets/css/orders.css" rel="stylesheet">
    <link href="assets/css/badges.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/admins.css" rel="stylesheet">
</head>
<body>
<div class="admin-layout"> 
    <?php include 'includes/theme.php'; ?>
    <nav class="navbar">
        <?php include 'includes/nav/collapsed.php'; ?>
    </nav>

    <div class="content-wrapper">
        <!-- Add Employee Button -->
        <div class="container mt-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Staff</h2>
                <a href="add_admin.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Employee
                </a>
            </div>

            <!-- Admins Table Section -->
            <div class="table-responsive">
                <table class="table table-hover table-striped">
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
                                <td><?= htmlspecialchars($admin['employee_number'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($admin['username']) ?></td>
                                <td><?= htmlspecialchars($admin['email']) ?></td>
                                <td><?= htmlspecialchars($admin['role']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $admin['is_active'] ? 'success' : 'danger' ?>">
                                        <?= $admin['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($admin['created_at']) ?></td>
                                <td>
                                    <div class="btn-group">
                                        <?php
                                            // Debug output of admin data
                                            $debug_data = json_encode($admin);
                                            echo "<!-- Debug: Admin Data for ID {$admin['id']}: $debug_data -->";
                                        ?>
                                        <?php if ($admin['id'] !== $_SESSION['id']): ?>
                                            <?php 
                                            $isActive = (int)$admin['is_active']; // Ensure integer type
                                            $switchClass = $isActive ? 'active' : 'inactive';
                                            ?>
                                            <button type="button" 
                                                    class="btn-switch <?= $switchClass ?>" 
                                                    data-status="<?= $isActive ?>"
                                                    onclick="toggleAdminStatus(<?= $admin['id'] ?>, <?= $isActive ?>)"
                                                    title="<?= $isActive ? 'Click to deactivate' : 'Click to activate' ?>">
                                                <span class="switch-slider"></span>
                                            </button>
                                        <?php else: ?>
                                            <span class="badge bg-info">Current User</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Drivers Table Section -->
        <div class="container mt-5">
            <h2>Riders</h2>
            <div class="table-responsive">
                <table class="table table-hover table-striped">
                    <thead>
                        <tr>
                            <th>Driver ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Driver Status</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Model</th>
                            <th>Registration</th>
                            
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
                                    
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ...existing scripts and footer... -->
</body>

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

            // Add this to debug the issue
            console.log('Current admin IDs:', <?= json_encode(array_column($admins, 'id')) ?>);

            function toggleAdminStatus(adminId, currentStatus) {
                currentStatus = parseInt(currentStatus); // Ensure integer type
                const action = currentStatus ? 'deactivate' : 'activate';
                const confirmMsg = `Are you sure you want to ${action} this employee?`;
                
                if (confirm(confirmMsg)) {
                    const button = event.target.closest('.btn-switch');
                    const row = button.closest('tr');
                    button.disabled = true;
                    
                    const newStatus = !currentStatus;
                    
                    fetch('toggle_admin_status.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `admin_id=${adminId}&status=${newStatus ? 1 : 0}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update switch button
                            button.className = `btn-switch ${newStatus ? 'active' : 'inactive'}`;
                            button.setAttribute('data-status', newStatus ? '1' : '0');
                            
                            // Update status badge
                            const statusBadge = row.querySelector('.badge');
                            statusBadge.className = `badge bg-${newStatus ? 'success' : 'danger'}`;
                            statusBadge.textContent = newStatus ? 'Active' : 'Inactive';
                            
                            // Update the onclick handler with new status
                            button.onclick = () => toggleAdminStatus(adminId, newStatus);
                        } else {
                            // Revert to previous state if there's an error
                            alert(data.message || 'Error updating status');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error updating status');
                    })
                    .finally(() => {
                        button.disabled = false;
                    });
                }
            }
        </script>

        <style>
/* Switch Button Styles */
.btn-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 24px;
    background-color: #e9ecef;
    border-radius: 12px;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-switch.active {
    background-color: #0d6efd;
}

.btn-switch.inactive {
    background-color: #6c757d;
    opacity: 0.65;
}

.switch-slider {
    position: absolute;
    top: 2px;
    left: 2px;
    width: 20px;
    height: 20px;
    background-color: #fff;
    border-radius: 50%;
    transition: 0.3s;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.btn-switch.active .switch-slider {
    left: calc(100% - 22px);
}

.btn-switch:hover {
    opacity: 0.85;
}

.btn-switch:focus {
    outline: none;
    box-shadow: 0 0 0 2px rgba(13, 110, 253, 0.25);
}

/* Dark mode adjustments */
[data-bs-theme="dark"] .btn-switch {
    background-color: #495057;
}

[data-bs-theme="dark"] .btn-switch.active {
    background-color: #0d6efd;
}

[data-bs-theme="dark"] .switch-slider {
    background-color: #fff;
}
</style>

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