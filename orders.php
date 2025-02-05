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
require_once 'includes/functions/transaction_functions.php'; // Include this first
require_once 'includes/functions/order_functions.php';

// Initialize database connection first
$database = new Database();
$db = $database->getConnection();

// Verify database connection
if (!$db) {
    die("Database connection failed");
}

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
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query with enhanced filters
$query = "SELECT o.order_id, u.username, o.status, o.payment_status, o.payment_method, 
          o.shipping_method, o.tracking_number, o.shipping_address, o.order_date, 
          GROUP_CONCAT(CONCAT(p.product_name, ' (', oi.quantity, ')') SEPARATOR ', ') AS products, 
          SUM(oi.subtotal) AS total_amount 
          FROM orders o 
          JOIN users u ON o.id = u.id 
          JOIN order_items oi ON o.order_id = oi.order_id 
          JOIN products p ON oi.product_id = p.product_id 
          WHERE 1";

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

$query .= " GROUP BY o.order_id ORDER BY o.order_date DESC";

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

foreach ($orders as $order) {
    if ($order['payment_status'] === 'Paid') {
        try {
            // Insert Customer Payment transaction
            $insertTransactionStmt = $db->prepare("
                INSERT INTO transactions (reference_id, transaction_type, total_amount, currency, payment_method, 
                                          payment_status, user, order_id, remarks)
                VALUES (:reference_id, 'Customer Payment', :total_amount, 'KES', :payment_method, 
                        'Completed', :user, :order_id, 'Order payment recorded')
            ");
            $reference_id = 'TXN-' . uniqid(); // Generate unique reference ID

            $insertTransactionStmt->bindParam(':reference_id', $reference_id);
            $insertTransactionStmt->bindParam(':total_amount', $order['total_amount']);
            $insertTransactionStmt->bindParam(':payment_method', $order['payment_method']);
            $insertTransactionStmt->bindParam(':user', $order['id']);
            $insertTransactionStmt->bindParam(':order_id', $order['order_id']);
            $insertTransactionStmt->execute();
        } catch (Exception $e) {
            $errorMessage = "Error inserting transaction: " . $e->getMessage();
        }
    }

    if ($order['status'] === 'Shipped') {
        try {
            $inventoryStmt = $db->prepare("SELECT stock_quantity FROM inventory WHERE product_id = :product_id");
            $inventoryStmt->bindParam(':product_id', $order['product_id']);
            $inventoryStmt->execute();
            $inventory = $inventoryStmt->fetch(PDO::FETCH_ASSOC);

            if (!$inventory) {
                $errorMessage = "Error: Product ID " . $order['product_id'] . " not found in inventory.";
            } else {
                if ($inventory['stock_quantity'] < $order['quantity']) {
                    $errorMessage = "Insufficient inventory for product: " . $order['product_name'];
                } else {
                    $updateInventoryStmt = $db->prepare("UPDATE inventory SET stock_quantity = stock_quantity - :quantity WHERE product_id = :product_id");
                    $updateInventoryStmt->bindParam(':quantity', $order['quantity']);
                    $updateInventoryStmt->bindParam(':product_id', $order['product_id']);
                    $updateInventoryStmt->execute();
                }
            }
        } catch (Exception $e) {
            $errorMessage = "Error updating inventory: " . $e->getMessage();
        }
    }
}


// Get order counts
$orderStats = getOrderStatistics($db);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Orders</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <link href="assets/css/orders.css" rel="stylesheet">
</head>

<body>
    <?php include 'includes/nav/collapsed.php'; ?>
    <?php include 'includes/theme.php' ?>

    <div class="container mt-5">
        <!-- Summary Stats Cards -->
        <div class="container mt-5">
            <h2 class="mb-4">Order Statistics</h2>
            <div class="row g-4 mb-4">
                <?php 
                $orderStats = getOrderStatistics($db);
                $statusIcons = [
                    'Pending' => 'hourglass-split',
                    'Processing' => 'gear-fill',
                    'Shipped' => 'truck',
                    'Delivered' => 'check-circle-fill',
                    'Cancelled' => 'x-circle-fill'
                ];
                
                foreach ($orderStats as $status => $data): ?>
                    <div class="col-md-4 col-lg-2">
                        <div class="card h-100 <?= getStatusCardClass($status) ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="card-title mb-2"><?= $status ?></h6>
                                        <h3 class="card-text mb-0"><?= $data['count'] ?></h3>
                                        <small class="text-nowrap">
                                            Ksh. <?= number_format($data['amount'], 2) ?>
                                        </small>
                                    </div>
                                    <div class="fs-1 opacity-50">
                                        <i class="bi bi-<?= $statusIcons[$status] ?? 'question-circle' ?>"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="container mt-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>View Orders</h2>
                <a href="create_order.php" class="btn btn-primary">Create New Order</a>
            </div>

            <!-- Filter Form -->
            <form method="GET" action="orders.php" class="mb-4">
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
                            <option value="Delivered" <?= $statusFilter === 'Delivered' ? 'selected' : '' ?>>Delivered
                            </option>
                            <option value="Cancelled" <?= $statusFilter === 'Cancelled' ? 'selected' : '' ?>>Cancelled
                            </option>
                        </select>
                    </div>

                    <!-- Payment Status -->
                    <div class="col-md-2">
                        <select name="payment_status" class="form-select">
                            <option value="">Payment Status</option>
                            <option value="Paid" <?= $paymentStatusFilter === 'Paid' ? 'selected' : '' ?>>Paid</option>
                            <option value="Pending" <?= $paymentStatusFilter === 'Pending' ? 'selected' : '' ?>>Pending
                            </option>
                            <option value="Failed" <?= $paymentStatusFilter === 'Failed' ? 'selected' : '' ?>>Failed
                            </option>
                            <option value="Refunded" <?= $paymentStatusFilter === 'Refunded' ? 'selected' : '' ?>>Refunded
                            </option>
                        </select>
                    </div>

                    <!-- Payment Method -->
                    <div class="col-md-2">
                        <select name="payment_method" class="form-select">
                            <option value="">Payment Method</option>
                            <option value="Mpesa" <?= $paymentMethodFilter === 'Mpesa' ? 'selected' : '' ?>>Mpesa</option>
                            <option value="Airtel Money" <?= $paymentMethodFilter === 'Airtel Money' ? 'selected' : '' ?>>
                                Airtel Money</option>
                            <option value="Credit Card" <?= $paymentMethodFilter === 'Credit Card' ? 'selected' : '' ?>>
                                Credit Card</option>
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
                            <a href="orders.php" class="btn btn-secondary">Reset</a>
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
                                    <td>#<?php echo htmlspecialchars($order['order_id']); ?></td>
                                    <td><?php echo htmlspecialchars($order['username']); ?></td>
                                    <td><?php echo htmlspecialchars($order['products']); ?></td>
                                    <td>Ksh.<?php echo htmlspecialchars(number_format($order['total_amount'], 2)); ?></td>
                                    <td>
                                        <span class="badge <?= getStatusBadgeClass($order['status']) ?>">
                                            <?= htmlspecialchars($order['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?= getStatusBadgeClass($order['payment_status'], 'payment') ?>">
                                            <?= htmlspecialchars($order['payment_status']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($order['tracking_number'] ?? 'N/A') ?></td>
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
        ?>
    </div>
</body>
<?php include 'includes/nav/footer.php'; ?>

</html>