<?php

class TransactionHistory {
    private $db;
    private $perPage = 25;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getTransactions($filters = [], $page = 1) {
        try {
            $conditions = [];
            $params = [];
            
            // Build filter conditions
            if (!empty($filters['start_date'])) {
                $conditions[] = "transaction_date >= :start_date";
                $params[':start_date'] = $filters['start_date'];
            }
            
            if (!empty($filters['end_date'])) {
                $conditions[] = "transaction_date <= :end_date";
                $params[':end_date'] = $filters['end_date'];
            }
            
            if (!empty($filters['payment_method'])) {
                $conditions[] = "payment_method = :payment_method";
                $params[':payment_method'] = $filters['payment_method'];
            }
            
            if (!empty($filters['status'])) {
                $conditions[] = "transaction_status = :status";
                $params[':status'] = $filters['status'];
            }
            
            if (!empty($filters['search'])) {
                $conditions[] = "(transaction_reference LIKE :search OR invoice_id LIKE :search)";
                $params[':search'] = "%{$filters['search']}%";
            }

            // Calculate pagination
            $offset = ($page - 1) * $this->perPage;
            
            // Build query
            $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
            
            $query = "SELECT 
                      t.id,
                      t.transaction_date,
                      t.transaction_type,
                      t.reference_id,
                      t.total_amount,
                      t.payment_method,
                      t.payment_status,
                      o.email as customer_email,
                      o.order_id,
                      o.payment_status as order_status
                      FROM transactions t
                      LEFT JOIN orders o ON t.order_id = o.order_id
                      {$whereClause}
                      ORDER BY t.transaction_date DESC
                      LIMIT :limit OFFSET :offset";

            $stmt = $this->db->prepare($query);
            
            // Bind pagination parameters
            $stmt->bindValue(':limit', $this->perPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            // Bind filter parameters
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Transaction history error: " . $e->getMessage());
            return false;
        }
    }

    public function getTotalTransactions($filters = []) {
        try {
            $conditions = [];
            $params = [];
            
            // Build filter conditions (same as above)
            // ...existing filter logic...
            
            $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
            
            $query = "SELECT COUNT(*) FROM transactions t {$whereClause}";
            $stmt = $this->db->prepare($query);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->execute();
            return $stmt->fetchColumn();
            
        } catch (Exception $e) {
            error_log("Transaction count error: " . $e->getMessage());
            return 0;
        }
    }

    public function getTransactionDetails($transactionId) {
        try {
            $query = "SELECT t.*, 
                      o.email as customer_email,
                      o.order_id as order_reference,
                      o.status as order_status,
                      o.total_amount as order_amount,
                      o.payment_status
                      FROM transactions t
                      LEFT JOIN orders o ON t.order_id = o.order_id
                      WHERE t.id = :id";
                      
            $stmt = $this->db->prepare($query);
            $stmt->execute([':id' => $transactionId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("Transaction details error: " . $e->getMessage());
            return false;
        }
    }
}
