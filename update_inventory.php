<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

// Check if user has admin access
if ($_SESSION['role'] !== 'admin') {
    echo "Access denied. You do not have permission to view this page.";
    exit();
}

// Initialize Database connection
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Handle Inventory Update
if (isset($_POST['update_inventory'])) {
    try {
        $inventoryId = $_POST['inventory_id'];
        $stockQuantity = $_POST['stock_quantity'];
        $minStockLevel = $_POST['min_stock_level'];
        $updatedBy = $_SESSION['id'];

        if (empty($inventoryId) || empty($stockQuantity) || empty($minStockLevel)) {
            throw new Exception("All fields are required");
        }

        $updateQuery = "UPDATE inventory SET stock_quantity = ?, min_stock_level = ?, updated_by = ? WHERE id = ?";
        $stmt = $db->prepare($updateQuery);
        $stmt->execute([$stockQuantity, $minStockLevel, $updatedBy, $inventoryId]);

        header("Location: inventory.php?success=Inventory updated successfully");
        exit();
    } catch (Exception $e) {
        $errorMessage = "Error updating inventory: " . $e->getMessage();
    }
}

// Fetch inventory details for the selected item
if (isset($_GET['id'])) {
    $inventoryId = $_GET['id'];
    $query = "SELECT i.id, i.product_id, p.product_name, i.stock_quantity, i.min_stock_level
              FROM inventory i
              LEFT JOIN products p ON i.product_id = p.product_id
              WHERE i.id = ?";
    
    try {
        $stmt = $db->prepare($query);
        $stmt->execute([$inventoryId]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$item) {
            throw new Exception("Item not found");
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="assets/css/inventory.css">
</head>
<body>
<?php include 'includes/nav/collapsed.php'; ?>
<?php include 'includes/theme.php'; ?>

    <div class="container mt-5">
        <div class="alert alert-primary" role="alert">
            <h4 class="mb-0">Update Inventory</h4>
        </div>

        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($_GET['success']); ?>
            </div>
        <?php endif; ?>

        <!-- Update Inventory Form -->
        <form method="POST" action="update_inventory.php">
            <input type="hidden" name="inventory_id" value="<?php echo $item['id']; ?>">
            <div class="mb-3">
                <label for="product_name" class="form-label">Product Name</label>
                <input type="text" class="form-control" id="product_name" value="<?php echo htmlspecialchars($item['product_name']); ?>" disabled>
            </div>
            <div class="mb-3">
                <label for="stock_quantity" class="form-label">Stock Quantity</label>
                <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" value="<?php echo htmlspecialchars($item['stock_quantity']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="min_stock_level" class="form-label">Min Stock Level</label>
                <input type="number" class="form-control" id="min_stock_level" name="min_stock_level" value="<?php echo htmlspecialchars($item['min_stock_level']); ?>" required>
            </div>
            <div class="d-flex justify-content-between">
                <button type="submit" name="update_inventory" class="btn btn-primary">Update Inventory</button>
                <a href="inventory.php" class="btn btn-danger">Cancel</a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php include 'includes/nav/footer.php'; ?>
</html>
