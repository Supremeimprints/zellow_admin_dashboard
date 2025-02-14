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
if (isset($_POST['delete_supplier']) && isset($_POST['id'])) {
    try {
        $stmt = $db->prepare("DELETE FROM suppliers WHERE id = ?");
        $stmt->execute([$_POST['id']]);
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

// Fetch invoices for each supplier
$supplierInvoices = [];
try {
    $stmt = $db->query("SELECT * FROM invoices");
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($invoices as $invoice) {
        $supplierInvoices[$invoice['supplier_id']][] = $invoice;
    }
} catch (PDOException $e) {
    $errorMsg = "Error fetching invoices: " . $e->getMessage();
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
                                                <a href="edit_supplier.php?id=<?= $supplier['id'] ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-danger"
                                                        onclick="confirmDelete(<?= $supplier['id'] ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td colspan="6">
                                            <div class="accordion" id="accordionInvoices<?= $supplier['id'] ?>">
                                                <div class="accordion-item">
                                                    <h2 class="accordion-header" id="heading<?= $supplier['id'] ?>">
                                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $supplier['id'] ?>" aria-expanded="false" aria-controls="collapse<?= $supplier['id'] ?>">
                                                            View Invoices
                                                        </button>
                                                    </h2>
                                                    <div id="collapse<?= $supplier['id'] ?>" class="accordion-collapse collapse" aria-labelledby="heading<?= $supplier['id'] ?>" data-bs-parent="#accordionInvoices<?= $supplier['id'] ?>">
                                                        <div class="accordion-body">
                                                            <?php if (isset($supplierInvoices[$supplier['id']])): ?>
                                                                <table class="table table-sm">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Invoice Number</th>
                                                                            <th>Amount</th>
                                                                            <th>Due Date</th>
                                                                            <th>Status</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php foreach ($supplierInvoices[$supplier['id']] as $invoice): ?>
                                                                            <tr>
                                                                                <td><?= htmlspecialchars($invoice['invoice_number']) ?></td>
                                                                                <td><?= htmlspecialchars($invoice['amount']) ?></td>
                                                                                <td><?= htmlspecialchars($invoice['due_date']) ?></td>
                                                                                <td>
                                                                                    <span class="badge bg-<?= $invoice['status'] === 'Paid' ? 'success' : 'danger' ?>">
                                                                                        <?= htmlspecialchars($invoice['status']) ?>
                                                                                    </span>
                                                                                </td>
                                                                            </tr>
                                                                        <?php endforeach; ?>
                                                                    </tbody>
                                                                </table>
                                                            <?php else: ?>
                                                                <div class="alert alert-info" role="alert">
                                                                    No invoices found for this supplier.
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
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
                        <input type="hidden" name="id" id="deleteSupplierID">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_supplier" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(id) {
            document.getElementById('deleteSupplierID').value = id;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>
