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

// Initialize variables
$stats = [
    'total' => 0,
    'low_stock' => 0,
    'out_of_stock' => 0,
    'total_value' => 0
];
$supplierStats = [];

// Modified queries to use correct supplier status conditions
$statsQueries = [
    // First query remains the same
    "SELECT 
        COUNT(*) as total,
        SUM(CASE 
            WHEN i.stock_quantity > 0 AND i.stock_quantity <= i.min_stock_level THEN 1 
            ELSE 0 
        END) as low_stock,
        SUM(CASE 
            WHEN i.stock_quantity = 0 THEN 1 
            ELSE 0 
        END) as out_of_stock,
        COALESCE(SUM(i.stock_quantity * p.price), 0) as total_value
    FROM inventory i 
    JOIN products p ON i.product_id = p.product_id",
    
    // Updated supplier query with correct status conditions
    "SELECT 
        s.company_name,
        COUNT(DISTINCT p.product_id) as product_count,
        SUM(CASE WHEN po.status IN ('pending', 'approved') THEN poi.quantity ELSE 0 END) as ordered_quantity,
        SUM(CASE WHEN po.status = 'received' THEN poi.quantity ELSE 0 END) as received_quantity,
        COALESCE(SUM(po_payments.amount), 0) as total_payments,
        SUM(CASE WHEN po.status = 'received' THEN poi.quantity ELSE 0 END) as total_received
    FROM suppliers s
    LEFT JOIN products p ON s.supplier_id = p.supplier_id
    LEFT JOIN purchase_orders po ON s.supplier_id = po.supplier_id
    LEFT JOIN purchase_order_items poi ON po.purchase_order_id = poi.purchase_order_id
    LEFT JOIN (
        SELECT purchase_order_id, SUM(amount) as amount 
        FROM purchase_payments 
        WHERE status = 'Completed'
        GROUP BY purchase_order_id
    ) po_payments ON po.purchase_order_id = po_payments.purchase_order_id
    WHERE s.status = 'Active' 
    AND s.is_active = 1 
    AND s.deactivated_at IS NULL
    GROUP BY s.supplier_id, s.company_name"
];

try {
    // Get inventory statistics
    $statsStmt = $db->query($statsQueries[0]);
    if ($statsStmt) {
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // Get supplier statistics
    $supplierStmt = $db->query($statsQueries[1]);
    if ($supplierStmt) {
        $supplierStats = $supplierStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $errorMessage = "Error fetching statistics: " . $e->getMessage();
}

// Update the inventory query with correct supplier status conditions
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
            WHEN i.stock_quantity = 0 THEN 'out_of_stock'
            WHEN i.stock_quantity <= i.min_stock_level THEN 'low'
            ELSE 'normal'
        END as stock_status,
        COALESCE(poi.quantity, 0) as last_received_quantity,
        COALESCE(po.order_date, '') as last_order_date
    FROM inventory i
    LEFT JOIN products p ON i.product_id = p.product_id
    LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id 
        AND s.status = 'Active' 
        AND s.is_active = 1 
        AND s.deactivated_at IS NULL
    LEFT JOIN users u ON i.updated_by = u.id
    LEFT JOIN (
        SELECT poi.product_id, MAX(po.purchase_order_id) as last_po_id
        FROM purchase_orders po
        JOIN purchase_order_items poi ON po.purchase_order_id = poi.purchase_order_id
        WHERE po.status = 'received'
        GROUP BY poi.product_id
    ) last_po ON p.product_id = last_po.product_id
    LEFT JOIN purchase_order_items poi ON last_po.last_po_id = poi.purchase_order_id
    LEFT JOIN purchase_orders po ON poi.purchase_order_id = po.purchase_order_id
    WHERE (p.product_name LIKE :search 
       OR s.company_name LIKE :search 
       OR u.username LIKE :search)
    ORDER BY 
        CASE stock_status 
            WHEN 'out_of_stock' THEN 1
            WHEN 'low' THEN 2
            ELSE 3
        END ASC, 
        p.product_name ASC
";

try {
    $stmt = $db->prepare($query);
    $stmt->execute([':search' => '%' . $search . '%']);
    $inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $errorMessage = "Error fetching inventory: " . $e->getMessage();
}

// Update the supplier statistics query to correctly count active orders
$supplierStatsQuery = "
    SELECT 
        s.company_name,
        COUNT(DISTINCT p.product_id) as product_count,
        (
            SELECT SUM(poi.quantity)
            FROM purchase_orders po 
            JOIN purchase_order_items poi ON po.purchase_order_id = poi.purchase_order_id
            WHERE po.supplier_id = s.supplier_id 
            AND po.is_fulfilled = FALSE
        ) as ordered_quantity,
        (
            SELECT SUM(poi.received_quantity)
            FROM purchase_orders po 
            JOIN purchase_order_items poi ON po.purchase_order_id = poi.purchase_order_id
            WHERE po.supplier_id = s.supplier_id 
            AND po.is_fulfilled = FALSE
        ) as received_quantity,
        (
            SELECT SUM(pp.amount)
            FROM purchase_payments pp
            JOIN purchase_orders po ON pp.purchase_order_id = po.purchase_order_id
            WHERE po.supplier_id = s.supplier_id
            AND pp.status = 'Completed'
        ) as total_payments
    FROM suppliers s
    LEFT JOIN products p ON s.supplier_id = p.supplier_id
    WHERE s.status = 'Active' 
    AND s.is_active = 1
    GROUP BY s.supplier_id, s.company_name
";

try {
    $supplierStmt = $db->query($supplierStatsQuery);
    $supplierStats = $supplierStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $errorMessage = "Error fetching supplier statistics: " . $e->getMessage();
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
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/badges.css">
    <link rel="stylesheet" href="assets/css/orders.css">
    <link rel="stylesheet" href="assets/css/collapsed.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/orders.css" rel="stylesheet">
    <link href="assets/css/badges.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/inventory.css" rel="stylesheet">
    <style>
    .summary-card {
        background: -var(--bs-light);
        border-radius: 10px;
        padding: 1.5rem;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        margin-bottom: 1.5rem;
        transition: transform 0.2s ease;
    }

    .summary-card:hover {
        transform: translateY(-3px);
    }

    .summary-card .icon-box {
        width: 48px;
        height: 48px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
        margin-bottom: 1rem;
    }

    .summary-card h6 {
        color: #6b7280;
        font-size: 0.875rem;
        margin-bottom: 0.5rem;
    }

    .summary-card h3 {
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .supplier-card {
        background: linear-gradient(145deg, #ffffff, #f8fafc);
    }

    .supplier-card .progress {
        height: 6px;
        border-radius: 3px;
    }

    .supplier-card .progress-bar {
        background: linear-gradient(45deg, #3b82f6, #60a5fa);
    }

    .stock-alert {
        display: block;
        margin-top: 0.5rem;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
        width: fit-content;
    }

    tr.out-of-stock {
        background-color: rgba(239, 68, 68, 0.1) !important;
    }

    tr.low-stock {
        background-color: rgba(245, 158, 11, 0.1) !important;
    }

    .asset-icon { background-color: rgba(59, 130, 246, 0.1); color: #3b82f6; }
    .stock-icon { background-color: rgba(16, 185, 129, 0.1); color: #10b981; }
    .warning-icon { background-color: rgba(245, 158, 11, 0.1); color: #f59e0b; }
    .danger-icon { background-color: rgba(239, 68, 68, 0.1); color: #ef4444; }
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

        <!-- Summary Cards with updated classes -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="icon-box stock-icon">
                        <i class="fas fa-box fa-lg"></i>
                    </div>
                    <h6>Available Products</h6>
                    <h3><?= $stats['total'] - $stats['low_stock'] - $stats['out_of_stock'] ?></h3>
                    <small class="text-success">In Stock</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="icon-box warning-icon">
                        <i class="fas fa-exclamation-triangle fa-lg"></i>
                    </div>
                    <h6>Low Stock Items</h6>
                    <h3 class="text-warning"><?= $stats['low_stock'] ?></h3>
                    <small class="text-warning">Needs Attention</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="icon-box danger-icon">
                        <i class="fas fa-times-circle fa-lg"></i>
                    </div>
                    <h6>Out of Stock</h6>
                    <h3 class="text-danger"><?= $stats['out_of_stock'] ?></h3>
                    <small class="text-danger">Requires Action</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="summary-card">
                    <div class="icon-box asset-icon">
                        <i class="fas fa-chart-line fa-lg"></i>
                    </div>
                    <h6>Total Inventory Value</h6>
                    <h3>Ksh. <?= number_format($stats['total_value'], 2) ?></h3>
                    <small class="text-primary">Asset Value</small>
                </div>
            </div>
        </div>

        <!-- Supplier Statistics with updated classes -->
        <div class="row mb-4">
            <?php if (!empty($supplierStats)): ?>
                <?php foreach ($supplierStats as $supplier): ?>
                    <div class="col-md-4 mb-3">
                        <div class="summary-card supplier-card">
                            <h6><?= htmlspecialchars($supplier['company_name']) ?></h6>
                            <div class="mt-3">
                                <p class="mb-2">Order Fulfillment</p>
                                <?php 
                                $ordered = $supplier['ordered_quantity'] ?? 0;
                                $received = $supplier['received_quantity'] ?? 0;
                                $percentage = $ordered > 0 ? ($received / $ordered) * 100 : 0;
                                ?>
                                <div class="progress mb-2">
                                    <div class="progress-bar" role="progressbar" 
                                         style="width: <?= $percentage ?>%">
                                    </div>
                                </div>
                                <small class="text-muted">
                                    <?= number_format($received) ?> / <?= number_format($ordered) ?> items
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        No supplier statistics available
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Updated buttons section with flex container -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <a href="products.php" class="btn btn-primary me-2">
                <i data-feather="shopping-bag"></i> Products
                </a>
                <a href="receive_goods.php" class="btn btn-info me-2">
                <i data-feather="arrow-down-circle"  style="color: white"></i> Receive Goods
                </a>
            </div>
            <div>
                <a href="order_inventory.php" class="btn btn-success">
                    <i class="fas fa-plus"></i> Order Inventory
                </a>
            </div>
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
                        <th>Last Received</th>
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
                            <tr class="<?= $item['stock_status'] === 'out_of_stock' ? 'out-of-stock' : 
                                           ($item['stock_status'] === 'low' ? 'low-stock' : '') ?>">
                                <td><?= htmlspecialchars($item['product_name']) ?></td>
                                <td>
                                    <?= htmlspecialchars($item['supplier_name'] ?? 'No Supplier') ?>
                                    <?php if ($item['stock_status'] === 'out_of_stock'): ?>
                                        <div class="stock-alert bg-danger">
                                            <i class="fas fa-exclamation-circle"></i>Out of Stock
                                        </div>
                                    <?php elseif ($item['stock_status'] === 'low'): ?>
                                        <div class="stock-alert bg-warning">
                                            <i class="fas fa-exclamation-triangle"></i>Low Stock
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($item['stock_quantity']); ?></td>
                                <td><?= htmlspecialchars($item['min_stock_level']); ?></td>
                                <td><?= $item['last_received_quantity'] > 0 
                                        ? htmlspecialchars($item['last_received_quantity']) . ' on ' . 
                                          date('Y-m-d', strtotime($item['last_order_date']))
                                        : 'No recent receipts' ?>
                                </td>
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
