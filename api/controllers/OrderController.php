<?php
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../utils/api_response.php';

class OrderController {
    private $order;
    private $db;
    private $conn;

    public function __construct($db) {
        $this->db = $db;
        $this->order = new Order($db);
        $this->conn = $db;
    }

    public function createOrder($data, $userId, $userRole) {
        if ($userRole !== 'customer' && $userRole !== 'admin') {
            send_error("Unauthorized access", 403);
        }

        try {
            $result = $this->order->create([
                'id' => $userId,  // Changed from user_id
                'service_id' => $data['service_id'],
                'total_amount' => $data['total_amount'],
                'address' => $data['address'],
                'notes' => $data['notes'] ?? '',
                'created_by' => $userId
            ]);

            if ($result) {
                // Send notification via Firebase
                $this->sendNotification('new_order', [
                    'order_id' => $this->db->lastInsertId(),
                    'id' => $userId  // Changed from user_id
                ]);
                
                send_success("Order created successfully", ['order_id' => $this->db->lastInsertId()]);
            }
        } catch (Exception $e) {
            send_error($e->getMessage(), 500);
        }
    }

    public function approveOrder($orderId, $userId, $userRole) {
        if (!in_array($userRole, ['admin', 'service_manager'])) {
            send_error("Unauthorized access", 403);
        }

        try {
            $result = $this->order->updateStatus($orderId, 'Approved', $userId);
            if ($result) {
                $this->sendNotification('order_approved', [
                    'order_id' => $orderId
                ]);
                send_success("Order approved successfully");
            }
        } catch (Exception $e) {
            send_error($e->getMessage(), 500);
        }
    }

    public function assignTechnician($orderId, $technicianId, $userId, $userRole) {
        if (!in_array($userRole, ['admin', 'service_manager'])) {
            send_error("Unauthorized access", 403);
        }

        try {
            // Get order details to check specialization
            $order = $this->order->getOrderById($orderId);
            
            // Verify technician specialization matches order type
            $stmt = $this->db->prepare("
                SELECT specialization 
                FROM technicians 
                WHERE technician_id = ?
            ");
            $stmt->execute([$technicianId]);
            $technician = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$technician) {
                send_error("Invalid technician", 400);
            }

            $result = $this->order->assignTechnician($orderId, $technicianId, $userId);
            if ($result) {
                $this->sendNotification('technician_assigned', [
                    'order_id' => $orderId,
                    'technician_id' => $technicianId,
                    'technician_name' => $technician['name']
                ]);
                send_success("Technician assigned successfully");
            }
        } catch (Exception $e) {
            send_error($e->getMessage(), 500);
        }
    }

    public function markCompleted($orderId, $userId, $userRole) {
        if (!in_array($userRole, ['technician'])) {
            send_error("Unauthorized access", 403);
        }

        try {
            $result = $this->order->markCompleted($orderId, $userId);
            if ($result) {
                $this->sendNotification('order_completed', [
                    'order_id' => $orderId
                ]);
                send_success("Order marked as completed");
            }
        } catch (Exception $e) {
            send_error($e->getMessage(), 500);
        }
    }

    public function assignDriver($orderId, $driverId, $userId, $userRole) {
        if (!in_array($userRole, ['admin', 'dispatch_manager'])) {
            send_error("Unauthorized access", 403);
        }

        try {
            // Verify driver availability
            $stmt = $this->db->prepare("
                SELECT name, vehicle_type, vehicle_status 
                FROM drivers 
                WHERE driver_id = ? AND status = 'Active'
            ");
            $stmt->execute([$driverId]);
            $driver = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$driver) {
                send_error("Driver not available", 400);
            }
            if ($driver['vehicle_status'] !== 'Available') {
                send_error("Driver's vehicle not available", 400);
            }

            $result = $this->order->assignDriver($orderId, $driverId, $userId);
            if ($result) {
                $this->sendNotification('driver_assigned', [
                    'order_id' => $orderId,
                    'driver_id' => $driverId,
                    'driver_name' => $driver['name'],
                    'vehicle_type' => $driver['vehicle_type']
                ]);
                send_success("Driver assigned successfully");
            }
        } catch (Exception $e) {
            send_error($e->getMessage(), 500);
        }
    }

    public function cancelOrder($orderId, $userId, $userRole, $reason) {
        // Check if user has permission to cancel
        if (!in_array($userRole, ['admin', 'customer', 'service_manager'])) {
            send_error("Unauthorized access", 403);
        }

        try {
            // Get order details
            $order = $this->order->getOrderById($orderId);
            
            // Customers can only cancel their own orders
            if ($userRole === 'customer' && $order['id'] !== $userId) {
                send_error("Unauthorized access", 403);
            }

            $result = $this->order->cancelOrder($orderId, $userId, $reason);
            if ($result) {
                $this->sendNotification('order_cancelled', [
                    'order_id' => $orderId,
                    'cancelled_by' => $userRole,
                    'reason' => $reason
                ]);
                send_success("Order cancelled successfully");
            }
        } catch (Exception $e) {
            send_error($e->getMessage(), 500);
        }
    }

    public function listOrders($userId, $role, $filters) {
        $query = "SELECT * FROM orders";
        $where = [];
        $params = [];

        if ($role !== 'admin') {
            $where[] = "id = ?";
            $params[] = $userId;
        }

        if (!empty($filters['status'])) {
            $where[] = "status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(order_number LIKE ? OR customer_name LIKE ?)";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }

        $stmt = $this->conn->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOrderById($orderId, $userId, $userRole) {
        try {
            // For admin and service_manager, allow access to all orders
            if (in_array($userRole, ['admin', 'service_manager'])) {
                return $this->order->getOrderById($orderId);
            }
            
            // For customers, only allow access to their own orders
            $order = $this->order->getOrderById($orderId);
            if (!$order) {
                return null;
            }
            
            if ($userRole === 'customer' && $order['id'] !== $userId) {
                throw new Exception("Unauthorized access to order");
            }
            
            // For technicians, only allow access to assigned orders
            if ($userRole === 'technician' && $order['technician_id'] !== $userId) {
                throw new Exception("Unauthorized access to order");
            }
            
            // For drivers, only allow access to assigned orders
            if ($userRole === 'driver' && $order['driver_id'] !== $userId) {
                throw new Exception("Unauthorized access to order");
            }
            
            return $order;
        } catch (Exception $e) {
            error_log("Error getting order: " . $e->getMessage());
            throw $e;
        }
    }

    private function sendNotification($type, $data) {
        // TODO: Implement Firebase notification logic
        // This will be implemented when we set up Firebase
    }
}
