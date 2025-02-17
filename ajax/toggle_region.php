<?php
require_once '../config/database.php';
require_once '../includes/functions/shipping_functions.php';

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['region_id'])) {
        throw new Exception("Region ID is required");
    }

    $database = new Database();
    $db = $database->getConnection();

    // Start transaction
    $db->beginTransaction();

    // Get current status first
    $checkStmt = $db->prepare("SELECT id, is_active FROM shipping_regions WHERE id = ?");
    $checkStmt->execute([$data['region_id']]);
    $region = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$region) {
        throw new Exception("Region not found");
    }

    // Update the status
    $stmt = $db->prepare("
        UPDATE shipping_regions 
        SET is_active = ?, updated_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    
    // Toggle the status
    $newStatus = !$region['is_active'];
    $result = $stmt->execute([$newStatus, $data['region_id']]);

    if ($result) {
        $db->commit();
        echo json_encode([
            'success' => true,
            'is_active' => $newStatus
        ]);
    } else {
        throw new Exception("Failed to update region status");
    }

} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    error_log("Toggle region error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
