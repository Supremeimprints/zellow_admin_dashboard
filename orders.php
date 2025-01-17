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
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$errorMessage = '';
$successMessage = '';

// Handle Create New Order
if (isset($_POST['create_order'])) {
    try {
        $username = $_POST['username'] ?? '';
        $totalAmount = $_POST['total_amount'] ?? 0;
        $countyName = $_POST['county'];
        $townName = $_POST['town'];
        $villageName = $_POST['village'];
        $shippingAddress = $_POST['shipping_address'] ?? '';
        $status = 'Pending';
        $orderDate = date('Y-m-d H:i:s');

        if (empty($username) || empty($totalAmount) || empty($shippingAddress) || empty($countyName) || empty($townName) || empty($villageName)) {
            throw new Exception("All fields are required");
        }
        
        // Fetch the names of county, town, and village
        $countyQuery = "SELECT name FROM counties WHERE id = ?";
        $stmt = $db->prepare($countyQuery);
        $stmt->execute([$countyName]);
        $countyName = $stmt->fetchColumn();

        $townQuery = "SELECT name FROM towns WHERE id = ?";
        $stmt = $db->prepare($townQuery);
        $stmt->execute([$townName]);
        $townName = $stmt->fetchColumn();

        $villageQuery = "SELECT name FROM villages WHERE id = ?";
        $stmt = $db->prepare($villageQuery);
        $stmt->execute([$villageName]);
        $villageName = $stmt->fetchColumn();

        // Combine them into one shipping address
        $shippingAddress = "$countyName, $townName, $villageName"; 

       

        

      /*  if (empty($username) || empty($totalAmount)  {
            throw new Exception("All fields are required");
        } */

        // Get the user_id from the username
        $userQuery = "SELECT id FROM users WHERE username = ?";
        $stmt = $db->prepare($userQuery);
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception("User not found");
        }

        $createQuery = "INSERT INTO orders (user_id, total_amount, status, shipping_address, order_date) 
                        VALUES (?, ?, ?, ?, ?)";
        $stmt = $db->prepare($createQuery);
        $stmt->execute([$user['id'], $totalAmount, $status, $shippingAddress, $orderDate]);

        $successMessage = "Order created successfully";
        header("Location: orders.php?success=" . urlencode($successMessage));
        exit();
    } catch (Exception $e) {
        $errorMessage = "Error creating order: " . $e->getMessage();
    }
}

// Build query for orders with optional filters
$query = "SELECT o.id, u.username, o.total_amount, o.status, o.shipping_address, o.order_date 
          FROM orders o
          JOIN users u ON o.user_id = u.id 
          WHERE 1";

if ($statusFilter) {
    $query .= " AND o.status = :status";
}

if ($search) {
    $query .= " AND (u.username LIKE :search OR o.shipping_address LIKE :search)";
}

$query .= " ORDER BY o.order_date DESC";

try {
    $stmt = $db->prepare($query);

    if ($statusFilter) {
        $stmt->bindParam(':status', $statusFilter);
    }
    if ($search) {
        $searchTerm = "%$search%";
        $stmt->bindParam(':search', $searchTerm);
    }

    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $errorMessage = "Error fetching orders: " . $e->getMessage();
    $orders = [];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
</head>

<body>
    <?php include 'navbar.php'; ?>
    <div class="container mt-5">
        <h2>Manage Orders</h2>

        <?php if ($errorMessage): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($_GET['success']); ?>
            </div>
        <?php endif; ?>

        <!-- Create Order Form -->
        <form method="POST" action="orders.php" class="mb-4">
            <h4>Create New Order</h4>
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" name="username" id="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="total_amount" class="form-label">Total Amount</label>
                <input type="number" step="0.01" min="0" class="form-control" id="total_amount" name="total_amount"
                    required>
            </div>
            <div class="mb-3">
                <label for="county" class="form-label">County</label>
                <select name="county" id="county" class="form-control" required>
                    <option value="">Select County</option>
                    <?php
                    // Fetch counties from the database
                    $countyQuery = "SELECT * FROM counties";
                    $stmt = $db->prepare($countyQuery);
                    $stmt->execute();
                    $counties = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($counties as $countyName) {
                        echo "<option value='{$countyName['id']}'>{$countyName['name']}</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="town" class="form-label">Town</label>
                <select name="town" id="town" class="form-control" required>
                    <option value="">Select Town</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="village" class="form-label">Village</label>
                <select name="village" id="village" class="form-control" required>
                    <option value="">Select Village</option>
                </select>
            </div>
            <button type="submit" name="create_order" class="btn btn-success">Create Order</button>
        </form>

        <!-- Search and Filter Form -->
        <form class="d-flex mb-4" method="GET" action="orders.php">
            <input type="text" name="search" class="form-control me-2" placeholder="Search by Username or Address"
                value="<?php echo htmlspecialchars($search); ?>">
            <select name="status" class="form-select me-2">
                <option value="">All Statuses</option>
                <option value="Pending" <?php echo $statusFilter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="Shipped" <?php echo $statusFilter === 'Shipped' ? 'selected' : ''; ?>>Shipped</option>
                <option value="Delivered" <?php echo $statusFilter === 'Delivered' ? 'selected' : ''; ?>>Delivered
                </option>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
        </form>

        <!-- Orders Table -->
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Username</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Shipping Address</th>
                        <th>Order Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="7" class="text-center">No orders found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($order['id']); ?></td>
                                <td><?php echo htmlspecialchars($order['username']); ?></td>
                                <td><?php echo htmlspecialchars(number_format($order['total_amount'], 2)); ?></td>
                                <td><?php echo htmlspecialchars($order['status']); ?></td>
                                <td><?php echo htmlspecialchars($order['shipping_address']); ?></td>
                                <td><?php echo htmlspecialchars($order['order_date']); ?></td>
                                <td>
                                    <a href="orders.php?action=delete&id=<?php echo $order['id']; ?>"
                                        class="btn btn-danger btn-sm"
                                        onclick="return confirm('Are you sure you want to delete this order?');">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        $(document).ready(function () {
            $("#username").autocomplete({
                source: function (request, response) {
                    $.ajax({
                        url: "get_users.php",
                        dataType: "json",
                        data: { term: request.term },
                        success: function (data) {
                            response(data);
                        }
                    });
                },
                minLength: 2
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        $(document).ready(function () {
            // When the County dropdown changes
            $('#county').change(function () {
                var county_id = $(this).val();

                // Fetch towns for the selected county
                if (county_id) {
                    $.ajax({
                        url: 'get_towns.php',
                        type: 'GET',
                        data: { county_id: county_id },
                        success: function (data) {
                            $('#town').html(data);
                            $('#village').html('<option value="">Select Village</option>'); // Reset Village dropdown
                        }
                    });
                } else {
                    $('#town').html('<option value="">Select Town</option>');
                    $('#village').html('<option value="">Select Village</option>');
                }
            });

            // When the Town dropdown changes
            $('#town').change(function () {
                var town_id = $(this).val();

                // Fetch villages for the selected town
                if (town_id) {
                    $.ajax({
                        url: 'get_villages.php',
                        type: 'GET',
                        data: { town_id: town_id },
                        success: function (data) {
                            $('#village').html(data);
                        }
                    });
                } else {
                    $('#village').html('<option value="">Select Village</option>');
                }
            });
        });
    </script> 

</body>

</html>