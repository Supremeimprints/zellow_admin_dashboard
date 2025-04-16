<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions/giftbox_functions.php';
require_once 'includes/head.php';

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$giftBoxId = $_GET['id'] ?? null;
if (!$giftBoxId) {
    header('Location: giftbox_manager.php');
    exit();
}

$giftBox = getGiftBoxById($db, $giftBoxId);
$giftBoxItems = getGiftBoxItems($db, $giftBoxId);
$availableProducts = getAllProducts($db);

// Add debug output
error_log("Available products count: " . count($availableProducts));
if (empty($availableProducts)) {
    error_log("No products found in the database matching criteria");
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Gift Box Items - <?= htmlspecialchars($giftBox['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/giftbox.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --danger-color: #e74a3b;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --light-color: #f8f9fc;
            --dark-color: #5a5c69;
            --border-radius: 0.5rem;
            --card-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        body {
            background-color: var(--light-color);
            color: var(--dark-color);
        }
        
        .page-container {
            padding: 1.5rem;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .page-header {
            background-color: #ffffff;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            transition: transform 0.2s ease-in-out;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .card-header {
            background-color: #ffffff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1rem 1.25rem;
        }
        
        .card-title {
            font-weight: 700;
            color: var(--primary-color);
        }
        
        .action-btn {
            border-radius: 50px;
            padding: 0.5rem 1rem;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .form-control, .form-select {
            border-radius: 0.5rem;
            padding: 0.675rem 1rem;
            border: 1px solid #d1d3e2;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #3a5ec8;
            border-color: #3a5ec8;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            border-top: 0;
            background-color: rgba(0, 0, 0, 0.03);
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .table td, .table th {
            padding: 1rem;
            vertical-align: middle;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 0;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--secondary-color);
            opacity: 0.5;
        }
        
        .badge {
            font-weight: 500;
            padding: 0.35rem 0.65rem;
        }
        
        .tooltip-inner {
            max-width: 200px;
            padding: 0.5rem 1rem;
            background-color: var(--dark-color);
            border-radius: 0.25rem;
        }
        
        .summary-card {
            background: linear-gradient(to right, #f8f9fa, #ffffff);
            border-radius: var(--border-radius);
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--card-shadow);
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
        }
        
        .summary-item:last-child {
            margin-bottom: 0;
            padding-top: 0.75rem;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .summary-label {
            font-weight: 500;
            color: var(--secondary-color);
        }
        
        .summary-value {
            font-weight: 700;
        }
        
        .price-input {
            max-width: 120px;
            text-align: center;
        }
        
        .card-footer {
            background-color: rgba(0, 0, 0, 0.02);
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1rem 1.25rem;
        }
        
        .sticky-top-offset {
            top: 1.5rem;
        }
        
        .table-container {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .price-badge {
            font-weight: 700;
            font-size: 1.1rem;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            color: white;
            background-color: var(--primary-color);
        }
        
        .info-tooltip {
            cursor: pointer;
            color: var(--primary-color);
        }
        
        .btn-icon {
            width: 2.5rem;
            height: 2.5rem;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .total-row {
            background-color: rgba(78, 115, 223, 0.05);
        }
    </style>
</head>

<body>
    <div class="admin-layout">
        <?php include 'includes/nav/collapsed.php'; ?>
        <?php include 'includes/theme.php'; ?>
        
        <div class="page-container">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h2 class="mb-1"><i class="fas fa-gift me-2 text-primary"></i> <?= htmlspecialchars($giftBox['name']) ?></h2>
                    <p class="text-muted mb-0">Manage the contents and pricing of this gift box</p>
                </div>
                <a href="giftbox_manager.php" class="btn btn-outline-primary action-btn">
                    <i class="fas fa-arrow-left me-2"></i> Back to Gift Boxes
                </a>
            </div>
            
            <div class="row">
                <!-- Left Column -->
                <div class="col-lg-5">
                    <!-- Add Products Form -->
                    <div class="card sticky-top sticky-top-offset">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Add Product to Gift Box</h5>
                            <span class="badge bg-primary rounded-pill">
                                <i class="fas fa-plus me-1"></i> New Item
                            </span>
                        </div>
                        <div class="card-body">
                            <form action="giftbox_add_item.php" method="POST">
                                <input type="hidden" name="gift_box_id" value="<?= $giftBoxId ?>">

                                <div class="mb-3">
                                    <label class="form-label fw-bold">Select Product</label>
                                    <select name="product_id" class="form-select" required>
                                        <option value="">-- Choose a product --</option>
                                        <?php foreach ($availableProducts as $product): ?>
                                            <option value="<?= $product['product_id'] ?>">
                                                <?= htmlspecialchars($product['product_name']) ?>
                                                (Ksh. <?= number_format($product['price'], 2) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold">Quantity</label>
                                        <input type="number" name="quantity" class="form-control" min="1" value="1" required>
                                    </div>

                                    <div class="col-md-6 mb-3">
                                        <label class="form-label fw-bold">
                                            Price Override
                                            <i class="fas fa-circle-info info-tooltip ms-1"
                                                data-bs-toggle="tooltip"
                                                title="Optional. Overrides the default product price for this gift box only."></i>
                                        </label>
                                        <div class="input-group">
                                            <span class="input-group-text">Ksh.</span>
                                            <input type="number" step="0.01" name="price_override" class="form-control"
                                                placeholder="Optional">
                                        </div>
                                    </div>
                                </div>

                                <div class="text-end mt-3">
                                    <button type="submit" class="btn btn-primary action-btn">
                                        <i class="fas fa-plus me-2"></i> Add to Gift Box
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Price Summary Card -->
                    <?php if (!empty($giftBoxItems)): 
                        $pricingDetails = getGiftBoxPricingDetails($db, $giftBoxId);
                    ?>
                    <div class="summary-card">
                        <h5 class="mb-3 text-primary">Pricing Summary</h5>
                        
                        <div class="summary-item">
                            <span class="summary-label">Items Total Value:</span>
                            <span class="summary-value">Ksh. <?= number_format($pricingDetails['items_total'], 2) ?></span>
                        </div>
                        
                        <div class="summary-item">
                            <span class="summary-label">Gift Box Price:</span>
                            <div class="d-flex align-items-center">
                                <span class="summary-value me-2">Ksh. <?= number_format($pricingDetails['base_price'], 2) ?></span>
                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#updatePriceModal">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="summary-item">
                            <span class="summary-label">Margin:</span>
                            <span class="summary-value text-<?= $pricingDetails['margin'] >= 0 ? 'success' : 'danger' ?>">
                                Ksh. <?= number_format($pricingDetails['margin'], 2) ?> 
                                (<?= number_format($pricingDetails['margin_percentage'], 1) ?>%)
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Right Column: Items List -->
                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Gift Box Contents</h5>
                            <span class="badge bg-<?= count($giftBoxItems) > 0 ? 'primary' : 'secondary' ?> rounded-pill">
                                <?= count($giftBoxItems) ?> <?= count($giftBoxItems) == 1 ? 'item' : 'items' ?>
                            </span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($giftBoxItems)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-box-open mb-3"></i>
                                    <h5>This gift box is empty</h5>
                                    <p class="text-muted mb-0">Use the form on the left to add products to this gift box.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-container">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th width="40%">Product</th>
                                                <th class="text-center" width="10%">Qty</th>
                                                <th class="text-end" width="20%">Unit Price</th>
                                                <th class="text-end" width="20%">Total</th>
                                                <th class="text-center" width="10%">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($giftBoxItems as $item): ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <?php if (!empty($item['product_image'])): ?>
                                                                <img src="<?= htmlspecialchars($item['product_image']) ?>" 
                                                                     alt="Product" 
                                                                     class="me-2" 
                                                                     width="40" height="40" 
                                                                     style="object-fit: cover; border-radius: 4px;">
                                                            <?php else: ?>
                                                                <div class="me-2 bg-light d-flex align-items-center justify-content-center" 
                                                                     style="width: 40px; height: 40px; border-radius: 4px;">
                                                                    <i class="fas fa-box text-secondary"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div>
                                                                <div class="fw-bold"><?= htmlspecialchars($item['product_name']) ?></div>
                                                                <?php if (!empty($item['product_code'])): ?>
                                                                    <div class="small text-muted"><?= htmlspecialchars($item['product_code']) ?></div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-secondary rounded-pill"><?= $item['quantity'] ?></span>
                                                    </td>
                                                    <td class="text-end">
                                                        Ksh. <?= number_format($item['price_override'] ?? $item['product_price'], 2) ?>
                                                        <?php if (isset($item['price_override'])): ?>
                                                            <span class="badge bg-info rounded-pill ms-1"
                                                                data-bs-toggle="tooltip"
                                                                title="Custom price set for this gift box">
                                                                <i class="fas fa-tags"></i>
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-end fw-bold">
                                                        Ksh. <?= number_format(($item['price_override'] ?? $item['product_price']) * $item['quantity'], 2) ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <button class="btn btn-sm btn-outline-danger btn-icon"
                                                            onclick="removeItem(<?= $item['id'] ?>)"
                                                            data-bs-toggle="tooltip"
                                                            title="Remove from gift box">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="total-row">
                                                <td colspan="3" class="text-end fw-bold">Total Items Value:</td>
                                                <td class="text-end fw-bold">
                                                    Ksh. <?= number_format($pricingDetails['items_total'], 2) ?>
                                                </td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Price Modal -->
    <div class="modal fade" id="updatePriceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-tag me-2 text-primary"></i>
                        Update Gift Box Price
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Set the price for this gift box package. This price will be shown to customers.</p>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Gift Box Price (Ksh)</label>
                        <div class="input-group">
                            <span class="input-group-text">Ksh.</span>
                            <input type="number" id="basePrice" class="form-control" 
                                   value="<?= $pricingDetails['base_price'] ?? '' ?>" 
                                   step="0.01" min="0">
                        </div>
                    </div>
                    
                    <?php if (!empty($giftBoxItems)): ?>
                    <div class="alert alert-info d-flex">
                        <div class="me-3">
                            <i class="fas fa-info-circle fa-2x"></i>
                        </div>
                        <div>
                            <h6 class="alert-heading">Pricing Information</h6>
                            <p class="mb-0">Total cost of items: 
                                <strong>Ksh. <?= number_format($pricingDetails['items_total'], 2) ?></strong>
                            </p>
                            <p class="mb-0">Recommended minimum price: 
                                <strong>Ksh. <?= number_format($pricingDetails['items_total'] * 1.2, 2) ?></strong> 
                                (20% markup)
                            </p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="updateBasePrice()">
                        <i class="fas fa-save me-1"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Remove item from gift box
        function removeItem(itemId) {
            if (confirm('Are you sure you want to remove this item from the gift box?')) {
                window.location.href = `giftbox_remove_item.php?id=${itemId}&box_id=<?= $giftBoxId ?>`;
            }
        }
        
        // Update base price
        function updateBasePrice() {
            const newPrice = document.getElementById('basePrice').value;
            if (!newPrice || newPrice <= 0) {
                alert('Please enter a valid price');
                return;
            }

            fetch('giftbox_update_price.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `gift_box_id=<?= $giftBoxId ?>&base_price=${newPrice}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed to update price: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error updating price:', error);
                alert('An error occurred while updating the price');
            });
        }
    </script>

</body>

<?php include 'includes/nav/footer.php'; ?>

</html>