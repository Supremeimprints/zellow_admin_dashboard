<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/api_response.php';
require_once __DIR__ . '/../../includes/middleware/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions/shipping_functions.php';

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    // Public endpoint for listing active methods
    if ($method === 'GET') {
        $regionId = $_GET['region_id'] ?? null;
        $activeOnly = !isset($_GET['include_inactive']);
        
        if ($regionId) {
            // Get methods for specific region
            $methods = getRegionShippingMethods($db, $regionId);
        } else {
            // Get all shipping methods
            $methods = getShippingMethods($db, $activeOnly);
        }
        
        send_success("Shipping methods retrieved successfully", $methods);
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
            
            if (!isset($data['name'], $data['display_name'], $data['base_rate'])) {
                send_error("Name, display name, and base rate are required", 400);
            }

            $stmt = $db->prepare("
                INSERT INTO shipping_methods (
                    name, display_name, base_rate, per_item_fee,
                    free_shipping_threshold, estimated_days, is_active
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['name'],
                $data['display_name'],
                $data['base_rate'],
                $data['per_item_fee'] ?? 0.00,
                $data['free_shipping_threshold'] ?? null,
                $data['estimated_days'] ?? '3-5',
                $data['is_active'] ?? 1
            ]);
            
            send_success("Shipping method created successfully", [
                'id' => $db->lastInsertId(),
                'name' => $data['name']
            ]);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents("php://input"), true);
            
            if (!isset($data['id'])) {
                send_error("Method ID is required", 400);
            }

            $fields = [];
            $params = [];
            
            $updateableFields = [
                'name', 'display_name', 'base_rate', 'per_item_fee',
                'free_shipping_threshold', 'estimated_days', 'is_active'
            ];

            foreach ($updateableFields as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }
            
            if (empty($fields)) {
                send_error("No fields to update", 400);
            }

            $params[] = $data['id']; // Add ID for WHERE clause
            
            $stmt = $db->prepare("
                UPDATE shipping_methods 
                SET " . implode(", ", $fields) . "
                WHERE id = ?
            ");
            
            $stmt->execute($params);
            
            if ($stmt->rowCount() === 0) {
                send_error("Method not found or no changes made", 404);
            }
            
            send_success("Shipping method updated successfully");
            break;

        case 'DELETE':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                send_error("Method ID is required", 400);
            }

            // Check if method has associated orders
            $checkStmt = $db->prepare("
                SELECT COUNT(*) FROM orders 
                WHERE shipping_method_id = ?
            ");
            $checkStmt->execute([$id]);
            if ($checkStmt->fetchColumn() > 0) {
                send_error("Cannot delete method with associated orders", 400);
            }

            $stmt = $db->prepare("DELETE FROM shipping_methods WHERE id = ?");
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() === 0) {
                send_error("Method not found", 404);
            }
            
            send_success("Shipping method deleted successfully");
            break;

        default:
            send_error("Method not allowed", 405);
    }
} catch (Exception $e) {
    error_log("Shipping Methods API Error: " . $e->getMessage());
    send_error("Server error: " . $e->getMessage(), 500);
}