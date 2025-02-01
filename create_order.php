<?php
session_start();

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

$error = null;

// Generate unique tracking number
function generateTrackingNumber() {
    $prefix = '#';
    $datePart = date('YmdHis');
    $randomPart = bin2hex(random_bytes(3));
    return $prefix . '-' . $datePart . '-' . strtoupper($randomPart);
}

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

        $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        if (!$productId) throw new Exception("Invalid product selection");

        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]);
        if (!$quantity) throw new Exception("Quantity must be at least 1");

        $total_amount = filter_input(INPUT_POST, 'total_amount', FILTER_VALIDATE_FLOAT, [
            'options' => ['min_range' => 0]
        ]);
        if (!$total_amount) throw new Exception("Invalid price per item");

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

        // Generate tracking number
        $trackingNumber = generateTrackingNumber();

        // Calculate total price based on quantity and total_amount (price per unit)
        $total_price = $total_amount * $quantity;

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
    id, username, product_id, quantity, total_amount,
    status, payment_status, payment_method,
    shipping_address, shipping_method, order_date, tracking_number
) VALUES (
    :id, :username, :product_id, :quantity, :total_amount,
    'Pending', 'Pending', :payment_method,
    :shipping_address, :shipping_method, CURRENT_TIMESTAMP, :tracking_number
)";

$orderStmt = $db->prepare($orderQuery);
$orderStmt->execute([
    ':id' => $customerId,
    ':username' => $customerUsername, // Use the fetched or new username
    ':product_id' => $productId,
    ':quantity' => $quantity,
    ':total_amount' => $total_price,
    ':payment_method' => $paymentMethod,
    ':shipping_address' => $shippingAddress,
    ':shipping_method' => $shippingMethod,
    ':tracking_number' => $trackingNumber
]);

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
    <style>
        .form-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }
    </style>
</head>
<body>
<?php include 'includes/nav/collapsed.php'; ?>

    <div class="container mt-5">
        <h2 class="mb-4">Create New Order</h2>
        
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
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="product_id" class="form-label">Product</label>
                        <select name="product_id" id="product_id" class="form-select" required>
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
                        <input type="number" name="quantity" id="quantity" 
                               class="form-control" min="1" value="1" required>
                    </div>
                    <div class="col-md-3">
                        <label for="total_amount" class="form-label">Price per Unit</label>
                        <input type="number" name="total_amount" id="total_amount" 
                               class="form-control" step="0.01" required>
                    </div>
                    <div class="col-md-6">
                        <label for="total_price" class="form-label">Total Price</label>
                        <input type="text" id="total_price" class="form-control" 
                               readonly value="0.00">
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

            <div class="mt-4">
                <button type="submit" class="btn btn-primary btn-lg">Create Order</button>
                <a href="orders.php" class="btn btn-secondary btn-lg">Cancel</a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Real-time price calculation
        function calculateTotal() {
            const pricePerUnit = parseFloat(document.getElementById('total_amount').value) || 0;
            const quantity = parseInt(document.getElementById('quantity').value) || 0;
            document.getElementById('total_price').value = (pricePerUnit * quantity).toFixed(2);
        }

        // Product selection handler
        document.getElementById('product_id').addEventListener('change', function() {
            const price = this.options[this.selectedIndex]?.dataset?.price || 0;
            document.getElementById('total_amount').value = price;
            calculateTotal();
        });

        // Update handlers
        document.getElementById('quantity').addEventListener('input', calculateTotal);
        document.getElementById('total_amount').addEventListener('input', calculateTotal);

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