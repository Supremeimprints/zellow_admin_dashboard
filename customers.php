<?php
session_start();
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Fetch all customers (from both tables)
$query = "
    SELECT id, username, email, is_active, created_at, 'users' as source_table 
    FROM users
    WHERE role = 'customer'
    ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle activation/deactivation
if (isset($_GET['toggle_status']) && isset($_GET['source_table'])) {
    $customer_id = $_GET['toggle_status'];

    // Validate source_table
    $valid_tables = ['customers', 'users', 'orders'];
    if (in_array($_GET['source_table'], $valid_tables)) {
        $source_table = $_GET['source_table'];
    } else {
        die('Invalid table selected');
    }

    // Update status
    $query = "UPDATE $source_table SET is_active = NOT is_active WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$customer_id]);
    header('Location: customers.php');
    exit();
}

// Handle customer deletion
if (isset($_GET['delete']) && isset($_GET['source_table'])) {
    $customer_id = $_GET['delete'];

    // Validate source_table
    $valid_tables = ['customers', 'users', 'orders'];
    if (in_array($_GET['source_table'], $valid_tables)) {
        $source_table = $_GET['source_table'];
    } else {
        die('Invalid table selected');
    }

    // Delete record
    $query = "DELETE FROM $source_table WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$customer_id]);
    header('Location: customers.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Customers</title>
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
    <link href="assets/css/orders.css" rel="stylesheet">
    <link href="assets/css/badges.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/customers.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', 'Segoe UI', sans-serif;
        }
        h2 {
            font-family: 'Poppins', 'Segoe UI', sans-serif;
            font-weight: 600;
        }
        .table {
            font-family: 'Poppins', 'Segoe UI', sans-serif;
        }
        .table thead th {
            font-weight: 600;
        }
    </style>
</head>

<body>
<div class="admin-layout"> 
<?php include 'includes/theme.php'; ?>
    <nav class="navbar">
    <?php include 'includes/nav/collapsed.php'; ?>
    </nav>
    
    <div class="container mt-5">
        <h2>Manage Customers</h2>

        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Registered At</th>
                    <th class="text-end">Actions</th>
                    
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($customer['id']); ?></td>
                        <td><?php echo htmlspecialchars($customer['username']); ?></td>
                        <td><?php echo htmlspecialchars($customer['email']); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $customer['is_active'] ? 'success' : 'danger'; ?>">
                                <?php echo $customer['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($customer['created_at']); ?></td>
                        <td class="text-end pe-4">
                            <a href="customers.php?toggle_status=<?php echo $customer['id']; ?>&source_table=<?php echo $customer['source_table']; ?>"
                                class="btn btn-sm btn-warning me-2">
                                <?php echo $customer['is_active'] ? 'Deactivate' : 'Activate'; ?>
                            </a>
                            <a href="customers.php?delete=<?php echo $customer['id']; ?>&source_table=<?php echo $customer['source_table']; ?>"
                                class="btn btn-sm btn-danger"
                                onclick="return confirm('Are you sure you want to delete this customer?');">
                                Delete
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
<?php include 'includes/nav/footer.php'; ?>

</html>