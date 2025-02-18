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

function createOrUpdateTransaction($db, $data) {
    try {
        // Check for existing transaction
        $checkQuery = "SELECT id, payment_status FROM transactions 
                      WHERE order_id = :order_id 
                      AND transaction_type = :type";
        
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([
            ':order_id' => $data['order_id'],
            ':type' => $data['type']
        ]);
        
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Only update if payment status has changed
            if ($existing['payment_status'] !== $data['payment_status']) {
                $updateQuery = "UPDATE transactions 
                              SET payment_status = :payment_status,
                                  payment_method = :payment_method,
                                  total_amount = :amount,
                                  remarks = :remarks,
                                  updated_at = CURRENT_TIMESTAMP
                              WHERE id = :id";
                
                $updateStmt = $db->prepare($updateQuery);
                return $updateStmt->execute([
                    ':payment_status' => $data['payment_status'],
                    ':payment_method' => $data['payment_method'],
                    ':amount' => $data['amount'],
                    ':remarks' => $data['remarks'] ?? 'Status updated',
                    ':id' => $existing['id']
                ]);
            }
            return true; // No update needed
        } else {
            // Create new transaction
            $insertQuery = "INSERT INTO transactions 
                          (reference_id, transaction_type, order_id, total_amount,
                           payment_method, payment_status, user, remarks, transaction_date) 
                          VALUES 
                          (:reference_id, :type, :order_id, :amount,
                           :payment_method, :payment_status, :user, :remarks, CURRENT_TIMESTAMP)";
            
            $insertStmt = $db->prepare($insertQuery);
            return $insertStmt->execute([
                ':reference_id' => 'TXN-' . uniqid(),
                ':type' => $data['type'],
                ':order_id' => $data['order_id'],
                ':amount' => $data['amount'],
                ':payment_method' => $data['payment_method'],
                ':payment_status' => $data['payment_status'],
                ':user' => $data['user'],
                ':remarks' => $data['remarks'] ?? 'New transaction'
            ]);
        }
    } catch (PDOException $e) {
        error_log("Transaction error: " . $e->getMessage());
        throw $e;
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
        'userid' => $userId,
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

// Remove the getStatusBadgeClass function from here - it's now in badge_functions.php

function createOrderTransaction($db, $orderId, $amount, $paymentMethod, $userId) {
    try {
        // Generate unique reference ID
        $referenceId = 'TRX-' . date('YmdHis') . '-' . substr(uniqid(), -4);
        
        $query = "INSERT INTO transactions (
            reference_id,
            transaction_type,
            total_amount,
            payment_method,
            payment_status,
            user,
            order_id,
            remarks
        ) VALUES (
            :reference_id,
            'Customer Payment',
            :amount,
            :payment_method,
            'completed',
            :user,
            :order_id,
            'Order payment'
        )";

        $stmt = $db->prepare($query);
        $stmt->execute([
            ':reference_id' => $referenceId,
            ':amount' => $amount,
            ':payment_method' => $paymentMethod,
            ':user' => $userId,
            ':order_id' => $orderId
        ]);

        return $referenceId;
    } catch (Exception $e) {
        error_log("Error creating transaction: " . $e->getMessage());
        throw $e;
    }
}
