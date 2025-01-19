<?php
session_start();

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

$orderId = $_GET['id'] ?? null;
if (!$orderId) {
    header('Location: orders.php');
    exit();
}

// Fetch order details
$query = "SELECT o.*, u.username, u.email 
          FROM orders o 
          JOIN users u ON o.id = u.id 
          WHERE o.order_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$orderId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: orders.php');
    exit();
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $status = $_POST['status'] ?? $order['status'];
        $paymentStatus = $_POST['payment_status'] ?? $order['payment_status'];
        $trackingNumber = $_POST['tracking_number'] ?? null;
        $deliveryDate = $_POST['delivery_date'] ?? null;
        $shippingMethod = $_POST['shipping_method'] ?? $order['shipping_method'];

        // Update order query
        $query = "UPDATE orders SET 
                  status = ?, 
                  payment_status = ?, 
                  tracking_number = ?, 
                  delivery_date = ?, 
                  shipping_method = ? 
                  WHERE order_id = ?";
                  
        $stmt = $db->prepare($query);
        $stmt->execute([
            $status,
            $paymentStatus,
            $trackingNumber,
            $deliveryDate,
            $shippingMethod,
            $orderId
        ]);

        header('Location: orders.php?success=Order updated successfully');
        exit();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Order</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container mt-5">
        <h2>Update Order #<?php echo htmlspecialchars($orderId); ?></h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" class="mb-4">
            <!-- Order Status -->
            <div class="mb-3">
                <label for="status" class="form-label">Order Status</label>
                <select name="status" id="status" class="form-control" required>
                    <option value="Pending" <?php echo $order['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Processing" <?php echo $order['status'] === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                    <option value="Shipped" <?php echo $order['status'] === 'Shipped' ? 'selected' : ''; ?>>Shipped</option>
                    <option value="Delivered" <?php echo $order['status'] === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                    <option value="Cancelled" <?php echo $order['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>

            <!-- Payment Status -->
            <div class="mb-3">
                <label for="payment_status" class="form-label">Payment Status</label>
                <select name="payment_status" id="payment_status" class="form-control" required>
                    <option value="Pending" <?php echo $order['payment_status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Paid" <?php echo $order['payment_status'] === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="Failed" <?php echo $order['payment_status'] === 'Failed' ? 'selected' : ''; ?>>Failed</option>
                </select>
            </div>

            <!-- Tracking Number -->
            <div class="mb-3">
                <label for="tracking_number" class="form-label">Tracking Number</label>
                <input type="text" name="tracking_number" id="tracking_number" 
                       class="form-control" value="<?php echo htmlspecialchars($order['tracking_number']); ?>">
            </div>

            <!-- Delivery Date -->
            <div class="mb-3">
                <label for="delivery_date" class="form-label">Delivery Date</label>
                <input type="date" name="delivery_date" id="delivery_date" 
                       class="form-control" value="<?php echo htmlspecialchars($order['delivery_date']); ?>">
            </div>

            <!-- Shipping Method -->
            <div class="mb-3">
                <label for="shipping_method" class="form-label">Shipping Method</label>
                <select name="shipping_method" id="shipping_method" class="form-control">
                    <option value="Standard" <?php echo $order['shipping_method'] === 'Standard' ? 'selected' : ''; ?>>Standard</option>
                    <option value="Express" <?php echo $order['shipping_method'] === 'Express' ? 'selected' : ''; ?>>Express</option>
                    <option value="Next Day" <?php echo $order['shipping_method'] === 'Next Day' ? 'selected' : ''; ?>>Next Day</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Update Order</button>
            <a href="orders.php" class="btn btn-secondary">Back to Orders</a>
        </form>
    </div>
</body>
</html>
