<?php
session_start();
require_once 'config/database.php';

// Check for admin authentication
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Initialize variables
$successMsg = $errorMsg = '';
$suppliers = [];

// Handle supplier deletion
if (isset($_POST['delete_supplier']) && isset($_POST['supplier_id'])) {
    try {
        $stmt = $db->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
        $stmt->execute([$_POST['supplier_id']]);
        $successMsg = "Supplier deleted successfully";
    } catch (PDOException $e) {
        $errorMsg = "Error deleting supplier: " . $e->getMessage();
    }
}

// Update the supplier query to use supplier_id instead of id
try {
    $stmt = $db->query("
        SELECT s.*, 
               COUNT(p.product_id) as product_count,
               GROUP_CONCAT(p.product_name) as supplied_products
        FROM suppliers s
        LEFT JOIN products p ON s.supplier_id = p.supplier_id
        GROUP BY s.supplier_id
        ORDER BY s.company_name");
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMsg = "Error fetching suppliers: " . $e->getMessage();
}

// Fix invoices fetching for each supplier
$supplierInvoices = [];
try {
    $stmt = $db->query("SELECT * FROM invoices");
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($invoices as $invoice) {
        // Change 'id' to 'supplier_id'
        $supplierInvoices[$invoice['supplier_id']][] = $invoice;
    }
} catch (PDOException $e) {
    $errorMsg = "Error fetching invoices: " . $e->getMessage();
}

// Handle product form submission
if (isset($_POST['add_product'])) {
    try {
        $stmt = $db->prepare("
            INSERT INTO supplier_products (
                supplier_id,
                product_name, 
                description, 
                unit_price, 
                moq, 
                lead_time
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_POST['supplier_id'],
            $_POST['product_name'],
            $_POST['description'],
            $_POST['unit_price'],
            $_POST['moq'],
            $_POST['lead_time']
        ]);
        
        $successMsg = "Product added successfully";
        header("Location: suppliers.php");
        exit();
    } catch (PDOException $e) {
        $errorMsg = "Error adding product: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Suppliers - Zellow Enterprises</title>
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <style>
        .supplier-status-active { color: #198754; }
        .supplier-status-inactive { color: #dc3545; }
        .table-hover tbody tr:hover {
            background-color: rgba(var(--bs-primary-rgb), 0.05);
            cursor: pointer;
        }
        .card { background: var(--bs-white); }
        .card-header {
            background: var(--bs-primary);
            color: var(--bs-white);
        }
    </style>
</head>
<?php include 'includes/theme.php'; ?>

<body>
<div class="admin-layout"> 
<?php include 'includes/theme.php'; ?>
    <nav class="navbar">
    <?php include 'includes/nav/collapsed.php'; ?>
    </nav>
    <div class="main-content">
        <div class="container mt-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Manage Suppliers</h2>
                <a href="add_supplier.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Add New Supplier
                </a>
            </div>

            <?php if ($successMsg): ?>
                <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
            <?php endif; ?>

            <?php if ($errorMsg): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
            <?php endif; ?>

            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Supplier ID</th>
                                    <th>Company Name</th>
                                    <th>Contact Person</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Products</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($supplier['supplier_id']) ?></td>
                                        <td><?= htmlspecialchars($supplier['company_name']) ?></td>
                                        <td><?= htmlspecialchars($supplier['contact_person']) ?></td>
                                        <td><?= htmlspecialchars($supplier['email']) ?></td>
                                        <td><?= htmlspecialchars($supplier['phone']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $supplier['status'] === 'Active' ? 'success' : 'danger' ?>">
                                                <?= htmlspecialchars($supplier['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-outline-primary dropdown-toggle" 
                                                        type="button" 
                                                        data-bs-toggle="dropdown">
                                                    Products (<?= $supplier['product_count'] ?>)
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <?php 
                                                    if ($supplier['product_count'] > 0):
                                                        $products = explode(',', $supplier['supplied_products']);
                                                        foreach ($products as $product): 
                                                    ?>
                                                        <li><span class="dropdown-item"><?= htmlspecialchars(trim($product)) ?></span></li>
                                                    <?php 
                                                        endforeach; 
                                                    else:
                                                    ?>
                                                        <li><span class="dropdown-item text-muted">No products</span></li>
                                                    <?php endif; ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <a class="dropdown-item text-primary" href="inventory.php?supplier_id=<?= $supplier['supplier_id'] ?>">
                                                            View in Inventory
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="edit_supplier.php?supplier_id=<?= $supplier['supplier_id'] ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="invoices.php?supplier_id=<?= $supplier['supplier_id'] ?>" 
                                                   class="btn btn-sm btn-outline-info">
                                                    <i class="fas fa-file-invoice"></i>
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-danger"
                                                        onclick="confirmDelete(<?= $supplier['supplier_id'] ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($suppliers)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="fas fa-box-open fa-2x mb-3"></i>
                                                <p>No suppliers found</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/nav/footer.php'; ?>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this supplier?
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="supplier_id" id="deleteSupplierID">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_supplier" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Supplier Product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="supplier_id" id="modalSupplierId">
                        <div class="mb-3">
                            <label class="form-label">Product Name</label>
                            <input type="text" name="product_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Unit Price</label>
                            <input type="number" name="unit_price" class="form-control" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Minimum Order Quantity</label>
                            <input type="number" name="moq" class="form-control" value="1" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Lead Time (days)</label>
                            <input type="number" name="lead_time" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_product" class="btn btn-primary">Add Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(supplierId) {
            document.getElementById('deleteSupplierID').value = supplierId;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }

        function showAddProductModal(supplierId) {
            document.getElementById('modalSupplierId').value = supplierId;
            new bootstrap.Modal(document.getElementById('addProductModal')).show();
        }

        function editProduct(product) {
            // Implement edit functionality
            console.log('Edit product:', product);
        }
    </script>
</body>
</html>
