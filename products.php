<?php
session_start();

// Check if user is logged in as an admin
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

// Initialize Database connection
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get admin info
$query = "SELECT email FROM users WHERE id = ? AND role = 'admin'";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// If admin not found, logout
if (!$admin) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Fetch all products with stock
$query = "SELECT p.product_id, p.product_name, p.price, p.category_id, p.is_active, p.image_url, p.description, 
                 c.category_name, i.stock_quantity
          FROM products p
          LEFT JOIN categories c ON p.category_id = c.category_id
          LEFT JOIN inventory i ON p.product_id = i.product_id";
$stmt = $db->prepare($query);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - Zellow Enterprises</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<!-- Navigation Bar -->
<?php include 'navbar.php'; ?>

<div class="container mt-4">
    <h1>Manage Products</h1>

    <!-- Add Product Button -->
    <a href="add_product.php" class="btn btn-primary mb-3">Add New Product</a>

    <!-- Products Table -->
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Image</th>
                <th>Name</th>
                <th>Price</th>
                <th>Category</th>
                <th>Stock</th>
                <th>Active</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $product): ?>
                <tr>
                    <td><?= htmlspecialchars($product['product_id']); ?></td>
                    <td>
                        <?php if (!empty($product['image_url'])): ?>
                            <img src="<?= htmlspecialchars($product['image_url']); ?>" alt="Product Image" style="width: 50px; height: 50px; object-fit: cover;">
                        <?php else: ?>
                            No image
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($product['product_name']); ?></td>
                    <td><?= htmlspecialchars($product['price']); ?></td>
                    <td><?= htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                    <td><?= htmlspecialchars($product['stock_quantity'] ?? '0'); ?></td>
                    <td><?= $product['is_active'] ? 'Yes' : 'No'; ?></td>
                    <td>
                        <a href="edit_product.php?id=<?= $product['product_id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                        <a href="delete_product.php?id=<?= $product['product_id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this product?');">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
