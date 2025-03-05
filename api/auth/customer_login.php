<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/api_response.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    send_error("Invalid request method", 405);
}

try {
    // Read JSON input
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data["email"]) || !isset($data["password"])) {
        send_error("Email and password required", 400);
    }

    $database = new Database();
    $db = $database->getConnection();

    // Verify customer credentials with updated fields
    $stmt = $db->prepare("SELECT 
            id,
            username,
            email,
            password,
            phone,
            address,
            created_at,
            role,
            is_active,
            profile_photo,
            theme,
            notification_enabled,
            status
        FROM users 
        WHERE email = ? 
        AND role = 'customer'
        AND status = 'active'
        AND is_active = 1");

    $stmt->execute([$data["email"]]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer || !password_verify($data["password"], $customer["password"])) {
        send_error("Invalid credentials", 401);
    }

    // Generate secure token
    $token = bin2hex(random_bytes(32));

    // Update last login time and token
    $stmt = $db->prepare("
        UPDATE users 
        SET updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$customer["id"]]);

    // Prepare customer data for response (excluding sensitive info)
    $customerData = [
        "customer" => [
            "id" => $customer["id"],
            "username" => $customer["username"],
            "email" => $customer["email"],
            "phone" => $customer["phone"],
            "address" => $customer["address"],
            "created_at" => $customer["created_at"],
            "profile_photo" => $customer["profile_photo"],
            "theme" => $customer["theme"],
            "notification_enabled" => (bool)$customer["notification_enabled"],
            "token" => $token
        ]
    ];

    send_success("Login successful", $customerData);

} catch (Exception $e) {
    error_log("Customer Login API Error: " . $e->getMessage());
    send_error("Server error occurred", 500);
}
