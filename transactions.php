<?php
session_start();
require_once 'config/database.php';
require_once 'includes/classes/TransactionHistory.php';
require_once 'includes/functions/badge_functions.php';

// Authentication check
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$transactionHistory = new TransactionHistory($db);

// Get filters from GET parameters
$filters = [
    'start_date' => $_GET['start_date'] ?? '',
    'end_date' => $_GET['end_date'] ?? '',
    'payment_method' => $_GET['payment_method'] ?? '',
    'status' => $_GET['status'] ?? '',
    'type' => $_GET['type'] ?? '',  // Add this line
    'search' => $_GET['search'] ?? ''
];

// Get current page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);

// Get transactions
$transactions = $transactionHistory->getTransactions($filters, $page);
$totalTransactions = $transactionHistory->getTotalTransactions($filters);
$totalPages = ceil($totalTransactions / 25);

if (empty($transactions)) {
    error_log("No transactions found with current filters: " . print_r($filters, true));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History - Zellow Admin</title>
    
    <!-- Icons and Fonts -->
    <script src="https://unpkg.com/feather-icons"></script>
    
   
   
   
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   
   
    <link rel="stylesheet" href="assets/css/collapsed.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/analytics.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .text-success {
            color: #10B981 !important;
        }
        
        .text-danger {
            color: #EF4444 !important;
        }
        
        .text-warning {
            color: #F59E0B !important;
        }
        
        .bg-warning-soft {
            background-color: rgba(245, 158, 11, 0.1);
        }
        
        .numeric-cell {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.9rem;
        }
        
        .numeric-cell span {
            font-weight: 500;
        }

        /* Add to the existing styles section */
        .bg-success-soft { background-color: rgba(16, 185, 129, 0.1); }
        .bg-warning-soft { background-color: rgba(245, 158, 11, 0.1); }
        .bg-danger-soft { background-color: rgba(239, 68, 68, 0.1); }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-completed { 
            background-color: var(--priority-low); 
            color: white;
        }

        .status-pending {
            background-color: var(--priority-medium);
            color: black;
        }

        .status-failed {
            background-color: var(--priority-high);
            color: white;
        }

        /* Add these badge styles */
        .badge {
            padding: 0.5em 0.8em;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 30px;
        }
        
        .transaction-type-badge,
        .payment-method-badge,
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include 'includes/theme.php'; ?>
    <nav class="navbar">
        <?php include 'includes/nav/collapsed.php'; ?>
    </nav>

    <div class="main-content">
        <div class="container mt-4">
            <!-- Summary Cards Row -->
            <div class="row g-4 mb-4">
                <!-- Total Transactions Card -->
                <div class="col-md-3">
                    <div class="card h-100 metric-card">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">Total Transactions</h6>
                            <h3 class="card-title mb-3">
                                <?= number_format($totalTransactions) ?>
                            </h3>
                            <small class="text-muted">In selected period</small>
                        </div>
                    </div>
                </div>

                <!-- Amount Processed Card -->
                <div class="col-md-3">
                    <div class="card h-100 metric-card">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">Amount Processed</h6>
                            <h3 class="card-title mb-3">
                                Ksh <?= number_format($transactionHistory->getTotalAmount($filters), 2) ?>
                            </h3>
                            <small class="text-muted">Total transaction value</small>
                        </div>
                    </div>
                </div>

                <!-- Success Rate Card -->
                <div class="col-md-3">
                    <div class="card h-100 metric-card">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">Success Rate</h6>
                            <?php 
                            $successRate = $transactionHistory->getSuccessRate($filters);
                            $rateColor = $successRate >= 95 ? 'text-success' : ($successRate >= 85 ? 'text-warning' : 'text-danger');
                            ?>
                            <h3 class="card-title mb-3 <?= $rateColor ?>">
                                <?= number_format($successRate, 1) ?>%
                            </h3>
                            <small class="text-muted">Transaction success rate</small>
                        </div>
                    </div>
                </div>

                <!-- Average Transaction Card -->
                <div class="col-md-3">
                    <div class="card h-100 metric-card">
                        <div class="card-body">
                            <h6 class="card-subtitle mb-2 text-muted">Avg. Transaction</h6>
                            <h3 class="card-title mb-3">
                                Ksh <?= number_format($transactionHistory->getAverageAmount($filters), 2) ?>
                            </h3>
                            <small class="text-muted">Average transaction value</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters Card with Updated Design -->
            <div class="card mb-4">
                <div class="card-body">
                    <form id="filterForm" class="row g-3" method="GET" action="<?= $_SERVER['PHP_SELF'] ?>">
                        <div class="col-md-2">
                            <label class="form-label fw-medium">Start Date</label>
                            <input type="date" name="start_date" class="form-control shadow-sm" 
                                   value="<?= htmlspecialchars($filters['start_date']) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-medium">End Date</label>
                            <input type="date" name="end_date" class="form-control shadow-sm"
                                   value="<?= htmlspecialchars($filters['end_date']) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-medium">Payment Method</label>
                            <select name="payment_method" class="form-select shadow-sm">
                                <option value="">All</option>
                                <option value="Credit Card" <?= $filters['payment_method'] === 'Credit Card' ? 'selected' : '' ?>>Credit Card</option>
                                <option value="Mpesa" <?= $filters['payment_method'] === 'Mpesa' ? 'selected' : '' ?>>Mpesa</option>
                                <option value="Cash" <?= $filters['payment_method'] === 'Cash' ? 'selected' : '' ?>>Cash</option>
                                <option value="Airtel Money" <?= $filters['payment_method'] === 'Airtel Money' ? 'selected' : '' ?>>Airtel Money</option>
                                <option value="Bank Transfer" <?= $filters['payment_method'] === 'Bank Transfer' ? 'selected' : '' ?>>Bank Transfer</option>
                                <option value="Other" <?= $filters['payment_method'] === 'Other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-medium">Status</label>
                            <select name="status" class="form-select shadow-sm">
                                <option value="">All</option>
                                <option value="completed" <?= $filters['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="failed" <?= $filters['status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-medium">Transaction Type</label>
                            <select name="type" class="form-select shadow-sm">
                                <option value="">All</option>
                                <option value="Customer Payment" <?= $filters['type'] === 'Customer Payment' ? 'selected' : '' ?>>Customer Payment</option>
                                <option value="Refund" <?= $filters['type'] === 'Refund' ? 'selected' : '' ?>>Refund</option>
                                <option value="Expense" <?= $filters['type'] === 'Expense' ? 'selected' : '' ?>>Expense</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-medium">Search</label>
                            <input type="text" name="search" class="form-control shadow-sm" 
                                   placeholder="Transaction ID/Invoice"
                                   value="<?= htmlspecialchars($filters['search']) ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="d-flex gap-2 w-100">
                                <button type="submit" class="btn btn-primary flex-grow-1">
                                    <i class="fas fa-filter me-1"></i> Filter
                                </button>
                                <button type="button" onclick="resetFilters()" class="btn btn-outline-secondary">
                                    <i class="fas fa-undo"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Transactions Table Card with Updated Design -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Transaction History</h5>
                    <div class="d-flex gap-2">
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportData('csv')">
                                <i class="fas fa-file-csv me-1"></i> CSV
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportData('excel')">
                                <i class="fas fa-file-excel me-1"></i> Excel
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportData('pdf')">
                                <i class="fas fa-file-pdf me-1"></i> PDF
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 transaction-list">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Transaction ID</th>
                                    <th>Customer</th>
                                    <th>Type</th>
                                    <th>Method</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (is_array($transactions) && !empty($transactions)): ?>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td><?= date('M d, Y H:i', strtotime($transaction['transaction_date'])) ?></td>
                                            <td>
                                                <?= htmlspecialchars($transaction['reference_id']) ?>
                                                <?php if (!empty($transaction['order_id'])): ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        Order: #<?= htmlspecialchars($transaction['order_id']) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($transaction['customer_email'] ?? 'N/A') ?></td>
                                            <td>
                                                <span class="badge <?= getTransactionTypeBadgeClass($transaction['transaction_type']) ?>">
                                                    <?php
                                                    $typeIcon = getTransactionTypeIcon($transaction['transaction_type']);
                                                    ?>
                                                    <i class="fas fa-<?= $typeIcon ?>"></i>
                                                    <?= htmlspecialchars($transaction['transaction_type']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge <?= getPaymentMethodBadgeClass($transaction['payment_method']) ?>">
                                                    <?php
                                                    $iconClass = 'money-bill-wave'; // default icon
                                                    switch ($transaction['payment_method']) {
                                                        case 'Credit Card':
                                                            $iconClass = 'credit-card';
                                                            break;
                                                        case 'Mpesa':
                                                            $iconClass = 'mobile-alt';
                                                            break;
                                                        case 'Cash':
                                                            $iconClass = 'money-bill';
                                                            break;
                                                        case 'Airtel Money':
                                                            $iconClass = 'mobile';
                                                            break;
                                                        case 'Bank Transfer':
                                                            $iconClass = 'university';
                                                            break;
                                                        case 'Other':
                                                            $iconClass = 'circle';
                                                            break;
                                                    }
                                                    ?>
                                                    <i class="fas fa-<?= $iconClass ?>"></i>
                                                    <?= htmlspecialchars($transaction['payment_method']) ?>
                                                </span>
                                            </td>
                                            <td class="numeric-cell">
                                                <?= htmlspecialchars($transaction['currency'] ?? 'KES') ?> 
                                                <?= number_format($transaction['total_amount'], 2) ?>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo getTransactionStatusBadgeClass($transaction['payment_status']); ?>">
                                                    <i class="fas fa-<?php echo $transaction['payment_status'] === 'completed' ? 'check-circle' : 
                                                        ($transaction['payment_status'] === 'pending' ? 'clock' : 'times-circle'); ?>"></i>
                                                    <?php echo ucfirst(htmlspecialchars($transaction['payment_status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-primary"
                                                        onclick="viewTransaction('<?= $transaction['id'] ?>')">
                                                    View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No transactions found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Update the Pagination section -->
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Page navigation" class="custom-pagination">
                            <ul class="pagination">
                                <?php
                                $startPage = max(1, $page - 2);
                                $endPage = min($totalPages, $page + 2);
                                
                                // Previous button
                                if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link pagination-nav" href="?page=<?= ($page - 1) ?>&<?= http_build_query($filters) ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php if ($startPage > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=1&<?= http_build_query($filters) ?>">1</a>
                                    </li>
                                    <?php if ($startPage > 2): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query($filters) ?>">
                                            <?= $i ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($endPage < $totalPages): ?>
                                    <?php if ($endPage < $totalPages - 1): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?= $totalPages ?>&<?= http_build_query($filters) ?>">
                                            <?= $totalPages ?>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link pagination-nav" href="?page=<?= ($page + 1) ?>&<?= http_build_query($filters) ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Transaction Details Modal -->
<div class="modal fade" id="transactionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Transaction Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="transactionDetails">
                Loading...
            </div>
        </div>
    </div>
</div>

<script>
function resetFilters() {
    window.location.href = 'transactions.php';
}

function exportData(format) {
    const params = new URLSearchParams(window.location.search);
    params.append('export', format);
    window.location.href = 'export_transactions.php?' + params.toString();
}

function viewTransaction(transactionId) {
    const modal = new bootstrap.Modal(document.getElementById('transactionModal'));
    const detailsContainer = document.getElementById('transactionDetails');
    
    // Load transaction details via AJAX
    fetch(`ajax/get_transaction.php?id=${transactionId}`)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                detailsContainer.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
            } else {
                detailsContainer.innerHTML = data.html;
            }
            modal.show();
        })
        .catch(error => {
            console.error('Error:', error);
            detailsContainer.innerHTML = '<div class="alert alert-danger">Failed to load transaction details</div>';
        });
}
</script>

<?php include 'includes/nav/footer.php'; ?>
</body>
</html>
