<?php
session_start();
require_once 'config/database.php';
require_once 'vendor/autoload.php';

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: invoices.php?error=invalid_id');
    exit();
}

$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$successMsg = $errorMsg = '';

// Fetch invoice details
try {
    // First, check if invoice_id is valid
    if (!$invoice_id) {
        throw new Exception("Invalid invoice ID");
    }

    // Replace the existing query with this updated version
    $query = "SELECT 
        i.*,
        s.company_name,
        po.purchase_order_id,
        (i.amount - COALESCE(i.amount_paid, 0)) as remaining_amount 
    FROM invoices i 
    LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id
    LEFT JOIN purchase_orders po ON i.purchase_order_id = po.purchase_order_id 
    WHERE i.invoice_id = ?";

    $stmt = $db->prepare($query);
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        throw new Exception("Invoice not found");
    }

    // Debug information - remove in production
    error_log("Invoice data: " . print_r($invoice, true));

} catch (Exception $e) {
    $errorMsg = "Error: " . $e->getMessage();
    error_log("Invoice view error: " . $e->getMessage());
    
    // Set default values for the invoice
    $invoice = [
        'invoice_id' => $invoice_id,
        'invoice_number' => 'N/A',
        'status' => 'Unknown',
        'company_name' => 'N/A',
        'due_date' => 'N/A',
        'amount' => 0,
        'amount_paid' => 0,
        'remaining_amount' => 0,
        'purchase_order_id' => null,
        'order_number' => null
    ];
}

// Add this debugging section temporarily
echo "<!-- Debug Information -->";
echo "<!-- Invoice ID: " . htmlspecialchars($invoice_id) . " -->";
echo "<!-- Query: " . htmlspecialchars($query) . " -->";

// Handle payment processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    try {
        if (!$invoice || !isset($invoice['invoice_id'])) {
            throw new Exception("Invalid invoice");
        }

        $payment_amount = floatval($_POST['payment_amount']);
        $remaining = $invoice['amount'] - $invoice['amount_paid'];

        if ($payment_amount <= 0 || $payment_amount > $remaining) {
            throw new Exception("Invalid payment amount");
        }

        // Replace the existing payment processing code in the POST handler
        try {
            $db->beginTransaction();

            // Update invoice
            $updateInvoice = $db->prepare("
                UPDATE invoices 
                SET 
                    amount_paid = amount_paid + ?,
                    status = CASE 
                        WHEN (amount_paid + ?) >= amount THEN 'Paid'
                        WHEN (amount_paid + ?) < amount THEN 'Partially Paid'
                        ELSE status 
                    END,
                    payment_date = NOW()
                WHERE invoice_id = ?
            ");
            
            if (!$updateInvoice->execute([$payment_amount, $payment_amount, $payment_amount, $invoice_id])) {
                throw new Exception("Failed to update invoice payment");
            }

            // Record payment in payment history
            $paymentRef = 'PAY-' . date('YmdHis') . '-' . rand(1000, 9999);
            $recordPayment = $db->prepare("
                INSERT INTO invoice_payments (
                    invoice_id,
                    amount,
                    payment_date,
                    payment_reference,
                    created_by
                ) VALUES (?, ?, NOW(), ?, ?)
            ");

            if (!$recordPayment->execute([
                $invoice_id,
                $payment_amount,
                $paymentRef,
                $_SESSION['id']
            ])) {
                throw new Exception("Failed to record payment history");
            }

            $db->commit();
            $successMsg = "Payment processed successfully";
            header("Location: view_invoice.php?id=$invoice_id&success=1");
            exit();

        } catch (Exception $e) {
            $db->rollBack();
            $errorMsg = "Payment processing failed: " . $e->getMessage();
        }
    } catch (Exception $e) {
        $errorMsg = "Payment processing failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Invoice - Zellow Enterprises</title>
    <script src="https://unpkg.com/feather-icons"></script>
    
    <!-- Existing stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/collapsed.css">
    <link href="assets/css/invoices.css" rel="stylesheet">
    <link href="assets/css/badges.css" rel="stylesheet">
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
                <h2>Invoice Details</h2>
                <a href="invoices.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Invoices
                </a>
            </div>

            <?php if ($successMsg): ?>
                <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
            <?php endif; ?>

            <?php if ($errorMsg): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-8">
                    <div class="card invoice-detail-card mb-4">
                        <div class="card-body">
                            <div class="row mb-4">
                                <div class="col-sm-6">
                                    <h6 class="mb-3">Invoice Number:</h6>
                                    <div class="fw-bold"><?= htmlspecialchars($invoice['invoice_number']) ?></div>
                                </div>
                                <div class="col-sm-6 text-sm-end">
                                    <h6 class="mb-3">Status:</h6>
                                    <span class="badge bg-<?php echo $invoice['status'] === 'Paid' ? 'success' : 
                                        ($invoice['status'] === 'Partially Paid' ? 'warning' : 'danger'); ?>">
                                        <?= htmlspecialchars($invoice['status']) ?>
                                    </span>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-sm-6">
                                    <h6 class="mb-3">Supplier:</h6>
                                    <div><?= htmlspecialchars($invoice['company_name']) ?></div>
                                </div>
                                <div class="col-sm-6 text-sm-end">
                                    <h6 class="mb-3">Due Date:</h6>
                                    <div><?= htmlspecialchars($invoice['due_date']) ?></div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-sm-6">
                                    <h6 class="mb-3">Total Amount:</h6>
                                    <div class="payment-amount">Ksh. <?= number_format($invoice['amount'], 2) ?></div>
                                </div>
                                <div class="col-sm-6 text-sm-end">
                                    <h6 class="mb-3">Amount Paid:</h6>
                                    <div class="text-success">Ksh. <?= number_format($invoice['amount_paid'], 2) ?></div>
                                    <?php if ($invoice['remaining_amount'] > 0): ?>
                                        <div class="remaining-amount mt-2">
                                            Remaining: Ksh. <?= number_format($invoice['remaining_amount'], 2) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Replace the existing payment form condition -->
                            <?php if ($invoice['remaining_amount'] > 0): ?>
                                <form method="POST" class="mt-4" id="paymentForm">
                                    <div class="row g-3">
                                        <div class="col-sm-6">
                                            <input type="number" 
                                                   step="0.01" 
                                                   class="form-control" 
                                                   name="payment_amount" 
                                                   required
                                                   min="0.01"
                                                   max="<?= $invoice['remaining_amount'] ?>"
                                                   placeholder="Enter amount (Remaining: $<?= number_format($invoice['remaining_amount'], 2) ?>)">
                                        </div>
                                        <div class="col-sm-6">
                                            <button type="submit" 
                                                    name="process_payment" 
                                                    class="btn btn-primary w-100">
                                                Update Payment
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Purchase Order Details</h5>
                            <?php if ($invoice['purchase_order_id']): ?>
                                <p class="mb-2">Purchase Order ID: <?= htmlspecialchars($invoice['purchase_order_id']) ?></p>
                                <a href="view_purchase_order.php?id=<?= $invoice['purchase_order_id'] ?>" 
                                   class="btn btn-outline-primary btn-sm">
                                    View Purchase Order
                                </a>
                            <?php else: ?>
                                <p class="text-muted">No purchase order associated</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'includes/nav/footer.php'; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('paymentForm')?.addEventListener('submit', function(e) {
    if (!confirm('Are you sure you want to process this payment?')) {
        e.preventDefault();
    }
});
</script>
<script>
document.getElementById('paymentForm')?.addEventListener('submit', function(e) {
    const paymentAmount = parseFloat(this.querySelector('[name="payment_amount"]').value);
    const remainingAmount = parseFloat(this.querySelector('[name="payment_amount"]').getAttribute('max'));
    
    if (paymentAmount <= 0 || paymentAmount > remainingAmount) {
        e.preventDefault();
        alert(`Please enter an amount between $0.01 and $${remainingAmount.toFixed(2)}`);
        return;
    }
    
    if (!confirm(`Process payment of $${paymentAmount.toFixed(2)}?`)) {
        e.preventDefault();
    }
});
</script>
</body>
</html>