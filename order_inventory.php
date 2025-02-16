<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions/email_functions.php';
require_once 'config/mail.php'; // Add this line to include SMTP settings

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$error = $success = '';

// Fetch suppliers
$suppliersQuery = "SELECT supplier_id, company_name, email, phone FROM suppliers WHERE status = 'active'";
$suppliers = $db->query($suppliersQuery)->fetchAll(PDO::FETCH_ASSOC);

// Fetch products with low stock
$productsQuery = "
    SELECT p.product_id, p.product_name, i.stock_quantity, i.min_stock_level 
    FROM products p 
    LEFT JOIN inventory i ON p.product_id = i.product_id 
    WHERE i.stock_quantity <= i.min_stock_level
    ORDER BY p.product_name";
$products = $db->query($productsQuery)->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verify supplier email settings first
        $validation = verifySupplierEmailSettings($db, $_POST['supplier_id']);
        
        if (!$validation['isValid']) {
            throw new Exception("Email validation failed: " . implode(", ", $validation['errors']));
        }

        $supplier_id = $_POST['supplier_id'];
        $order_items = $_POST['items'];
        $total_amount = 0;

        // Calculate total amount before starting transaction
        foreach ($order_items as $item) {
            if (!empty($item['quantity']) && !empty($item['unit_price'])) {
                $total_amount += $item['quantity'] * $item['unit_price'];
            }
        }

        // Start transaction only when we're ready to insert data
        $db->beginTransaction();
        $transactionStarted = true;

        // Create purchase order
        $stmt = $db->prepare("
            INSERT INTO purchase_orders (
                supplier_id,
                order_date,
                status,
                total_amount,
                created_by
            ) VALUES (?, NOW(), 'pending', ?, ?)
        ");

        $stmt->execute([
            $supplier_id,
            $total_amount,
            $_SESSION['id']
        ]);

        $purchase_order_id = $db->lastInsertId();

        // Insert order items with correct column names matching your existing table
        $itemStmt = $db->prepare("
            INSERT INTO purchase_order_items (
                purchase_order_id,
                product_id,
                quantity,
                unit_price
            ) VALUES (?, ?, ?, ?)
        ");

        foreach ($order_items as $item) {
            if (!empty($item['quantity']) && !empty($item['unit_price'])) {
                $itemStmt->execute([
                    $purchase_order_id,
                    $item['product_id'],
                    $item['quantity'],
                    $item['unit_price']
                ]);
            }
        }

        // Generate invoice number (e.g., INV-2023-001)
        $invoice_number = 'INV-' . date('Y') . '-' . str_pad($purchase_order_id, 3, '0', STR_PAD_LEFT);

        // Create invoice
        $invoiceStmt = $db->prepare("
            INSERT INTO invoices (
                invoice_number,
                supplier_id,
                amount,
                due_date,
                status
            ) VALUES (?, ?, ?, DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY), 'Unpaid')
        ");

        $invoiceStmt->execute([
            $invoice_number,
            $supplier_id,
            $total_amount
        ]);

        $invoice_id = $db->lastInsertId();

        // Record expense
        $expenseStmt = $db->prepare("
            INSERT INTO expenses (
                category,
                amount,
                expense_date,
                description
            ) VALUES ('Inventory Purchase', ?, CURRENT_DATE, ?)
        ");

        $expenseStmt->execute([
            $total_amount,
            "Purchase Order #$purchase_order_id - Invoice #$invoice_number"
        ]);

        // Create initial payment record in purchase_payments instead of payments
        $paymentStmt = $db->prepare("
            INSERT INTO purchase_payments (
                purchase_order_id,
                amount,
                payment_method,
                status,
                transaction_id
            ) VALUES (?, ?, 'Mpesa', 'Pending', ?)
        ");

        $transaction_id = 'TRX-' . date('YmdHis') . '-' . rand(1000, 9999);
        
        $paymentStmt->execute([
            $purchase_order_id,
            $total_amount,
            $transaction_id
        ]);

        // Handle email sending outside of transaction
        $db->commit();
        $transactionStarted = false;

        // Email sending code moved here, after successful commit
        try {
            require_once 'vendor/autoload.php';
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Enable debug output
            $mail->SMTPDebug = SMTP_DEBUG;
            $mail->Debugoutput = 'error_log';
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = SMTP_SECURE;
            $mail->Port = SMTP_PORT;
            
            // Additional SMTP settings for Gmail
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );

            // Get supplier details with error checking
            $supplierStmt = $db->prepare("
                SELECT company_name, email, contact_person 
                FROM suppliers 
                WHERE supplier_id = ?
            ");
            $supplierStmt->execute([$supplier_id]);
            $supplier = $supplierStmt->fetch(PDO::FETCH_ASSOC);

            if (!$supplier || empty($supplier['email'])) {
                throw new Exception("Invalid supplier email");
            }

            // Setup email
            $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
            $mail->addAddress($supplier['email'], $supplier['company_name']);
            
            if (isset($_SESSION['email'])) {
                $mail->addCC($_SESSION['email']); // Send copy to admin if session email exists
            }

            // Get product details for the email
            $productDetailsQuery = "
                SELECT p.product_name, poi.quantity, poi.unit_price
                FROM purchase_order_items poi
                JOIN products p ON poi.product_id = p.product_id
                WHERE poi.purchase_order_id = ?
            ";
            $productStmt = $db->prepare($productDetailsQuery);
            $productStmt->execute([$purchase_order_id]);
            $orderProducts = $productStmt->fetchAll(PDO::FETCH_ASSOC);

            // Setup email
            $mail->setFrom(SMTP_USER, 'Zellow Enterprises');
            $mail->addAddress($supplier['email'], $supplier['company_name']);
            $mail->addCC($_SESSION['email']); // Send copy to admin

            $mail->isHTML(true);
            $mail->Subject = "New Purchase Order #$purchase_order_id - $invoice_number";
            
            // Create email body with professional formatting
            $mail->Body = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                    <h2 style='color: #333; border-bottom: 2px solid #ddd; padding-bottom: 10px;'>Purchase Order Details</h2>
                    
                    <div style='background: #f9f9f9; padding: 15px; margin: 20px 0; border-radius: 5px;'>
                        <h3 style='color: #444; margin-top: 0;'>Order Information</h3>
                        <p><strong>Purchase Order:</strong> #$purchase_order_id</p>
                        <p><strong>Invoice Number:</strong> $invoice_number</p>
                        <p><strong>Order Date:</strong> " . date('Y-m-d') . "</p>
                        <p><strong>Due Date:</strong> " . date('Y-m-d', strtotime('+30 days')) . "</p>
                    </div>

                    <div style='margin: 20px 0;'>
                        <h3 style='color: #444;'>Order Items</h3>
                        <table style='width: 100%; border-collapse: collapse; margin-top: 10px;'>
                            <thead>
                                <tr style='background: #eee;'>
                                    <th style='padding: 10px; text-align: left; border: 1px solid #ddd;'>Product</th>
                                    <th style='padding: 10px; text-align: right; border: 1px solid #ddd;'>Quantity</th>
                                    <th style='padding: 10px; text-align: right; border: 1px solid #ddd;'>Unit Price</th>
                                    <th style='padding: 10px; text-align: right; border: 1px solid #ddd;'>Total</th>
                                </tr>
                            </thead>
                            <tbody>";

            foreach ($orderProducts as $product) {
                $itemTotal = $product['quantity'] * $product['unit_price'];
                $mail->Body .= "
                    <tr>
                        <td style='padding: 10px; border: 1px solid #ddd;'>" . htmlspecialchars($product['product_name']) . "</td>
                        <td style='padding: 10px; text-align: right; border: 1px solid #ddd;'>" . number_format($product['quantity']) . "</td>
                        <td style='padding: 10px; text-align: right; border: 1px solid #ddd;'>Ksh. " . number_format($product['unit_price'], 2) . "</td>
                        <td style='padding: 10px; text-align: right; border: 1px solid #ddd;'>Ksh. " . number_format($itemTotal, 2) . "</td>
                    </tr>";
            }

            $mail->Body .= "
                            <tr style='background: #f5f5f5;'>
                                <td colspan='3' style='padding: 10px; text-align: right; border: 1px solid #ddd;'><strong>Total Amount:</strong></td>
                                <td style='padding: 10px; text-align: right; border: 1px solid #ddd;'><strong>Ksh. " . number_format($total_amount, 2) . "</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div style='margin-top: 20px; padding: 15px; background: #f9f9f9; border-radius: 5px;'>
                    <p style='margin: 0;'><strong>Please Note:</strong></p>
                    <ul style='margin: 10px 0;'>
                        <li>Payment is due within 30 days</li>
                        <li>Please reference the invoice number in all communications</li>
                        <li>For any queries, please contact our purchasing department</li>
                    </ul>
                </div>

                <div style='margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px;'>
                    <p>This is an automated message from Zellow Enterprises. Please do not reply directly to this email.</p>
                </div>
            </div>";

            // Plain text version
            $mail->AltBody = "Purchase Order #$purchase_order_id\n"
                . "Invoice Number: $invoice_number\n"
                . "Total Amount: Ksh. " . number_format($total_amount, 2) . "\n"
                . "Due Date: " . date('Y-m-d', strtotime('+30 days')) . "\n\n"
                . "Please log in to your supplier portal for more details.";

            $sent = $mail->send();
            if (!$sent) {
                throw new Exception("Mailer Error: " . $mail->ErrorInfo);
            }
            error_log("Email sent successfully to: " . $supplier['email']);
            $success = "Purchase order created successfully! Order ID: $purchase_order_id, Invoice: $invoice_number";
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            $success = "Purchase order created successfully! Order ID: $purchase_order_id, Invoice: $invoice_number (Email notification failed: " . $e->getMessage() . ")";
        }

    } catch (Exception $e) {
        // Only rollback if transaction was started
        if (isset($transactionStarted) && $transactionStarted) {
            $db->rollBack();
        }
        $error = "Error creating purchase order: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Inventory</title>
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
    <link href="assets/css/inventory.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/collapsed.css">
    <style>
        .low-stock {
            color: #dc3545;
            font-weight: bold;
        }
        .order-form {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Order Inventory from Suppliers</h2>
            <a href="inventory.php" class="btn btn-secondary">Back to Inventory</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <div class="order-form">
            <form method="POST">
                <input type="hidden" name="supplier_id" value="<?= htmlspecialchars($_GET['supplier_id'] ?? '') ?>">
                <div class="mb-4">
                    <label for="id" class="form-label">Select Supplier</label>
                    <select name="supplier_id" id="supplier_id" class="form-select" required>
                        <option value="">Choose a supplier...</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?= $supplier['supplier_id'] ?>">
                                <?= htmlspecialchars($supplier['company_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <h5>Order Items</h5>
                    <div id="orderItems">
                        <!-- Items will be added here -->
                        <div class="row mb-3 order-item">
                            <div class="col-md-4">
                                <select name="items[0][product_id]" class="form-select" required>
                                    <option value="">Select Product...</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?= $product['product_id'] ?>" 
                                                data-stock="<?= $product['stock_quantity'] ?>"
                                                data-min="<?= $product['min_stock_level'] ?>">
                                            <?= htmlspecialchars($product['product_name']) ?>
                                            (Stock: <?= $product['stock_quantity'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="number" name="items[0][quantity]" 
                                       class="form-control" placeholder="Quantity" required>
                            </div>
                            <div class="col-md-3">
                                <input type="number" name="items[0][unit_price]" 
                                       class="form-control" placeholder="Unit Price" required>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-danger remove-item">Remove</button>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary" id="addItem">+ Add Item</button>
                </div>

                <div class="d-flex justify-content-between">
                    <button type="submit" class="btn btn-primary">Create Purchase Order</button>
                    <div class="h4">Total: Ksh. <span id="orderTotal">0.00</span></div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let itemCount = 1;

document.getElementById('addItem').addEventListener('click', function() {
    const container = document.getElementById('orderItems');
    const newItem = document.createElement('div');
    newItem.className = 'row mb-3 order-item';
    newItem.innerHTML = `
        <div class="col-md-4">
            <select name="items[${itemCount}][product_id]" class="form-select" required>
                <option value="">Select Product...</option>
                <?php foreach ($products as $product): ?>
                    <option value="<?= $product['product_id'] ?>"
                            data-stock="<?= $product['stock_quantity'] ?>"
                            data-min="<?= $product['min_stock_level'] ?>">
                        <?= htmlspecialchars($product['product_name']) ?>
                        (Stock: <?= $product['stock_quantity'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <input type="number" name="items[${itemCount}][quantity]" 
                   class="form-control" placeholder="Quantity" required>
        </div>
        <div class="col-md-3">
            <input type="number" name="items[${itemCount}][unit_price]" 
                   class="form-control" placeholder="Unit Price" required>
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-danger remove-item">Remove</button>
        </div>
    `;
    container.appendChild(newItem);
    itemCount++;
    updateTotal();
});

document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-item')) {
        e.target.closest('.order-item').remove();
        updateTotal();
    }
});

document.addEventListener('input', function(e) {
    if (e.target.matches('input[type="number"]')) {
        updateTotal();
    }
});

function updateTotal() {
    let total = 0;
    document.querySelectorAll('.order-item').forEach(item => {
        const quantity = parseFloat(item.querySelector('input[name*="quantity"]').value) || 0;
        const price = parseFloat(item.querySelector('input[name*="unit_price"]').value) || 0;
        total += quantity * price;
    });
    document.getElementById('orderTotal').textContent = total.toFixed(2);
}
</script>

<?php include 'includes/nav/footer.php'; ?>
</body>
</html>
