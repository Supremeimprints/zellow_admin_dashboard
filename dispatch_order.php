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

// Get order_id from URL and validate
$orderId = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;

if (!$orderId) {
    $_SESSION['error'] = "Invalid order ID";
    header('Location: dispatch.php');
    exit();
}

// Fetch order details including payment status
try {
    $stmt = $db->prepare("
        SELECT o.*,
               u.username,
               u.email,
               GROUP_CONCAT(DISTINCT CONCAT(p.product_name, ' (', oi.quantity, ')') SEPARATOR ', ') as products,
               SUM(oi.subtotal) as original_amount,
               o.discount_amount,
               o.shipping_fee,
               o.total_amount
        FROM orders o
        LEFT JOIN users u ON o.id = u.id
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.product_id
        WHERE o.order_id = :order_id
        GROUP BY o.order_id");

    $stmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception("Order not found");
    }

    // Fetch individual order items for detailed display
    $itemsStmt = $db->prepare("
        SELECT oi.*, p.product_name, 
               (oi.quantity * oi.unit_price) as line_total
        FROM order_items oi
        JOIN products p ON oi.product_id = p.product_id
        WHERE oi.order_id = :order_id");
    $itemsStmt->bindParam(':order_id', $orderId);
    $itemsStmt->execute();
    $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Initialize tracking number if not exists
    if (empty($order['tracking_number'])) {
        $order['tracking_number'] = getOrCreateTrackingNumber($db, $orderId);
    }

} catch (Exception $e) {
    $_SESSION['error'] = "Error fetching order details: " . $e->getMessage();
    header('Location: dispatch.php');
    exit();
}

// Check inventory levels
$inventoryStmt = $db->prepare("SELECT product_id, stock_quantity FROM inventory WHERE product_id IN (SELECT product_id FROM order_items WHERE order_id = :order_id)");
$inventoryStmt->bindParam(':order_id', $orderId);
$inventoryStmt->execute();
$inventory = $inventoryStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($inventory as $item) {
    $productId = $item['product_id'];
    $stockQuantity = $item['stock_quantity'];

    $orderItemStmt = $db->prepare("SELECT quantity FROM order_items WHERE order_id = :order_id AND product_id = :product_id");
    $orderItemStmt->bindParam(':order_id', $orderId);
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

        // Remove updated_at from the query
        $stmt = $db->prepare("
            UPDATE orders 
            SET 
                status = ?,
                driver_id = ?,
                tracking_number = ?,
                delivery_date = ?
            WHERE order_id = ?
        ");

        $stmt->execute([
            $_POST['status'],
            $_POST['driver_id'],
            $_POST['tracking_number'],
            $_POST['delivery_date'],
            $orderId
        ]);

        // Log with current timestamp
        $logStmt = $db->prepare("
            INSERT INTO dispatch_history (
                order_id,
                driver_id,
                status,
                tracking_number,
                assigned_by,
                scheduled_delivery,
                notes,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");

        $logStmt->execute([
            $orderId,
            $_POST['driver_id'],
            $_POST['status'],
            $_POST['tracking_number'],
            $_SESSION['id'],
            $_POST['delivery_date'],
            $_POST['notes'] ?? null
        ]);

        $db->commit();
        $_SESSION['success'] = "Order dispatched successfully";
        header("Location: dispatch.php");
        exit();

    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
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
                ':order_id' => $orderId
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
                $orderItemStmt->bindParam(':order_id', $orderId);
                $orderItemStmt->bindParam(':product_id', $productId);
                $orderItemStmt->execute();
                $orderItem = $orderItemStmt->fetch(PDO::FETCH_ASSOC);

                $updateInventoryStmt = $db->prepare("UPDATE inventory SET stock_quantity = stock_quantity - :quantity WHERE product_id = :product_id");
                $updateInventoryStmt->bindParam(':quantity', $orderItem['quantity']);
                $updateInventoryStmt->bindParam(':product_id', $productId);
                $updateInventoryStmt->execute();
            }

            $db->commit();
            $_SESSION['success'] = "Order #$orderId has been dispatched successfully! Tracking Number: $tracking_number";
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
    <title>Dispatch Order #<?= htmlspecialchars($orderId) ?></title>
    <!-- Feather Icons - Add this line -->
    <script src="https://unpkg.com/feather-icons"></script>

    <!-- Existing stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   
    <link rel="stylesheet" href="assets/css/collapsed.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/orders.css" rel="stylesheet">
    <link href="assets/css/badges.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/orders.css" rel="stylesheet">
    <style>
        .receipt-container {
            background-color: -var(--bs-light);
            padding: 20px;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            font-size: 14px;
            line-height: 1.5;
            font-family: 'Courier New', Courier, monospace
        }
        .order-sections {
            display: grid;
            grid-gap: 2rem;
            margin-top: 2rem;
        }
        .status-badge {
            font-size: 0.9rem;
            padding: 0.5em 1.5em;
            border-radius: 50px;
        }
        .status-badge.processing {
            background-color: #17a2b8;
            color: white;
        }
        .status-badge.pending {
            background-color: #ffc107;
            color: #000;
        }
        .card-header-custom {
            background-color: -var(--bs-light);
            border-bottom: 1px solid rgba(0,0,0,.125);
            padding: 1rem 1.25rem;
        }
        .section-header {
            margin-bottom: 1.5rem;
            color: #495057;
            font-size: 1.25rem;
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
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <!-- Main header with order number -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="mb-0">Order #<?= htmlspecialchars($orderId) ?></h2>
                        <span class="status-badge <?= strtolower($order['status']) ?>">
                            <?= htmlspecialchars($order['status']) ?>
                        </span>
                    </div>

                    <div class="order-sections">
                        <!-- Order Details Section -->
                        <section>
                            <h3 class="section-header">Order Information</h3>
                            <div class="card mb-4">
                                <!-- Receipt content here -->
                                <div class="card-body">
                                    <div class="receipt-container">
                                        <div class="text-center mb-4">
                                            <h4 class="mb-0">ORDER DETAILS</h4>
                                            <div class="mb-2"><?= date('d/m/Y H:i', strtotime($order['order_date'])) ?></div>
                                            <div><?= str_repeat('-', 60) ?></div>
                                        </div>

                                        <!-- Customer Info Section -->
                                        <div class="row mb-3">
                                            <div class="col-6">
                                                <div>Customer: <?= htmlspecialchars($order['username']) ?></div>
                                                <div>Email: <?= htmlspecialchars($order['email']) ?></div>
                                                
                                            </div>
                                            <div class="col-6 text-end">
                                                <div>Payment: <?= htmlspecialchars($order['payment_method']) ?></div>
                                                <div>Status: 
                                                    <?php if ($order['payment_status'] === 'Pending'): ?>
                                                        <span class="text-warning">
                                                            <?= htmlspecialchars($order['payment_status']) ?>
                                                        </span>
                                                    <?php elseif ($order['payment_status'] === 'Paid'): ?>
                                                        <span class="text-success">
                                                            <?= htmlspecialchars($order['payment_status']) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <?= htmlspecialchars($order['payment_status']) ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div>Shipping: <?= htmlspecialchars($order['shipping_method']) ?></div>
                                            </div>
                                        </div>

                                        <div><?= str_repeat('=', 60) ?></div>

                                        <!-- Items Table -->
                                        <div class="mt-3">
                                            <div class="row fw-bold">
                                                <div class="col-4">Item</div>
                                                <div class="col-2 text-end">Qty</div>
                                                <div class="col-3 text-end">Price</div>
                                                <div class="col-3 text-end">Total</div>
                                            </div>
                                            <div><?= str_repeat('-', 60) ?></div>
                                            
                                            <?php foreach ($orderItems as $item): 
                                                $lineTotal = $item['quantity'] * $item['unit_price'];
                                            ?>
                                                <div class="row">
                                                    <div class="col-4"><?= htmlspecialchars($item['product_name']) ?></div>
                                                    <div class="col-2 text-end"><?= $item['quantity'] ?></div>
                                                    <div class="col-3 text-end"><?= number_format($item['unit_price'], 2) ?></div>
                                                    <div class="col-3 text-end"><?= number_format($lineTotal, 2) ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                            
                                            <div><?= str_repeat('-', 60) ?></div>

                                            <!-- Totals Section -->
                                            <div class="row">
                                                <div class="col-9 text-end">Subtotal:</div>
                                                <div class="col-3 text-end"><?= number_format($order['original_amount'], 2) ?></div>
                                            </div>
                                            <?php if ($order['discount_amount'] > 0): ?>
                                                <div class="row text-danger">
                                                    <div class="col-9 text-end">Discount:</div>
                                                    <div class="col-3 text-end">-<?= number_format($order['discount_amount'], 2) ?></div>
                                                </div>
                                            <?php endif; ?>
                                            <div class="row">
                                                <div class="col-9 text-end">Shipping Fee:</div>
                                                <div class="col-3 text-end"><?= number_format($order['shipping_fee'], 2) ?></div>
                                            </div>
                                            <div><?= str_repeat('=', 60) ?></div>
                                            <div class="row fw-bold">
                                                <div class="col-9 text-end">TOTAL:</div>
                                                <div class="col-3 text-end">KSH <?= number_format($order['total_amount'], 2) ?></div>
                                            </div>
                                        </div>

                                        <!-- Shipping Details -->
                                        <div class="mt-4">
                                            <div><?= str_repeat('-', 60) ?></div>
                                            <div class="mt-2">
                                                <strong>Delivery Address:</strong><br>
                                                <?= nl2br(htmlspecialchars($order['shipping_address'])) ?>
                                            </div>
                                            <?php if ($order['tracking_number']): ?>
                                                <div class="mt-2">
                                                    <strong>Tracking Number:</strong><br>
                                                    <?= htmlspecialchars($order['tracking_number']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Dispatch Section -->
                        <section>
                            <h3 class="section-header">Dispatch Information</h3>
                            <div class="card">
                                <div class="card-header-custom">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">Assign Driver</h5>
                                        <a href="dispatch_order.php?order_id=<?= $orderId ?>" 
                                           class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-sync-alt"></i> Refresh Drivers
                                        </a>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($available_drivers)): ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            No available drivers found.
                                        </div>
                                    <?php else: ?>
                                        <form method="POST" action="">
                                            <div class="row">
                                                <div class="col-md-8 mb-3">
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
                                                <div class="col-md-4 mb-3">
                                                    <label class="form-label">Tracking Number</label>
                                                    <input type="text" class="form-control" name="tracking_number"
                                                           value="<?= htmlspecialchars($order['tracking_number']) ?>" readonly>
                                                </div>
                                            </div>
                                            <div class="d-flex justify-content-between mt-3">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-truck me-2"></i>Dispatch Order
                                                </button>
                                                <a href="dispatch.php" class="btn btn-danger ms-auto">
                                                    <i class="fas fa-times me-2"></i>Cancel
                                                </a>
                                            </div>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php include 'includes/nav/footer.php'; ?>

</html>
<?php
// ...existing initialization code...

// Update the order query to include all necessary fields
$orderQuery = "SELECT o.*,
               u.username,
               u.email,
               GROUP_CONCAT(DISTINCT CONCAT(p.product_name, ' (', oi.quantity, ')') SEPARATOR ', ') as products,
               SUM(oi.subtotal) as original_amount,
               o.discount_amount,
               o.shipping_fee,
               o.total_amount
          FROM orders o
          LEFT JOIN users u ON o.id = u.id
          LEFT JOIN order_items oi ON o.order_id = oi.order_id
          LEFT JOIN products p ON oi.product_id = p.product_id
          WHERE o.order_id = ?
          GROUP BY o.order_id";

$stmt = $db->prepare($orderQuery);
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

// ...existing code...

// In the HTML section, update the order summary card:
?>


<!-- Keep existing driver assignment form -->
<?php
// ... rest of existing code ...