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
    <link href="assets/css/dispatch.css" rel="stylesheet">
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
    </style>
</head>
<body>
<?php include 'includes/nav/collapsed.php'; ?>
<?php include 'includes/theme.php'; ?>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2>Edit Category</h2>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <!-- Edit Category Form -->
                <form method="POST">
                    <div class="form-section">
                        <h4 class="mb-3"><i class="bi bi-info-circle"></i></h4>
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
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <button type="submit" name="update_category" class="btn btn-primary btn-lg">
                            <i class="bi bi-save"></i> Save Changes
                        </button>
                        <a href="categories.php" class="btn btn-danger btn-lg">
                            <i class="bi bi-x-circle"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php include 'includes/nav/footer.php'; ?>
</html>