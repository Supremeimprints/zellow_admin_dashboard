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
    switch ($status) {
        case 'Pending':
            return 'bg-warning text-dark border-warning';
        case 'Processing':
            return 'bg-info text-white border-info';
        case 'Shipped':
            return 'bg-primary text-white border-primary';
        case 'Delivered':
            return 'bg-success text-white border-success';
        case 'Cancelled':
            return 'bg-danger text-white border-danger';
        default:
            return 'bg-secondary text-white border-secondary';
    }
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

/**
 * Get all service requests with related information
 * @param PDO $db Database connection
 * @return array Array of service requests
 */
function getServiceRequests($db) {
    try {
        $query = "SELECT 
            sr.service_request_id,
            sr.id as order_id,
            sr.service_id as product_id,
            sr.status,
            sr.request_date,
            sr.completion_date,
            o.username,
            o.email,
            o.customization_type,
            o.customization_details,
            o.customization_cost
        FROM service_requests sr
        JOIN orders o ON sr.id = o.order_id
        WHERE o.customization_type IS NOT NULL
        ORDER BY sr.request_date DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Log error and return empty array
        error_log("Error fetching service requests: " . $e->getMessage());
        return [];
    }
}

function createServiceRequest($db, $orderId, $productId, $customizationType, $message) {
    try {
        $query = "INSERT INTO service_requests (
            id,            -- This maps to order_id
            service_id,    -- This maps to product_id
            status,
            request_date,
            created_at,
            customization_type,
            customization_details
        ) VALUES (
            :order_id,
            :product_id,
            'Pending',
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP,
            :customization_type,
            :customization_details
        )";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':order_id' => $orderId,
            ':product_id' => $productId,
            ':customization_type' => $customizationType,
            ':customization_details' => $message
        ]);
        
        $serviceRequestId = $db->lastInsertId();
        
        // Update the order with customization details
        $updateOrderQuery = "UPDATE orders SET
            customization_type = :type,
            customization_details = :details,
            customization_cost = :cost
            WHERE order_id = :order_id
            AND product_id = :product_id";
            
        $cost = ($customizationType === 'engraving') ? 500 : 300;
        
        $stmt = $db->prepare($updateOrderQuery);
        $stmt->execute([
            ':type' => $customizationType,
            ':details' => $message,
            ':cost' => $cost,
            ':order_id' => $orderId,
            ':product_id' => $productId
        ]);
        
        return $serviceRequestId;
    } catch (Exception $e) {
        error_log("Error creating service request: " . $e->getMessage());
        return false;
    }
}

function createOrderWithServices($db, $orderData, $products) {
    try {
        $db->beginTransaction();
        
        // Calculate total amount including services
        $orderTotals = calculateOrderTotals($db, $products, $orderData['shipping_fee']);
        
        // Update order data with calculated totals
        $orderData['total_amount'] += $orderTotals['special_services']; // Add service costs to total
        
        // Insert order
        $orderId = insertOrder($db, $orderData);
        
        foreach ($products as $product) {
            // Insert order items
            $orderItemId = insertOrderItem($db, $orderId, $product);
            
            // Handle service request if present
            if (!empty($product['request_service']) && !empty($product['service_type'])) {
                $serviceRequestId = createServiceRequest(
                    $db,
                    $orderId,
                    $product['product_id'],
                    $product['service_type'],
                    $product['service_message']
                );
                
                if ($serviceRequestId) {
                    // Auto-assign to available technician if possible
                    $technician = getAvailableTechnician($db, $product['service_type']);
                    if ($technician) {
                        assignTechnician($db, $serviceRequestId, $technician['technician_id']);
                        sendTechnicianNotification($db, $technician['technician_id'], $serviceRequestId);
                    }
                }
            }
        }
        
        $db->commit();
        return $orderId;
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error creating order with services: " . $e->getMessage());
        throw $e;
    }
}

function calculateCustomizationCost($type, $quantity = 1) {
    $costs = [
        'engraving' => 500,
        'printing' => 300
    ];
    return ($costs[$type] ?? 0) * $quantity;
}

function calculateOrderTotals($db, $items, $shipping_fee = 0, $isOrderItems = false) {
    $totals = [
        'subtotal' => 0,
        'products_subtotal' => 0,
        'service_costs' => 0,
        'special_services' => 0,
        'gift_wrap_total' => 0,
        'shipping_fee' => $shipping_fee,
        'total' => 0
    ];
    
    if ($isOrderItems) {
        // Handle existing order items
        foreach ($items as $item) {
            $totals['products_subtotal'] += $item['subtotal'];
            $totals['service_costs'] += $item['service_cost'];
        }
        $totals['subtotal'] = $totals['products_subtotal'];
        $totals['special_services'] = $totals['service_costs'];
    } else {
        // Handle new order products
        foreach ($items as $item) {
            $quantity = (int)$item['quantity'];
            $unit_price = (float)$item['unit_price'];
            
            // Base product cost
            $totals['products_subtotal'] += ($quantity * $unit_price);
            
            // Special services cost
            if (!empty($item['special_request']) && !empty($item['service_type'])) {
                $service_cost = $item['service_type'] === 'engraving' ? 500 : 300;
                $totals['special_services'] += $service_cost;
                $totals['service_costs'] += $service_cost;
            }
            
            // Gift wrap cost if applicable
            if (!empty($item['is_gift']) && !empty($item['gift_wrap_style_id'])) {
                $wrap_cost = getGiftWrapCostById($db, $item['gift_wrap_style_id']);
                $totals['gift_wrap_total'] += ($wrap_cost * $quantity);
            }
        }
        $totals['subtotal'] = $totals['products_subtotal'];
    }
    
    // Calculate final total
    $totals['total'] = $totals['subtotal'] + 
                       $totals['special_services'] + 
                       $totals['gift_wrap_total'] + 
                       $totals['shipping_fee'];
    
    return $totals;
}

function formatServiceDetails($serviceType, $serviceCost) {
    if (!$serviceType) return '';
    
    return sprintf(
        "%s (Ksh. %s)",
        ucfirst($serviceType),
        number_format($serviceCost, 2)
    );
}

// Add missing helper functions
function getGiftWrapCostById($db, $style_id) {
    $stmt = $db->prepare("SELECT price FROM gift_wrap_styles WHERE id = ?");
    $stmt->execute([$style_id]);
    return (float)$stmt->fetchColumn() ?: 0;
}

require_once __DIR__ . '/badge_functions.php';

function sendTechnicianNotification($db, $technician_id, $request_id) {
    require_once __DIR__ . '/../email_templates/service_notification.php';
    
    // Get technician details
    $stmt = $db->prepare("SELECT email FROM technicians WHERE technician_id = ?");
    $stmt->execute([$technician_id]);
    $email = $stmt->fetchColumn();
    
    if (!$email) {
        error_log("Could not find technician email for ID: $technician_id");
        return false;
    }
    
    // Get service request details
    $stmt = $db->prepare("SELECT * FROM service_requests WHERE id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        error_log("Could not find service request for ID: $request_id");
        return false;
    }
    
    $details = [
        'service_type' => $request['service_type'],
        'order_id' => $request['order_id'],
        'message' => $request['details']
    ];
    
    // Send email using your email helper function
    return sendEmail($email, "New Service Assignment", getServiceNotificationTemplate($details));
}

function sendGiftNotification($db, $order_id, $recipient_email, $recipient_name, $message, $hide_price = false) {
    // Implementation of gift notification email
    // You can add this functionality based on your requirements
    return true;
}

function handleSpecialServices($db, $order_id, $products) {
    foreach ($products as $product) {
        if (!empty($product['customize'])) {
            createServiceRequest(
                $db,
                $order_id,
                $product['product_id'],
                $product['customization_type'],
                $product['customization_message']
            );
        }
    }
}

function notifyRecipients($db, $order_id, $products) {
    foreach ($products as $product) {
        if (!empty($product['is_gift']) && !empty($product['recipient_email'])) {
            sendGiftNotification(
                $db,
                $order_id,
                $product['recipient_email'],
                $product['recipient_name'],
                $product['gift_message'],
                !empty($product['hide_price'])
            );
        }
    }
}

function renderSpecialRequestsSection($index) {
    // Convert the index to string to ensure proper handling
    $index = (string)$index;
    
    return <<<HTML
    <div class="col-12 mt-3 special-requests-section">
        <div class="card">
            <div class="card-body">
                <div class="form-check mb-3">
                    <input type="checkbox" class="form-check-input special-request-toggle" 
                           id="special_request_{$index}" name="products[{$index}][special_request]">
                    <label class="form-check-label" for="special_request_{$index}">
                        Add Special Request
                    </label>
                </div>
                
                <div class="special-request-options" style="display: none;">
                    <div class="mb-3">
                        <label class="form-label">Service Type</label>
                        <select class="form-select service-type" 
                                name="products[{$index}][service_type]" required>
                            <option value="">Select Service</option>
                            <option value="engraving">Engraving (Ksh 500)</option>
                            <option value="printing">Printing (Ksh 300)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Service Details</label>
                        <textarea class="form-control service-details" 
                                name="products[{$index}][service_details]" 
                                rows="3" placeholder="Describe your customization requirements"></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <small><i class="fas fa-info-circle"></i> Special requests may extend processing time by 2-3 business days</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
HTML;
}

function processSpecialRequests($db, $order_id, $products) {
    foreach ($products as $product) {
        if (!empty($product['special_request']) && !empty($product['service_type'])) {
            createServiceRequest(
                $db,
                $order_id,
                $product['product_id'],
                $product['service_type'],
                $product['service_details']
            );
        }
    }
}

function getAvailableTechnician($db, $service_type) {
    $query = "SELECT t.* 
              FROM technicians t
              LEFT JOIN (
                  SELECT technician_id, COUNT(*) as active_assignments
                  FROM technician_assignments
                  WHERE status IN ('pending', 'in_progress')
                  GROUP BY technician_id
              ) ta ON t.technician_id = ta.technician_id
              WHERE t.specialization = ? 
              AND t.status = 'active'
              AND (ta.active_assignments IS NULL OR ta.active_assignments < t.max_assignments)
              ORDER BY COALESCE(ta.active_assignments, 0) ASC
              LIMIT 1";
              
    $stmt = $db->prepare($query);
    $stmt->execute([$service_type]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function calculateServiceCost($service_type, $quantity = 1) {
    $rates = [
        'engraving' => 500,
        'printing' => 300
    ];
    
    return ($rates[$service_type] ?? 0) * $quantity;
}

function insertOrder($db, $orderData) {
    try {
        $query = "INSERT INTO orders (
            customer_id,
            email,
            username,
            total_amount,
            discount_amount,
            shipping_fee,
            status,
            shipping_address,
            tracking_number,
            payment_status,
            shipping_method,
            shipping_method_id,
            shipping_region_id,
            payment_method,
            coupon_id,
            created_at
        ) VALUES (
            :customer_id,
            :email,
            :username,
            :total_amount,
            :discount_amount,
            :shipping_fee,
            :status,
            :shipping_address,
            :tracking_number,
            :payment_status,
            :shipping_method,
            :shipping_method_id,
            :shipping_region_id,
            :payment_method,
            :coupon_id,
            CURRENT_TIMESTAMP
        )";

        $stmt = $db->prepare($query);
        $stmt->execute([
            ':customer_id' => $orderData['customer_id'],
            ':email' => $orderData['email'],
            ':username' => $orderData['username'],
            ':total_amount' => $orderData['total_amount'],
            ':discount_amount' => $orderData['discount_amount'] ?? 0,
            ':shipping_fee' => $orderData['shipping_fee'],
            ':status' => 'Pending',
            ':shipping_address' => $orderData['shipping_address'],
            ':tracking_number' => generateTrackingNumber(),
            ':payment_status' => 'Pending',
            ':shipping_method' => $orderData['shipping_method'],
            ':shipping_method_id' => $orderData['shipping_method_id'],
            ':shipping_region_id' => $orderData['shipping_region_id'],
            ':payment_method' => $orderData['payment_method'],
            ':coupon_id' => $orderData['coupon_id'] ?? null
        ]);

        return $db->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error inserting order: " . $e->getMessage());
        throw $e;
    }
}

function insertOrderItem($db, $orderId, $product) {
    try {
        $query = "INSERT INTO order_items (
            order_id,
            product_id,
            quantity,
            unit_price,
            subtotal,
            status,
            service_type,
            service_details,
            service_cost
        ) VALUES (
            :order_id,
            :product_id,
            :quantity,
            :unit_price,
            :subtotal,
            :status,
            :service_type,
            :service_details,
            :service_cost
        )";

        $quantity = (int)$product['quantity'];
        $unitPrice = (float)$product['unit_price'];
        $subtotal = $quantity * $unitPrice;

        // Calculate service cost if service is requested
        $serviceCost = 0;
        if (!empty($product['request_service']) && !empty($product['service_type'])) {
            $serviceCost = calculateServiceCost($product['service_type'], $quantity);
        }

        $stmt = $db->prepare($query);
        $stmt->execute([
            ':order_id' => $orderId,
            ':product_id' => $product['product_id'],
            ':quantity' => $quantity,
            ':unit_price' => $unitPrice,
            ':subtotal' => $subtotal,
            ':status' => 'purchased',
            ':service_type' => $product['service_type'] ?? null,
            ':service_details' => $product['service_message'] ?? null,
            ':service_cost' => $serviceCost
        ]);

        return $db->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error inserting order item: " . $e->getMessage());
        throw $e;
    }
}
