<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/api_response.php';
require_once __DIR__ . '/../../includes/middleware/auth_middleware.php';

$database = new Database();
$db = $database->getConnection();

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $headers = getallheaders();
    $token = str_replace('Bearer ', '', $headers['Authorization'] ?? $headers['authorization'] ?? '');
    
    if (!$token) {
        send_error('No token provided', 401);
    }

    // Check admin token
    $stmt = $db->prepare("
        SELECT id, role FROM users 
        WHERE api_token = ? AND role = 'admin' AND is_active = 1
    ");
    $stmt->execute([$token]);
    $admin = $stmt->fetch(PDO::FETCH_OBJ);

    if (!$admin || $admin->role !== 'admin') {
        send_error("Admin access required", 403);
    }

    $data = json_decode(file_get_contents("php://input"), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        send_error("Invalid JSON input", 400);
    }

    if (!isset($data['feedback_id'], $data['reply'])) {
        send_error("Missing required fields", 400);
    }

    // Check if feedback exists
    $checkStmt = $db->prepare("SELECT id FROM feedback WHERE id = ?");
    $checkStmt->execute([$data['feedback_id']]);
    if (!$checkStmt->fetch()) {
        send_error("Feedback not found", 404);
    }

    // Update feedback with reply
    $stmt = $db->prepare("
        UPDATE feedback 
        SET 
            admin_reply = ?,
            replied_by = ?,
            replied_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");

    $params = [
        $data['reply'],
        $admin->id,
        $data['feedback_id']
    ];
    error_log("Query params: " . print_r($params, true));

    $success = $stmt->execute($params);
    error_log("Query execution result: " . ($success ? 'success' : 'failed'));
    
    if (!$success) {
        error_log("Database error: " . print_r($stmt->errorInfo(), true));
        send_error("Failed to update feedback", 500);
    }

    // Verify the update
    $verifyStmt = $db->prepare("SELECT admin_reply FROM feedback WHERE id = ?");
    $verifyStmt->execute([$data['feedback_id']]);
    $result = $verifyStmt->fetch(PDO::FETCH_ASSOC);

    if ($result && $result['admin_reply'] === $data['reply']) {
        send_success("Reply added successfully");
    } else {
        send_error("Failed to verify reply update", 500);
    }

} catch (Exception $e) {
    error_log("Reply Error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    send_error("Server error: " . $e->getMessage(), 500);
}
