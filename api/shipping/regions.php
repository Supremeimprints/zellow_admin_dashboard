<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/api_response.php';
require_once __DIR__ . '/../../includes/middleware/auth_middleware.php';
require_once __DIR__ . '/../../includes/functions/shipping_functions.php';

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    // Public endpoint for listing active regions
    if ($method === 'GET') {
        $activeOnly = !isset($_GET['include_inactive']);
        $regions = getRegions($db, $activeOnly);
        
        foreach ($regions as &$region) {
            // Get associated shipping methods for each region
            $methods = getRegionShippingMethods($db, $region['id']);
            $region['shipping_methods'] = $methods;
        }
        
        send_success("Regions retrieved successfully", $regions);
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
            
            if (!isset($data['name']) || empty($data['name'])) {
                send_error("Region name is required", 400);
            }

            $stmt = $db->prepare("
                INSERT INTO shipping_regions (name, description, zone_regions, is_active) 
                VALUES (?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $data['zone_regions'] ?? null,
                $data['is_active'] ?? 1
            ]);
            
            send_success("Region created successfully", [
                'id' => $db->lastInsertId(),
                'name' => $data['name']
            ]);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents("php://input"), true);
            
            if (!isset($data['id'])) {
                send_error("Region ID is required", 400);
            }

            $fields = [];
            $params = [];
            
            if (isset($data['name'])) {
                $fields[] = "name = ?";
                $params[] = $data['name'];
            }
            if (isset($data['description'])) {
                $fields[] = "description = ?";
                $params[] = $data['description'];
            }
            if (isset($data['zone_regions'])) {
                $fields[] = "zone_regions = ?";
                $params[] = $data['zone_regions'];
            }
            if (isset($data['is_active'])) {
                $fields[] = "is_active = ?";
                $params[] = $data['is_active'];
            }
            
            if (empty($fields)) {
                send_error("No fields to update", 400);
            }

            $params[] = $data['id']; // Add ID for WHERE clause
            
            $stmt = $db->prepare("
                UPDATE shipping_regions 
                SET " . implode(", ", $fields) . "
                WHERE id = ?
            ");
            
            $stmt->execute($params);
            
            if ($stmt->rowCount() === 0) {
                send_error("Region not found or no changes made", 404);
            }
            
            send_success("Region updated successfully");
            break;

        case 'DELETE':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                send_error("Region ID is required", 400);
            }

            // Check if region has associated orders
            $checkStmt = $db->prepare("
                SELECT COUNT(*) FROM orders 
                WHERE shipping_region_id = ?
            ");
            $checkStmt->execute([$id]);
            if ($checkStmt->fetchColumn() > 0) {
                send_error("Cannot delete region with associated orders", 400);
            }

            $stmt = $db->prepare("DELETE FROM shipping_regions WHERE id = ?");
            $stmt->execute([$id]);
            
            if ($stmt->rowCount() === 0) {
                send_error("Region not found", 404);
            }
            
            send_success("Region deleted successfully");
            break;

        default:
            send_error("Method not allowed", 405);
    }
} catch (Exception $e) {
    error_log("Shipping Regions API Error: " . $e->getMessage());
    send_error("Server error: " . $e->getMessage(), 500);
}