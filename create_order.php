<?php
session_start();

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions/order_functions.php';
require_once 'includes/functions/shipping_functions.php';
require_once 'includes/classes/CouponValidator.php';
$database = new Database();
$db = $database->getConnection();

$couponValidator = new CouponValidator($db);

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

// Add this after fetching products
try {
    // Fetch active services from the catalog
    $serviceQuery = "SELECT id, name, description, price, status 
                    FROM services 
                    WHERE status = 'active'
                    ORDER BY name";
    $serviceStmt = $db->prepare($serviceQuery);
    $serviceStmt->execute();
    $services = $serviceStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Error fetching services: " . $e->getMessage();
}

// Add this after database connection
$promotionsQuery = "SELECT * FROM marketing_campaigns WHERE status = 'active' AND CURDATE() BETWEEN start_date AND end_date";
$couponsQuery = "SELECT * FROM coupons 
                 WHERE expiration_date >= CURRENT_DATE() 
                 AND status = 'active'
                 ORDER BY expiration_date ASC";

try {
    $couponsStmt = $db->query($couponsQuery);
    $validCoupons = $couponsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If status column doesn't exist, fall back to simpler query
    if ($e->getCode() == '42S22') { // SQL state for column not found
        $couponsQuery = "SELECT * FROM coupons 
                        WHERE expiration_date >= CURRENT_DATE()
                        ORDER BY expiration_date ASC";
        $couponsStmt = $db->query($couponsQuery);
        $validCoupons = $couponsStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        throw $e; // Re-throw if it's a different error
    }
}

$promotionsStmt = $db->query($promotionsQuery);

$activePromotions = $promotionsStmt->fetchAll(PDO::FETCH_ASSOC);

// Add this to handle AJAX coupon validation
if (isset($_POST['action']) && $_POST['action'] === 'validate_coupon') {
    $couponCode = $_POST['coupon_code'] ?? '';
    $orderTotal = floatval($_POST['order_total'] ?? 0);
    $userId = $_SESSION['id'] ?? null;
    
    $result = $couponValidator->validateCoupon($couponCode, $userId, $orderTotal);
    
    if ($result['valid']) {
        $_SESSION['valid_coupon'] = [
            'code' => $couponCode,
            'discount_type' => $result['discount_type'],
            'discount_value' => $result['discount_value'],
            'coupon_id' => $result['coupon_id']
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

// Add this after database connection
if (isset($_POST['action']) && $_POST['action'] === 'lookup_email') {
    header('Content-Type: application/json');
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    
    if ($email) {
        $stmt = $db->prepare("SELECT username FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'found' => $result !== false,
            'username' => $result ? $result['username'] : ''
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid email']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        // Validate and sanitize inputs
        $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
        if (!$email) throw new Exception("Invalid email address");
        
        $username = filter_var($_POST['username'], FILTER_SANITIZE_STRING);
        if (empty($username)) throw new Exception("Username is required");

        // Update shipping method validation
        $shippingMethodId = filter_var($_POST['shipping_method'] ?? null, FILTER_VALIDATE_INT);
        if (!$shippingMethodId) {
            throw new Exception("Please select a valid shipping method");
        }

        $regionId = filter_var($_POST['region_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$regionId) {
            throw new Exception("Please select a valid delivery region");
        }

        // Validate shipping method exists for region
        if (!isValidShippingMethod($db, $shippingMethodId, $regionId)) {
            throw new Exception("Selected shipping method is not available for this region");
        }

        $paymentMethod = $_POST['payment_method'] ?? null;
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

        // Calculate base total from products
        $total_amount = 0;
        $totalItems = 0;

        foreach ($_POST['products'] as $product) {
            $quantity = (int)$product['quantity'];
            $unit_price = (float)$product['unit_price'];
            $total_amount += ($quantity * $unit_price);
            $totalItems += $quantity;
        }

        // Validate shipping method and region
        $regionId = filter_var($_POST['region_id'], FILTER_VALIDATE_INT);
        $shippingMethodId = filter_var($_POST['shipping_method'], FILTER_VALIDATE_INT);
        
        if (!$regionId || !$shippingMethodId) {
            throw new Exception("Invalid shipping region or method");
        }

        // Validate shipping method exists for region
        if (!isValidShippingMethod($db, $shippingMethodId, $regionId)) {
            throw new Exception("Selected shipping method is not available for this region");
        }

        // Get shipping rate details
        $shippingRate = getShippingRate($db, $shippingMethodId, $regionId);
        if (!$shippingRate) {
            throw new Exception("Could not determine shipping rate");
        }

        // Calculate total items for shipping
        $totalItems = 0;
        foreach ($_POST['products'] as $product) {
            $totalItems += (int)$product['quantity'];
        }

        // Calculate shipping cost using the proper rate
        $shipping_fee = calculateShippingCost($db, $shippingMethodId, $regionId, $totalItems);
        if ($shipping_fee === null) {
            throw new Exception("Could not calculate shipping cost");
        }

        // Initialize discount variables
        $discount_amount = 0;
        $coupon_id = null;

        // Apply discount if coupon is valid
        if (!empty($_POST['coupon_code'])) {
            $result = $couponValidator->validateCoupon(
                $_POST['coupon_code'], 
                $customerId, 
                $total_amount
            );
            
            if (!$result['valid']) {
                throw new Exception($result['message']);
            }
            
            // Calculate discount based on type
            if ($result['discount_type'] === 'percentage') {
                $discount_amount = ($total_amount * $result['discount_value']) / 100;
            } else {
                $discount_amount = $result['discount_value'];
            }
            
            $coupon_id = $result['coupon_id'];
        }

        // Add shipping fee to total
        $final_total = $total_amount - $discount_amount + $shipping_fee;

        // Replace the order creation query with this corrected version
        $orderStmt = $db->prepare("INSERT INTO orders (
            id, 
            email,
            username,
            total_amount,
            discount_amount,
            shipping_fee,
            status,
            shipping_address,
            tracking_number,
            payment_status,
            shipping_method,
            shipping_method_id,
            shipping_region_id,
            payment_method,
            coupon_id,
            product_id,
            quantity,
            price
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        // Get shipping method name from ID
        $methodStmt = $db->prepare("SELECT name FROM shipping_methods WHERE id = ?");
        $methodStmt->execute([$shippingMethodId]);
        $methodName = $methodStmt->fetchColumn();

        // Get first product's details for the main order record
        $firstProduct = $_POST['products'][0];
        
        $orderStmt->execute([
            $customerId,
            $email,
            $customerUsername, // Use the correct username from users table
            $final_total,
            $discount_amount,
            $shipping_fee,
            'Pending',
            $_POST['shipping_address'],
            generateTrackingNumber(),
            'Pending',
            $methodName,          // Keep original shipping_method field
            $shippingMethodId,    // New shipping_method_id field
            $regionId,            // New shipping_region_id field
            $_POST['payment_method'],
            $coupon_id,
            $firstProduct['product_id'],  // First product details
            $firstProduct['quantity'],
            $firstProduct['unit_price']
        ]);

        $orderId = $db->lastInsertId();

        // If there are additional products, create additional order records
        if (count($_POST['products']) > 1) {
            $additionalOrderStmt = $db->prepare("INSERT INTO orders (
                order_id,
                id,
                email,
                username,
                product_id,
                quantity,
                price,
                status,
                payment_status,
                shipping_method_id,
                shipping_region_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

            for ($i = 1; $i < count($_POST['products']); $i++) {
                $product = $_POST['products'][$i];
                $additionalOrderStmt->execute([
                    $orderId,
                    $customerId,
                    $email,
                    $customerUsername, // Use the correct username
                    $product['product_id'],
                    $product['quantity'],
                    $product['unit_price'],
                    'Pending',
                    'Pending',
                    $shippingMethodId,
                    $regionId
                ]);
            }
        }

        // Now record the coupon usage with the valid order ID
        if ($coupon_id) {
            if (!$couponValidator->recordCouponUsage($coupon_id, $customerId, $orderId)) {
                throw new Exception("Error recording coupon usage");
            }
        }

        // Insert order items with correct fields
        foreach ($_POST['products'] as $product) {
            $quantity = (int)$product['quantity'];
            $unit_price = (float)$product['unit_price'];
            $subtotal = $quantity * $unit_price;

            $itemStmt = $db->prepare("INSERT INTO order_items (
                order_id,
                product_id,
                quantity,
                unit_price,
                subtotal,
                status
            ) VALUES (?, ?, ?, ?, ?, 'purchased')");

            $itemStmt->execute([
                $orderId,
                $product['product_id'],
                $quantity,
                $unit_price,
                $subtotal
            ]);
        }

        // Insert order services
        if (!empty($selected_services)) {
            $serviceOrderStmt = $db->prepare("INSERT INTO order_services (
                order_id,
                service_id,
                price,
                status,
                created_at
            ) VALUES (?, ?, ?, 'pending', CURRENT_TIMESTAMP)");

            foreach ($selected_services as $service_id) {
                $serviceStmt = $db->prepare("SELECT price FROM services WHERE id = ?");
                $serviceStmt->execute([$service_id]);
                $service_price = $serviceStmt->fetchColumn();

                $serviceOrderStmt->execute([
                    $orderId,
                    $service_id,
                    $service_price
                ]);
            }
        }

        $db->commit();
        $_SESSION['success'] = "Order created successfully!";
        header('Location: orders.php');
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
        .btn-link {
            text-decoration: none;
            color: var(--bs-body-color);
            transition: transform 0.2s;
        }
        
        .btn-link[aria-expanded="true"] .fa-chevron-down {
            transform: rotate(180deg);
        }
        
        .fa-chevron-down {
            transition: transform 0.2s;
        }
        
        #servicesCollapse {
            transition: all 0.3s ease-in-out;
        }
    </style>
</head>
<body>
<div class="admin-layout"> 
<?php include 'includes/theme.php'; ?>
    <nav class="navbar">
    <?php include 'includes/nav/collapsed.php'; ?>
    </nav>
    <div class="main-content">
        <div class="container mt-5">
            <div class="row g-3">
                <div class="col-12">
                    <h2>Create New Order</h2>
        
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form method="POST" class="needs-validation" novalidate id="orderForm">
                        <div class="form-section">
                           
                            <div class="row g-3">
                                <!-- Customer Information Column -->
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-header">
                                            <h5 class="mb-0">Customer Information</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label for="email" class="form-label">Email</label>
                                                <input type="email" name="email" id="email" class="form-control" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="username" class="form-label">Username</label>
                                                <input type="text" name="username" id="username" class="form-control" required>
                                                <div id="username-feedback" class="form-text"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Giftee Information Column -->
                                <div class="col-md-6">
                                    <div class="card h-100">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h5 class="mb-0">Giftee Information</h5>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" 
                                                       id="enableGiftee" name="is_gift" 
                                                       onchange="toggleGifteeFields(this.checked)">
                                                <label class="form-check-label" for="enableGiftee">
                                                    This is a gift
                                                </label>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div id="gifteeFields" style="opacity: 0.5; pointer-events: none;">
                                                <div class="mb-3">
                                                    <label for="giftee_name" class="form-label">Recipient Name</label>
                                                    <input type="text" name="giftee_name" id="giftee_name" 
                                                           class="form-control">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="giftee_email" class="form-label">Recipient Email</label>
                                                    <input type="email" name="giftee_email" id="giftee_email" 
                                                           class="form-control">
                                                </div>
                                                <div class="mb-3">
                                                    <label for="gift_message" class="form-label">Gift Message</label>
                                                    <textarea name="gift_message" id="gift_message" 
                                                              class="form-control" rows="2"></textarea>
                                                </div>
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input" 
                                                           id="hide_prices" name="hide_prices">
                                                    <label class="form-check-label" for="hide_prices">
                                                        Hide prices in recipient email
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h4 class="mb-0">Order Details</h4>
                                <button type="button" id="add-product" class="btn btn-secondary">Add Another Product</button>
                            </div>
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
                                    <div class="col-md-12 text-end">
                                        <button type="button" class="btn btn-danger remove-product">Remove</button>
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
                                    <label for="region_id" class="form-label">Delivery Region</label>
                                    <?php echo renderRegionDropdown($db); ?>
                                </div>
                                <div class="col-md-4">
                                    <label for="shipping_method" class="form-label">Shipping Method</label>
                                    <select name="shipping_method" id="shipping_method" class="form-select" required disabled>
                                        <option value="">Select Region First</option>
                                    </select>
                                    <div class="form-text">Shipping fee will be calculated based on region and items</div>
                                </div>
                                <div class="col-md-12">
                                    <label for="shipping_address" class="form-label">Shipping Address</label>
                                    <textarea name="shipping_address" id="shipping_address" 
                                            class="form-control" rows="3" required></textarea>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h4 class="mb-3">Promotions & Discounts</h4>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="campaign_id" class="form-label">Select Campaign (Optional)</label>
                                    <select name="campaign_id" id="campaign_id" class="form-select">
                                        <option value="">No Campaign</option>
                                        <?php foreach ($activePromotions as $campaign): ?>
                                            <option value="<?= $campaign['campaign_id'] ?>">
                                                <?= htmlspecialchars($campaign['name']) ?> 
                                                (<?= date('M d', strtotime($campaign['end_date'])) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="coupon_code" class="form-label">Coupon Code</label>
                                    <div class="input-group has-validation">
                                        <input type="text" 
                                               class="form-control" 
                                               name="coupon_code" 
                                               id="couponCode" 
                                               placeholder="Enter coupon code">
                                        <button class="btn btn-outline-secondary" 
                                                type="button" 
                                                onclick="validateCoupon()">Apply</button>
                                        <div class="invalid-feedback" id="couponError"></div>
                                        <div class="valid-feedback" id="couponSuccess"></div>
                                    </div>
                                    <div id="couponFeedback" class="form-text"></div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <h6>Available Coupons:</h6>
                                <div class="list-group">
                                    <?php if (empty($validCoupons)): ?>
                                        <div class="list-group-item text-muted">
                                            No active coupons available
                                        </div>
                                    <?php else: ?>
                                        <?php foreach ($validCoupons as $coupon): 
                                            $isExpired = strtotime($coupon['expiration_date']) < time();
                                            $badgeClass = $isExpired ? 'bg-danger' : 'bg-success';
                                            $status = $isExpired ? 'Expired' : 'Active';
                                        ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <code><?= htmlspecialchars($coupon['code']) ?></code>
                                                    <span class="badge <?= $badgeClass ?> ms-2"><?= $status ?></span>
                                                    <small class="d-block text-muted">
                                                        <?= $coupon['discount_percentage'] ?>% off
                                                        (Expires: <?= date('M d, Y', strtotime($coupon['expiration_date'])) ?>)
                                                    </small>
                                                </div>
                                                <?php if (!$isExpired): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                                            onclick="applyCoupon('<?= $coupon['code'] ?>')">
                                                        Apply
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h4 class="mb-0">Additional Services</h4>
                                <button class="btn btn-link p-0" 
                                        type="button" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#servicesCollapse" 
                                        aria-expanded="false">
                                    <i class="fas fa-chevron-down"></i>
                                </button>
                            </div>
                            <div class="collapse" id="servicesCollapse">
                                <div class="row">
                                    <?php foreach ($services as $service): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card">
                                            <div class="card-body">
                                                <div class="form-check d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <input type="checkbox" 
                                                               class="form-check-input service-checkbox" 
                                                               id="service_<?= $service['id'] ?>" 
                                                               name="services[]"
                                                               value="<?= $service['id'] ?>"
                                                               data-price="<?= $service['price'] ?>">
                                                        <label class="form-check-label" for="service_<?= $service['id'] ?>">
                                                            <?= htmlspecialchars($service['name']) ?>
                                                        </label>
                                                    </div>
                                                    <span class="badge bg-primary">Ksh. <?= number_format($service['price'], 2) ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="row mt-3">
                            <div class="col-md-6 ms-auto">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Products Subtotal:</span>
                                            <span class="text-muted" id="products-subtotal">Ksh. 0.00</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Services Subtotal:</span>
                                            <span class="text-muted" id="services-subtotal">Ksh. 0.00</span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted">Shipping Fee:</span>
                                            <span class="text-muted" id="shipping-fee">Ksh. 0.00</span>
                                        </div>
                                        <div id="discount-row" class="d-flex justify-content-between mb-2" style="display: none !important;">
                                            <span class="text-danger">Discount:</span>
                                            <span class="text-danger" id="discount-amount">-Ksh. 0.00</span>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <strong>Total:</strong>
                                            <strong id="final-total">Ksh. 0.00</strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mt-3">
                            <button type="submit" class="btn btn-primary btn-lg" id="submitOrder">Create Order</button>
                            <a href="orders.php" class="btn btn-danger btn-lg">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentSubtotal = 0;
        let currentDiscount = 0;
        let shippingFee = 0;
        let lastValidShippingFee = 0; // Add this line

        // Real-time price calculation
        function calculateTotal() {
            const productItems = document.querySelectorAll('.product-item');
            currentSubtotal = 0;
            let serviceCosts = 0;
            
            productItems.forEach(item => {
                const quantity = parseInt(item.querySelector('.quantity-input').value) || 0;
                const pricePerUnit = parseFloat(item.querySelector('.unit-price-input').value) || 0;
                currentSubtotal += (pricePerUnit * quantity);
                
                // Add service cost if service is requested
                const serviceToggle = item.querySelector('.service-toggle');
                if (serviceToggle && serviceToggle.checked) {
                    const serviceSelect = item.querySelector('.service-type-select');
                    if (serviceSelect && serviceSelect.value) {
                        const servicePrice = parseFloat(serviceSelect.options[serviceSelect.selectedIndex].dataset.price) || 0;
                        serviceCosts += (servicePrice * quantity);
                    }
                }
            });

            // Update displays
            document.getElementById('subtotal').textContent = `Ksh. ${currentSubtotal.toFixed(2)}`;
            document.getElementById('service-costs').textContent = `Ksh. ${serviceCosts.toFixed(2)}`;
            
            // Update final total including service costs
            const finalTotal = currentSubtotal + serviceCosts - (currentDiscount || 0) + (shippingFee || 0);
            document.getElementById('total').textContent = `Ksh. ${finalTotal.toFixed(2)}`;
        }

        // Add new product row
        document.getElementById('add-product').addEventListener('click', function() {
            const container = document.getElementById('products-container');
            const index = container.children.length;
            const newItem = document.createElement('div');
            newItem.classList.add('row', 'g-3', 'product-item');

            // Generate unique IDs for service elements
            const serviceId = `service_request_${Date.now()}_${index}`;
            const serviceOptionsId = `service_options_${Date.now()}_${index}`;

            newItem.innerHTML = `
                <div class="col-md-6">
                    <label for="product_id_${index}" class="form-label">Product</label>
                    <select name="products[${index}][product_id]" id="product_id_${index}" class="form-select product-select" required>
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
                    <label for="quantity_${index}" class="form-label">Quantity</label>
                    <input type="number" name="products[${index}][quantity]" id="quantity_${index}" class="form-control quantity-input" min="1" value="1" required>
                </div>
                <div class="col-md-3">
                    <label for="unit_price_${index}" class="form-label">Price per Unit</label>
                    <input type="number" name="products[${index}][unit_price]" id="unit_price_${index}" class="form-control unit-price-input" step="0.01" required>
                </div>
                <div class="col-md-12 mt-2 mb-3">
                    <button type="button" class="btn btn-danger remove-product">Remove Product</button>
                </div>
                <div class="col-12 mt-2 service-request-section">
                    <div class="card">
                        <div class="card-body">
                            <div class="form-check mb-3">
                                <input type="checkbox" class="form-check-input service-toggle" 
                                       id="${serviceId}" name="products[${index}][request_service]">
                                <label class="form-check-label" for="${serviceId}">
                                    Add Service Request (Engraving/Printing)
                                </label>
                            </div>
                            
                            <div id="${serviceOptionsId}" class="service-options" style="display: none;">
                                <div class="mb-3">
                                    <label class="form-label">Service Type</label>
                                    <select class="form-select service-type-select" 
                                            name="products[${index}][service_type]" required>
                                        <option value="">Select Service</option>
                                        <option value="engraving" data-price="500">Engraving (Ksh 500)</option>
                                        <option value="printing" data-price="300">Printing (Ksh 300)</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Service Details</label>
                                    <textarea class="form-control" 
                                            name="products[${index}][service_message]" 
                                            rows="2" 
                                            placeholder="Describe your customization requirements"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            // Add event listeners for the new row
            newItem.querySelector('.service-toggle').addEventListener('change', function(e) {
                const serviceOptions = document.getElementById(serviceOptionsId);
                serviceOptions.style.display = e.target.checked ? 'block' : 'none';
                
                if (!e.target.checked) {
                    const serviceSelect = serviceOptions.querySelector('.service-type-select');
                    serviceSelect.value = '';
                }
                calculateTotal();
            });

            newItem.querySelector('.remove-product').addEventListener('click', function() {
                newItem.remove();
                reindexProducts();
                calculateTotal();
            });

            // Add product selection handler
            newItem.querySelector('.product-select').addEventListener('change', function(e) {
                const price = e.target.options[e.target.selectedIndex]?.dataset?.price || 0;
                newItem.querySelector('.unit-price-input').value = price;
                calculateTotal();
            });

            // Add quantity/price change handlers
            newItem.querySelectorAll('.quantity-input, .unit-price-input').forEach(input => {
                input.addEventListener('input', calculateTotal);
            });

            container.appendChild(newItem);

            // Reindex all products after adding new one
            reindexProducts();
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

        // Coupon handling
        async function validateCoupon() {
            const couponCode = document.getElementById('couponCode')?.value.trim();
            const couponFeedback = document.getElementById('couponFeedback');
            const discountRow = document.getElementById('discount-row');
            const discountAmount = document.getElementById('discount-amount');
            
            if (!couponCode) {
                showCouponMessage('Please enter a coupon code', 'danger');
                return;
            }

            const totals = calculateTotal();
            
            try {
                const response = await fetch('ajax/validate_order_coupon.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        couponCode: couponCode,
                        orderTotal: totals.finalTotal,
                        items: getOrderItems()
                    })
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();
                
                if (result.valid) {
                    currentDiscount = result.discount_amount || 0;
                    showCouponMessage(result.message, 'success');
                    if (discountRow && discountAmount) {
                        discountRow.style.display = 'flex';
                        discountAmount.textContent = `-Ksh. ${currentDiscount.toFixed(2)}`;
                    }
                    
                    // Add success styling to coupon input
                    const couponInput = document.getElementById('couponCode');
                    if (couponInput) {
                        couponInput.classList.add('is-valid');
                        couponInput.classList.remove('is-invalid');
                    }
                } else {
                    currentDiscount = 0;
                    showCouponMessage(result.message || 'Invalid coupon', 'danger');
                    if (discountRow) {
                        discountRow.style.display = 'none';
                    }
                    
                    // Add error styling to coupon input
                    const couponInput = document.getElementById('couponCode');
                    if (couponInput) {
                        couponInput.classList.add('is-invalid');
                        couponInput.classList.remove('is-valid');
                    }
                }
                
                calculateTotal();
                
            } catch (error) {
                console.error('Error:', error);
                showCouponMessage('Error validating coupon: ' + error.message, 'danger');
            }
        }

        function showCouponMessage(message, type) {
            const feedback = document.getElementById('couponFeedback');
            if (feedback) {
                feedback.textContent = message;
                feedback.className = `form-text text-${type}`;
            }
        }

        function getOrderItems() {
            return Array.from(document.querySelectorAll('.product-item')).map(item => {
                return {
                    product_id: item.querySelector('.product-select').value,
                    quantity: parseInt(item.querySelector('.quantity-input').value) || 0,
                    price: parseFloat(item.querySelector('.unit-price-input').value) || 0
                };
            });
        }

        function applyCoupon(code) {
            document.getElementById('couponCode').value = code;
            validateCoupon();
        }

        function updateOrderTotal(discountPercentage) {
            // Calculate subtotal from all products
            const subtotal = currentSubtotal;
            
            // Calculate discount amount
            currentDiscount = (subtotal * discountPercentage) / 100;
            
            // Update discount display
            const discountRow = document.getElementById('discountRow');
            const discountAmount = document.getElementById('discountAmount');
            
            if (currentDiscount > 0) {
                discountRow.style.display = 'flex';
                discountAmount.textContent = `-Ksh.${currentDiscount.toFixed(2)}`;
            } else {
                discountRow.style.display = 'none';
                discountAmount.textContent = `-Ksh.0.00`;
            }
            
            // Calculate final total (subtotal - discount + shipping)
            const finalTotal = subtotal - currentDiscount + shippingFee;
            
            // Update all displays
            document.getElementById('subtotal').textContent = `Ksh.${subtotal.toFixed(2)}`;
            document.getElementById('finalTotal').textContent = `Ksh.${finalTotal.toFixed(2)}`;
            
            // If there's a hidden input for the total, update it
            const totalInput = document.querySelector('input[name="total_amount"]');
            if (totalInput) {
                totalInput.value = finalTotal.toFixed(2);
            }
            
            return finalTotal;
        }

        // Add shipping method change handler
        document.getElementById('shipping_method').addEventListener('change', calculateShippingFee);
        
        // Add form submission validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const couponCode = document.getElementById('couponCode').value;
            if (couponCode && !document.getElementById('couponCode').classList.contains('is-valid')) {
                e.preventDefault();
                alert('Please use a valid coupon code or remove it before submitting.');
            }
        });

        // Update the shipping fee calculation
        async function calculateShippingFee() {
            const methodId = document.getElementById('shipping_method').value;
            const regionId = document.getElementById('region_id').value;
            const totalItems = Array.from(document.querySelectorAll('.quantity-input'))
                .reduce((sum, input) => sum + parseInt(input.value || 0), 0);

            if (!methodId || !regionId) {
                document.getElementById('shipping_fee').textContent = 'Ksh. 0.00';
                updateFinalTotal();
                return;
            }

            try {
                const response = await fetch('ajax/calculate_shipping.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        methodId: methodId,
                        regionId: regionId,
                        itemCount: totalItems,
                        subtotal: currentSubtotal
                    })
                });

                const data = await response.json();
                if (data.success) {
                    shippingFee = parseFloat(data.fee) || 0;
                    document.getElementById('shipping_fee').textContent = `Ksh. ${shippingFee.toFixed(2)}`;
                } else {
                    console.error('Shipping calculation failed:', data.error);
                    shippingFee = 0;
                    document.getElementById('shipping_fee').textContent = 'Ksh. 0.00';
                }
            } catch (error) {
                console.error('Error calculating shipping:', error);
                shippingFee = 0;
                document.getElementById('shipping_fee').textContent = 'Ksh. 0.00';
            }
            
            updateFinalTotal();
        }

        // Update the updateFinalTotal function
        function updateFinalTotal() {
            currentSubtotal = Array.from(document.querySelectorAll('.product-item')).reduce((sum, item) => {
                const quantity = parseInt(item.querySelector('.quantity-input').value) || 0;
                const price = parseFloat(item.querySelector('.unit-price-input').value) || 0;
                return sum + (quantity * price);
            }, 0);

            const finalTotal = currentSubtotal - (currentDiscount || 0) + (shippingFee || 0);
            
            document.getElementById('subtotal').textContent = `Ksh. ${currentSubtotal.toFixed(2)}`;
            document.getElementById('shipping_fee').textContent = `Ksh. ${(shippingFee || 0).toFixed(2)}`;
            document.getElementById('total').textContent = `Ksh. ${finalTotal.toFixed(2)}`;
        }

        // Replace the updateShippingMethods function in your JavaScript
        function updateShippingMethods(regionId) {
            const methodSelect = document.getElementById('shipping_method');
            methodSelect.disabled = true;
            methodSelect.innerHTML = '<option value="">Loading...</option>';

            if (!regionId) {
                methodSelect.innerHTML = '<option value="">Select Region First</option>';
                methodSelect.disabled = true;
                return;
            }

            fetch(`ajax/get_shipping_methods.php?region_id=${regionId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    methodSelect.innerHTML = '<option value="">Select Shipping Method</option>';
                    
                    if (data.success) {
                        if (data.methods && data.methods.length > 0) {
                            data.methods.forEach(method => {
                                methodSelect.innerHTML += `
                                    <option value="${method.id}">
                                        ${method.display_name}
                                    </option>
                                `;
                            });
                            methodSelect.disabled = false;
                        } else {
                            methodSelect.innerHTML = '<option value="">No shipping methods available for this region</option>';
                            methodSelect.disabled = true;
                        }
                    } else {
                        throw new Error(data.error || 'Failed to load shipping methods');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    methodSelect.innerHTML = '<option value="">Error loading shipping methods</option>';
                    methodSelect.disabled = true;
                });
        }

        // Add region change event listener
        document.getElementById('region_id').addEventListener('change', function() {
            updateShippingMethods(this.value);
        });

        // Remove all service-toggle related JavaScript code and just keep the service checkbox handler
        document.addEventListener('change', function(event) {
            if (event.target.classList.contains('service-checkbox')) {
                calculateTotal();
            }
        });

        // Update the total calculation function to handle only products and selected services
        function calculateTotal() {
            let productsSubtotal = 0;
            let servicesSubtotal = 0;
            
            // Calculate products subtotal
            document.querySelectorAll('.product-item').forEach(item => {
                const quantity = parseInt(item.querySelector('.quantity-input').value) || 0;
                const price = parseFloat(item.querySelector('.unit-price-input').value) || 0;
                productsSubtotal += (quantity * price);
            });
            
            // Calculate services subtotal
            document.querySelectorAll('.service-checkbox:checked').forEach(checkbox => {
                servicesSubtotal += parseFloat(checkbox.dataset.price) || 0;
            });

            // Update displays
            document.getElementById('products-subtotal').textContent = `Ksh. ${productsSubtotal.toFixed(2)}`;
            document.getElementById('services-subtotal').textContent = `Ksh. ${servicesSubtotal.toFixed(2)}`;
            
            // Calculate final total
            const finalTotal = productsSubtotal + servicesSubtotal - (currentDiscount || 0) + (shippingFee || 0);
            document.getElementById('final-total').textContent = `Ksh. ${finalTotal.toFixed(2)}`;
        }

        function reindexProducts() {
            const container = document.getElementById('products-container');
            const items = container.querySelectorAll('.product-item');
            
            items.forEach((item, index) => {
                // Update all name attributes
                item.querySelectorAll('[name*="products["]').forEach(element => {
                    element.name = element.name.replace(/products\[\d+\]/, `products[${index}]`);
                });
                
                // Update all IDs and labels
                item.querySelectorAll('[id*="_"]').forEach(element => {
                    const oldId = element.id;
                    const newId = oldId.replace(/_\d+$/, `_${index}`);
                    element.id = newId;
                    
                    // Update corresponding labels
                    const labels = item.querySelectorAll(`label[for="${oldId}"]`);
                    labels.forEach(label => label.setAttribute('for', newId));
                });
            });
        }

        function toggleGifteeFields(enabled) {
            const gifteeFields = document.getElementById('gifteeFields');
            const inputs = gifteeFields.getElementsByTagName('input');
            const textareas = gifteeFields.getElementsByTagName('textarea');
            
            gifteeFields.style.opacity = enabled ? '1' : '0.5';
            gifteeFields.style.pointerEvents = enabled ? 'auto' : 'none';
            
            // Toggle required attribute for giftee fields
            [inputs, textareas].forEach(elements => {
                Array.from(elements).forEach(element => {
                    if (element.id !== 'hide_prices') { // Don't make checkbox required
                        element.required = enabled;
                    }
                });
            });
        }

        // Replace the calculateTotal function with this updated version
        function calculateTotal() {
            let productsSubtotal = 0;
            let servicesSubtotal = 0;
            
            // Calculate products subtotal
            document.querySelectorAll('.product-item').forEach(item => {
                const quantity = parseInt(item.querySelector('.quantity-input').value) || 0;
                const price = parseFloat(item.querySelector('.unit-price-input').value) || 0;
                productsSubtotal += (quantity * price);
            });
            
            // Calculate services subtotal
            document.querySelectorAll('.service-checkbox:checked').forEach(checkbox => {
                servicesSubtotal += parseFloat(checkbox.dataset.price) || 0;
            });

            // Update subtotal displays
            document.getElementById('products-subtotal').textContent = `Ksh. ${productsSubtotal.toFixed(2)}`;
            document.getElementById('services-subtotal').textContent = `Ksh. ${servicesSubtotal.toFixed(2)}`;
            
            // Calculate base subtotal
            const subtotal = productsSubtotal + servicesSubtotal;
            currentSubtotal = subtotal; // Update global subtotal for coupon calculations
            
            // Update shipping fee if needed
            if (document.getElementById('shipping_method').value) {
                calculateShippingFee();
            }
            
            // Show discount if applicable
            const discountRow = document.getElementById('discount-row');
            const discountAmount = document.getElementById('discount-amount');
            if (currentDiscount > 0) {
                discountRow.style.display = 'flex';
                discountAmount.textContent = `-Ksh. ${currentDiscount.toFixed(2)}`;
            } else {
                discountRow.style.display = 'none';
                discountAmount.textContent = `-Ksh. 0.00`;
            }
            
            // Calculate final total
            const finalTotal = subtotal + lastValidShippingFee - currentDiscount;
            document.getElementById('final-total').textContent = `Ksh. ${finalTotal.toFixed(2)}`;
            document.getElementById('shipping-fee').textContent = `Ksh. ${lastValidShippingFee.toFixed(2)}`;

            // Update hidden input for form submission
            const totalInput = document.querySelector('input[name="total_amount"]');
            if (totalInput) {
                totalInput.value = finalTotal.toFixed(2);
            }

            return {
                productsSubtotal,
                servicesSubtotal,
                shippingFee: lastValidShippingFee,
                discount: currentDiscount,
                finalTotal
            };
        }

        // Add form submission handler to validate totals
        document.querySelector('form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const totals = calculateTotal();
            const formData = new FormData(this);
            
            // Add calculated totals to form data
            formData.append('calculated_totals', JSON.stringify(totals));
            
            try {
                const response = await fetch('ajax/validate_order_totals.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.valid) {
                    this.submit();
                } else {
                    alert('Order total validation failed. Please try again.');
                }
            } catch (error) {
                console.error('Error validating totals:', error);
                alert('Error validating order totals. Please try again.');
            }
        });

        // Add email lookup functionality
        document.getElementById('email').addEventListener('blur', async function() {
            const email = this.value;
            const usernameInput = document.getElementById('username');
            const usernameFeedback = document.getElementById('username-feedback');
            
            if (!email) return;
            
            try {
                const response = await fetch('create_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=lookup_email&email=${encodeURIComponent(email)}`
                });
                
                const data = await response.json();
                
                if (data.success) {
                    if (data.found) {
                        usernameInput.value = data.username;
                        usernameInput.readOnly = true;
                        usernameFeedback.textContent = 'Existing customer found';
                        usernameFeedback.className = 'form-text text-success';
                    } else {
                        usernameInput.value = '';
                        usernameInput.readOnly = false;
                        usernameFeedback.textContent = 'New customer - please enter username';
                        usernameFeedback.className = 'form-text text-primary';
                    }
                }
            } catch (error) {
                console.error('Error looking up email:', error);
            }
        });

        // Replace the calculateTotal function with this updated version
        function calculateTotal() {
            let productsSubtotal = 0;
            let servicesSubtotal = 0;
            
            // Calculate products subtotal
            document.querySelectorAll('.product-item').forEach(item => {
                const quantity = parseInt(item.querySelector('.quantity-input').value) || 0;
                const price = parseFloat(item.querySelector('.unit-price-input').value) || 0;
                productsSubtotal += (quantity * price);
            });
            
            // Calculate services subtotal
            document.querySelectorAll('.service-checkbox:checked').forEach(checkbox => {
                servicesSubtotal += parseFloat(checkbox.dataset.price) || 0;
            });

            // Update subtotal displays
            document.getElementById('products-subtotal').textContent = `Ksh. ${productsSubtotal.toFixed(2)}`;
            document.getElementById('services-subtotal').textContent = `Ksh. ${servicesSubtotal.toFixed(2)}`;
            
            // Calculate base subtotal
            const subtotal = productsSubtotal + servicesSubtotal;
            currentSubtotal = subtotal;
            
            // Update shipping fee if needed
            if (document.getElementById('shipping_method').value) {
                calculateShippingFee();
            }
            
            // Show discount if applicable
            const discountRow = document.getElementById('discount-row');
            if (currentDiscount > 0) {
                discountRow.style.display = 'flex';
                document.getElementById('discount-amount').textContent = `-Ksh. ${currentDiscount.toFixed(2)}`;
            } else {
                discountRow.style.display = 'none';
            }
            
            // Calculate final total
            const finalTotal = subtotal + lastValidShippingFee - currentDiscount;
            document.getElementById('final-total').textContent = `Ksh. ${finalTotal.toFixed(2)}`;
            document.getElementById('shipping-fee').textContent = `Ksh. ${lastValidShippingFee.toFixed(2)}`;

            return {
                productsSubtotal,
                servicesSubtotal,
                shippingFee: lastValidShippingFee,
                discount: currentDiscount,
                finalTotal
            };
        }

        // Update the calculateShippingFee function
        async function calculateShippingFee() {
            const methodId = document.getElementById('shipping_method').value;
            const regionId = document.getElementById('region_id').value;
            const totalItems = Array.from(document.querySelectorAll('.quantity-input'))
                .reduce((sum, input) => sum + parseInt(input.value || 0), 0);

            if (!methodId || !regionId) {
                lastValidShippingFee = 0;
                calculateTotal();
                return;
            }

            try {
                const response = await fetch('ajax/calculate_shipping.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        methodId: methodId,
                        regionId: regionId,
                        itemCount: totalItems,
                        subtotal: currentSubtotal
                    })
                });

                const data = await response.json();
                if (data.success) {
                    lastValidShippingFee = parseFloat(data.fee) || 0;
                } else {
                    console.error('Shipping calculation failed:', data.error);
                    lastValidShippingFee = 0;
                }
            } catch (error) {
                console.error('Error calculating shipping:', error);
                lastValidShippingFee = 0;
            }
            
            calculateTotal();
        }
    </script>
</body>
<?php include 'includes/nav/footer.php'; ?>
</html>