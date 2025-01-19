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

        // Collect and validate form data
        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
        $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
        $productId = filter_input(INPUT_POST, 'product_id', FILTER_SANITIZE_NUMBER_INT);
        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_SANITIZE_NUMBER_INT);
        $totalAmount = filter_input(INPUT_POST, 'total_amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $shippingMethod = $_POST['shipping_method'] ?? null;
        $paymentMethod = $_POST['payment_method'] ?? 'Mpesa';
        $shippingAddress = filter_var($_POST['shipping_address'], FILTER_SANITIZE_STRING);
        
        // Calculate total price
        $totalPrice = $totalAmount * $quantity;

        // Check if the customer exists
        $customerQuery = "SELECT id FROM users WHERE email = :email";
        $customerStmt = $db->prepare($customerQuery);
        $customerStmt->execute([':email' => $email]);
        $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);

        // If customer does not exist, create them
        if (!$customer) {
            $createCustomerQuery = "INSERT INTO users (username, email, password, role) 
                                    VALUES (:username, :email, :password, 'customer')";
            $createCustomerStmt = $db->prepare($createCustomerQuery);
            $createCustomerStmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':password' => password_hash('defaultPassword', PASSWORD_BCRYPT) // Use a default or secure password
            ]);
            $customerId = $db->lastInsertId(); // Get the newly created user's ID
        } else {
            $customerId = $customer['id']; // Use the existing user's ID
        }

        // Insert the order
        $orderQuery = "INSERT INTO orders (
            id, product_id, quantity, total_amount, 
            total_price, status, payment_status, payment_method,
            shipping_address, shipping_method, order_date
        ) VALUES (
            :id, :product_id, :quantity, :total_amount,
            :total_price, 'Pending', 'Pending', :payment_method,
            :shipping_address, :shipping_method, CURRENT_TIMESTAMP
        )";

        $orderStmt = $db->prepare($orderQuery);
        $orderStmt->execute([
            ':id' => $customerId,
            ':product_id' => $productId,
            ':quantity' => $quantity,
            ':total_amount' => $totalAmount,
            ':total_price' => $totalPrice,
            ':payment_method' => $paymentMethod,
            ':shipping_address' => $shippingAddress,
            ':shipping_method' => $shippingMethod
        ]);

        $db->commit();
        header('Location: orders.php?success=' . urlencode('Order created successfully'));
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
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container mt-5">
        <h2>Create New Order</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" class="needs-validation" novalidate>
            <!-- Customer Information -->
            <div class="mb-3">
                <label for="email" class="form-label">Customer Email</label>
                <input type="email" name="email" id="email" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="username" class="form-label">Customer Username</label>
                <input type="text" name="username" id="username" class="form-control" required>
            </div>

            <!-- Product Selection -->
            <div class="mb-3">
                <label for="product_id" class="form-label">Product</label>
                <select name="product_id" id="product_id" class="form-control" required>
                    <option value="">Select Product</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?php echo htmlspecialchars($product['product_id']); ?>" 
                                data-price="<?php echo htmlspecialchars($product['price']); ?>">
                            <?php echo htmlspecialchars($product['product_name']); ?> - 
                            Ksh. <?php echo htmlspecialchars($product['price']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label for="quantity" class="form-label">Quantity</label>
                <input type="number" name="quantity" id="quantity" class="form-control" required min="1">
            </div>

            <div class="mb-3">
                <label for="total_amount" class="form-label">Amount per Item</label>
                <input type="number" name="total_amount" id="total_amount" class="form-control" required min="0" step="0.01">
            </div>

            <!-- Payment Method -->
            <div class="mb-3">
                <label for="payment_method" class="form-label">Payment Method</label>
                <select name="payment_method" id="payment_method" class="form-control" required>
                    <option value="Mpesa">Mpesa</option>
                    <option value="Airtel money">Airtel Money</option>
                    <option value="Bank">Bank</option>
                </select>
            </div>

            <!-- Shipping Method -->
            <div class="mb-3">
                <label for="shipping_method" class="form-label">Shipping Method</label>
                <select name="shipping_method" id="shipping_method" class="form-control">
                    <option value="Standard">Standard</option>
                    <option value="Express">Express</option>
                    <option value="Next Day">Next Day</option>
                </select>
            </div>

            <!-- Shipping Address -->
            <div class="mb-3">
                <label for="shipping_address" class="form-label">Shipping Address</label>
                <textarea name="shipping_address" id="shipping_address" class="form-control" required rows="3"></textarea>
            </div>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary">Create Order</button>
                <a href="orders.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Auto-fill total_amount when product is selected
    document.getElementById('product_id').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const price = selectedOption.getAttribute('data-price');
        document.getElementById('total_amount').value = price;
    });
    </script>
</body>
</html>
