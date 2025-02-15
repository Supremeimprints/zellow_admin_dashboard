<?php
session_start();
require_once 'config/database.php';

// Check if user is logged in as admin
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    // Validate inputs
    if (!isset($_POST['id']) || !isset($_POST['status'])) {
        throw new Exception('Missing required parameters');
    }

    $id = (int)$_POST['id'];
    $status = $_POST['status'];

    // Validate status value
    if (!in_array($status, ['active', 'inactive'])) {
        throw new Exception('Invalid status value');
    }

    // Update service status
    $stmt = $db->prepare("UPDATE services SET status = ? WHERE id = ?");
    $result = $stmt->execute([$status, $id]);

    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to update service status');
    }

} catch (Exception $e) {
    error_log("Error updating service status: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
