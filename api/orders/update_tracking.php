<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/api_response.php';
require_once __DIR__ . '/../../includes/middleware/auth_middleware.php';

$user = authorize('admin');

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['order_id'], $data['status'])) {
    send_error("Order ID and status are required", 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Validate status
    $validStatuses = ['processing', 'confirmed', 'preparing', 'ready_for_delivery', 'out_for_delivery', 'delivered', 'cancelled'];
    if (!in_array($data['status'], $validStatuses)) {
        send_error("Invalid status", 400);
    }

    // Start transaction
    $db->beginTransaction();

    // Insert tracking update
    $stmt = $db->prepare("
        INSERT INTO order_tracking (
            order_id, 
            status, 
            notes, 
            location, 
            updated_by
        ) VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $data['order_id'],
        $data['status'],
        $data['notes'] ?? null,
        $data['location'] ?? null,
        $user->id
    ]);

    // Update order status
    $orderStmt = $db->prepare("
        UPDATE orders 
        SET status = ?, 
            updated_at = CURRENT_TIMESTAMP 
        WHERE order_id = ?
    ");

    $orderStmt->execute([$data['status'], $data['order_id']]);

    $db->commit();

    send_success("Order tracking updated successfully");

} catch (Exception $e) {
    if ($db) $db->rollBack();
    error_log("Order Tracking Update Error: " . $e->getMessage());
    send_error("Failed to update tracking", 500);
}
