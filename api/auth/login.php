<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/api_response.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    send_error("Invalid request method", 405);
}

try {
    // Log incoming request
    error_log("Login attempt - Email: " . ($data["email"] ?? 'not provided'));
    
    // Read JSON input
    $input = file_get_contents("php://input");
    error_log("Raw input: " . $input);
    
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON format: " . json_last_error_msg());
    }

    if (!isset($data["email"]) || !isset($data["password"])) {
        send_error("Email and password required", 400);
    }

    $database = new Database();
    $db = $database->getConnection();

    // Debug database connection
    if (!$db) {
        throw new Exception("Database connection failed");
    }

    // Simplified query for debugging
    $stmt = $db->prepare("SELECT 
        id, 
        username,
        email,
        password,
        role,
        status,
        is_active
    FROM users 
    WHERE email = ?");

    if (!$stmt) {
        throw new Exception("Query preparation failed: " . print_r($db->errorInfo(), true));
    }

    $stmt->execute([$data["email"]]);
    error_log("Query executed for email: " . $data["email"]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        send_error("User not found", 401);
    }

    // Log user data (excluding password)
    $logUser = $user;
    unset($logUser['password']);
    error_log("Found user: " . print_r($logUser, true));

    if (!password_verify($data["password"], $user["password"])) {
        send_error("Invalid password", 401);
    }

    // Check status and role
    if ($user["status"] !== 'active' || !$user["is_active"]) {
        send_error("Account is not active", 403);
    }

    // Check if api_token column exists
    $columnCheckStmt = $db->prepare("
        SELECT COUNT(*) 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = ? 
        AND TABLE_NAME = 'users' 
        AND COLUMN_NAME = 'api_token'
    ");
    $columnCheckStmt->execute([getenv('DB_NAME') ?: 'zellowdb']);
    $hasApiTokenColumn = (bool)$columnCheckStmt->fetchColumn();

    // Generate token
    $token = bin2hex(random_bytes(32));
    
    if ($hasApiTokenColumn) {
        // Update with token if column exists
        $updateStmt = $db->prepare("
            UPDATE users 
            SET updated_at = NOW(),
                api_token = ?,
                token_expiry = DATE_ADD(NOW(), INTERVAL 24 HOUR)
            WHERE id = ?
        ");
        $updateResult = $updateStmt->execute([$token, $user["id"]]);
    } else {
        // Skip token storage if column doesn't exist
        $updateStmt = $db->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?");
        $updateResult = $updateStmt->execute([$user["id"]]);
    }

    if (!$updateResult) {
        throw new Exception("Failed to update user token: " . print_r($updateStmt->errorInfo(), true));
    }

    // Success response
    $response = [
        "user" => [
            "id" => $user["id"],
            "username" => $user["username"],
            "email" => $user["email"],
            "role" => $user["role"],
            "token" => $token
        ]
    ];

    send_success("Login successful", $response);

} catch (Exception $e) {
    error_log("Login API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    send_error("Server error: " . $e->getMessage(), 500);
}

function get_role_permissions($role) {
    $permissions = [
        'admin' => [
            'can_manage_users' => true,
            'can_manage_inventory' => true,
            'can_manage_orders' => true,
            'can_manage_finances' => true,
            'can_manage_services' => true,
            'can_manage_reports' => true,
            'can_manage_settings' => true
        ],
        'finance_manager' => [
            'can_manage_finances' => true,
            'can_view_orders' => true,
            'can_manage_invoices' => true,
            'can_view_reports' => true
        ],
        'supply_manager' => [
            'can_manage_inventory' => true,
            'can_manage_suppliers' => true,
            'can_manage_purchases' => true,
            'can_view_reports' => true
        ],
        'inventory_manager' => [
            'can_manage_inventory' => true,
            'can_view_orders' => true,
            'can_manage_stock' => true,
            'can_view_reports' => true
        ],
        'dispatch_manager' => [
            'can_manage_deliveries' => true,
            'can_view_orders' => true,
            'can_manage_drivers' => true,
            'can_view_reports' => true
        ],
        'service_manager' => [
            'can_manage_services' => true,
            'can_manage_technicians' => true,
            'can_view_orders' => true,
            'can_view_reports' => true
        ]
    ];

    return $permissions[$role] ?? [];
}
