<?php
class Order {
    private $conn;
    private $table_name = "orders";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($data) {
        $this->conn->beginTransaction();
        try {
            // Insert main order record
            $query = "INSERT INTO " . $this->table_name . " SET 
                id = ?, product_id = ?, quantity = ?, price = ?,
                shipping_address = ?, shipping_method = ?, shipping_method_id = ?,
                shipping_region_id = ?, payment_method = ?, email = ?, username = ?,
                total_amount = ?, shipping_fee = ?, status = 'Pending',
                is_gift = ?, gift_message = ?, recipient_email = ?, recipient_name = ?,
                customization_type = ?, customization_details = ?, customization_cost = ?,
                active = 1, created_at = CURRENT_TIMESTAMP";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                $data['id'],
                $data['product_id'],
                $data['quantity'],
                $data['price'],
                $data['shipping_address'],
                $data['shipping_method'],
                $data['shipping_method_id'],
                $data['shipping_region_id'],
                $data['payment_method'],
                $data['email'],
                $data['username'],
                $data['total_amount'],
                $data['shipping_fee'],
                $data['is_gift'] ?? 0,
                $data['gift_message'] ?? null,
                $data['recipient_email'] ?? null,
                $data['recipient_name'] ?? null,
                $data['customization_type'] ?? null,
                $data['customization_details'] ?? null,
                $data['customization_cost'] ?? 0
            ]);

            $orderId = $this->conn->lastInsertId();

            // Record initial status
            $this->recordStatusHistory($orderId, 'Pending', 'Pending', $data['id']);

            $this->conn->commit();
            return $orderId;
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    public function updateStatus($orderId, $newStatus, $userId, $additionalData = []) {
        $this->conn->beginTransaction();
        try {
            // Update order status and any additional fields
            $updateFields = ["status = ?", "updated_by = ?"];
            $params = [$newStatus, $userId];
            
            foreach ($additionalData as $field => $value) {
                $updateFields[] = "$field = ?";
                $params[] = $value;
            }
            
            $params[] = $orderId;
            
            $query = "UPDATE " . $this->table_name . "
                     SET " . implode(", ", $updateFields) . "
                     WHERE order_id = ?";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);

            // Record in history
            $query = "INSERT INTO order_status_history 
                     (order_id, status, payment_status, updated_by, notes)
                     SELECT order_id, status, payment_status, ?, ?
                     FROM " . $this->table_name . " WHERE order_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$userId, json_encode($additionalData), $orderId]);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    public function getOrderById($orderId) {
        $query = "SELECT o.*, 
                  u.username, u.email, u.phone, u.role,
                  t.name as technician_name, t.specialization as technician_specialization,
                  d.name as driver_name, d.vehicle_type, d.registration_number
                  FROM " . $this->table_name . " o
                  LEFT JOIN users u ON o.id = u.id
                  LEFT JOIN technicians t ON o.technician_id = t.technician_id
                  LEFT JOIN drivers d ON o.driver_id = d.driver_id
                  WHERE o.order_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$orderId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function assignTechnician($orderId, $technicianId, $userId) {
        // Verify technician exists and is valid
        $stmt = $this->conn->prepare("SELECT technician_id FROM technicians WHERE technician_id = ?");
        $stmt->execute([$technicianId]);
        if (!$stmt->fetch()) {
            throw new Exception("Invalid technician ID");
        }
        
        return $this->updateStatus($orderId, 'Assigned_Technician', $userId, [
            'technician_id' => $technicianId
        ]);
    }

    public function markCompleted($orderId, $userId) {
        return $this->updateStatus($orderId, 'Completed', $userId);
    }

    public function approveCompletion($orderId, $userId) {
        return $this->updateStatus($orderId, 'Approved_Completion', $userId);
    }

    public function assignDriver($orderId, $driverId, $userId) {
        // Verify driver exists and is available
        $stmt = $this->conn->prepare("
            SELECT driver_id 
            FROM drivers 
            WHERE driver_id = ? 
            AND status = 'Active' 
            AND vehicle_status = 'Available'
        ");
        $stmt->execute([$driverId]);
        if (!$stmt->fetch()) {
            throw new Exception("Driver not available");
        }
        
        $this->conn->beginTransaction();
        try {
            // Update driver's assigned_orders count
            $this->conn->prepare("
                UPDATE drivers 
                SET assigned_orders = assigned_orders + 1,
                    vehicle_status = 'In Use'
                WHERE driver_id = ?
            ")->execute([$driverId]);
            
            $result = $this->updateStatus($orderId, 'Assigned_Driver', $userId, [
                'driver_id' => $driverId
            ]);
            
            $this->conn->commit();
            return $result;
        } catch (Exception $e) {
            $this->conn->rollBack();
            throw $e;
        }
    }

    public function cancelOrder($orderId, $userId, $reason) {
        // Check if order can be cancelled (not delivered/completed)
        $stmt = $this->conn->prepare("
            SELECT status 
            FROM " . $this->table_name . " 
            WHERE order_id = ? 
            AND status NOT IN ('Delivered', 'Completed')
        ");
        $stmt->execute([$orderId]);
        
        if (!$stmt->fetch()) {
            throw new Exception("Order cannot be cancelled");
        }
        
        return $this->updateStatus($orderId, 'Cancelled', $userId, [
            'notes' => $reason
        ]);
    }

    public function listOrders($userId, $userRole, $filters) {
        $conditions = [];
        $params = [];
        
        // Base query with all necessary fields
        $query = "SELECT o.*, 
                  u.username, u.email,
                  p.product_name,
                  d.name as driver_name
                  FROM " . $this->table_name . " o
                  LEFT JOIN users u ON o.id = u.id
                  LEFT JOIN products p ON o.product_id = p.product_id
                  LEFT JOIN drivers d ON o.driver_id = d.driver_id
                  WHERE o.active = 1";

        // Apply role-based filters
        switch($userRole) {
            case 'customer':
                $conditions[] = "o.id = ?";
                $params[] = $userId;
                break;
            case 'driver':
                $conditions[] = "o.driver_id = ?";
                $params[] = $userId;
                break;
            case 'dispatch_manager':
                $conditions[] = "o.status IN ('Processing', 'Shipped')";
                break;
        }

        // Apply filters
        if (!empty($filters['status'])) {
            $conditions[] = "o.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['start_date'])) {
            $conditions[] = "o.order_date >= ?";
            $params[] = $filters['start_date'];
        }

        if (!empty($filters['end_date'])) {
            $conditions[] = "o.order_date <= ?";
            $params[] = $filters['end_date'];
        }

        if (!empty($filters['search'])) {
            $searchTerm = "%{$filters['search']}%";
            $conditions[] = "(o.order_id LIKE ? OR o.tracking_number LIKE ? OR u.username LIKE ?)";
            array_push($params, $searchTerm, $searchTerm, $searchTerm);
        }

        // Add conditions to query
        if (!empty($conditions)) {
            $query .= " AND " . implode(" AND ", $conditions);
        }

        // Add sorting and pagination
        $query .= " ORDER BY o.order_date DESC LIMIT ? OFFSET ?";
        $params[] = $filters['limit'];
        $params[] = ($filters['page'] - 1) * $filters['limit'];

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);

        return [
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'page' => $filters['page'],
            'limit' => $filters['limit'],
            'total' => $this->getTotalOrderCount($conditions, array_slice($params, 0, -2))
        ];
    }

    private function getTotalOrderCount($conditions, $params) {
        $query = "SELECT COUNT(*) FROM " . $this->table_name . " o WHERE active = 1";
        if (!empty($conditions)) {
            $query .= " AND " . implode(" AND ", $conditions);
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private function recordStatusHistory($orderId, $status, $paymentStatus, $userId, $notes = '') {
        $query = "INSERT INTO order_status_history SET
                  order_id = ?, status = ?, payment_status = ?,
                  updated_by = ?, notes = ?, created_at = CURRENT_TIMESTAMP";
        
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$orderId, $status, $paymentStatus, $userId, $notes]);
    }

    // Add other necessary methods
}
