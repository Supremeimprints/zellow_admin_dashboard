<?php
session_start();

if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    echo "Access denied. You do not have permission to view this page.";
    exit();
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$errorMessage = '';
$successMessage = '';

// Build query for orders with optional filters and additional fields
$query = "SELECT o.order_id, 
       o.username AS order_username, 
       o.email, 
       u.username AS user_username, 
       o.total_amount, 
       o.quantity, 
       o.total_price, 
       o.status, 
       o.shipping_address, 
       o.payment_status, 
       o.payment_method, 
       o.tracking_number, 
       o.order_date, 
       o.shipping_method 
FROM orders o
JOIN users u ON o.id = u.id
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
    <title>View Orders</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>View Orders</h2>
            <a href="create_order.php" class="btn btn-primary">Create New Order</a>
        </div>

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

        <!-- Search and Filter Form -->
        <form class="d-flex mb-4" method="GET" action="orders.php">
            <input type="text" name="search" class="form-control me-2" 
                   placeholder="Search by Username or Address" 
                   value="<?php echo htmlspecialchars($search); ?>">
            <select name="status" class="form-select me-2">
                <option value="">All Statuses</option>
                <option value="Pending" <?php echo $statusFilter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="Processing" <?php echo $statusFilter === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                <option value="Shipped" <?php echo $statusFilter === 'Shipped' ? 'selected' : ''; ?>>Shipped</option>
                <option value="Delivered" <?php echo $statusFilter === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                <option value="Cancelled" <?php echo $statusFilter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
        </form>

        <!-- Orders Table -->
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Username</th>
                        <th>Quantity</th>
                        <th>Total Amount</th>
                        <th>Total Price</th>
                        <th>Status</th>
                        <th>Payment Status</th>
                        <th>Shipping Address</th>
                        <th>Order Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="10" class="text-center">No orders found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($order['order_id']); ?></td>
                                <td><?php echo htmlspecialchars($order['user_username']); ?></td>
                                <td><?php echo htmlspecialchars($order['quantity']); ?></td>
                                <td>Ksh.<?php echo htmlspecialchars(number_format($order['total_amount'], 2)); ?></td>
                                <td>Ksh.<?php echo htmlspecialchars(number_format($order['total_price'], 2)); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo getStatusColor($order['status']); ?>">
                                        <?php echo htmlspecialchars($order['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo getPaymentStatusColor($order['payment_status']); ?>">
                                        <?php echo htmlspecialchars($order['payment_status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($order['shipping_address']); ?></td>
                                <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($order['order_date']))); ?></td>
                                <td>
                                    <a href="update_order.php?id=<?php echo $order['order_id']; ?>" 
                                       class="btn btn-sm btn-primary">Update</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
function getStatusColor($status) {
    return match($status) {
        'Pending' => 'warning',
        'Processing' => 'info',
        'Shipped' => 'primary',
        'Delivered' => 'success',
        'Cancelled' => 'danger',
        default => 'secondary'
    };
}

function getPaymentStatusColor($status) {
    return match($status) {
        'Pending' => 'warning',
        'Paid' => 'success',
        'Failed' => 'danger',
        'Refunded' => 'info',
        default => 'secondary'
    };
}
?>