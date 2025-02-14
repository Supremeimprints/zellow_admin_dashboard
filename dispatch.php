<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions/transaction_functions.php';
require_once 'includes/functions/order_functions.php';
require_once 'includes/functions/badge_functions.php';

// Authentication check
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

$allowed_roles = ['admin', 'dispatch_manager'];
if (!in_array($_SESSION['role'], $allowed_roles)) {
    header('Location: login.php');
    exit();
}


// Initialize database after auth checks
$database = new Database();
$db = $database->getConnection();

// ... [keep existing filter and order query code] ...
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

// Update the stats query for dispatch page (only Pending and Processing orders)
$statsQuery = "SELECT o.status,
    COUNT(DISTINCT o.order_id) as count,
    COALESCE(SUM(oi.quantity * oi.unit_price), 0) as total_amount
    FROM orders o
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    WHERE o.status IN ('Pending', 'Processing')
    AND (o.payment_status = 'Paid' OR o.payment_status = 'Pending')
    GROUP BY o.status";

$statsStmt = $db->prepare($statsQuery);
$statsStmt->execute();
$orderStats = $statsStmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize stats array with default values
$stats = [
    'Pending' => ['count' => 0, 'amount' => 0],
    'Processing' => ['count' => 0, 'amount' => 0]
];

// Update stats with actual values
foreach ($orderStats as $stat) {
    if (isset($stats[$stat['status']])) {
        $stats[$stat['status']] = [
            'count' => (int) $stat['count'],
            'amount' => (float) $stat['total_amount']
        ];
    }
}

// Modify the main query to prevent duplicates
$query = "SELECT DISTINCT o.order_id, u.username, o.status, o.payment_status, o.payment_method, 
          o.shipping_method, o.tracking_number, o.shipping_address, o.order_date, 
          GROUP_CONCAT(DISTINCT p.product_name, ' (', oi.quantity, ')' SEPARATOR ', ') AS products,
          SUM(oi.quantity * oi.unit_price) as total_amount
          FROM orders o 
          JOIN users u ON o.id = u.id 
          JOIN order_items oi ON o.order_id = oi.order_id 
          JOIN products p ON oi.product_id = p.product_id 
          WHERE o.status IN ('Pending', 'Processing')
          AND (o.payment_status = 'Paid' OR o.payment_status = 'Pending')";

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

// Add GROUP BY to prevent duplicates
$query .= " GROUP BY o.order_id, u.username, o.status, o.payment_status, o.payment_method, 
            o.shipping_method, o.tracking_number, o.shipping_address, o.order_date, o.total_amount 
            ORDER BY o.order_date DESC";


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

// Update badge classes in the table
foreach ($orders as &$order) {
    $order['status_badge'] = getStatusBadgeClass($order['status'], 'status');
    $order['payment_badge'] = getStatusBadgeClass($order['payment_status'], 'payment');
}

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

// ... [keep existing order counts code] ...
// Get order counts
$orderCounts = [];
$statuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
$statusCountQuery = "SELECT status, COUNT(*) as count 
                    FROM orders 
                    GROUP BY status";
$statusStmt = $db->prepare($statusCountQuery);
$statusStmt->execute();
$statusResults = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize all statuses with 0
foreach ($statuses as $status) {
    $orderCounts[$status] = 0;
}

// Update counts from database results
foreach ($statusResults as $result) {
    if (isset($orderCounts[$result['status']])) {
        $orderCounts[$result['status']] = (int) $result['count'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Keep existing head section -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispatch Orders</title>
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/orders.css">
    <link href="assets/css/orders.css" rel="stylesheet">
    <link href="assets/css/badges.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>
    <div class="admin-layout">
        <?php include 'includes/theme.php'; ?>
        <nav class="navbar">
            <?php include 'includes/nav/collapsed.php'; ?>
        </nav>

        <div class="main-content">

            <div class="row g-3">
                <div class="col-12">
                    <div class="container mt-5">
                        <h2 class="mb-4">Dispatch & Logistics Summary</h2>

                        <!-- Add this HTML just before the orders table -->
                        <div class="row g-3 mb-4">
                            <?php
                            $statusColors = [
                                'Pending' => 'pending',
                                'Processing' => 'processing',
                                'Shipped' => 'shipped',
                                'Delivered' => 'delivered',
                                'Cancelled' => 'cancelled'
                            ];

                            foreach ($statusColors as $status => $colorClass):
                                $count = $stats[$status]['count'] ?? 0;
                                $amount = $stats[$status]['amount'] ?? 0;
                                ?>
                                <div class="col-md">
                                    <div class="card stats-card <?= $colorClass ?> h-100">
                                        <div class="card-body">
                                            <h6 class="card-title mb-3">
                                                <?= $status ?> Orders
                                            </h6>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="display-6 fw-bold mb-0">
                                                    <?= number_format($count) ?>
                                                </div>
                                                <div class="fs-6">
                                                    Ksh. <?= number_format($amount, 2) ?>
                                                </div>
                                            </div>
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
                                        <option value="Pending" <?= $statusFilter === 'Pending' ? 'selected' : '' ?>>
                                            Pending</option>
                                        <option value="Processing" <?= $statusFilter === 'Processing' ? 'selected' : '' ?>>
                                            Processing
                                        </option>
                                        <option value="Shipped" <?= $statusFilter === 'Shipped' ? 'selected' : '' ?>>
                                            Shipped</option>
                                        <option value="Delivered" <?= $statusFilter === 'Delivered' ? 'selected' : '' ?>>
                                            Delivered</option>
                                        <option value="Cancelled" <?= $statusFilter === 'Cancelled' ? 'selected' : '' ?>>
                                            Cancelled</option>
                                    </select>
                                </div>

                                <!-- Payment Status -->
                                <div class="col-md-2">
                                    <select name="payment_status" class="form-select">
                                        <option value="">Payment Status</option>
                                        <option value="Paid" <?= $paymentStatusFilter === 'Paid' ? 'selected' : '' ?>>Paid
                                        </option>
                                        <option value="Pending" <?= $paymentStatusFilter === 'Pending' ? 'selected' : '' ?>>Pending
                                        </option>
                                        <option value="Failed" <?= $paymentStatusFilter === 'Failed' ? 'selected' : '' ?>>
                                            Failed</option>
                                        <option value="Refunded" <?= $paymentStatusFilter === 'Refunded' ? 'selected' : '' ?>>Refunded
                                        </option>
                                    </select>
                                </div>

                                <!-- Payment Method -->
                                <div class="col-md-2">
                                    <select name="payment_method" class="form-select">
                                        <option value="">Payment Method</option>
                                        <option value="Mpesa" <?= $paymentMethodFilter === 'Mpesa' ? 'selected' : '' ?>>
                                            Mpesa</option>
                                        <option value="Airtel Money" <?= $paymentMethodFilter === 'Airtel Money' ? 'selected' : '' ?>>
                                            Airtel Money</option>
                                        <option value="Credit Card" <?= $paymentMethodFilter === 'Credit Card' ? 'selected' : '' ?>>Credit
                                            Card</option>
                                        <option value="Cash On Delivery" <?= $paymentMethodFilter === 'Cash On Delivery' ? 'selected' : '' ?>>Cash On Delivery</option>
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
                                        <th>Products</th>
                                        <th>Total Amount</th>
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
                                                <td>#<?= htmlspecialchars($order['order_id']) ?></td>
                                                <td><?= htmlspecialchars($order['username']) ?></td>
                                                <td><?= htmlspecialchars($order['products']) ?></td>
                                                <td class="text-end">Ksh.<?= number_format($order['total_amount'], 2) ?></td>
                                                <td><?= renderStatusBadge($order['status'], 'order', 'sm') ?></td>
                                                <td><?= renderStatusBadge($order['payment_status'], 'payment', 'sm') ?></td>
                                                <td><?= htmlspecialchars($order['tracking_number'] ?? 'N/A') ?></td>
                                                <td><?= htmlspecialchars($order['shipping_address']) ?></td>
                                                <td><?= date('Y-m-d H:i', strtotime($order['order_date'])) ?></td>
                                                <td>
                                                    <?php if ($order['payment_status'] === 'Paid' || $order['payment_status'] === 'Pending'): ?>
                                                        <a href="dispatch_order.php?order_id=<?= $order['order_id'] ?>"
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


                        <!-- Enhanced Drivers Table -->
                        <h2 class="container mt-5">Manage Drivers</h2>
                        <div>
                            <a href="create_driver.php" class="btn btn-primary mb-2">
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
                                                    <span
                                                        class="badge bg-<?= $driver['status'] === 'Active' ? 'success' : 'danger' ?>">
                                                        <?= $driver['status'] ?>
                                                    </span>
                                                </td>
                                                <td><?= htmlspecialchars($driver['vehicle_type'] ?? 'N/A') ?></td>
                                                <td>
                                                    <?php if ($driver['vehicle_status'] ?? false): ?>
                                                        <span
                                                            class="badge bg-<?= getVehicleStatusColor($driver['vehicle_status']) ?>">
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
                                                                <form method="POST" action="update_driver.php"
                                                                    class="dropdown-item p-0">
                                                                    <input type="hidden" name="driver_id"
                                                                        value="<?= $driver['driver_id'] ?>">
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
                                                                    <input type="hidden" name="driver_id"
                                                                        value="<?= $driver['driver_id'] ?>">
                                                                    <button type="submit" class="dropdown-item py-2 px-3">
                                                                        <i class="bi bi-trash me-2"></i>Delete
                                                                    </button>
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

                        <!-- First, ensure Bootstrap JS is properly loaded -->

                        <script
                            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
                        <?php
                        // Handle deletion inside the same file
                        if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['delete_driver'])) {
                            $driver_id = (int) $_POST['driver_id'];

                            $query = "DELETE FROM drivers WHERE driver_id = ?";
                            $stmt = $db->prepare($query);
                            if ($stmt->execute([$driver_id])) {
                                echo "<script>
            alert('Driver deleted successfully.');
            window.location.href = 'dispatch.php';
        </script>";
                            } else {
                                echo "Error: Unable to delete driver.";
                            }
                        }
                        ?>



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
</body>
<?php include 'includes/nav/footer.php'; ?>

</html>