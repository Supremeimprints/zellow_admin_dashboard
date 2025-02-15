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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        // Insert supplier
        $stmt = $db->prepare("
            INSERT INTO suppliers (
                company_name, 
                contact_person, 
                email, 
                phone, 
                address, 
                status
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $_POST['company_name'],
            $_POST['contact_person'],
            $_POST['email'],
            $_POST['phone'],
            $_POST['address'],
            'Active' // Default status
        ]);

        $supplierId = $db->lastInsertId();

        // Handle supplier products if any were submitted
        if (!empty($_POST['products'])) {
            $productStmt = $db->prepare("
                INSERT INTO supplier_products (
                    id,
                    product_name,
                    description,
                    unit_price,
                    moq,
                    lead_time,
                    status
                ) VALUES (?, ?, ?, ?, ?, ?, 'Active')
            ");

            foreach ($_POST['products'] as $product) {
                if (!empty($product['product_name'])) {
                    $productStmt->execute([
                        $supplierId,
                        $product['product_name'],
                        $product['description'] ?? '',
                        $product['unit_price'],
                        $product['moq'] ?? 1,
                        $product['lead_time'] ?? 0
                    ]);
                }
            }
        }

        $db->commit();
        $success = "Supplier added successfully!";
        header("Location: suppliers.php");
        exit();

    } catch (Exception $e) {
        $db->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Supplier</title>
    <!-- Include your existing CSS files -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/collapsed.css">
    <style>
        .product-entry {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
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
            <h2>Add New Supplier</h2>
            <a href="suppliers.php" class="btn btn-secondary">Back to Suppliers</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" class="needs-validation" novalidate>
            <!-- Supplier Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Supplier Information</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Company Name</label>
                            <input type="text" name="company_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Person</label>
                            <input type="text" name="contact_person" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="3" required></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products Section -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Supplier Products</h5>
                    <button type="button" class="btn btn-primary btn-sm" onclick="addProductField()">
                        Add Product
                    </button>
                </div>
                <div class="card-body">
                    <div id="productsContainer">
                        <!-- Product entries will be added here -->
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">Add Supplier</button>
            </div>
        </form>
    </div>
</div>

<script>
let productCount = 0;

function addProductField() {
    const container = document.getElementById('productsContainer');
    const productDiv = document.createElement('div');
    productDiv.className = 'product-entry';
    productDiv.innerHTML = `
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Product Name</label>
                <input type="text" name="products[${productCount}][product_name]" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Unit Price</label>
                <input type="number" name="products[${productCount}][unit_price]" class="form-control" step="0.01" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Minimum Order Quantity</label>
                <input type="number" name="products[${productCount}][moq]" class="form-control" value="1" min="1">
            </div>
            <div class="col-md-4">
                <label class="form-label">Lead Time (days)</label>
                <input type="number" name="products[${productCount}][lead_time]" class="form-control" value="0">
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="products[${productCount}][description]" class="form-control" rows="2"></textarea>
            </div>
            <div class="col-12">
                <button type="button" class="btn btn-danger btn-sm" onclick="removeProduct(this)">
                    Remove Product
                </button>
            </div>
        </div>
    `;
    container.appendChild(productDiv);
    productCount++;
}

function removeProduct(button) {
    button.closest('.product-entry').remove();
}

// Add at least one product field by default
document.addEventListener('DOMContentLoaded', function() {
    addProductField();
});
</script>

<?php include 'includes/nav/footer.php'; ?>
</body>
</html>
