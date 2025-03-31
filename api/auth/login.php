<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../utils/api_response.php';
use \Firebase\JWT\JWT;

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/api_errors.log');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    send_error("Invalid request method", 405);
}

try {
    // Read JSON input
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON format: " . json_last_error_msg());
    }

    if (!isset($data["email"]) || !isset($data["password"])) {
        send_error("Email and password required", 400);
    }

    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception("Database connection failed");
    }

    // Fetch user details
    $stmt = $db->prepare("SELECT 
        id, username, email, password, role, status, is_active, phone, address,
        employee_number, profile_photo, theme, notification_enabled, login_count, last_login
    FROM users 
    WHERE email = ?");

    $stmt->execute([$data["email"]]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        send_error("Invalid credentials", 401);
    }

    if (!password_verify($data["password"], $user["password"])) {
        send_error("Invalid password", 401);
    }

    if ($user["status"] !== 'active' || !$user["is_active"]) {
        send_error("Account is not active", 403);
    }

    // Generate JWT token
    $issuedAt = time();
    $expire = $issuedAt + ($_ENV['JWT_EXPIRY'] ?? 86400); // 24 hours

    $payload = [
        'iss' => $_ENV['JWT_ISSUER'] ?? 'zellow_admin',
        'aud' => $_ENV['JWT_AUDIENCE'] ?? 'zellow_app',
        'iat' => $issuedAt,
        'exp' => $expire,
        'sub' => $user["id"],
        'role' => $user["role"]
    ];

    $jwt = JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');

    // Update API token in users table
    $updateStmt = $db->prepare("
        UPDATE users 
        SET updated_at = NOW(), api_token = ?, token_expiry = FROM_UNIXTIME(?), last_login = NOW(), login_count = login_count + 1 
        WHERE id = ?
    ");
    $updateStmt->execute([$jwt, $expire, $user["id"]]);

    // Base response
    $response = [
        "user" => [
            "id" => $user["id"],
            "username" => $user["username"],
            "email" => $user["email"],
            "role" => $user["role"],
            "token" => $jwt,
            "phone" => $user["phone"] ?? '',
            "address" => $user["address"] ?? '',
            "employee_number" => $user["employee_number"] ?? '',
            "profile_photo" => $user["profile_photo"] ?? '',
            "theme" => $user["theme"] ?? 'light',
            "notification_enabled" => (bool)($user["notification_enabled"] ?? false),
            "status" => $user["status"],
            "login_count" => (int)($user["login_count"] ?? 0),
            "last_login" => $user["last_login"] ?? null,
            "permissions" => get_role_permissions($user["role"])
        ]
    ];

    // Fetch additional role-based details
    switch ($user["role"]) {
        case 'technician':
            $stmt = $db->prepare("SELECT specialization FROM technicians WHERE user_id = ?");
            break;
        case 'driver':
            $stmt = $db->prepare("SELECT vehicle_type, vehicle_status FROM drivers WHERE user_id = ?");
            break;
        case 'supplier':
            $stmt = $db->prepare("SELECT company_name, status FROM suppliers WHERE user_id = ?");
            break;
    }

    if (isset($stmt)) {
        $stmt->execute([$user["id"]]);
        $extraData = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($extraData) {
            $response["user"] = array_merge($response["user"], $extraData);
        }
    }

    send_success("Login successful", $response);

} catch (Exception $e) {
    error_log("Login API Error: " . $e->getMessage());
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
        ],
        'technician' => [
            'can_view_orders' => true,
            'can_manage_services' => true
        ],
        'driver' => [
            'can_view_orders' => true,
            'can_manage_deliveries' => true
        ],
        'supplier' => [
            'can_manage_inventory' => true,
            'can_view_orders' => true
        ]
    ];

    return $permissions[$role] ?? [];
}
