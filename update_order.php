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

// Fetch order details along with product details
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

// Fetch order items
$query = "SELECT oi.*, p.product_name, p.price AS product_price 
          FROM order_items oi
          JOIN products p ON oi.product_id = p.product_id
          WHERE oi.order_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$orderId]);
$orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch products for dropdown
try {
    $productQuery = "SELECT product_id, product_name, price FROM products";
    $productStmt = $db->prepare($productQuery);
    $productStmt->execute();
    $products = $productStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Error fetching products: " . $e->getMessage();
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        $oldStatus = $order['status'];
        $newStatus = $_POST['status'];
        $paymentStatus = $_POST['payment_status'];

        // If order is being cancelled or refunded
        if (($newStatus === 'Cancelled' || $paymentStatus === 'Refunded') && $oldStatus !== 'Cancelled') {
            // Restore inventory quantities
            foreach ($orderItems as $item) {
                $updateInventoryQuery = "
                    UPDATE inventory 
                    SET stock_quantity = stock_quantity + :quantity
                    WHERE product_id = :product_id";
                $inventoryStmt = $db->prepare($updateInventoryQuery);
                $inventoryStmt->execute([
                    ':quantity' => $item['quantity'],
                    ':product_id' => $item['product_id']
                ]);
            }

            // Record refund transaction if payment was completed
            if ($order['payment_status'] === 'Paid' && $paymentStatus === 'Refunded') {
                $refundQuery = "
                    INSERT INTO transactions (
                        reference_id, 
                        order_id, 
                        transaction_type, 
                        total_amount, 
                        payment_status,
                        payment_method
                    ) VALUES (
                        :reference_id,
                        :order_id,
                        'Refund',
                        :total_amount,
                        'completed',
                        :payment_method
                    )";
                $refundStmt = $db->prepare($refundQuery);
                $refundStmt->execute([
                    ':reference_id' => 'REF-' . $orderId . '-' . time(),
                    ':order_id' => $orderId,
                    ':total_amount' => $order['total_amount'],
                    ':payment_method' => $order['payment_method']
                ]);
            }
        }

        // Get all form values
        $status = $_POST['status'];
        $paymentStatus = $_POST['payment_status'];
        $trackingNumber = $_POST['tracking_number'];
        $deliveryDate = $_POST['delivery_date'];
        $shippingMethod = $_POST['shipping_method'];
        $shippingAddress = $_POST['shipping_address'];
        $paymentMethod = $_POST['payment_method'];

        // Update order query
        $query = "UPDATE orders SET 
                  status = ?, 
                  payment_status = ?, 
                  tracking_number = ?, 
                  delivery_date = ?, 
                  shipping_method = ?,
                  shipping_address = ?,
                  payment_method = ?
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
            $orderId
        ]);

        // Delete existing order items
        $deleteQuery = "DELETE FROM order_items WHERE order_id = ?";
        $deleteStmt = $db->prepare($deleteQuery);
        $deleteStmt->execute([$orderId]);

        // Insert updated order items
        $totalAmount = 0;
        foreach ($_POST['products'] as $product) {
            $productId = filter_var($product['product_id'], FILTER_VALIDATE_INT);
            if (!$productId) throw new Exception("Invalid product selection");

            $quantity = filter_var($product['quantity'], FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1]
            ]);
            if (!$quantity) throw new Exception("Quantity must be at least 1");

            $unitPrice = filter_var($product['unit_price'], FILTER_VALIDATE_FLOAT, [
                'options' => ['min_range' => 0]
            ]);
            if (!$unitPrice) throw new Exception("Invalid price per item");

            $subtotal = $unitPrice * $quantity;
            $totalAmount += $subtotal;

            $orderItemQuery = "INSERT INTO order_items (
                order_id, product_id, quantity, unit_price, subtotal
            ) VALUES (
                :order_id, :product_id, :quantity, :unit_price, :subtotal
            )";
            $orderItemStmt = $db->prepare($orderItemQuery);
            $orderItemStmt->execute([
                ':order_id' => $orderId,
                ':product_id' => $productId,
                ':quantity' => $quantity,
                ':unit_price' => $unitPrice,
                ':subtotal' => $subtotal
            ]);
        }

        // Update total amount in orders table
        $updateOrderQuery = "UPDATE orders SET total_amount = ? WHERE order_id = ?";
        $updateOrderStmt = $db->prepare($updateOrderQuery);
        $updateOrderStmt->execute([$totalAmount, $orderId]);

        $db->commit();
        header('Location: orders.php?success=Order updated successfully');
        exit();
    } catch (Exception $e) {
        $db->rollBack();
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
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
        }
        .form-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }
        h2, h4 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
        }
        .form-label {
            font-weight: 500;
        }
        .form-control, .form-select {
            font-family: 'Montserrat', sans-serif;
        }
    </style>
    <script>
        function updateTotalAmount() {
            const productItems = document.querySelectorAll('.product-item');
            let totalAmount = 0;
            productItems.forEach(item => {
                const pricePerUnit = parseFloat(item.querySelector('.unit-price-input').value) || 0;
                const quantity = parseInt(item.querySelector('.quantity-input').value) || 0;
                totalAmount += pricePerUnit * quantity;
            });
            document.getElementById('total_amount').value = totalAmount.toFixed(2);
        }
    </script>
</head>
<body>
<?php include 'includes/nav/collapsed.php'; ?>
<?php include 'includes/theme.php'; ?>

    <div class="container mt-5">
        <div class="alert alert-primary" role="alert">
            <h4 class="mb-0">Update Order #<?= htmlspecialchars($orderId) ?></h4>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-section">
                <h4 class="mb-3">Order Information</h4>
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
                </div>
            </div>

            <div class="form-section">
                <h4 class="mb-3">Shipping Details</h4>
                <div class="row g-3">
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
                </div>
            </div>

            <div class="form-section">
                <h4 class="mb-3">Order Details</h4>
                <div id="products-container">
                    <?php foreach ($orderItems as $index => $item): ?>
                        <div class="row g-3 product-item">
                            <div class="col-md-6">
                                <label for="product_id" class="form-label">Product</label>
                                <select name="products[<?= $index ?>][product_id]" class="form-select product-select" required>
                                    <option value="">Select Product</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?= htmlspecialchars($product['product_id']) ?>" 
                                                data-price="<?= htmlspecialchars($product['price']) ?>"
                                                <?= $product['product_id'] == $item['product_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($product['product_name']) ?> - 
                                            Ksh. <?= number_format($product['price'], 2) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="quantity" class="form-label">Quantity</label>
                                <input type="number" name="products[<?= $index ?>][quantity]" class="form-control quantity-input" min="1" value="<?= htmlspecialchars($item['quantity']) ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label for="unit_price" class="form-label">Price per Unit</label>
                                <input type="number" name="products[<?= $index ?>][unit_price]" class="form-control unit-price-input" step="0.01" value="<?= htmlspecialchars($item['unit_price']) ?>" required>
                            </div>
                            <div class="col-md-12 text-end">
                                <button type="button" class="btn btn-danger remove-product">Remove</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" id="add-product" class="btn btn-secondary mt-3">Add Another Product</button>

                <div class="col-md-4 mt-3">
                    <label class="form-label">Total Amount</label>
                    <input type="text" id="total_amount" class="form-control" value="<?= htmlspecialchars($order['total_amount']) ?>" readonly>
                </div>
            </div>

            <div class="d-flex justify-content-between mt-4">
                <button type="submit" class="btn btn-primary">Update Order</button>
                <button type="button" onclick="window.history.back()" class="btn btn-danger">Cancel</button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Real-time price calculation
        function updateTotalAmount() {
            const productItems = document.querySelectorAll('.product-item');
            let totalAmount = 0;
            productItems.forEach(item => {
                const pricePerUnit = parseFloat(item.querySelector('.unit-price-input').value) || 0;
                const quantity = parseInt(item.querySelector('.quantity-input').value) || 0;
                totalAmount += pricePerUnit * quantity;
            });
            document.getElementById('total_amount').value = totalAmount.toFixed(2);
        }

        // Add new product row
        document.getElementById('add-product').addEventListener('click', function() {
            const container = document.getElementById('products-container');
            const index = container.children.length;
            const newItem = document.createElement('div');
            newItem.classList.add('row', 'g-3', 'product-item');
            newItem.innerHTML = `
                <div class="col-md-6">
                    <label for="product_id" class="form-label">Product</label>
                    <select name="products[${index}][product_id]" class="form-select product-select" required>
                        <option value="">Select Product</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= htmlspecialchars($product['product_id']) ?>" 
                                    data-price="<?= htmlspecialchars($product['price']) ?>">
                                <?= htmlspecialchars($product['product_name']) ?> - 
                                Ksh. <?= number_format($product['price'], 2) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="quantity" class="form-label">Quantity</label>
                    <input type="number" name="products[${index}][quantity]" class="form-control quantity-input" min="1" value="1" required>
                </div>
                <div class="col-md-3">
                    <label for="unit_price" class="form-label">Price per Unit</label>
                    <input type="number" name="products[${index}][unit_price]" class="form-control unit-price-input" step="0.01" required>
                </div>
                <div class="col-md-12 text-end">
                    <button type="button" class="btn btn-danger remove-product">Remove</button>
                </div>
            `;
            container.appendChild(newItem);
        });

        // Remove product row
        document.addEventListener('click', function(event) {
            if (event.target.classList.contains('remove-product')) {
                event.target.closest('.product-item').remove();
                updateTotalAmount();
            }
        });

        // Product selection handler
        document.addEventListener('change', function(event) {
            if (event.target.classList.contains('product-select')) {
                const price = event.target.options[event.target.selectedIndex]?.dataset?.price || 0;
                event.target.closest('.product-item').querySelector('.unit-price-input').value = price;
                updateTotalAmount();
            }
        });

        // Update handlers
        document.addEventListener('input', function(event) {
            if (event.target.classList.contains('quantity-input') || event.target.classList.contains('unit-price-input')) {
                updateTotalAmount();
            }
        });

        // Initial calculation
        updateTotalAmount();
    </script>
</body>
<?php include 'includes/nav/footer.php'; ?>
</html>
