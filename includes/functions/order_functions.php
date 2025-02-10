<?php

function getOrderStatistics($db, $type = 'all') {
    try {
        // Base query to get order counts and amounts with subquery
        $query = "SELECT 
                    o.status,
                    COUNT(DISTINCT o.order_id) as count,
                    COALESCE(SUM(o.total_amount), 0) as amount
                FROM orders o
                WHERE 1=1 ";

        if ($type === 'dispatch') {
            $query .= " AND o.status IN ('Pending', 'Processing')
                       AND (o.payment_status = 'Paid' OR o.payment_status = 'Pending')";
        }
        
        $query .= " GROUP BY o.status";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Initialize with all possible statuses
        $stats = [
            'Pending' => ['count' => 0, 'amount' => 0],
            'Processing' => ['count' => 0, 'amount' => 0],
            'Shipped' => ['count' => 0, 'amount' => 0],
            'Delivered' => ['count' => 0, 'amount' => 0],
            'Cancelled' => ['count' => 0, 'amount' => 0]
        ];
        
        // Update with actual counts
        foreach ($results as $row) {
            if (isset($stats[$row['status']])) {
                $stats[$row['status']] = [
                    'count' => (int)$row['count'],
                    'amount' => (float)$row['amount']
                ];
            }
        }
        
        return $stats;
    } catch (PDOException $e) {
        error_log("Error getting order statistics: " . $e->getMessage());
        return [];
    }
}

function generateTrackingNumber() {
    $prefix = 'ZE';
    $timestamp = date('ymd');
    $random = strtoupper(substr(uniqid(), -4));
    return "{$prefix}{$timestamp}-{$random}";
}

function getOrCreateTrackingNumber($db, $orderId) {
    // First check if order already has a tracking number
    $stmt = $db->prepare("SELECT tracking_number FROM orders WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result && $result['tracking_number']) {
        return $result['tracking_number'];
    }

    // Generate new tracking number
    $trackingNumber = generateTrackingNumber();

    // Update the order with the new tracking number
    $updateStmt = $db->prepare("UPDATE orders SET tracking_number = ? WHERE order_id = ?");
    $updateStmt->execute([$trackingNumber, $orderId]);

    return $trackingNumber;
}

// Modify the existing updateOrderStatus function
function updateOrderStatus($db, $orderId, $newStatus) {
    try {
        $db->beginTransaction();
        
        // Get existing tracking number or generate new one
        $trackingNumber = getOrCreateTrackingNumber($db, $orderId);
        
        // Update order status
        $query = "UPDATE orders 
                 SET status = :status,
                     tracking_number = :tracking_number 
                 WHERE order_id = :order_id";
        
        $stmt = $db->prepare($query);
        $result = $stmt->execute([
            ':status' => $newStatus,
            ':tracking_number' => $trackingNumber,
            ':order_id' => $orderId
        ]);

        if ($result) {
            // Add status change to order history
            $historyQuery = "INSERT INTO order_history 
                           (order_id, status, changed_at, changed_by) 
                           VALUES (:order_id, :status, NOW(), :changed_by)";
            $historyStmt = $db->prepare($historyQuery);
            $historyStmt->execute([
                ':order_id' => $orderId,
                ':status' => $newStatus,
                ':changed_by' => $_SESSION['id'] // Using id instead of user_id
            ]);
        }
        
        $db->commit();
        return true;
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Error updating order status: " . $e->getMessage());
        return false;
    }
}

function getStatusCardClass($status) {
    return match ($status) {
        'Pending' => 'bg-warning text-dark border-warning',
        'Processing' => 'bg-info text-white border-info',
        'Shipped' => 'bg-primary text-white border-primary',
        'Delivered' => 'bg-success text-white border-success',
        'Cancelled' => 'bg-danger text-white border-danger',
        default => 'bg-secondary text-white border-secondary'
    };
}

function validateTrackingNumber($trackingNumber) {
    // Format: TRK-YYYYMMDD-XXXX
    return preg_match('/^TRK-\d{8}-[A-Z0-9]{4}$/', $trackingNumber);
}

function renderOrdersTable($orders, $isDispatch = false) {
    ob_start();
    ?>
    <div class="table-responsive">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Username</th>
                    <th>Products</th>
                    <th>Total Amount</th>
                    <th>Status</th>
                    <th>Payment Status</th>
                    <th>Tracking Number</th>
                    <th>Shipping Address</th>
                    <th>Order Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="10" class="text-center">No orders found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>#<?= htmlspecialchars($order['order_id']) ?></td>
                            <td><?= htmlspecialchars($order['username']) ?></td>
                            <td><?= htmlspecialchars($order['products']) ?></td>
                            <td>Ksh.<?= number_format($order['total_amount'], 2) ?></td>
                            <td>
                                <span class="badge <?= getStatusBadgeClass($order['status'], 'status') ?>">
                                    <?= htmlspecialchars($order['status']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= getStatusBadgeClass($order['payment_status'], 'payment') ?>">
                                    <?= htmlspecialchars($order['payment_status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($order['tracking_number'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($order['shipping_address']) ?></td>
                            <td><?= date('Y-m-d H:i', strtotime($order['order_date'])) ?></td>
                            <td>
                                <?php if ($isDispatch): ?>
                                    <?php if ($order['payment_status'] === 'Paid' || $order['payment_status'] === 'Pending'): ?>
                                        <a href="dispatch_order.php?order_id=<?= $order['order_id'] ?>"
                                           class="btn btn-sm btn-success">Dispatch</a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-secondary" disabled>Cannot Dispatch</button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a href="update_order.php?id=<?= $order['order_id'] ?>"
                                       class="btn btn-sm btn-primary">Update</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}
