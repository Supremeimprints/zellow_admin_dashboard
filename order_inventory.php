<?php
session_start();
require_once 'config/database.php';

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
        $db->beginTransaction();

        $id = $_POST['id'];
        $order_items = $_POST['items'];
        $total_amount = 0;

        // Create purchase order
        $stmt = $db->prepare("
            INSERT INTO purchase_orders (
                id, 
                order_date, 
                status, 
                total_amount, 
                created_by
            ) VALUES (?, NOW(), 'pending', ?, ?)
        ");

        // Calculate total amount
        foreach ($order_items as $item) {
            if (!empty($item['quantity']) && !empty($item['unit_price'])) {
                $total_amount += $item['quantity'] * $item['unit_price'];
            }
        }

        $stmt->execute([
            $id,
            $total_amount,
            $_SESSION['id']
        ]);

        $purchase_order_id = $db->lastInsertId();

        // Insert order items
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

        $db->commit();
        $success = "Purchase order created successfully! Order ID: " . $purchase_order_id;
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
            <form method="POST" action="create_purchase_order.php">
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
