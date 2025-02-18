<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

if (isset($_POST['supplier_id'])) {
    try {
        $db->beginTransaction();
        
        // Soft delete supplier
        $stmt = $db->prepare("
            UPDATE suppliers 
            SET 
                is_active = 0,
                status = 'Inactive',
                deactivated_at = CURRENT_TIMESTAMP,
                deactivated_by = ?
            WHERE supplier_id = ?
        ");

        $result = $stmt->execute([$_SESSION['id'], $_POST['supplier_id']]);

        if ($result) {
            $db->commit();
            echo json_encode([
                'success' => true, 
                'message' => 'Supplier deactivated successfully'
            ]);
        } else {
            throw new Exception("Failed to deactivate supplier");
        }

    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid supplier ID'
    ]);
}
