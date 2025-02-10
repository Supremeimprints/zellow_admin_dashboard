<?php
session_start();

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';
require_once 'includes/utilities.php';
$database = new Database();
$db = $database->getConnection();

$error = null;



// Fetch products for dropdown
try {
    $productQuery = "SELECT product_id, product_name, price FROM products";
    $productStmt = $db->prepare($productQuery);
    $productStmt->execute();
    $products = $productStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Error fetching products: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        // Validate and sanitize inputs
        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
        if (!$email) throw new Exception("Invalid email address");
        
        $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
        if (empty($username)) throw new Exception("Username is required");

        $shippingMethod = $_POST['shipping_method'] ?? 'Standard';
        $allowedShippingMethods = ['Standard', 'Express', 'Next Day'];
        if (!in_array($shippingMethod, $allowedShippingMethods)) {
            throw new Exception("Invalid shipping method");
        }

        $paymentMethod = $_POST['payment_method'] ?? 'Mpesa';
        $allowedPaymentMethods = ['Mpesa', 'Airtel Money', 'Credit Card', 'Cash On Delivery'];
        if (!in_array($paymentMethod, $allowedPaymentMethods)) {
            throw new Exception("Invalid payment method");
        }

        $shippingAddress = filter_var($_POST['shipping_address'], FILTER_SANITIZE_STRING);
        if (empty($shippingAddress)) throw new Exception("Shipping address is required");

        // Generate tracking number using the standardized function
        $trackingNumber = generateTrackingNumber();

        // Check/create customer
        $customerQuery = "SELECT id, username FROM users WHERE email = :email";  // Also fetch username
        $customerStmt = $db->prepare($customerQuery);
        $customerStmt->execute([':email' => $email]);
        $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);

        if (!$customer) {
            // First check if username already exists
            $checkUsernameStmt = $db->prepare("SELECT username FROM users WHERE username = :username");
            $checkUsernameStmt->execute([':username' => $username]);
            if ($checkUsernameStmt->fetch()) {
                throw new Exception("Username already exists. Please choose a different username.");
            }

            // Create new user
            $createCustomerQuery = "INSERT INTO users (username, email, password, role) 
                                    VALUES (:username, :email, :password, 'customer')";
            $createCustomerStmt = $db->prepare($createCustomerQuery);
            $createCustomerStmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':password' => password_hash('TempPassword123!', PASSWORD_DEFAULT)
            ]);
            $customerId = $db->lastInsertId();
            $customerUsername = $username; // Use the new username
        } else {
            $customerId = $customer['id'];
            $customerUsername = $customer['username']; // Use existing username
        }

        // Insert order with tracking number
        $orderQuery = "INSERT INTO orders (
            id, username, status, payment_status, payment_method,
            shipping_address, shipping_method, order_date, tracking_number
        ) VALUES (
            :id, :username, 'Pending', 'Pending', :payment_method,
            :shipping_address, :shipping_method, CURRENT_TIMESTAMP, :tracking_number
        )";
        $orderStmt = $db->prepare($orderQuery);
        $orderStmt->execute([
            ':id' => $customerId,
            ':username' => $customerUsername, // Use the fetched or new username
            ':payment_method' => $paymentMethod,
            ':shipping_address' => $shippingAddress,
            ':shipping_method' => $shippingMethod,
            ':tracking_number' => $trackingNumber
        ]);
        $orderId = $db->lastInsertId();

        // Insert order items
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

        $db->commit();
        header('Location: orders.php?success=' . urlencode("Order created successfully. Tracking Number: $trackingNumber"));
        exit();
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Error creating order: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Order</title>
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
</head>
<body>
<?php include 'includes/nav/collapsed.php'; ?>
<?php include 'includes/theme.php'; ?>

    <div class="container mt-5">
        <h2>Create New Order</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" class="needs-validation" novalidate>
            <div class="form-section">
                <h4 class="mb-3">Customer Information</h4>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" name="email" id="email" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" name="username" id="username" class="form-control" required>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h4 class="mb-3">Order Details</h4>
                <div id="products-container">
                    <div class="row g-3 product-item">
                        <div class="col-md-6">
                            <label for="product_id" class="form-label">Product</label>
                            <select name="products[0][product_id]" class="form-select product-select" required>
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
                            <input type="number" name="products[0][quantity]" class="form-control quantity-input" min="1" value="1" required>
                        </div>
                        <div class="col-md-3">
                            <label for="unit_price" class="form-label">Price per Unit</label>
                            <input type="number" name="products[0][unit_price]" class="form-control unit-price-input" step="0.01" required>
                        </div>
                        <div class="d-flex justify-content-between">
                        <button type="button" id="add-product" class="btn btn-secondary mt-3">Add Another Product</button>
                            <button type="button" class="btn btn-danger remove-product mt-3">Remove</button>
                        </div>
                    </div>
                </div>
                
            </div>

            <div class="form-section">
                <h4 class="mb-3">Shipping & Payment</h4>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="payment_method" class="form-label">Payment Method</label>
                        <select name="payment_method" id="payment_method" class="form-select" required>
                            <option value="Mpesa">Mpesa</option>
                            <option value="Airtel Money">Airtel Money</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Cash On Delivery">Cash On Delivery</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="shipping_method" class="form-label">Shipping Method</label>
                        <select name="shipping_method" id="shipping_method" class="form-select" required>
                            <option value="Standard">Standard (3-5 days)</option>
                            <option value="Express">Express (2 days)</option>
                            <option value="Next Day">Next Day</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label for="shipping_address" class="form-label">Shipping Address</label>
                        <textarea name="shipping_address" id="shipping_address" 
                                  class="form-control" rows="3" required></textarea>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between">
                <button type="submit" class="btn btn-primary btn-lg">Create Order</button>
                <a href="orders.php" class="btn btn-danger btn-lg">Cancel</a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Real-time price calculation
        function calculateTotal() {
            const productItems = document.querySelectorAll('.product-item');
            productItems.forEach(item => {
                const pricePerUnit = parseFloat(item.querySelector('.unit-price-input').value) || 0;
                const quantity = parseInt(item.querySelector('.quantity-input').value) || 0;
                item.querySelector('.total-price-input').value = (pricePerUnit * quantity).toFixed(2);
            });
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
                calculateTotal();
            }
        });

        // Product selection handler
        document.addEventListener('change', function(event) {
            if (event.target.classList.contains('product-select')) {
                const price = event.target.options[event.target.selectedIndex]?.dataset?.price || 0;
                event.target.closest('.product-item').querySelector('.unit-price-input').value = price;
                calculateTotal();
            }
        });

        // Update handlers
        document.addEventListener('input', function(event) {
            if (event.target.classList.contains('quantity-input') || event.target.classList.contains('unit-price-input')) {
                calculateTotal();
            }
        });

        // Form validation
        (() => {
            'use strict'
            const forms = document.querySelectorAll('.needs-validation')
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()
    </script>
</body>
<?php include 'includes/nav/footer.php'; ?>
</html>