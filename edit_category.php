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

// Get category ID from URL
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No category specified.";
    header('Location: categories.php');
    exit();
}

$category_id = (int)$_GET['id'];

// Prevent editing of "Uncategorized" category
if ($category_id === 1) {
    $_SESSION['error'] = "The default category cannot be edited.";
    header('Location: categories.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
    $category_name = trim($_POST['category_name']);

    if (!empty($category_name)) {
        $update_query = "UPDATE categories 
                        SET category_name = :category_name 
                        WHERE category_id = :category_id";
        $update_stmt = $db->prepare($update_query);

        try {
            $update_stmt->execute([
                ':category_name' => $category_name,
                ':category_id' => $category_id
            ]);
            $_SESSION['success'] = "Category updated successfully.";
            header('Location: categories.php');
            exit();
        } catch (Exception $e) {
            $_SESSION['error'] = "Error updating category: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Category name cannot be empty.";
    }
}

// Fetch category details
$query = "SELECT * FROM categories WHERE category_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$category_id]);
$category = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$category) {
    $_SESSION['error'] = "Category not found.";
    header('Location: categories.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Category - Zellow Enterprises</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <h1>Edit Category</h1>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger">
                <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Edit Category Form -->
        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="category_name" class="form-label">Category Name</label>
                        <input type="text" 
                               class="form-control" 
                               id="category_name" 
                               name="category_name" 
                               value="<?= htmlspecialchars($category['category_name']); ?>" 
                               required>
                    </div>

                    <!-- Display number of products in this category -->
                    <?php
                    $products_query = "SELECT COUNT(*) as count FROM products WHERE category_id = ?";
                    $products_stmt = $db->prepare($products_query);
                    $products_stmt->execute([$category_id]);
                    $product_count = $products_stmt->fetch(PDO::FETCH_ASSOC)['count'];
                    ?>
                    <div class="mb-3">
                        <p class="text-info">
                            This category contains <?= $product_count ?> product(s).
                        </p>
                    </div>

                    <div class="mb-3">
                        <button type="submit" name="update_category" class="btn btn-primary">
                            Update Category
                        </button>
                        <a href="categories.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>