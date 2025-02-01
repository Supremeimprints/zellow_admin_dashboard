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

// Fetch order details along with product price
$query = "SELECT o.*, u.username, u.email, p.price AS product_price 
          FROM orders o
          JOIN users u ON o.id = u.id
          JOIN products p ON o.product_id = p.product_id
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
        // Get all form values
        $status = $_POST['status'];
        $paymentStatus = $_POST['payment_status'];
        $trackingNumber = $_POST['tracking_number'];
        $deliveryDate = $_POST['delivery_date'];
        $shippingMethod = $_POST['shipping_method'];
        $shippingAddress = $_POST['shipping_address'];
        $paymentMethod = $_POST['payment_method'];
        $quantity = $_POST['quantity'];

        // Auto-calculate the total amount
        $totalAmount = $quantity * $order['product_price'];

        // Update order query
        $query = "UPDATE orders SET 
                  status = ?, 
                  payment_status = ?, 
                  tracking_number = ?, 
                  delivery_date = ?, 
                  shipping_method = ?,
                  shipping_address = ?,
                  payment_method = ?,
                  quantity = ?,
                  total_amount = ?
                  WHERE order_id = ?";

        $stmt = $db->prepare($query);
        $stmt->execute([
            $status,
            $paymentStatus,
            $trackingNumber,
            $deliveryDate,
            $shippingMethod,
            $shippingAddress,
            $paymentMethod,
            $quantity,
            $totalAmount,
            $orderId
        ]);

        header('Location: orders.php?success=Order updated successfully');
        exit();
    } catch (Exception $e) {
        $error = "Error updating order: " . $e->getMessage();
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
    <script>
        function updateTotalAmount() {
            let quantity = document.getElementById('quantity').value;
            let pricePerUnit = <?= $order['product_price'] ?>;
            let totalAmount = quantity * pricePerUnit;
            document.getElementById('total_amount').value = totalAmount.toFixed(2);
        }
    </script>
</head>
<body>
<?php include 'includes/nav/collapsed.php'; ?>
    <div class="container mt-5">
        <h2>Update Order #<?= htmlspecialchars($orderId) ?></h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="row g-3">
                <!-- Order Status -->
                <div class="col-md-4">
                    <label class="form-label">Order Status</label>
                    <select name="status" class="form-select" required>
                        <option value="Pending" <?= $order['status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="Processing" <?= $order['status'] === 'Processing' ? 'selected' : '' ?>>Processing</option>
                        <option value="Shipped" <?= $order['status'] === 'Shipped' ? 'selected' : '' ?>>Shipped</option>
                        <option value="Delivered" <?= $order['status'] === 'Delivered' ? 'selected' : '' ?>>Delivered</option>
                        <option value="Cancelled" <?= $order['status'] === 'Cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>

                <!-- Payment Status -->
                <div class="col-md-4">
                    <label class="form-label">Payment Status</label>
                    <select name="payment_status" class="form-select" required>
                        <option value="Pending" <?= $order['payment_status'] === 'Pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="Paid" <?= $order['payment_status'] === 'Paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="Failed" <?= $order['payment_status'] === 'Failed' ? 'selected' : '' ?>>Failed</option>
                        <option value="Refunded" <?= $order['payment_status'] === 'Refunded' ? 'selected' : '' ?>>Refunded</option>
                    </select>
                </div>

                <!-- Payment Method -->
                <div class="col-md-4">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" class="form-select" required>
                        <option value="Mpesa" <?= $order['payment_method'] === 'Mpesa' ? 'selected' : '' ?>>Mpesa</option>
                        <option value="Airtel Money" <?= $order['payment_method'] === 'Airtel Money' ? 'selected' : '' ?>>Airtel Money</option>
                        <option value="Bank" <?= $order['payment_method'] === 'Bank' ? 'selected' : '' ?>>Bank</option>
                    </select>
                </div>

                <!-- Tracking Info -->
                <div class="col-md-6">
                    <label class="form-label">Tracking Number</label>
                    <input type="text" name="tracking_number" class="form-control" value="<?= htmlspecialchars($order['tracking_number']) ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Delivery Date</label>
                    <input type="date" name="delivery_date" class="form-control" value="<?= htmlspecialchars($order['delivery_date']) ?>">
                </div>

                <!-- Shipping Details -->
                <div class="col-md-6">
                    <label class="form-label">Shipping Method</label>
                    <select name="shipping_method" class="form-select" required>
                        <option value="Standard" <?= $order['shipping_method'] === 'Standard' ? 'selected' : '' ?>>Standard</option>
                        <option value="Express" <?= $order['shipping_method'] === 'Express' ? 'selected' : '' ?>>Express</option>
                        <option value="Next Day" <?= $order['shipping_method'] === 'Next Day' ? 'selected' : '' ?>>Next Day</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Shipping Address</label>
                    <textarea name="shipping_address" class="form-control" rows="2" required><?= htmlspecialchars($order['shipping_address']) ?></textarea>
                </div>

                <!-- Order Details -->
                <div class="col-md-4">
                    <label class="form-label">Quantity</label>
                    <input type="number" name="quantity" id="quantity" class="form-control" value="<?= htmlspecialchars($order['quantity']) ?>" min="1" required oninput="updateTotalAmount()">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Total Amount</label>
                    <input type="text" id="total_amount" class="form-control" value="<?= htmlspecialchars($order['total_amount']) ?>" readonly>
                </div>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Update Order</button>
                <button type="button" onclick="window.history.back()" class="btn btn-secondary ms-2">Cancel</button>
            </div>
        </form>
    </div>
</body>
<?php include 'includes/nav/footer.php'; ?>
</html>
