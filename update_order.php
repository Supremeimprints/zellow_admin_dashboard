<?php
session_start();

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions/transaction_functions.php'; // Add this line to include transaction functions
require_once 'includes/functions/order_functions.php'; // Add this line
require_once 'includes/functions/badge_functions.php'; // Add this line

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

        // Remove updated_at from the query
        $stmt = $db->prepare("
            UPDATE orders 
            SET 
                status = ?,
                payment_status = ?
            WHERE order_id = ?
        ");

        $stmt->execute([
            $_POST['status'],
            $_POST['payment_status'],
            $orderId
        ]);

        // Log the status change
        $logStmt = $db->prepare("
            INSERT INTO order_status_history (
                order_id, 
                status, 
                payment_status,
                updated_by,
                notes,
                created_at
            ) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");

        $logStmt->execute([
            $orderId,
            $_POST['status'],
            $_POST['payment_status'],
            $_SESSION['id'],
            $_POST['notes'] ?? null
        ]);

        $db->commit();
        $_SESSION['success'] = "Order status updated successfully";
        header("Location: orders.php");
        exit();

    } catch (Exception $e) {
        $db->rollBack();
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
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/badges.css" rel="stylesheet">
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
            // Update both display and hidden field
            document.getElementById('total_amount').value = totalAmount.toFixed(2);
            document.getElementById('total_amount_hidden').value = totalAmount.toFixed(2);
        }
    </script>
</head>
<body>
<div class="admin-layout"> 
<?php include 'includes/theme.php'; ?>
    <nav class="navbar">
    <?php include 'includes/nav/collapsed.php'; ?>
    </nav>
    <div class="main-content">
        <div class="container-fluid p-3">
            <div class="row g-3">
                <div class="col-12">
                    <div class="container mt-5">
                        <div class="alert alert-primary" role="alert">
                            <h4 class="mb-0">Update Order #<?= htmlspecialchars($orderId) ?></h4>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="grid-container">
                                <div class="form-section">
                                    <h4 class="mb-3">Customer Information</h4>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Email</label>
                                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($order['email']) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Username</label>
                                            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($order['username']) ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-section">
                                    <h4 class="mb-3">Order Information</h4>
                                    <div class="row g-3">
                                        <!-- Order Status -->
                                        <div class="col-md-4">
                                            <label class="form-label">Order Status</label>
                                            <select name="status" class="form-select" required>
                                                <?php
                                                $statuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
                                                foreach ($statuses as $status) {
                                                    $selected = ($order['status'] === $status) ? 'selected' : '';
                                                    echo "<option value=\"$status\" $selected>$status</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>

                                        <!-- Payment Status -->
                                        <div class="col-md-4">
                                            <label class="form-label">Payment Status</label>
                                            <select name="payment_status" class="form-select" required>
                                                <?php
                                                $paymentStatuses = ['Pending', 'Paid', 'Failed', 'Refunded'];
                                                foreach ($paymentStatuses as $status) {
                                                    $selected = ($order['payment_status'] === $status) ? 'selected' : '';
                                                    echo "<option value=\"$status\" $selected>$status</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>

                                        <!-- Payment Method -->
                                        <div class="col-md-4">
                                            <label class="form-label">Payment Method</label>
                                            <select name="payment_method" class="form-select" required>
                                                <?php
                                                $paymentMethods = ['Mpesa', 'Airtel Money', 'Credit Card', 'Cash On Delivery'];
                                                foreach ($paymentMethods as $method) {
                                                    $selected = ($order['payment_method'] === $method) ? 'selected' : '';
                                                    echo "<option value=\"$method\" $selected>$method</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-section">
                                    <h4 class="mb-3">Shipping Details</h4>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Shipping Method</label>
                                            <select name="shipping_method" class="form-select" required>
                                                <?php
                                                $shippingMethods = [
                                                    'Standard' => 'Standard (3-5 days)',
                                                    'Express' => 'Express (2 days)',
                                                    'Next Day' => 'Next Day'
                                                ];
                                                foreach ($shippingMethods as $value => $label) {
                                                    $selected = ($order['shipping_method'] === $value) ? 'selected' : '';
                                                    echo "<option value=\"$value\" $selected>$label</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">Tracking Number</label>
                                            <input type="text" name="tracking_number" class="form-control" 
                                                value="<?= htmlspecialchars($order['tracking_number']) ?>"
                                                readonly>
                                            <small class="text-muted">Tracking numbers cannot be modified</small>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">Delivery Date</label>
                                            <input type="date" name="delivery_date" class="form-control" 
                                                value="<?= htmlspecialchars($order['delivery_date']) ?>">
                                        </div>

                                        <div class="col-md-12">
                                            <label class="form-label">Shipping Address</label>
                                            <textarea name="shipping_address" class="form-control" rows="3" required><?= htmlspecialchars($order['shipping_address']) ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Order Items Section -->
                                <div class="form-section">
                                    <h4 class="mb-3">Order Items</h4>
                                    <div id="products-container">
                                        <?php foreach ($orderItems as $index => $item): ?>
                                            <div class="row g-3 product-item mb-3">
                                                <div class="col-md-6">
                                                    <label class="form-label">Product</label>
                                                    <select name="products[<?= $index ?>][product_id]" class="form-select product-select" required>
                                                        <option value="">Select Product</option>
                                                        <?php foreach ($products as $product): ?>
                                                            <option value="<?= $product['product_id'] ?>" 
                                                                    data-price="<?= $product['price'] ?>"
                                                                    <?= ($product['product_id'] == $item['product_id']) ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($product['product_name']) ?> - 
                                                                Ksh. <?= number_format($product['price'], 2) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Quantity</label>
                                                    <input type="number" name="products[<?= $index ?>][quantity]" 
                                                        class="form-control quantity-input" min="1" 
                                                        value="<?= htmlspecialchars($item['quantity']) ?>" required>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label">Price per Unit</label>
                                                    <input type="number" name="products[<?= $index ?>][unit_price]" 
                                                        class="form-control unit-price-input" step="0.01" 
                                                        value="<?= htmlspecialchars($item['unit_price']) ?>" required>
                                                </div>
                                                <div class="col-12 text-end">
                                                    <button type="button" class="btn btn-danger remove-product">Remove</button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" id="add-product" class="btn btn-secondary mt-3">Add Another Product</button>
                                </div>

                                <div class="form-section">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label class="form-label">Total Amount</label>
                                            <input type="hidden" name="total_amount" id="total_amount_hidden">
                                            <input type="text" id="total_amount" class="form-control" 
                                                value="<?= number_format($order['total_amount'], 2) ?>" readonly>
                                        </div>
                                    </div>
                                </div>

                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="mb-0">Order Summary</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row mb-3">
                                            <div class="col-md-4">
                                                <label class="form-label">Original Amount:</label>
                                                <div class="form-control-plaintext">
                                                    Ksh. <?= number_format($order['total_amount'] + $order['discount_amount'], 2) ?>
                                                </div>
                                            </div>
                                            <?php if ($order['discount_amount'] > 0): ?>
                                                <div class="col-md-4">
                                                    <label class="form-label">Discount Applied:</label>
                                                    <div class="form-control-plaintext text-success">
                                                        -Ksh. <?= number_format($order['discount_amount'], 2) ?>
                                                        <?php if ($order['coupon_id']): ?>
                                                            <small class="text-muted d-block">
                                                                Coupon: <?= getCouponCode($db, $order['coupon_id']) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                            <div class="col-md-4">
                                                <label class="form-label">Final Amount:</label>
                                                <div class="form-control-plaintext fw-bold">
                                                    Ksh. <?= number_format($order['total_amount'], 2) ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between mt-4">
                                    <button type="submit" class="btn btn-primary">Update Order</button>
                                    <a href="orders.php" class="btn btn-danger">Cancel</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
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
            // Update both display and hidden field
            document.getElementById('total_amount').value = totalAmount.toFixed(2);
            document.getElementById('total_amount_hidden').value = totalAmount.toFixed(2);
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

