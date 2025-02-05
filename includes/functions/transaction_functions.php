<?php

if (!function_exists('getRecentTransactions')) {
    function getRecentTransactions($pdo, $search = '', $startDate = null, $endDate = null) {
        try {
            $query = "
                SELECT 
                    t.reference_id,
                    t.total_amount,
                    t.order_id,
                    t.transaction_type,
                    t.payment_status,
                    t.transaction_date,
                    t.payment_method,
                    o.total_amount as original_amount,
                    CASE 
                        WHEN t.transaction_type = 'Customer Payment' 
                        AND t.payment_status = 'completed' THEN t.total_amount 
                        ELSE 0 
                    END as money_in,
                    CASE 
                        WHEN t.transaction_type IN ('Expense', 'Refund') 
                        THEN t.total_amount 
                        ELSE 0 
                    END as money_out,
                    CASE 
                        WHEN t.transaction_type = 'Customer Payment' THEN 'bg-success-soft text-success'
                        WHEN t.transaction_type = 'Refund' THEN 'bg-warning-soft text-warning'
                        WHEN t.transaction_type = 'Expense' THEN 'bg-danger-soft text-danger'
                    END as badge_class
                FROM transactions t
                LEFT JOIN orders o ON t.order_id = o.order_id
                WHERE 1=1";
            
            $params = [];
            
            if ($search) {
                $query .= " AND (t.reference_id LIKE :search OR t.payment_method LIKE :search)";
                $params[':search'] = "%$search%";
            }
            
            if ($startDate) {
                $query .= " AND t.transaction_date >= :start_date";
                $params[':start_date'] = $startDate . ' 00:00:00';
            }
            
            if ($endDate) {
                $query .= " AND t.transaction_date <= :end_date";
                $params[':end_date'] = $endDate . ' 23:59:59';
            }
            
            $query .= " ORDER BY t.transaction_date DESC LIMIT 10";

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error in getRecentTransactions: " . $e->getMessage());
            return [];
        }
    }
}

function createTransaction($db, $data) {
    // First check if a transaction already exists for this order
    if (isset($data['order_id'])) {
        $checkQuery = "SELECT id FROM transactions 
                      WHERE order_id = ? AND transaction_type = ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$data['order_id'], $data['type']]);
        
        if ($checkStmt->fetch()) {
            // Transaction already exists for this order, don't create a duplicate
            return true;
        }
    }

    // Generate a unique reference ID
    $reference = generateTransactionRef();
    
    $sql = "INSERT INTO transactions (
        reference_id, 
        transaction_type,
        order_id,
        total_amount,
        payment_method,
        payment_status,
        id,  /* Changed from user_id to id to match users table */
        remarks,
        transaction_date
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";

    $stmt = $db->prepare($sql);
    return $stmt->execute([
        $reference,
        $data['type'],
        $data['order_id'] ?? null,
        $data['amount'],
        $data['payment_method'],
        $data['payment_status'] ?? 'pending',
        $data['id'] ?? null,  // Changed from user_id to id
        $data['remarks'] ?? null
    ]);
}

function updateTransaction($db, $orderId, $data) {
    $sql = "UPDATE transactions 
            SET payment_status = ?,
                total_amount = ?,
                payment_method = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE order_id = ? AND transaction_type = ?";

    $stmt = $db->prepare($sql);
    return $stmt->execute([
        $data['payment_status'],
        $data['amount'],
        $data['payment_method'],
        $orderId,
        $data['type']
    ]);
}

function getTransactionByOrderId($db, $orderId, $type = 'Customer Payment') {
    $sql = "SELECT * FROM transactions 
            WHERE order_id = ? AND transaction_type = ? 
            ORDER BY transaction_date DESC LIMIT 1";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$orderId, $type]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function generateTransactionRef() {
    return 'TRX-' . date('YmdHis') . '-' . substr(uniqid(), -4);
}

function createRefundTransaction($db, $orderId, $amount, $userId) {
    return createTransaction($db, [
        'type' => 'Refund',
        'amount' => -abs($amount), // Make sure refund amount is negative
        'payment_method' => 'Mpesa', // Or get from original order
        'payment_status' => 'completed',
        'user_id' => $userId,
        'order_id' => $orderId,
        'remarks' => 'Order refund'
    ]);
}

// Add this to your existing code
function recordPayment($db, $orderId, $amount, $paymentMethod, $userId) {
    return createTransaction($db, [
        'type' => 'Customer Payment',
        'amount' => $amount,
        'payment_method' => $paymentMethod,
        'payment_status' => 'completed',
        'user_id' => $userId,
        'order_id' => $orderId,
        'remarks' => 'Order payment'
    ]);
}

function updateInventoryOnRefund($db, $orderId) {
    try {
        $db->beginTransaction();
        
        // Get order items
        $stmt = $db->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
        $stmt->execute([$orderId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Update inventory for each item
        foreach ($items as $item) {
            $updateStmt = $db->prepare("UPDATE inventory 
                                      SET stock_quantity = stock_quantity + :quantity 
                                      WHERE product_id = :product_id");
            $updateStmt->execute([
                ':quantity' => $item['quantity'],
                ':product_id' => $item['product_id']
            ]);
        }
        
        $db->commit();
        return true;
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Error in updateInventoryOnRefund: " . $e->getMessage());
        return false;
    }
}

function getStatusBadgeClass($status, $type = 'status') {
    if ($type === 'status') {
        return match ($status) {
            'Pending' => 'bg-warning text-dark',
            'Processing' => 'bg-info text-white',
            'Shipped' => 'bg-primary text-white',
            'Delivered' => 'bg-success text-white',
            'Cancelled' => 'bg-danger text-white',
            'Refunded' => 'bg-secondary text-white',
            default => 'bg-secondary text-white'
        };
    }
    
    if ($type === 'payment') {
        return match ($status) {
            'Paid' => 'bg-success text-white',
            'Pending' => 'bg-warning text-dark',
            'Failed' => 'bg-danger text-white',
            'Refunded' => 'bg-info text-white',
            default => 'bg-secondary text-white'
        };
    }
}
