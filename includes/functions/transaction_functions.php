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
