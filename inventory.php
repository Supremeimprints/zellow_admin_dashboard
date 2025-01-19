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

// Fetch inventory data
$query = "SELECT i.id, p.product_name, i.stock_quantity, i.min_stock_level, i.last_restocked, u.username AS updated_by
          FROM inventory i
          LEFT JOIN products p ON i.product_id = p.product_id
          LEFT JOIN users u ON i.updated_by = u.id";

try {
    $stmt = $db->prepare($query);
    $stmt->execute();
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $errorMessage = "Error fetching inventory: " . $e->getMessage();
    $inventory = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>
    <div class="container mt-5">
        <h2>Manage Inventory</h2>
        <!-- Add Product Button -->
    <a href="products.php" class="btn btn-primary mb-3">Back to Products</a>

        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <!-- Inventory Table -->
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Stock Quantity</th>
                        <th>Min Stock Level</th>
                        <th>Last Restocked</th>
                        <th>Updated By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inventory)): ?>
                        <tr>
                            <td colspan="5" class="text-center">No inventory found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($inventory as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($item['stock_quantity']); ?></td>
                                <td><?php echo htmlspecialchars($item['min_stock_level']); ?></td>
                                <td><?php echo htmlspecialchars(date('Y-m-d H:i:s', strtotime($item['last_restocked']))); ?></td>
                                <td><?php echo htmlspecialchars($item['updated_by']); ?></td>
                                <td>
                                    <a href="update_inventory.php?id=<?php echo $item['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                    
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
