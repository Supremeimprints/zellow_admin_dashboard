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

// Add after the initialization of variables
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_range = isset($_GET['date_range']) ? $_GET['date_range'] : '';

// Get supplier_id from URL if provided
$supplier_id = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : null;

// Fetch invoices - either all or supplier specific
try {
    // Modify the query building
    $where_conditions = [];
    $params = [];

    if ($supplier_id) {
        $where_conditions[] = "i.supplier_id = ?";
        $params[] = $supplier_id;
    }

    if ($search) {
        $where_conditions[] = "(i.invoice_number LIKE ? OR s.company_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($status_filter) {
        $where_conditions[] = "i.status = ?";
        $params[] = $status_filter;
    }

    if ($date_range) {
        $dates = explode(' - ', $date_range);
        if (count($dates) == 2) {
            $where_conditions[] = "i.due_date BETWEEN ? AND ?";
            $params[] = $dates[0];
            $params[] = $dates[1];
        }
    }

    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

    $query = "SELECT i.*, i.amount_paid, s.company_name as supplier_name 
              FROM invoices i 
              LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id 
              $where_clause
              ORDER BY i.invoice_id DESC";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
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

// Replace the existing stats calculation code
$stats = [
    'total_outstanding' => 0,
    'total_paid' => 0,
    'total_pending' => 0,
    'overdue' => 0
];

foreach ($invoices as $invoice) {
    $remaining_amount = $invoice['amount'] - $invoice['amount_paid'];
    
    // Add to total outstanding only if there's a remaining amount
    if ($remaining_amount > 0) {
        $stats['total_outstanding'] += $remaining_amount;
    }
    
    // Count fully paid and pending invoices
    if ($remaining_amount <= 0) {
        $stats['total_paid']++;
    } else {
        $stats['total_pending']++;
        
        // Check for overdue invoices (only count if not fully paid)
        if (strtotime($invoice['due_date']) < strtotime('today')) {
            $stats['overdue']++;
        }
    }
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
    <!-- Add to the head section -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="assets/js/invoices.js"></script>
    <link href="assets/css/invoices.css" rel="stylesheet">
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

            <!-- Add after the header section -->
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <?php if ($supplier_id): ?>
                            <input type="hidden" name="supplier_id" value="<?= $supplier_id ?>">
                        <?php endif; ?>
                        
                        <div class="col-md-3">
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Search invoices..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        
                        <div class="col-md-3">
                            <select class="form-select" name="status">
                                <option value="">All Statuses</option>
                                <option value="Pending" <?= $status_filter === 'Pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="Paid" <?= $status_filter === 'Paid' ? 'selected' : '' ?>>Paid</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <input type="text" class="form-control" name="date_range" 
                                   placeholder="Select date range" id="date-range" 
                                   value="<?= htmlspecialchars($date_range) ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i>Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Replace the existing statistics cards with this improved version -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white hover-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Total Outstanding</h6>
                                    <h3 class="mt-2 mb-0">Ksh. <?= number_format($stats['total_outstanding'], 2) ?></h3>
                                    
                                </div>
                                <i class="fas fa-coins fa-2x opacity-50"></i>

                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white hover-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Fully Paid</h6>
                                    <h3 class="mt-2 mb-0"><?= $stats['total_paid'] ?></h3>
                                </div>
                                <i class="fas fa-check-circle fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white hover-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Pending/Partial</h6>
                                    <h3 class="mt-2 mb-0"><?= $stats['total_pending'] ?></h3>
                                </div>
                                <i class="fas fa-clock fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white hover-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title mb-0">Overdue</h6>
                                    <h3 class="mt-2 mb-0"><?= $stats['overdue'] ?></h3>
                                </div>
                                <i class="fas fa-exclamation-circle fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
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
                                    <th>Amount Paid</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $invoice): 
                                    $remaining_amount = $invoice['amount'] - $invoice['amount_paid'];
                                    $status_class = '';
                                    $status_text = '';
                                    
                                    if ($remaining_amount <= 0) {
                                        $status_class = 'success';
                                        $status_text = 'Paid';
                                    } elseif ($invoice['amount_paid'] > 0) {
                                        $status_class = 'warning';
                                        $status_text = 'Partially Paid';
                                    } else {
                                        $status_class = 'danger';
                                        $status_text = 'Pending';
                                    }
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($invoice['invoice_number']) ?></td>
                                        <td><?= htmlspecialchars($invoice['supplier_name'] ?? 'N/A') ?></td>
                                        <td>Ksh. <?= number_format($invoice['amount'], 2) ?></td>
                                        <td>
                                            <?php if ($invoice['amount_paid'] > 0): ?>
                                                <span class="text-success">
                                                Ksh. <?= number_format($invoice['amount_paid'], 2) ?>
                                                </span>
                                                <?php if ($remaining_amount > 0): ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        Remaining: Ksh. <?= number_format($remaining_amount, 2) ?>
                                                    </small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">No Payment</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $due_date = strtotime($invoice['due_date']);
                                            $today = strtotime('today');
                                            $date_status = $due_date < $today ? 'overdue' : 'upcoming';
                                            ?>
                                            <span class="due-date-text <?= $date_status ?>">
                                                <?= date('M d, Y', $due_date) ?>
                                                <?php if ($date_status === 'overdue' && $remaining_amount > 0): ?>
                                                    <br><small class="overdue-label">Overdue</small>
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $status_class ?>">
                                                <?= $status_text ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="view_invoice.php?id=<?= htmlspecialchars($invoice['invoice_id']) ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i> View
                                            </a>
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
