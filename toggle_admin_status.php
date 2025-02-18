<?php
session_start();

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_id'])) {
    try {
        $adminId = (int)$_POST['admin_id'];
        $newStatus = (int)$_POST['status'];
        
        // Begin transaction
        $db->beginTransaction();

        // Update with explicit status value
        $stmt = $db->prepare("
            UPDATE users 
            SET 
                is_active = :status,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id 
            AND role IN ('admin', 'finance_manager', 'supply_manager', 'inventory_manager', 'dispatch_manager', 'service_manager')
            AND id != :current_user
        ");
        
        $params = [
            ':status' => $newStatus,
            ':id' => $adminId,
            ':current_user' => $_SESSION['id']
        ];
        
        $result = $stmt->execute($params);

        if (!$result) {
            throw new Exception("Failed to update status");
        }

        // Verify the update
        $verifyStmt = $db->prepare("SELECT is_active FROM users WHERE id = ?");
        $verifyStmt->execute([$adminId]);
        $updatedStatus = $verifyStmt->fetchColumn();

        $db->commit();
        
        echo json_encode([
            'success' => true,
            'newStatus' => (int)$updatedStatus,
            'message' => 'Status updated successfully'
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
