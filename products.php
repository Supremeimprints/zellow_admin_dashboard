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
$query = "SELECT email, profile_photo FROM users WHERE id = ? AND role = 'admin'";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// If admin not found, logout
if (!$admin) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Update session profile photo
$_SESSION['profile_photo'] = $admin['profile_photo'];

// Handle search input
$search = '';
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

// Fetch all products with stock, filtered by search
// Corrected SELECT statement (line ~33 in your code)
$query = "
    SELECT p.product_id, p.product_name, p.price, p.category_id, p.is_active, p.main_image, p.description, 
           c.category_name, i.stock_quantity
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN inventory i ON p.product_id = i.product_id
    WHERE p.product_name LIKE :search OR c.category_name LIKE :search
";
$stmt = $db->prepare($query);
$stmt->execute([':search' => "%$search%"]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - Zellow Enterprises</title>
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
    <link href="assets/css/products.css" rel="stylesheet">
    <style>
        .action-buttons {
            display: flex;
            justify-content: space-between;
            width: 100%;
            margin-bottom: 1rem;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn i {
            font-size: 1.1rem;
        }
    </style>
</head>

<body>

<div class="admin-layout"> 
<?php include 'includes/theme.php'; ?>
    <nav class="navbar">
    <?php include 'includes/nav/collapsed.php'; ?>
    </nav>
    <div class="container mt-4">
       <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manage Products</h2>
        </div>
        <div class="action-buttons">
            <a href="add_product.php" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                Add New Product
            </a>
            <a href="order_inventory.php" class="btn btn-success">
                <i class="fas fa-tags"></i>
                Update Stock
            </a>
        </div>

        <!-- Search Form -->
        <form method="GET" action="products.php" class="mb-3">
            <div class="input-group">
                <input type="text" name="search" class="form-control" placeholder="Search by product name or category"
                    value="<?= htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary">Search</button>
            </div>
        </form>

        <!-- Products Table -->
        <div class="table-responsive">
            <table class="table table-striped">
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
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $product): ?>
                            <tr data-product-id="<?= htmlspecialchars($product['product_id']); ?>">
                                <td><?= htmlspecialchars($product['product_id']); ?></td>
                                <td>
                                    <?php if (!empty($product['main_image'])): ?>
                                        <div class="thumbnail-container">
                                            <img src="<?= htmlspecialchars($product['main_image']); ?>" alt="Product Thumbnail"
                                                class="img-thumbnail table-thumbnail">
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">No image</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($product['product_name']); ?></td>
                                <td><?= htmlspecialchars($product['price']); ?></td>
                                <td><?= htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                                <td><?= htmlspecialchars($product['stock_quantity'] ?? '0'); ?></td>
                                <td><?= $product['is_active'] ? 'Yes' : 'No'; ?></td>
                                <td>
                                    <a href="edit_product.php?id=<?= $product['product_id']; ?>"
                                        class="btn btn-warning btn-sm">Edit</a>
                                    <button onclick="deleteProduct(<?= $product['product_id']; ?>)" class="btn btn-danger btn-sm">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center">No products found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
        function deleteProduct(productId) {
            if (!productId) {
                alert('Invalid product ID');
                return;
            }
            
            if (confirm('Are you sure you want to delete this product?')) {
                const formData = new URLSearchParams();
                formData.append('product_id', productId);

                fetch('delete_product.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: formData.toString()
                })
                .then(response => response.json())
                .then(data => {
                    console.log('Delete response:', data); // Debug log
                    if (data.success) {
                        const row = document.querySelector(`tr[data-product-id="${productId}"]`);
                        if (row) {
                            row.remove();
                        }
                        alert('Product deleted successfully');
                    } else {
                        throw new Error(data.message || 'Failed to delete product');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert(error.message || 'Error deleting product');
                });
            }
        }
        </script>
</body>
<?php include 'includes/nav/footer.php'; ?>

</html>