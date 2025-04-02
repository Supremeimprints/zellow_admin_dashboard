<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/api_response.php';
require_once __DIR__ . '/../../includes/middleware/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions/shipping_functions.php';

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    // Public endpoint for getting shipping rate
    if ($method === 'GET') {
        $methodId = $_GET['method_id'] ?? null;
        $regionId = $_GET['region_id'] ?? null;
        $itemCount = $_GET['item_count'] ?? 1;
        $orderTotal = $_GET['order_total'] ?? 0;

        if (!$methodId || !$regionId) {
            send_error("Method ID and Region ID are required", 400);
        }

        $rate = getShippingRate($db, $methodId, $regionId);
        if (!$rate) {
            send_error("No shipping rate found for this combination", 404);
        }

        $fee = calculateShippingFee($db, $methodId, $regionId, $itemCount, $orderTotal);
        
        $response = [
            'rate' => $rate,
            'calculated_fee' => $fee,
            'free_shipping_eligible' => ($rate['free_shipping_threshold'] && $orderTotal >= $rate['free_shipping_threshold'])
        ];

        send_success("Shipping rate retrieved successfully", $response);
        exit();
    }

    // Protected endpoints require admin authentication
    $userData = authenticate();
    if (!$userData || $userData->role !== 'admin') {
        send_error('Unauthorized access', 401);
    }

    switch ($method) {
        case 'POST':
            $data = json_decode(file_get_contents("php://input"), true);
            
            if (!isset($data['region_id'], $data['method_id'], $data['base_rate'])) {
                send_error("Region ID, Method ID, and base rate are required", 400);
            }

            // Validate region and method exist
            $checkStmt = $db->prepare("
                SELECT r.id as region_id, m.id as method_id
                FROM shipping_regions r
                CROSS JOIN shipping_methods m
                WHERE r.id = ? AND m.id = ?
                AND r.is_active = 1 AND m.is_active = 1
            ");
            $checkStmt->execute([$data['region_id'], $data['method_id']]);
            if (!$checkStmt->fetch()) {
                send_error("Invalid or inactive region or method", 400);
            }

            $stmt = $db->prepare("
                INSERT INTO region_shipping_rates (
                    region_id, shipping_method_id, base_rate, 
                    per_item_fee, is_active
                ) VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    base_rate = VALUES(base_rate),
                    per_item_fee = VALUES(per_item_fee),
                    is_active = VALUES(is_active)
            ");
            
            $stmt->execute([
                $data['region_id'],
                $data['method_id'],
                $data['base_rate'],
                $data['per_item_fee'] ?? 0.00,
                $data['is_active'] ?? 1
            ]);
            
            send_success("Shipping rate created/updated successfully");
            break;

        case 'PUT':
            $data = json_decode(file_get_contents("php://input"), true);
            
            if (!isset($data['region_id'], $data['method_id'])) {
                send_error("Region ID and Method ID are required", 400);
            }

            $fields = [];
            $params = [];
            
            if (isset($data['base_rate'])) {
                $fields[] = "base_rate = ?";
                $params[] = $data['base_rate'];
            }
            if (isset($data['per_item_fee'])) {
                $fields[] = "per_item_fee = ?";
                $params[] = $data['per_item_fee'];
            }
            if (isset($data['is_active'])) {
                $fields[] = "is_active = ?";
                $params[] = $data['is_active'];
            }
            
            if (empty($fields)) {
                send_error("No fields to update", 400);
            }

            $params[] = $data['region_id'];
            $params[] = $data['method_id'];
            
            $stmt = $db->prepare("
                UPDATE region_shipping_rates 
                SET " . implode(", ", $fields) . "
                WHERE region_id = ? AND shipping_method_id = ?
            ");
            
            $stmt->execute($params);
            
            if ($stmt->rowCount() === 0) {
                send_error("Rate not found or no changes made", 404);
            }
            
            send_success("Shipping rate updated successfully");
            break;

        case 'DELETE':
            $regionId = $_GET['region_id'] ?? null;
            $methodId = $_GET['method_id'] ?? null;

            if (!$regionId || !$methodId) {
                send_error("Region ID and Method ID are required", 400);
            }

            // Check if rate is used in orders
            $checkStmt = $db->prepare("
                SELECT COUNT(*) FROM orders 
                WHERE shipping_region_id = ? AND shipping_method_id = ?
            ");
            $checkStmt->execute([$regionId, $methodId]);
            if ($checkStmt->fetchColumn() > 0) {
                // Instead of deleting, just deactivate
                $stmt = $db->prepare("
                    UPDATE region_shipping_rates 
                    SET is_active = 0 
                    WHERE region_id = ? AND shipping_method_id = ?
                ");
                $stmt->execute([$regionId, $methodId]);
                send_success("Shipping rate deactivated due to existing orders");
            } else {
                // Safe to delete if no orders exist
                $stmt = $db->prepare("
                    DELETE FROM region_shipping_rates 
                    WHERE region_id = ? AND shipping_method_id = ?
                ");
                $stmt->execute([$regionId, $methodId]);
                
                if ($stmt->rowCount() === 0) {
                    send_error("Rate not found", 404);
                }
                
                send_success("Shipping rate deleted successfully");
            }
            break;

        default:
            send_error("Method not allowed", 405);
    }
} catch (Exception $e) {
    error_log("Shipping Rates API Error: " . $e->getMessage());
    send_error("Server error: " . $e->getMessage(), 500);
}