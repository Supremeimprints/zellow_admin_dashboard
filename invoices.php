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
$invoices = [];

// Get supplier_id from URL if provided
$supplier_id = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : null;

// Fetch invoices - either all or supplier specific
try {
    if ($supplier_id) {
        // Query for specific supplier
        $query = "SELECT i.*, s.company_name as supplier_name 
                 FROM invoices i 
                 LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id 
                 WHERE i.supplier_id = ?
                 ORDER BY i.invoice_id DESC";
        $stmt = $db->prepare($query);
        $stmt->execute([$supplier_id]);
    } else {
        // Query for all invoices
        $query = "SELECT i.*, s.company_name as supplier_name 
                 FROM invoices i 
                 LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id 
                 ORDER BY i.invoice_id DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
    }
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If viewing supplier-specific invoices, get supplier name
    if ($supplier_id) {
        $supplierStmt = $db->prepare("SELECT company_name FROM suppliers WHERE supplier_id = ?");
        $supplierStmt->execute([$supplier_id]);
        $supplierName = $supplierStmt->fetchColumn();
    }
} catch (PDOException $e) {
    $errorMsg = "Error fetching invoices: " . $e->getMessage();
}

// Handle invoice settlement
if (isset($_POST['settle_invoice']) && isset($_POST['invoice_id'])) {
    try {
        $stmt = $db->prepare("UPDATE invoices SET status = 'Paid' WHERE invoice_id = ?");
        $stmt->execute([$_POST['invoice_id']]);
        $successMsg = "Invoice settled successfully";
    } catch (PDOException $e) {
        $errorMsg = "Error settling invoice: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Invoices - Zellow Enterprises</title>
     <!-- Feather Icons - Add this line -->
     <script src="https://unpkg.com/feather-icons"></script>
    
    <!-- Existing stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
    <link rel="stylesheet" href="assets/css/collapsed.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/orders.css" rel="stylesheet">
    <link href="assets/css/badges.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   
</head>


<body>
<div class="admin-layout"> 
<?php include 'includes/theme.php'; ?>
    <nav class="navbar">
    <?php include 'includes/nav/collapsed.php'; ?>
    </nav>

    <div class="main-content">
        <div class="container mt-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <?php if (isset($supplierName)): ?>
                        Invoices for <?= htmlspecialchars($supplierName) ?>
                    <?php else: ?>
                        Manage Invoices
                    <?php endif; ?>
                </h2>
                <?php if (!$supplier_id): ?>
                    <a href="generate_invoice.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Generate New Invoice
                    </a>
                <?php else: ?>
                    <div>
                        <a href="generate_invoice.php?supplier_id=<?= $supplier_id ?>" class="btn btn-primary me-2">
                            <i class="fas fa-plus me-2"></i>New Invoice
                        </a>
                        <a href="suppliers.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Suppliers
                        </a>
                    </div>
                <?php endif; ?>
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
                                    <th>Invoice Number</th>
                                    <th>Supplier</th>
                                    <th>Amount</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $invoice): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($invoice['invoice_number']) ?></td>
                                        <td><?= htmlspecialchars($invoice['supplier_name'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($invoice['amount']) ?></td>
                                        <td><?= htmlspecialchars($invoice['due_date']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $invoice['status'] === 'Paid' ? 'success' : 'danger' ?>">
                                                <?= htmlspecialchars($invoice['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <?php if ($invoice['status'] !== 'Paid'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="invoice_id" value="<?= $invoice['invoice_id'] ?>">
                                                        <button type="submit" name="settle_invoice" class="btn btn-sm btn-outline-success">
                                                            <i class="fas fa-check"></i> Settle
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($invoices)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="fas fa-box-open fa-2x mb-3"></i>
                                                <p>No invoices found</p>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
