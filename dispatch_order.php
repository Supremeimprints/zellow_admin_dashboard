<?php
session_start();

// Include required files
require_once 'includes/functions/transaction_functions.php';  // First
require_once 'includes/functions/order_functions.php';        // Second

if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

// Ensure only admin can access this page
if ($_SESSION['role'] !== 'admin') {
    echo "Access denied. You do not have permission to dispatch orders.";
    exit();
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Check if order_id is provided
if (!isset($_GET['order_id'])) {
    header('Location: dispatch.php');
    exit();
}

$order_id = $_GET['order_id'];

// Fetch order details including payment status
try {
    $stmt = $db->prepare("
        SELECT o.*,
               GROUP_CONCAT(DISTINCT CONCAT(p.product_name, ' (', oi.quantity, ' x ', oi.unit_price, ')') SEPARATOR ', ') AS products,
               SUM(oi.quantity * oi.unit_price) as total_amount
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        JOIN products p ON oi.product_id = p.product_id
        WHERE o.order_id = :order_id
        GROUP BY o.order_id
    ");
    $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch individual order items for detailed display
    $itemsStmt = $db->prepare("
        SELECT oi.*, p.product_name, 
               (oi.quantity * oi.unit_price) as line_total
        FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
        WHERE oi.order_id = :order_id
    ");
    $itemsStmt->bindParam(':order_id', $order_id);
    $itemsStmt->execute();
    $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$order) {
        header('Location: dispatch.php');
        exit();
    }

    // Initialize tracking number
    $tracking_number = $order['tracking_number'];
    if (empty($tracking_number)) {
        $tracking_number = getOrCreateTrackingNumber($db, $order_id);
    }

    // Check if order is eligible for dispatch (Paid or Pending payment status)
    if (!in_array($order['payment_status'], ['Paid', 'Pending'])) {
        $_SESSION['error'] = "Order cannot be dispatched - Invalid payment status";
        header('Location: dispatch.php');
        exit();
    }

    // Check inventory levels
    $inventoryStmt = $db->prepare("SELECT product_id, stock_quantity FROM inventory WHERE product_id IN (SELECT product_id FROM order_items WHERE order_id = :order_id)");
    $inventoryStmt->bindParam(':order_id', $order_id);
    $inventoryStmt->execute();
    $inventory = $inventoryStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($inventory as $item) {
        $productId = $item['product_id'];
        $stockQuantity = $item['stock_quantity'];

        $orderItemStmt = $db->prepare("SELECT quantity FROM order_items WHERE order_id = :order_id AND product_id = :product_id");
        $orderItemStmt->bindParam(':order_id', $order_id);
        $orderItemStmt->bindParam(':product_id', $productId);
        $orderItemStmt->execute();
        $orderItem = $orderItemStmt->fetch(PDO::FETCH_ASSOC);

        if ($stockQuantity < $orderItem['quantity']) {
            // Add notification to notifications.php
            $notificationStmt = $db->prepare("INSERT INTO notifications (message, type) VALUES (:message, 'warning')");
            $message = "Product " . $order['product_name'] . " is out of stock.";
            $notificationStmt->bindParam(':message', $message);
            $notificationStmt->execute();

            // Redirect to dispatch.php with alert
            echo "<script>
                alert('Insufficient inventory for product: " . $order['product_name'] . ". Redirecting to inventory page.');
                window.location.href = 'inventory.php';
            </script>";
            exit();
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $db->beginTransaction();
            
            $driver_id = $_POST['driver_id'] ?? '';
            if (empty($driver_id)) {
                throw new Exception("Please select a driver");
            }

            // Update order status without updated_at fieldd driver_id
            $stmt = $db->prepare("
                UPDATE orders 
                SET status = 'Shipped',
                    driver_id = :driver_id
                WHERE order_id = :order_id
            ");
            
            $stmt->execute([
                ':driver_id' => $driver_id,
                ':order_id' => $order_id
            ]);

            // Update vehicle status
            $stmt = $db->prepare("
                UPDATE vehicles 
                SET vehicle_status = 'In Use'
                WHERE driver_id = :driver_id
            ");
            $stmt->execute([':driver_id' => $driver_id]);

            // Update inventory and create transaction record
            foreach ($inventory as $item) {
                $productId = $item['product_id'];
                $orderItemStmt = $db->prepare("SELECT quantity FROM order_items WHERE order_id = :order_id AND product_id = :product_id");
                $orderItemStmt->bindParam(':order_id', $order_id);
                $orderItemStmt->bindParam(':product_id', $productId);
                $orderItemStmt->execute();
                $orderItem = $orderItemStmt->fetch(PDO::FETCH_ASSOC);

                $updateInventoryStmt = $db->prepare("UPDATE inventory SET stock_quantity = stock_quantity - :quantity WHERE product_id = :product_id");
                $updateInventoryStmt->bindParam(':quantity', $orderItem['quantity']);
                $updateInventoryStmt->bindParam(':product_id', $productId);
                $updateInventoryStmt->execute();
            }

            $db->commit();
            $_SESSION['success'] = "Order #$order_id has been dispatched successfully!";
            header('Location: dispatch.php');
            exit();
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Error dispatching order: " . $e->getMessage();
        }
    }

} catch (Exception $e) {
    $_SESSION['error'] = "Error fetching order details: " . $e->getMessage();
    header('Location: dispatch.php');
    exit();
}

// Fetch available drivers (those with 'Available' vehicle status)
try {
    $stmt = $db->prepare("
        SELECT d.*, v.vehicle_type, v.vehicle_status 
        FROM drivers d
        LEFT JOIN vehicles v ON d.driver_id = v.driver_id
        WHERE d.status = 'Active' 
        AND (v.vehicle_status = 'Available' OR v.vehicle_status IS NULL)
    ");
    $stmt->execute();
    $available_drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Error fetching available drivers: " . $e->getMessage();
}

// Update driver query to exclude drivers with vehicles under maintenance
$stmt = $db->prepare("
    SELECT d.*, v.vehicle_type, v.vehicle_status 
    FROM drivers d
    LEFT JOIN vehicles v ON d.driver_id = v.driver_id
    WHERE d.status = 'Active' 
    AND (v.vehicle_status = 'Available' OR v.vehicle_status IS NULL)
    AND (v.vehicle_status != 'Under Maintenance' OR v.vehicle_status IS NULL)
");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $driver_id = $_POST['driver_id'] ?? '';
    $tracking_number = $_POST['tracking_number'] ?? '';

    if (empty($driver_id)) {
        $error = "Please select a driver";
    } else {
        try {
            $db->beginTransaction();

            // Update order status and assign driver
            $stmt = $db->prepare("
                UPDATE orders 
                SET status = 'Shipped',
                    driver_id = :driver_id,
                    tracking_number = :tracking_number
                WHERE order_id = :order_id
            ");
            $stmt->execute([
                ':driver_id' => $driver_id,
                ':tracking_number' => $tracking_number,
                ':order_id' => $order_id
            ]);

            // Update vehicle status to 'In Use'
            $stmt = $db->prepare("
                UPDATE vehicles 
                SET vehicle_status = 'In Use' 
                WHERE driver_id = :driver_id
            ");
            $stmt->execute([':driver_id' => $driver_id]);

            // Update inventory
            foreach ($inventory as $item) {
                $productId = $item['product_id'];
                $orderItemStmt = $db->prepare("SELECT quantity FROM order_items WHERE order_id = :order_id AND product_id = :product_id");
                $orderItemStmt->bindParam(':order_id', $order_id);
                $orderItemStmt->bindParam(':product_id', $productId);
                $orderItemStmt->execute();
                $orderItem = $orderItemStmt->fetch(PDO::FETCH_ASSOC);

                $updateInventoryStmt = $db->prepare("UPDATE inventory SET stock_quantity = stock_quantity - :quantity WHERE product_id = :product_id");
                $updateInventoryStmt->bindParam(':quantity', $orderItem['quantity']);
                $updateInventoryStmt->bindParam(':product_id', $productId);
                $updateInventoryStmt->execute();
            }

            $db->commit();
            $_SESSION['success'] = "Order #$order_id has been dispatched successfully! Tracking Number: $tracking_number";
            header('Location: dispatch.php');
            exit();

        } catch (Exception $e) {
            $db->rollBack();
            $error = "Error dispatching order: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispatch Order #<?= htmlspecialchars($order_id) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/orders.css" rel="stylesheet">
</head>

<body>
    <?php include 'includes/nav/collapsed.php'; ?>
    <?php include 'includes/theme.php'; ?>
    <div class="container mt-4">
        <h2>Dispatch Order #<?= htmlspecialchars($order_id) ?></h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Order Details Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Order Details</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Customer:</strong>
                            <?= !empty($order['username']) ? htmlspecialchars($order['username']) : 'Unknown' ?></p>
                        <p><strong>Shipping Address:</strong> <?= htmlspecialchars($order['shipping_address']) ?></p>
                        <p><strong>Payment Status:</strong> 
                            <span class="status-badge badge-<?= strtolower($order['payment_status']) ?>">
                                <?= htmlspecialchars($order['payment_status']) ?>
                            </span>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Order Date:</strong> <?= date('M j, Y H:i', strtotime($order['order_date'])) ?></p>
                      <p><strong>Shipping Method:</strong> <?= htmlspecialchars($order['shipping_method']) ?></p>
                    </div>
                </div>

                <!-- Add Order Items Table -->
                <div class="table-responsive mt-3">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="text-end">Quantity</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orderItems as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['product_name']) ?></td>
                                    <td class="text-end"><?= htmlspecialchars($item['quantity']) ?></td>
                                    <td class="text-end">Ksh.<?= number_format($item['unit_price'], 2) ?></td>
                                    <td class="text-end">Ksh.<?= number_format($item['line_total'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <td colspan="3" class="text-end"><strong>Total Amount:</strong></td>
                                <td class="text-end"><strong>Ksh.<?= number_format($order['total_amount'], 2) ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Driver Assignment Form -->
        <form method="POST" class="card">
            <div class="card-header">
                <div class="mb-3 d-flex justify-content-between align-items-center">
                <h5> <label for="driver_id" class="form-label">Assign Driver</label></h5>
               <a href="dispatch_order.php?order_id=<?= $order_id ?>" class="btn btn-sm btn-secondary">
                    Refresh Drivers
                </a> </div>
            </div>
            <div class="card-body">
                <?php if (empty($available_drivers)): ?>
                    <div class="alert alert-warning">No available drivers found.</div>
                <?php else: ?>
                    <div class="mb-3">
                        <label for="driver_id" class="form-label">Select Driver</label>
                        <select name="driver_id" id="driver_id" class="form-select" required>
                            <option value="">Choose a driver...</option>
                            <?php foreach ($available_drivers as $driver): ?>
                                <option value="<?= $driver['driver_id'] ?>">
                                    <?= htmlspecialchars($driver['name']) ?>
                                    (<?= htmlspecialchars($driver['vehicle_type'] ?? 'No vehicle assigned') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Order Tracking Number</label>
                        <input type="text" class="form-control" 
                               value="<?= htmlspecialchars($order['tracking_number']) ?>"
                               readonly>
                        <small class="text-muted">Original tracking number</small>
                    </div>
                    <button type="submit" class="btn btn-primary">Dispatch Order</button>
                <?php endif; ?>
                <a href="dispatch.php" class="btn btn-secondary ms-2">Cancel</a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php include 'includes/nav/footer.php'; ?>

</html>