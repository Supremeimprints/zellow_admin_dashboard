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

// Handle search input
$search = '';
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

// Update the inventory query to include more details
$query = "
    SELECT 
        i.id,
        p.product_name,
        p.price,
        s.company_name as supplier_name,
        i.stock_quantity,
        i.min_stock_level,
        i.last_restocked,
        u.username AS updated_by,
        CASE 
            WHEN i.stock_quantity <= i.min_stock_level THEN 'low'
            ELSE 'normal'
        END as stock_status
    FROM inventory i
    LEFT JOIN products p ON i.product_id = p.product_id
    LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
    LEFT JOIN users u ON i.updated_by = u.id
    WHERE p.product_name LIKE :search 
       OR s.company_name LIKE :search 
       OR u.username LIKE :search
    ORDER BY stock_status DESC, p.product_name ASC
";

try {
    $stmt = $db->prepare($query);
    $stmt->execute([':search' => '%' . $search . '%']);
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
    <style>
        .supplier-badge {
            display: inline-block;
            max-width: 200px;
            white-space: normal;
            word-wrap: break-word;
            background-color: #dcfce7;
            color: #166534;
            border: 1px solid #16a34a;
            font-size: 0.875rem;
            padding: 0.35rem 0.65rem;
            border-radius: 0.375rem;
            line-height: 1.2;
        }
        
        .low-stock-warning {
            margin-top: 0.5rem;
            display: block;
            color: #dc2626;
            font-size: 0.8rem;
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
        <h2>Manage Inventory</h2>
        
        <!-- Updated buttons section with flex container -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="products.php" class="btn btn-primary">Back to Products</a>
            <a href="order_inventory.php" class="btn btn-success">+ Order Inventory</a>
        </div>

        <?php if (isset($errorMessage)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <!-- Search Form -->
        <form method="GET" action="inventory.php" class="mb-3">
            <div class="input-group">
                <input type="text" name="search" class="form-control" placeholder="Search by product name or updated by" value="<?= htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-primary">Search</button>
            </div>
        </form>

        <!-- Inventory Table -->
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Supplier</th>
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
                            <td colspan="7" class="text-center">No inventory found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($inventory as $item): ?>
                            <tr>
                                <td class="<?= $item['stock_status'] === 'low' ? 'text-danger' : '' ?>">
                                    <?= htmlspecialchars($item['product_name']) ?>
                                </td>
                                <td>
                                    <div class="supplier-badge">
                                        <?= htmlspecialchars($item['supplier_name'] ?? 'No Supplier') ?>
                                    </div>
                                    <?php if ($item['stock_status'] === 'low'): ?>
                                        <span class="low-stock-warning">
                                            <i class="fas fa-exclamation-triangle"></i> Low Stock
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($item['stock_quantity']); ?></td>
                                <td><?= htmlspecialchars($item['min_stock_level']); ?></td>
                                <td><?= htmlspecialchars(date('Y-m-d H:i:s', strtotime($item['last_restocked']))); ?></td>
                                <td><?= htmlspecialchars($item['updated_by']); ?></td>
                                <td>
                                    <a href="update_inventory.php?id=<?= $item['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
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
<?php include 'includes/nav/footer.php'; ?>
</html>
