<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'create':
        $orderId = $_POST['order_id'] ?? null;
        $technicianId = $_POST['technician_id'] ?? null;
        
        try {
            $stmt = $db->prepare("
                INSERT INTO technician_assignments (order_id, technician_id, status)
                VALUES (?, ?, 'pending')
            ");
            $stmt->execute([$orderId, $technicianId]);
            
            echo json_encode(['success' => true, 'message' => 'Assignment created successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'update':
        $assignmentId = $_POST['assignment_id'] ?? null;
        $status = $_POST['status'] ?? null;
        
        try {
            $stmt = $db->prepare("
                UPDATE technician_assignments 
                SET status = ?
                WHERE assignment_id = ?
            ");
            $stmt->execute([$status, $assignmentId]);
            
            echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
