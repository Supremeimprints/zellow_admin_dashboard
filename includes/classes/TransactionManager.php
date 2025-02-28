<?php

class TransactionManager {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getTransactionHistory($startDate, $endDate, $limit = 10) {
        try {
            // Ensure end date includes the full day
            $startDateTime = date('Y-m-d 00:00:00', strtotime($startDate));
            $endDateTime = date('Y-m-d 23:59:59', strtotime($endDate));

            // Debug logging
            error_log("TransactionManager fetching from {$startDateTime} to {$endDateTime}");

            $query = "SELECT 
                t.transaction_date,
                t.transaction_type,
                t.reference_id,
                t.order_id,
                CASE 
                    WHEN t.transaction_type = 'Order Payment' THEN ABS(t.amount)
                    WHEN t.transaction_type IN ('Invoice Payment', 'Expense', 'Refund') THEN -ABS(t.amount)
                    ELSE t.amount
                END as amount,
                t.payment_status,
                t.description,
                -- Add badge color class based on transaction type
                CASE 
                    WHEN t.transaction_type = 'Order Payment' THEN 'bg-success'
                    WHEN t.transaction_type IN ('Expense', 'Refund') THEN 'bg-danger'
                    WHEN t.transaction_type = 'Invoice Payment' THEN 'bg-primary'
                    ELSE 'bg-secondary'
                END as badge_class,
                -- Add amount color class
                CASE 
                    WHEN t.transaction_type = 'Order Payment' THEN 'text-success'
                    WHEN t.transaction_type = 'Invoice Payment' THEN 'text-primary'
                    WHEN t.transaction_type IN ('Expense', 'Refund') THEN 'text-danger'
                    ELSE ''
                END as amount_class
                FROM (
                    -- Customer order payments
                    SELECT 
                        p.payment_date as transaction_date,
                        'Order Payment' as transaction_type,
                        COALESCE(p.transaction_id, CONCAT('ORD-', o.order_id)) as reference_id,
                        o.order_id,
                        p.amount,
                        CASE 
                            WHEN o.payment_status = 'Paid' THEN 'completed'
                            WHEN o.payment_status = 'Pending' THEN 'pending'
                            ELSE o.payment_status
                        END as payment_status,
                        CONCAT('Order by ', o.email) as description
                    FROM payments p
                    JOIN orders o ON p.order_id = o.order_id
                    WHERE p.payment_date BETWEEN :start_date AND :end_date
                    AND p.status = 'completed'
                    
                    UNION ALL
                    
                    -- Order refunds
                    SELECT 
                        t.transaction_date,
                        'Refund' as transaction_type,
                        CONCAT('REF-', t.reference_id) as reference_id,
                        t.order_id,
                        -t.total_amount as amount,
                        'Refunded' as payment_status,
                        CONCAT('Refund for order #', t.order_id) as description
                    FROM transactions t
                    WHERE t.transaction_type = 'Refund'
                    AND t.transaction_date BETWEEN :start_date AND :end_date

                    UNION ALL

                    -- Invoice payments (with negative amount)
                    SELECT 
                        ip.payment_date as transaction_date,
                        'Invoice Payment' as transaction_type,
                        ip.payment_reference as reference_id,
                        i.invoice_id as order_id,
                        ip.amount,  -- Will be made negative in the outer query
                        i.status as payment_status,
                        CONCAT('Invoice #', i.invoice_number, ' - ', s.company_name) as description
                    FROM invoice_payments ip
                    JOIN invoices i ON ip.invoice_id = i.invoice_id
                    LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id
                    WHERE ip.payment_date BETWEEN :start_date AND :end_date

                    UNION ALL

                    -- Purchase order payments
                    SELECT 
                        pp.payment_date as transaction_date,
                        'Purchase Payment' as transaction_type,
                        COALESCE(pp.transaction_id, CONCAT('PO-', pp.purchase_order_id)) as reference_id,
                        pp.purchase_order_id as order_id,
                        pp.amount,
                        pp.status as payment_status,
                        CONCAT('Purchase Order #', pp.purchase_order_id) as description
                    FROM purchase_payments pp
                    WHERE pp.payment_date BETWEEN :start_date AND :end_date
                    AND pp.status = 'completed'

                    UNION ALL

                    -- Expenses
                    SELECT 
                        e.expense_date as transaction_date,
                        'Expense' as transaction_type,
                        CONCAT('EXP-', e.expense_id) as reference_id,
                        NULL as order_id,
                        -e.amount as amount,
                        'completed' as payment_status,
                        CONCAT(e.category, ': ', e.description) as description
                    FROM expenses e
                    WHERE e.expense_date BETWEEN :start_date AND :end_date
                ) t
                ORDER BY t.transaction_date DESC
                LIMIT :limit";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':start_date', $startDateTime);
            $stmt->bindValue(':end_date', $endDateTime);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Add debug logging
            error_log("Found " . count($results) . " transactions");
            
            return $results;

        } catch (Exception $e) {
            error_log('Transaction history error: ' . $e->getMessage());
            return [];
        }
    }

    public function getTransactionStats($startDate, $endDate) {
        try {
            $stats = [
                'total_revenue' => 0,
                'total_expenses' => 0,
                'total_refunds' => 0,
                'total_transactions' => 0
            ];

            $query = "SELECT 
                    SUM(CASE 
                        WHEN t.transaction_type IN ('Order Payment', 'Invoice Payment') THEN t.amount 
                        ELSE 0 
                    END) as total_revenue,
                    SUM(CASE 
                        WHEN t.transaction_type = 'Expense' THEN ABS(t.amount)
                        ELSE 0 
                    END) as total_expenses,
                    SUM(CASE 
                        WHEN t.transaction_type = 'Refund' THEN ABS(t.amount)
                        ELSE 0 
                    END) as total_refunds,
                    COUNT(*) as total_transactions
                FROM (
                    -- Reuse the same subquery from getTransactionHistory
                    {$this->getTransactionSubquery()}
                ) t";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':start_date', $startDate . ' 00:00:00');
            $stmt->bindValue(':end_date', $endDate . ' 23:59:59');
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return array_merge($stats, $result ?: []);

        } catch (Exception $e) {
            error_log('Transaction stats error: ' . $e->getMessage());
            return $stats;
        }
    }

    private function getTransactionSubquery() {
        return "SELECT 
            transaction_date, transaction_type, amount 
            FROM (
                -- Add the same UNION ALL queries as in getTransactionHistory
                -- but only select necessary columns for stats
            ) t";
    }
}
