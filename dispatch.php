<?php
session_start();

if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

//Restrict access to specific roles
$allowed_roles = ['admin', 'dispatch_manager'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    echo "You do not have permission to view this page.";
    exit();
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Initialize all filter parameters
$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$paymentStatusFilter = $_GET['payment_status'] ?? '';
$paymentMethodFilter = $_GET['payment_method'] ?? '';
$shippingMethodFilter = $_GET['shipping_method'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

$errorMessage = '';
$successMessage = '';

// Build query with enhanced filters for dispatch page
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
WHERE 1 AND (o.status = 'Pending' OR o.status = 'Processing')
AND (o.payment_status = 'Paid' OR o.payment_status = 'Pending')"; // Assuming "Pending" status is relevant for dispatch orders

// Add filters to query
if ($statusFilter) {
    $query .= " AND o.status = :status";
}
if ($paymentStatusFilter) {
    $query .= " AND o.payment_status = :payment_status";
}
if ($paymentMethodFilter) {
    $query .= " AND o.payment_method = :payment_method";
}
if ($shippingMethodFilter) {
    $query .= " AND o.shipping_method = :shipping_method";
}
if ($startDate) {
    $query .= " AND o.order_date >= :start_date";
    $startDateParam = "$startDate 00:00:00";
}
if ($endDate) {
    $query .= " AND o.order_date <= :end_date";
    $endDateParam = "$endDate 23:59:59";
}
if ($search) {
    $query .= " AND (u.username LIKE :search 
              OR o.shipping_address LIKE :search 
              OR o.email LIKE :search 
              OR o.tracking_number LIKE :search)";
}

$query .= " ORDER BY o.order_date DESC";

try {
    $stmt = $db->prepare($query);

    // Bind parameters
    if ($statusFilter) {
        $stmt->bindParam(':status', $statusFilter);
    }
    if ($paymentStatusFilter) {
        $stmt->bindParam(':payment_status', $paymentStatusFilter);
    }
    if ($paymentMethodFilter) {
        $stmt->bindParam(':payment_method', $paymentMethodFilter);
    }
    if ($shippingMethodFilter) {
        $stmt->bindParam(':shipping_method', $shippingMethodFilter);
    }
    if ($startDate) {
        $stmt->bindParam(':start_date', $startDateParam);
    }
    if ($endDate) {
        $stmt->bindParam(':end_date', $endDateParam);
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
// Get driver data
$driverQuery = "SELECT * FROM drivers";
$driverStmt = $db->prepare($driverQuery);
$driverStmt->execute();
$drivers = $driverStmt->fetchAll(PDO::FETCH_ASSOC);


// Get driver data with vehicle information
$driverQuery = "SELECT d.*, v.vehicle_type, v.model as vehicle_model, v.registration_number, v.status as vehicle_status 
                FROM drivers d 
                LEFT JOIN vehicles v ON d.driver_id = v.driver_id";

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
    <title>Dispatch Orders</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card {
            min-height: 120px;
        }

        .collapse-toggle {
            cursor: pointer;
        }

        .order-details {
            background-color: #f8f9fa;
            padding: 15px;
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>
    <div class="container mt-5">
        <!-- Summary Stats Cards -->
        <h2>Dispatch Summary</h2>
        <div class="row row-cols-1 row-cols-md-6 g-4 mb-4">
            <?php foreach ($statuses as $status):
                $color = match ($status) {
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
        <form method="GET" action="dispatch.php" class="mb-4">
            <div class="row g-3">
                <!-- Search Input -->
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control"
                        placeholder="Search (username, address, email, tracking)"
                        value="<?= htmlspecialchars($search) ?>">
                </div>

                <!-- Status Filters -->
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">Shipping Status</option>
                        <option value="Pending" <?= $statusFilter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="Processing" <?= $statusFilter === 'Processing' ? 'selected' : '' ?>>Processing
                        </option>
                        <option value="Shipped" <?= $statusFilter === 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                        <option value="Delivered" <?= $statusFilter === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                        <option value="Cancelled" <?= $statusFilter === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>

                <!-- Payment Status -->
                <div class="col-md-2">
                    <select name="payment_status" class="form-select">
                        <option value="">Payment Status</option>
                        <option value="Paid" <?= $paymentStatusFilter === 'Paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="Pending" <?= $paymentStatusFilter === 'Pending' ? 'selected' : '' ?>>Pending
                        </option>
                        <option value="Failed" <?= $paymentStatusFilter === 'Failed' ? 'selected' : '' ?>>Failed</option>
                        <option value="Refunded" <?= $paymentStatusFilter === 'Refunded' ? 'selected' : '' ?>>Refunded
                        </option>
                    </select>
                </div>

                <!-- Payment Method -->
                <div class="col-md-2">
                    <select name="payment_method" class="form-select">
                        <option value="">Payment Method</option>
                        <option value="Credit Card" <?= $paymentMethodFilter === 'Credit Card' ? 'selected' : '' ?>>Credit
                            Card</option>
                        <option value="PayPal" <?= $paymentMethodFilter === 'PayPal' ? 'selected' : '' ?>>PayPal</option>
                        <option value="M-Pesa" <?= $paymentMethodFilter === 'M-Pesa' ? 'selected' : '' ?>>M-Pesa</option>
                    </select>
                </div>

                <!-- Shipping Method -->
                <div class="col-md-2">
                    <select name="shipping_method" class="form-select">
                        <option value="">Shipping Method</option>
                        <option value="Standard" <?= $shippingMethodFilter === 'Standard' ? 'selected' : '' ?>>Standard
                        </option>
                        <option value="Express" <?= $shippingMethodFilter === 'Express' ? 'selected' : '' ?>>Express
                        </option>
                    </select>
                </div>

                <!-- Date Range -->
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="date" name="start_date" class="form-control"
                            value="<?= htmlspecialchars($startDate) ?>" placeholder="Start Date">
                        <input type="date" name="end_date" class="form-control"
                            value="<?= htmlspecialchars($endDate) ?>" placeholder="End Date">
                    </div>
                </div>

                <!-- Submit and Reset Buttons -->
                <div class="col-md-2">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1">Filter</button>
                        <a href="dispatch.php" class="btn btn-secondary">Reset</a>
                    </div>
                </div>
            </div>
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
                        <th>Tracking Number</th>
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
                                <td>#<?php echo htmlspecialchars($order['order_id']); ?></td>
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
                                <td><?php echo htmlspecialchars($order['tracking_number'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($order['shipping_address']); ?></td>
                                <td><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($order['order_date']))); ?></td>
                                <td>
                                    <?php if ($order['payment_status'] === 'Paid' || $order['payment_status'] === 'Pending'): ?>
                                        <a href="dispatch_order.php?order_id=<?php echo $order['order_id']; ?>"
                                            class="btn btn-sm btn-success">Dispatch</a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" disabled>Cannot Dispatch</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Drivers Table -->
        <h3 class="mt-5">Drivers</h3>
        <div>
            <a href="create_driver.php" class="btn btn-primary">
                <i class="bi bi-person-plus"></i> Create New Driver
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Driver ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Vehicle Type</th>
                        <th>Vehicle Model</th>
                        <th>Registration</th>
                        <th>Vehicle Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($drivers)): ?>
                        <tr>
                            <td colspan="9" class="text-center">No drivers available</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($drivers as $driver): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($driver['driver_id']); ?></td>
                                <td><?php echo htmlspecialchars($driver['name']); ?></td>
                                <td><?php echo htmlspecialchars($driver['email']); ?></td>
                                <td><?php echo htmlspecialchars($driver['phone_number']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $driver['status'] === 'Active' ? 'success' : 'danger'; ?>">
                                        <?php echo htmlspecialchars($driver['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($driver['vehicle_type'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($driver['vehicle_model'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($driver['registration_number'] ?? 'N/A'); ?></td>
                                <td>
                                    <span
                                        class="badge bg-<?php echo getVehicleStatusColor($driver['vehicle_status'] ?? 'N/A'); ?>">
                                        <?php echo htmlspecialchars($driver['vehicle_status'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

        <?php
        function getStatusColor($status)
        {
            return match ($status) {
                'Pending' => 'warning',
                'Processing' => 'info',
                'Shipped' => 'primary',
                'Delivered' => 'success',
                'Cancelled' => 'danger',
                default => 'secondary'
            };
        }

        function getPaymentStatusColor($status)
        {
            return match ($status) {
                'Pending' => 'warning',
                'Paid' => 'success',
                'Failed' => 'danger',
                'Refunded' => 'info',
                default => 'secondary'
            };
        }

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
<?php include 'footer.php'; ?>

</html>