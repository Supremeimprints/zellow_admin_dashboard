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

// Fetch all categories
$query = "SELECT * FROM categories ORDER BY category_name";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Add new category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $category_name = trim($_POST['category_name']);

    if (!empty($category_name)) {
        $add_query = "INSERT INTO categories (category_name) VALUES (:category_name)";
        $add_stmt = $db->prepare($add_query);

        try {
            $add_stmt->execute([':category_name' => $category_name]);
            $_SESSION['success'] = "Category added successfully.";
        } catch (Exception $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Category name cannot be empty.";
    }
    header('Location: categories.php');
    exit();
}

// Replace the existing delete category section with this:
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];

    // Prevent deletion of "Uncategorized" category
    if ($delete_id === 1) {
        $_SESSION['error'] = "The default category cannot be deleted.";
        header('Location: categories.php');
        exit();
    }

    // Begin transaction
    $db->beginTransaction();

    try {
        // First update all products in this category to "Uncategorized"
        $update_query = "UPDATE products 
                        SET category_id = 1 
                        WHERE category_id = :category_id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute([':category_id' => $delete_id]);

        // Then delete the category
        $delete_query = "DELETE FROM categories WHERE category_id = :category_id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->execute([':category_id' => $delete_id]);

        $db->commit();
        $_SESSION['success'] = "Category deleted successfully. All associated products moved to Uncategorized.";
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header('Location: categories.php');
    exit();
}
?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Zellow Enterprises</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/categories.css" rel="stylesheet">
</head>

<body>
    <!-- Navigation Bar -->
    <?php include 'includes/nav/collapsed.php'; ?>
    <!-- Theme CSS -->
    <?php include 'includes/theme.php'; ?>

    <div class="container mt-5">
        <h2>Manage Categories</h2>

        <!-- Display Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <!-- Add Category Form -->
        <form action="categories.php" method="POST" class="mb-4">
            <div class="row">
                <div class="col-md-8">
                    <input type="text" name="category_name" class="form-control" placeholder="Enter new category name" required>
                </div>
                <div class="col-md-4">
                    <button type="submit" name="add_category" class="btn btn-primary w-100">Add Category</button>
                </div>
            </div>
        </form>

        <!-- Categories Table -->
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Category Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><?= htmlspecialchars($category['category_id']); ?></td>
                        <td><?= htmlspecialchars($category['category_name']); ?></td>
                        <td>
                            <a href="edit_category.php?id=<?= $category['category_id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                            <a href="categories.php?delete_id=<?= $category['category_id']; ?>" 
                               class="btn btn-danger btn-sm"
                               onclick="return confirm('Are you sure you want to delete this category?');">
                               Delete
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php include 'includes/nav/footer.php'; ?>
</html>
