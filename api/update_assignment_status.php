<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents('php://input'), true);

try {
    $stmt = $db->prepare("UPDATE technician_assignments SET status = ? WHERE assignment_id = ?");
    $success = $stmt->execute([$data['status'], $data['assignment_id']]);
    
    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
