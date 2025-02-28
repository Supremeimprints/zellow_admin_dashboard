<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions/email_functions.php'; // Make sure this line is present
require_once 'config/mail.php';
require_once 'includes/functions/mailer_helper.php';
require_once 'includes/functions/purchase_order_functions.php';

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

// Update the products query to include supplier info
$productsQuery = "
    SELECT 
        p.product_id, 
        p.product_name, 
        p.price as unit_price,
        i.stock_quantity, 
        i.min_stock_level,
        s.supplier_id,
        s.company_name as supplier_name,
        s.email as supplier_email
    FROM products p 
    LEFT JOIN inventory i ON p.product_id = i.product_id 
    LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
    WHERE i.stock_quantity <= i.min_stock_level
        AND s.status = 'Active'
        AND s.is_active = 1
    ORDER BY p.product_name";

$products = $db->query($productsQuery)->fetchAll(PDO::FETCH_ASSOC);

// Group products by supplier for easier handling
$productsBySupplier = [];
foreach ($products as $product) {
    $productsBySupplier[$product['supplier_id']][] = $product;
}

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
            "Purchase Order #$purchase_order_id" // Remove invoice reference
        ]);

        // Generate invoice number using the same format as invoices table
        $invoice_number = 'INV-' . date('Ymd-') . str_pad($purchase_order_id, 4, '0', STR_PAD_LEFT);

        // Create initial payment record in purchase_payments
        $paymentStmt = $db->prepare("
            INSERT INTO purchase_payments (
                purchase_order_id,
                amount,
                payment_method,
                status,
                transaction_id,
                invoice_number  
            ) VALUES (?, ?, 'Mpesa', 'Pending', ?, ?)
        ");

        $transaction_id = 'TRX-' . date('YmdHis') . '-' . rand(1000, 9999);

        $paymentStmt->execute([
            $purchase_order_id,
            $total_amount,
            $transaction_id,
            $invoice_number  // Use the generated invoice number
        ]);

        // Handle email sending outside of transaction
        $db->commit();
        $transactionStarted = false;

        // Prepare order products array for email
        $orderProducts = [];
        try {
            // Fetch all order items with product details in one query
            $orderItemsQuery = $db->prepare("
                SELECT 
                    poi.*,
                    p.product_name,
                    (poi.quantity * poi.unit_price) as total
                FROM purchase_order_items poi
                JOIN products p ON poi.product_id = p.product_id
                WHERE poi.purchase_order_id = ?
            ");
            
            $orderItemsQuery->execute([$purchase_order_id]);
            $orderProducts = $orderItemsQuery->fetchAll(PDO::FETCH_ASSOC);

            // Send email using the mailer helper
            require_once 'includes/functions/mailer_helper.php';
            
            sendPurchaseOrderEmail(
                $db,
                $supplier_id,
                $purchase_order_id,
                $total_amount,
                $orderProducts,
                $invoice_number  // Pass invoice_number instead of transaction_id
            );
            
            $success = "Purchase order #$purchase_order_id created successfully!";
            
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            $success = "Purchase order #$purchase_order_id created successfully! (Email notification failed: " . $e->getMessage() . ")";
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
    <link rel="stylesheet" href="assets/css/settings.css">
    <link rel="stylesheet" href="assets/css/badges.css">
    <link rel="stylesheet" href="assets/css/orders.css">
    <link rel="stylesheet" href="assets/css/collapsed.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/orders.css" rel="stylesheet">
    <link href="assets/css/badges.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/inventory.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/collapsed.css">
    <style>
        .low-stock {
            color: var(--text-danger, #dc3545);
        }
        .order-form {
            background: var(--content-bg-light, #fff);
            padding: 1.25rem;
            border-radius: 0.5rem;
            box-shadow: var(--card-shadow-light);
        }
        [data-bs-theme="dark"] .order-form {
            background: var(--content-bg-dark, #1a202c);
            box-shadow: var(--card-shadow-dark);
        }
        [data-bs-theme="dark"] .form-select,
        [data-bs-theme="dark"] .form-control {
            background-color: var(--bg-dark);
            border-color: var(--border-dark);
            color: var(--text-dark);
        }
        [data-bs-theme="dark"] .form-select option {
            background-color: var(--bg-dark);
            color: var (--text-dark);
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include 'includes/theme.php'; ?>
    <nav class="navbar">
        <?php include 'includes/nav/collapsed.php'; ?>
    </nav>

    <main class="main-content">
        <div class="container mt-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Order Inventory from Suppliers</h2>
                <div>
                    <a href="inventory.php" class="btn btn-secondary me-2">
                    <i data-feather="archive"></i> Inventory
                    </a>
                    <a href="receive_goods.php" class="btn btn-info">
                    <i data-feather="arrow-down-circle" style="color: white;"></i> Receive Goods
                    </a>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>

        <div class="order-form">
            <form method="POST" id="orderForm">
                <div class="mb-4">
                    <label for="supplier_id" class="form-label">Select Supplier</label>
                    <select name="supplier_id" id="supplier_id" class="form-select" required>
                        <option value="">Choose a supplier...</option>
                        <?php foreach ($productsBySupplier as $supplierId => $products): 
                            $supplier = $products[0]; // All products in group have same supplier
                        ?>
                            <option value="<?= $supplierId ?>" 
                                    data-email="<?= htmlspecialchars($supplier['supplier_email']) ?>">
                                <?= htmlspecialchars($supplier['supplier_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <h5>Order Items</h5>
                    <div id="orderItems">
                        <div class="row mb-3 order-item">
                            <div class="col-md-4">
                                <select name="items[0][product_id]" class="form-select product-select" required>
                                    <option value="">Select Product...</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="number" name="items[0][quantity]" 
                                       class="form-control quantity-input" placeholder="Quantity" required>
                            </div>
                            <div class="col-md-3">
                                <input type="number" name="items[0][unit_price]" 
                                       class="form-control unit-price" placeholder="Unit Price" readonly>
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
        <?php include 'includes/nav/footer.php'; ?>
    </main>
</div>

<script>
let itemCount = 1;

// Store products data for JavaScript use
const productsBySupplier = <?= json_encode($productsBySupplier) ?>;
let selectedProducts = new Set(); // Track selected product IDs

document.getElementById('supplier_id').addEventListener('change', function() {
    selectedProducts.clear(); // Clear selected products when supplier changes
    updateProductOptions();
});

function updateProductOptions() {
    const supplierId = document.getElementById('supplier_id').value;
    const products = productsBySupplier[supplierId] || [];
    
    document.querySelectorAll('.product-select').forEach(select => {
        const currentValue = select.value;
        
        // Clear and rebuild options
        select.innerHTML = '<option value="">Select Product...</option>';
        
        // Add only unselected products, except for the current selection
        products.forEach(product => {
            if (!selectedProducts.has(product.product_id) || currentValue === product.product_id) {
                const option = new Option(
                    `${product.product_name} (Stock: ${product.stock_quantity})`,
                    product.product_id
                );
                option.dataset.price = product.unit_price;
                option.dataset.stock = product.stock_quantity;
                select.add(option);
            }
        });
        
        // Restore current value if it exists
        if (currentValue) {
            select.value = currentValue;
        }
    });
}

// Update price and track selected products when product is selected
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('product-select')) {
        const row = e.target.closest('.row');
        const priceInput = row.querySelector('.unit-price');
        const option = e.target.selectedOptions[0];
        const oldValue = e.target.dataset.previousValue;
        
        // Remove old selection from tracking
        if (oldValue) {
            selectedProducts.delete(oldValue);
        }
        
        // Add new selection to tracking
        if (e.target.value) {
            selectedProducts.add(e.target.value);
            e.target.dataset.previousValue = e.target.value;
        }
        
        if (option && option.dataset.price) {
            priceInput.value = option.dataset.price;
        } else {
            priceInput.value = '';
        }
        
        // Update all product selects to reflect the new selection
        updateProductOptions();
    }
});

// Modified addItem function
document.getElementById('addItem').addEventListener('click', function() {
    const container = document.getElementById('orderItems');
    const newRow = document.createElement('div');
    newRow.className = 'row mb-3 order-item';
    newRow.innerHTML = `
        <div class="col-md-4">
            <select name="items[${itemCount}][product_id]" class="form-select product-select" required>
                <option value="">Select Product...</option>
            </select>
        </div>
        <div class="col-md-3">
            <input type="number" name="items[${itemCount}][quantity]" 
                   class="form-control quantity-input" placeholder="Quantity" required>
        </div>
        <div class="col-md-3">
            <input type="number" name="items[${itemCount}][unit_price]" 
                   class="form-control unit-price" placeholder="Unit Price" readonly>
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-danger remove-item">Remove</button>
        </div>
    `;
    container.appendChild(newRow);
    itemCount++;
    
    // Update product options for new row
    updateProductOptions();
});

// Update remove item handler to remove product from tracking
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('remove-item')) {
        const row = e.target.closest('.order-item');
        const select = row.querySelector('.product-select');
        if (select.value) {
            selectedProducts.delete(select.value);
        }
        row.remove();
        updateProductOptions();
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

</body>
</html>
