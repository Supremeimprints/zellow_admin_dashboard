<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions/badge_functions.php';  // Add this line

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Modified query to only show unfulfilled orders
$ordersQuery = "
    SELECT 
        po.purchase_order_id,
        po.order_date,
        po.total_amount,
        po.status,
        po.payment_status,
        s.company_name,
        SUM(poi.quantity) as total_ordered,
        SUM(COALESCE(poi.received_quantity, 0)) as total_received,
        i.invoice_number,
        i.status as invoice_status,
        po.is_fulfilled
    FROM purchase_orders po
    JOIN suppliers s ON po.supplier_id = s.supplier_id
    JOIN purchase_order_items poi ON po.purchase_order_id = poi.purchase_order_id
    LEFT JOIN invoices i ON po.purchase_order_id = i.purchase_order_id
    WHERE po.is_fulfilled = FALSE
    GROUP BY po.purchase_order_id
    ORDER BY 
        CASE po.status 
            WHEN 'pending' THEN 1 
            WHEN 'partial' THEN 2 
            ELSE 3 
        END,
        po.order_date DESC
";

try {
    $orders = $db->query($ordersQuery)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading orders: " . $e->getMessage();
    $orders = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receive Goods</title>
    <!-- Include your existing stylesheets -->
     <!-- Feather Icons - Add this line -->
    <script src="https://unpkg.com/feather-icons"></script>
    
    <!-- Existing stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/settings.css">
    <link rel="stylesheet" href="assets/css/badges.css">
    
    <link rel="stylesheet" href="assets/css/inventory.css">
    <link rel="stylesheet" href="assets/css/collapsed.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .order-card {
            background: -var(--bs-light);
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.07);
            margin-bottom: 1.5rem;
            border: 1px solid #e5e7eb;
        }
        .progress {
            height: 0.8rem;
            border-radius: 1rem;
        }
        .received-input {
            max-width: 100px;
            display: inline-block;
        }
        .receipt-notes {
            font-size: 0.85rem;
            color: #666;
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include 'includes/theme.php'; ?>
    <nav class="navbar">
        <?php include 'includes/nav/collapsed.php'; ?>
    </nav>

    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Receive Inventory</h2>
            <div>
                <a href="inventory.php" class="btn btn-secondary me-2">
                    <i data-feather="archive"></i> Inventory
                </a>
                <a href="invoices.php" class="btn btn-primary">
                    <i data-feather="file-text"></i> Invoices
                </a>
            </div>
        </div>

        <?php foreach ($orders as $order): ?>
            <div class="order-card">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <h5 class="mb-0">PO #<?= $order['purchase_order_id'] ?></h5>
                            <span class="badge <?= getStatusBadgeClass($order['status']) ?>">
                                <?= ucfirst($order['status']) ?>
                            </span>
                            <?php if ($order['invoice_number']): ?>
                                <span class="badge bg-primary">
                                    <?= $order['invoice_number'] ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="text-muted mb-0">
                            <?= htmlspecialchars($order['company_name']) ?> | 
                            Ordered: <?= date('M d, Y', strtotime($order['order_date'])) ?>
                        </p>
                    </div>
                    <div class="text-end">
                        <h6>Total Amount: Ksh. <?= number_format($order['total_amount'], 2) ?></h6>
                        <small class="text-muted">
                            Received: <?= $order['total_received'] ?> / <?= $order['total_ordered'] ?> items
                        </small>
                    </div>
                </div>

                <div class="progress mb-3">
                    <?php 
                    $progress = $order['total_ordered'] > 0 
                        ? ($order['total_received'] / $order['total_ordered']) * 100 
                        : 0;
                    ?>
                    <div class="progress-bar bg-success" role="progressbar" 
                         style="width: <?= $progress ?>%" 
                         aria-valuenow="<?= $progress ?>" 
                         aria-valuemin="0" 
                         aria-valuemax="100">
                        <?= round($progress) ?>% Received
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm" id="items-<?= $order['purchase_order_id'] ?>">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th class="text-center">Ordered</th>
                                <th class="text-center">Received</th>
                                <th class="text-center">Remaining</th>
                                <th>Unit Price</th>
                                <th>Receive Items</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Items loaded via AJAX -->
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <button class="btn btn-primary btn-sm load-items" 
                            data-po-id="<?= $order['purchase_order_id'] ?>">
                        <i data-feather="refresh-cw"></i> Load Items
                    </button>
                    <div class="receipt-notes">
                        Last update: <span id="last-update-<?= $order['purchase_order_id'] ?>">
                            <?= date('Y-m-d H:i:s') ?>
                        </span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Feather icons
    feather.replace();

    // Load items when clicking the Load Items button
    document.querySelectorAll('.load-items').forEach(button => {
        button.addEventListener('click', function() {
            loadOrderItems(this.dataset.poId);
        });
    });

    // Auto-load items for pending and partial orders
    document.querySelectorAll('.load-items').forEach(button => {
        const orderStatus = button.closest('.order-card').querySelector('.badge').textContent.toLowerCase();
        if (orderStatus === 'pending' || orderStatus === 'partial') {
            loadOrderItems(button.dataset.poId);
        }
    });
});

function loadOrderItems(poId) {
    fetch(`ajax/get_order_items.php?po_id=${poId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById(`items-${poId}`).querySelector('tbody').innerHTML = html;
            setupUpdateHandlers();
        });
}

function setupUpdateHandlers() {
    document.querySelectorAll('.update-received').forEach(button => {
        button.addEventListener('click', function() {
            const row = this.closest('tr');
            const itemId = this.dataset.itemId;
            const quantity = row.querySelector('.received-quantity').value;
            
            if (!quantity || quantity <= 0) {
                alert('Please enter a valid quantity');
                return;
            }

            updateReceivedQuantity(itemId, quantity, row);
        });
    });
}

function updateReceivedQuantity(itemId, quantity, row) {
    fetch('ajax/update_received_quantity.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `item_id=${itemId}&quantity=${quantity}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the displayed quantities
            row.querySelector('.current-received').textContent = 
                parseInt(row.querySelector('.current-received').textContent || 0) + parseInt(quantity);
            row.querySelector('.remaining-quantity').textContent = data.remaining;
            
            // Clear the input
            row.querySelector('.received-quantity').value = '';
            
            // Update progress bar
            const orderCard = row.closest('.order-card');
            const progressBar = orderCard.querySelector('.progress-bar');
            progressBar.style.width = `${data.progress}%`;
            progressBar.textContent = `${Math.round(data.progress)}% Received`;

            // Update last updated timestamp
            const poId = row.closest('table').id.split('-')[1];
            document.getElementById(`last-update-${poId}`).textContent = 
                new Date().toLocaleString();

            // If fully received, reload the page to update status
            if (data.status === 'received') {
                location.reload();
            }
        } else {
            alert(data.message || 'Error updating quantity');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating quantity');
    });
}
</script>

</body>
</html>
