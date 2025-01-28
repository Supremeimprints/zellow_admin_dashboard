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

// Function to get driver name
function getDriverName($driverId, $db) {
    $stmt = $db->prepare("SELECT name FROM drivers WHERE driver_id = :driver_id");
    $stmt->bindParam(':driver_id', $driverId);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['name'] : 'Unknown';
}

// Initialize parameters
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$driverFilter = $_GET['driver'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$errorMessage = '';
$successMessage = '';

// Build main query
$query = "SELECT o.order_id, o.username AS order_username, o.email, 
                 u.username AS user_username, o.total_amount, o.quantity, 
                 o.status, o.payment_status, o.shipping_method, 
                 o.shipping_address, o.tracking_number, o.order_date, 
                 o.driver_id
          FROM orders o
          JOIN users u ON o.id = u.id
          WHERE 1";

// Add filters
$params = [];
if ($statusFilter) {
    $query .= " AND o.status = :status";
    $params[':status'] = $statusFilter;
}
if ($driverFilter) {
    $query .= " AND o.driver_id = :driver_id";
    $params[':driver_id'] = $driverFilter;
}
if ($startDate) {
    $query .= " AND o.order_date >= :start_date";
    $params[':start_date'] = "$startDate 00:00:00";
}
if ($endDate) {
    $query .= " AND o.order_date <= :end_date";
    $params[':end_date'] = "$endDate 23:59:59";
}
if ($search) {
    $query .= " AND (u.username LIKE :search 
              OR o.shipping_address LIKE :search 
              OR o.email LIKE :search 
              OR o.tracking_number LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY o.order_date DESC";

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $errorMessage = "Error fetching orders: " . $e->getMessage();
    $orders = [];
}

// Get drivers for filters and dropdowns
$drivers = [];
try {
    $driverStmt = $db->query("SELECT * FROM drivers");
    $drivers = $driverStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $errorMessage = "Error fetching drivers: " . $e->getMessage();
}

// Handle order updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order'])) {
    $orderId = $_POST['order_id'];
    $newStatus = $_POST['new_status'];
    $newDriver = $_POST['driver_id'] ?? null;

    try {
        $updateStmt = $db->prepare("UPDATE orders 
                                   SET status = :status, driver_id = :driver_id 
                                   WHERE order_id = :order_id");
        $updateStmt->execute([
            ':status' => $newStatus,
            ':driver_id' => $newDriver,
            ':order_id' => $orderId
        ]);
        
        if ($updateStmt->rowCount() > 0) {
            $successMessage = "Order #$orderId updated successfully!";
        } else {
            $errorMessage = "No changes made to order #$orderId";
        }
    } catch (Exception $e) {
        $errorMessage = "Error updating order: " . $e->getMessage();
    }
}

// Get order counts
$orderCounts = [];
$statuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
foreach ($statuses as $status) {
    try {
        $countStmt = $db->prepare("SELECT COUNT(*) AS count FROM orders WHERE status = ?");
        $countStmt->execute([$status]);
        $orderCounts[$status] = $countStmt->fetchColumn();
    } catch (Exception $e) {
        $orderCounts[$status] = 0;
    }
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispatch Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card { min-height: 120px; }
        .collapse-toggle { cursor: pointer; }
        .order-details { background-color: #f8f9fa; padding: 15px; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container mt-4">
        <!-- Alerts -->
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($errorMessage) ?></div>
        <?php endif; ?>
        <?php if ($successMessage): ?>
            <div class="alert alert-success"><?= htmlspecialchars($successMessage) ?></div>
        <?php endif; ?>

        <!-- Header Section -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Order Management</h2>
        </div>

        <!-- Statistics Cards -->
        <div class="row row-cols-1 row-cols-md-6 g-4 mb-4">
            <?php foreach ($statuses as $status): 
                $color = match($status) {
                    'Pending' => 'warning',
                    'Processing' => 'info',
                    'Shipped' => 'success',
                    'Delivered' => 'dark',
                    'Cancelled' => 'danger',
                    default => 'primary'
                };
            ?>
            <div class="col">
                <div class="card text-white bg-<?= $color ?>">
                    <div class="card-body">
                        <h6 class="card-title"><?= $status ?></h6>
                        <h3 class="card-text"><?= $orderCounts[$status] ?></h3>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Filter Form -->
        <form method="GET" class="mb-4 bg-light p-3 rounded-3">
            <div class="row g-3">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search orders..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= $status ?>" <?= $statusFilter === $status ? 'selected' : '' ?>>
                                <?= $status ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="driver" class="form-select">
                        <option value="">All Drivers</option>
                        <?php foreach ($drivers as $driver): ?>
                            <option value="<?= $driver['driver_id'] ?>" 
                                <?= $driverFilter == $driver['driver_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($driver['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="date" name="start_date" class="form-control" 
                           value="<?= htmlspecialchars($startDate) ?>">
                </div>
                <div class="col-md-2">
                    <input type="date" name="end_date" class="form-control" 
                           value="<?= htmlspecialchars($endDate) ?>">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </div>
        </form>

        <!-- Orders Table -->
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Status</th>
                        <th>Driver</th>
                        <th>Payment</th>
                        <th>Shipping</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>#<?= htmlspecialchars($order['order_id']) ?></td>
                        <td>
                            <div><?= htmlspecialchars($order['order_username']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($order['email']) ?></small>
                        </td>
                        <td>
                            <span class="badge bg-<?= match($order['status']) {
                                'Pending' => 'warning',
                                'Processing' => 'info',
                                'Shipped' => 'success',
                                'Delivered' => 'dark',
                                'Cancelled' => 'danger'
                            } ?>">
                                <?= htmlspecialchars($order['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($order['driver_id']): ?>
                                <?= htmlspecialchars(getDriverName($order['driver_id'], $db)) ?>
                            <?php else: ?>
                                <span class="text-muted">Not assigned</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($order['payment_status']) ?></td>
                        <td><?= htmlspecialchars($order['shipping_method']) ?></td>
                        <td>
                            <form method="POST" class="row g-2">
                                <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                                

                                <div class="col-12">
                                    <select name="new_status" class="form-select form-select-sm">
                                        <?php foreach ($statuses as $status): ?>
                                            <option value="<?= $status ?>" 
                                                <?= $order['status'] === $status ? 'selected' : '' ?>>
                                                <?= $status ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                

                                <div class="col-12">
                                    <select name="driver_id" class="form-select form-select-sm">
                                        <option value="">Select Driver</option>
                                        <?php foreach ($drivers as $driver): ?>
                                            <option value="<?= $driver['driver_id'] ?>" 
                                                <?= $order['driver_id'] == $driver['driver_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($driver['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                

                                <div class="col-12">
                                    <button type="submit" name="update_order" 
                                            class="btn btn-sm w-100 btn-<?= $order['status'] === 'Shipped' ? 'primary' : 'success' ?>">
                                        <?= $order['status'] === 'Shipped' ? 'Dispatch' : 'Update' ?>
                                    </button>
                                </div>
                            </form>
                        </td>
                    </tr>
                    <tr class="order-details">
                        <td colspan="7">
                            <div class="row">
                                <div class="col-md-4">
                                    <strong>Shipping Address:</strong>
                                    <p><?= nl2br(htmlspecialchars($order['shipping_address'])) ?></p>
                                </div>
                                <div class="col-md-3">
                                    <strong>Tracking Number:</strong>
                                    <p><?= $order['tracking_number'] ? htmlspecialchars($order['tracking_number']) : 'N/A' ?></p>
                                </div>
                                <div class="col-md-3">
                                    <strong>Order Date:</strong>
                                    <p><?= date('M j, Y H:i', strtotime($order['order_date'])) ?></p>
                                </div>
                                <div class="col-md-2">
                                    <strong>Total:</strong>
                                    <p>Ksh. <?= number_format($order['total_amount'], 2) ?></p>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php include 'footer.php'; ?>
</html>
