<?php

function getOrderStatistics($db, $type = 'all') {
    try {
        // Base query to get order counts and amounts
        $query = "SELECT 
                    status,
                    COUNT(*) as count,
                    COALESCE(SUM(total_amount), 0) as total_amount
                FROM orders ";

        // Add condition for dispatch view
        if ($type === 'dispatch') {
            $query .= " WHERE status IN ('Pending', 'Processing')";
        }
        
        $query .= " GROUP BY status";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Initialize all possible statuses with zero counts
        $stats = [
            'Pending' => ['count' => 0, 'amount' => 0],
            'Processing' => ['count' => 0, 'amount' => 0],
            'Shipped' => ['count' => 0, 'amount' => 0],
            'Delivered' => ['count' => 0, 'amount' => 0],
            'Cancelled' => ['count' => 0, 'amount' => 0]
        ];
        
        // Update with actual counts from database
        foreach ($results as $row) {
            if (isset($stats[$row['status']])) {
                $stats[$row['status']] = [
                    'count' => (int)$row['count'],
                    'amount' => (float)$row['total_amount']
                ];
            }
        }
        
        return $stats;
    } catch (PDOException $e) {
        error_log("Error getting order statistics: " . $e->getMessage());
        return [];
    }
}

function updateOrderStatus($db, $orderId, $newStatus) {
    try {
        $db->beginTransaction();
        
        $query = "UPDATE orders SET status = :status WHERE order_id = :order_id";
        $stmt = $db->prepare($query);
        $result = $stmt->execute([
            ':status' => $newStatus,
            ':order_id' => $orderId
        ]);
        
        if ($result) {
            // Add status change to order history
            $historyQuery = "INSERT INTO order_history 
                           (order_id, status, changed_at, changed_by) 
                           VALUES (:order_id, :status, NOW(), :user_id)";
            $historyStmt = $db->prepare($historyQuery);
            $historyStmt->execute([
                ':order_id' => $orderId,
                ':status' => $newStatus,
                ':user_id' => $_SESSION['id'] ?? null
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

function getStatusBadgeClass($status) {
    return match ($status) {
        'Pending' => 'bg-warning text-dark',
        'Processing' => 'bg-info text-white',
        'Shipped' => 'bg-primary text-white',
        'Delivered' => 'bg-success text-white',
        'Cancelled' => 'bg-danger text-white',
        default => 'bg-secondary text-white'
    };
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

function generateTrackingNumber() {
    $prefix = 'TRK';
    $date = date('Ymd');
    $random = strtoupper(substr(uniqid(), -4));
    return "{$prefix}-{$date}-{$random}";
}

function validateTrackingNumber($trackingNumber) {
    // Format: TRK-YYYYMMDD-XXXX
    return preg_match('/^TRK-\d{8}-[A-Z0-9]{4}$/', $trackingNumber);
}
