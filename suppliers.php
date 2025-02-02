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

// Fetch all suppliers
try {
    $stmt = $db->query("SELECT * FROM suppliers ORDER BY company_name");
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMsg = "Error fetching suppliers: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Suppliers - Zellow Enterprises</title>
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

<body class="admin-layout">
    <?php include 'includes/nav/collapsed.php'; ?>
    
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
                                    <th>Company Name</th>
                                    <th>Contact Person</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <tr>
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
                                                <a href="edit_supplier.php?id=<?= $supplier['supplier_id'] ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(supplierID) {
            document.getElementById('deleteSupplierID').value = supplierID;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>
