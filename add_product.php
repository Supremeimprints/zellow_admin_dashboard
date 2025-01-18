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

// Fetch available categories
$category_query = "SELECT * FROM categories"; // Assuming you have a 'categories' table
$category_stmt = $db->prepare($category_query);
$category_stmt->execute();
$categories = $category_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handling form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle form submission to add product
    $name = $_POST['product_name'];
    $image_url = $_POST['image_url'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $category_id = $_POST['category_id'];
    $is_active = isset($_POST['is_active']) ? 1 : 0; // 1 for active, 0 for inactive

    // Insert product into 'products' table
    $insert_product_query = "INSERT INTO products (image_url, product_name, description, category_id, price, is_active) VALUES (?, ?, ?, ?, ?, ?)";
    $insert_product_stmt = $db->prepare($insert_product_query);
    $insert_product_stmt->execute([$image_url, $name, $description, $category_id, $price, $is_active]);

    // Get the last inserted product ID
    $product_id = $db->lastInsertId();

    // Insert corresponding record into 'inventory' table (initializing stock to 0 or a default value)
    $insert_inventory_query = "INSERT INTO inventory (product_id, stock_quantity) VALUES (?, ?)";
    $insert_inventory_stmt = $db->prepare($insert_inventory_query);
    $insert_inventory_stmt->execute([$product_id, 0]);  // You can change 0 to any default stock value you want.

    // Redirect to products page after successful addition
    header("Location: products.php?message=Product added successfully");
    exit();
}
?>

<!-- HTML form for adding product -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - Zellow Enterprises</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<!-- Navigation Bar (included separately for simplicity) -->
<?php include 'navbar.php'; ?>

<div class="container mt-4">
    <h1>Add Product</h1>

    <!-- Add Product Form -->
    <form method="POST">
    <div class="mb-3">
            <label for="image_url" class="form-label">Image URL</label>
            <input type="url" class="form-control" id="image_url" name="image_url">
        </div>
        <div class="mb-3">
            <label for="product_name" class="form-label">Product Name</label>
            <input type="text" class="form-control" id="product_name" name="product_name" required>
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
        </div>
        <div class="mb-3">
            <label for="price" class="form-label">Price</label>
            <input type="number" class="form-control" id="price" name="price" required>
        </div>
        <div class="mb-3">
            <label for="category_id" class="form-label">Category</label>
            <select class="form-select" id="category_id" name="category_id" required>
                <option value="">Select a category</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= $category['category_id']; ?>"><?= htmlspecialchars($category['category_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="is_active" name="is_active">
            <label class="form-check-label" for="is_active">Active</label>
        </div>
        <button type="submit" class="btn btn-primary">Add Product</button>
        <a href="products.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
