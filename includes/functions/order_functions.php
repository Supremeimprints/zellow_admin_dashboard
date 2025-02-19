<?php

function getOrderStatistics($db, $type = 'all') {
    try {
        // Base query to get order counts and amounts with subquery
        $query = "SELECT 
                    o.status,
                    COUNT(DISTINCT o.order_id) as count,
                    COALESCE(SUM(o.total_amount), 0) as amount,
                    COALESCE(SUM(o.discount_amount), 0) as total_discounts,
                    COALESCE(SUM(o.shipping_fee), 0) as total_shipping
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
    return 'TRK-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
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
function updateOrderStatus($db, $orderId, $newStatus, $paymentStatus = null) {
    try {
        $db->beginTransaction();
        
        // Get original order details first
        $orderQuery = "SELECT coupon_id, total_amount, discount_amount, payment_status 
                      FROM orders WHERE order_id = ?";
        $stmt = $db->prepare($orderQuery);
        $stmt->execute([$orderId]);
        $orderDetails = $stmt->fetch(PDO::FETCH_ASSOC);

        // If order is being cancelled or refunded and had a coupon
        if (($newStatus === 'Cancelled' || $paymentStatus === 'Refunded') && 
            $orderDetails['coupon_id'] !== null) {
            
            // Remove coupon usage record
            $deleteCouponUsage = "DELETE FROM coupon_usage 
                                 WHERE order_id = :order_id 
                                 AND coupon_id = :coupon_id";
            $stmt = $db->prepare($deleteCouponUsage);
            $stmt->execute([
                ':order_id' => $orderId,
                ':coupon_id' => $orderDetails['coupon_id']
            ]);

            // Update coupon usage count
            $updateCoupon = "UPDATE coupons 
                           SET times_used = times_used - 1 
                           WHERE coupon_id = :coupon_id 
                           AND times_used > 0";
            $stmt = $db->prepare($updateCoupon);
            $stmt->execute([':coupon_id' => $orderDetails['coupon_id']]);

            // Set coupon_id to NULL in orders table
            $clearCouponQuery = "UPDATE orders 
                               SET coupon_id = NULL 
                               WHERE order_id = :order_id";
            $stmt = $db->prepare($clearCouponQuery);
            $stmt->execute([':order_id' => $orderId]);
        }

        // Update order status (existing code)
        $orderUpdateQuery = "UPDATE orders SET 
            status = :status" . 
            ($paymentStatus ? ", payment_status = :payment_status" : "") . 
            " WHERE order_id = :order_id";
        
        $params = [
            ':status' => $newStatus,
            ':order_id' => $orderId
        ];
        
        if ($paymentStatus) {
            $params[':payment_status'] = $paymentStatus;
        }
        
        $stmt->execute($params);

        // Handle transaction records
        if ($paymentStatus === 'Refunded') {
            // Add refund transaction
            $refundQuery = "INSERT INTO transactions (
                order_id,
                transaction_type,
                reference_id,
                total_amount,
                payment_status,
                transaction_date,
                description
            ) VALUES (
                :order_id,
                'Refund',
                :reference_id,
                :amount,
                'Completed',
                CURRENT_TIMESTAMP,
                'Order refund - Discount removed'
            )";

            $stmt = $db->prepare($refundQuery);
            $stmt->execute([
                ':order_id' => $orderId,
                ':reference_id' => 'REF-' . $orderId,
                ':amount' => -($orderDetails['total_amount'])
            ]);

            // If there was a discount, add adjustment transaction
            if ($orderDetails['discount_amount'] > 0) {
                $discountAdjustQuery = "INSERT INTO transactions (
                    order_id,
                    transaction_type,
                    reference_id,
                    total_amount,
                    payment_status,
                    transaction_date,
                    description
                ) VALUES (
                    :order_id,
                    'Adjustment',
                    :reference_id,
                    :amount,
                    'Completed',
                    CURRENT_TIMESTAMP,
                    'Discount reversal'
                )";

                $stmt = $db->prepare($discountAdjustQuery);
                $stmt->execute([
                    ':order_id' => $orderId,
                    ':reference_id' => 'ADJ-' . $orderId,
                    ':amount' => $orderDetails['discount_amount']
                ]);
            }
        }

        $db->commit();
        return true;

    } catch (Exception $e) {
        $db->rollBack();
        error_log('Order status update error: ' . $e->getMessage());
        return false;
    }
}

function processPayment($db, $orderId, $paymentDetails) {
    try {
        $db->beginTransaction();
        
        // Update order payment status
        $orderQuery = "UPDATE orders SET 
            payment_status = :payment_status,
            transaction_id = :transaction_id,
            updated_at = CURRENT_TIMESTAMP
            WHERE order_id = :order_id";
            
        $stmt = $db->prepare($orderQuery);
        $stmt->execute([
            ':payment_status' => $paymentDetails['status'],
            ':transaction_id' => $paymentDetails['transaction_id'],
            ':order_id' => $orderId
        ]);

        // Only create transaction record for successful payments
        if ($paymentDetails['status'] === 'Paid') {
            // Check for existing transaction
            $checkQuery = "SELECT id FROM transactions 
                          WHERE order_id = :order_id 
                          AND transaction_type = 'Payment'";
            
            $stmt = $db->prepare($checkQuery);
            $stmt->execute([':order_id' => $orderId]);
            
            if (!$stmt->fetch()) {
                $transQuery = "INSERT INTO transactions (
                    order_id,
                    transaction_type,
                    reference_id,
                    total_amount,
                    payment_method,
                    payment_status,
                    transaction_date
                ) VALUES (
                    :order_id,
                    'Payment',
                    :reference_id,
                    :amount,
                    :method,
                    :status,
                    CURRENT_TIMESTAMP
                )";
                
                $stmt = $db->prepare($transQuery);
                $stmt->execute([
                    ':order_id' => $orderId,
                    ':reference_id' => $paymentDetails['transaction_id'],
                    ':amount' => $paymentDetails['amount'],
                    ':method' => $paymentDetails['method'],
                    ':status' => $paymentDetails['status']
                ]);
            }
        }

        $db->commit();
        return true;

    } catch (Exception $e) {
        $db->rollBack();
        error_log('Payment processing error: ' . $e->getMessage());
        return false;
    }
}

function getStatusCardClass($status) {
    $class = '';
    switch ($status) {
        case 'Pending':
            $class = 'bg-warning text-dark border-warning';
            break;
        case 'Processing':
            $class = 'bg-info text-white border-info';
            break;
        case 'Shipped':
            $class = 'bg-primary text-white border-primary';
            break;
        case 'Delivered':
            $class = 'bg-success text-white border-success';
            break;
        case 'Cancelled':
            $class = 'bg-danger text-white border-danger';
            break;
        default:
            $class = 'bg-secondary text-white border-secondary';
    }
    return $class;
}

function validateTrackingNumber($trackingNumber) {
    // Format: TRK-YYYYMMDD-XXXX
    return preg_match('/^TRK-\d{8}-[A-Z0-9]{4}$/', $trackingNumber);
}

function renderOrdersTable($orders, $isDispatch = false) {
    ob_start();
    ?>
    <div class="table-responsive"></div></div>
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
            <tbody></tbody></tbody>
                <?php if (empty($orders)): ?>
                    <tr>
                        <td colspan="10" class="text-center">No orders found</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <tr></tr></tr>
                            <td>#<?= htmlspecialchars($order['order_id']) ?></td>
                            <td><?= htmlspecialchars($order['username']) ?></td>
                            <td><?= htmlspecialchars($order['products']) ?></td>
                            <td>Ksh.<?= number_format($order['total_amount'], 2) ?></td>
                            <td></td></td>
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
                            <td></td></td>
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

function getCouponCode($db, $coupon_id) {
    try {
        $stmt = $db->prepare("SELECT code, discount_percentage FROM coupons WHERE coupon_id = ?");
        $stmt->execute([$coupon_id]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
        return $coupon ? "{$coupon['code']} ({$coupon['discount_percentage']}% off)" : 'N/A';
    } catch (Exception $e) {
        return 'N/A';
    }
}

function getOrderTotals($order) {
    return [
        'subtotal' => $order['original_amount'],
        'discount' => $order['discount_amount'],
        'shipping' => $order['shipping_fee'],
        'total' => $order['total_amount']
    ];
}

function formatOrderAmount($amount, $prefix = 'Ksh.') {
    return $prefix . ' ' . number_format($amount, 2);
}

function validateAndApplyCoupon($db, $couponCode, $userId, $orderTotal) {
    $validator = new CouponValidator($db);
    $result = $validator->validateCoupon($couponCode, $userId, $orderTotal);
    
    if (!$result['valid']) {
        return [
            'valid' => false,
            'message' => $result['message']
        ];
    }
    
    $discountAmount = 0;
    if ($result['discount_type'] === 'percentage') {
        $discountAmount = ($orderTotal * $result['discount_value']) / 100;
    } else {
        $discountAmount = $result['discount_value'];
    }
    
    return [
        'valid' => true,
        'message' => 'Coupon applied successfully',
        'discount_amount' => $discountAmount,
        'coupon_id' => $result['coupon_id'],
        'discount_type' => $result['discount_type'],
        'discount_value' => $result['discount_value']
    ];
}

function getOrderStatus($status) {
    switch ($status) {
        case 'pending': return 'Pending';
        case 'processing': return 'Processing';
        case 'completed': return 'Completed';
        case 'cancelled': return 'Cancelled';
        default: return 'Unknown';
    }
}
