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

// Update the suppliers fetch query to show only essential columns
$query = "
    SELECT 
        s.supplier_id,
        s.company_name,
        s.status,
        s.is_active,
        s.created_at
    FROM suppliers s
    WHERE s.is_active = 1
    ORDER BY s.created_at DESC";

try {
    $stmt = $db->query($query);
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMsg = "Error fetching suppliers: " . $e->getMessage();
    // Add debug information
    error_log("Query error: " . $e->getMessage());
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
        .action-buttons {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .action-buttons .btn {
            padding: 0.4rem 0.6rem;
            border-radius: 8px;
            transition: all 0.2s ease;
            border: none;
            font-size: 0.875rem;
        }

        .btn-soft-primary {
            color: #0d6efd !important;
            background-color: rgba(13, 110, 253, 0.1) !important;
        }

        .btn-soft-primary:hover {
            color: #fff;
            background-color: #0d6efd !important;
        }

        .btn-soft-warning {
            color: #ffc107 !important;
            background-color: rgba(255, 193, 7, 0.1) !important;
        }

        .btn-soft-warning:hover {
            color: #fff !important;
            background-color: #ffc107 !important;
        }

        .btn-soft-danger {
            color: #dc3545 !important;
            background-color: rgba(220, 53, 69, 0.1) !important;
        }

        .btn-soft-danger:hover {
            color: #fff !important;
            background-color: #dc3545 !important;
        }

        /* Dark mode adjustments */
        [data-bs-theme="dark"] .btn-soft-primary {
            color: #4d94ff !important;
            background-color: rgba(77, 148, 255, 0.15) !important;
        }

        [data-bs-theme="dark"] .btn-soft-warning {
            color: #ffcd39;
            background-color: rgba(255, 205, 57, 0.15);
        }

        [data-bs-theme="dark"] .btn-soft-danger {
            color: #ff4d4d;
            background-color: rgba(255, 77, 77, 0.15);
        }

        [data-bs-theme="dark"] .btn-soft-primary:hover {
            background-color: #4d94ff;
            color: #fff;
        }

        [data-bs-theme="dark"] .btn-soft-warning:hover {
            background-color: #ffcd39;
            color: #fff;
        }

        [data-bs-theme="dark"] .btn-soft-danger:hover {
            background-color: #ff4d4d;
            color: #fff;
        }

        .table td {
            vertical-align: middle;
            padding: 1rem 0.75rem;
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
                                    <th>ID</th>
                                    <th>Company Name</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <tr data-supplier-id="<?= $supplier['supplier_id'] ?>">
                                        <td><?= htmlspecialchars($supplier['supplier_id']) ?></td>
                                        <td><?= htmlspecialchars($supplier['company_name']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $supplier['status'] === 'Active' ? 'success' : 'danger' ?>">
                                                <?= htmlspecialchars($supplier['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group action-buttons" role="group">
                                                <a href="supplier_products.php?id=<?= $supplier['supplier_id'] ?>" 
                                                   class="btn btn-soft-primary"
                                                   title="Manage Products">
                                                    <i class="fas fa-box"></i>
                                                </a>
                                                <a href="edit_supplier.php?supplier_id=<?= $supplier['supplier_id'] ?>" 
                                                   class="btn btn-soft-warning"
                                                   title="Edit Supplier">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-soft-danger"
                                                        title="Remove Supplier"
                                                        onclick="deactivateSupplier(<?= $supplier['supplier_id'] ?>)">
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

        function deactivateSupplier(supplierId) {
            if (confirm('Are you sure you want to remove this supplier?')) {
                fetch('delete_supplier.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `supplier_id=${supplierId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remove the row from the table
                        document.querySelector(`tr[data-supplier-id="${supplierId}"]`).remove();
                    } else {
                        alert(data.message || 'Error deactivating supplier');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error processing request');
                });
            }
        }
    </script>
</body>
</html>
