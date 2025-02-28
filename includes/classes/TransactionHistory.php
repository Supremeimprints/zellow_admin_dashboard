<?php

class TransactionHistory {
    private $db;
    private $perPage = 25;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getTransactions($filters, $page = 1) {
        try {
            // Modified query to include customer email from orders
            $query = "SELECT t.*, o.email as customer_email 
                     FROM transactions t 
                     LEFT JOIN orders o ON t.order_id = o.order_id 
                     ORDER BY t.transaction_date DESC 
                     LIMIT ? OFFSET ?";
            
            // Calculate limit and offset
            $limit = $this->perPage;
            $offset = ($page - 1) * $limit;
            
            // Debug information
            error_log("Executing query: " . $query);
            error_log("Limit: " . $limit . ", Offset: " . $offset);
            
            $stmt = $this->db->prepare($query);
            $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
            $stmt->bindValue(2, (int)$offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Debug the results
            error_log("Number of transactions fetched: " . count($results));
            
            return $results;
            
        } catch (PDOException $e) {
            error_log("Database error in getTransactions: " . $e->getMessage());
            return [];
        }
    }

    public function getTotalTransactions($filters) {
        try {
            $query = "SELECT COUNT(DISTINCT t.id) 
                     FROM transactions t 
                     LEFT JOIN orders o ON t.order_id = o.order_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Database error in getTotalTransactions: " . $e->getMessage());
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

    // Add the missing methods
    public function getTotalAmount($filters) {
        try {
            $query = "SELECT COALESCE(SUM(total_amount), 0) as total FROM transactions WHERE 1=1";
            list($whereClause, $params) = $this->buildWhereClause($filters);
            $query .= $whereClause;
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Error getting total amount: " . $e->getMessage());
            return 0;
        }
    }

    public function getSuccessRate($filters) {
        try {
            $query = "SELECT 
                (COUNT(CASE WHEN payment_status = 'completed' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)) as success_rate 
                FROM transactions t 
                WHERE 1=1";
            
            list($whereClause, $params) = $this->buildWhereClause($filters);
            $query .= $whereClause;
            
            // Debug the query
            error_log("Success Rate Query: " . $query);
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $rate = $stmt->fetchColumn();
            
            // Debug the result
            error_log("Success Rate Result: " . $rate);
            
            return round((float)$rate ?? 0, 1); // Round to 1 decimal place
        } catch (Exception $e) {
            error_log("Error calculating success rate: " . $e->getMessage());
            return 0;
        }
    }

    public function getAverageAmount($filters) {
        try {
            $query = "SELECT COALESCE(AVG(total_amount), 0) as avg_amount FROM transactions WHERE 1=1";
            list($whereClause, $params) = $this->buildWhereClause($filters);
            $query .= $whereClause;
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchColumn();
        } catch (Exception $e) {
            error_log("Error getting average amount: " . $e->getMessage());
            return 0;
        }
    }

    private function buildWhereClause($filters) {
        $conditions = [];
        $params = [];
        
        if (!empty($filters['start_date'])) {
            $conditions[] = "transaction_date >= :start_date";
            $params[':start_date'] = $filters['start_date'] . ' 00:00:00';
        }
        
        if (!empty($filters['end_date'])) {
            $conditions[] = "transaction_date <= :end_date";
            $params[':end_date'] = $filters['end_date'] . ' 23:59:59';
        }
        
        if (!empty($filters['payment_method'])) {
            $conditions[] = "payment_method = :payment_method";
            $params[':payment_method'] = $filters['payment_method'];
        }
        
        if (!empty($filters['status'])) {
            $conditions[] = "payment_status = :status";
            $params[':status'] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $conditions[] = "(reference_id LIKE :search OR order_id LIKE :search)";
            $params[':search'] = "%{$filters['search']}%";
        }
        
        $whereClause = !empty($conditions) ? " AND " . implode(" AND ", $conditions) : "";
        
        return [$whereClause, $params];
    }
}
