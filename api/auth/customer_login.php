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
    $input = json_decode(file_get_contents("php://input"), true);

    if (!isset($input["email"]) || !isset($input["password"])) {
        send_error("Email and password required", 400);
    }

    $database = new Database();
    $db = $database->getConnection();

    // Join users and customer_details tables
    $stmt = $db->prepare("
        SELECT 
            u.*,
            cd.address,
            cd.city,
            cd.state,
            cd.country,
            cd.postal_code,
            cd.phone_alternative
        FROM users u
        LEFT JOIN customer_details cd ON u.id = cd.id
        WHERE u.email = ? 
        AND u.role = 'customer'
        AND u.status = 'active'
        AND u.is_active = 1
    ");

    $stmt->execute([$input["email"]]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$customer || !password_verify($input["password"], $customer["password"])) {
        send_error("Invalid credentials", 401);
    }

    // Generate token
    $token = bin2hex(random_bytes(32));

    // Update last login and token
    $updateStmt = $db->prepare("
        UPDATE users 
        SET last_login = NOW(),
            login_count = login_count + 1,
            api_token = ?,
            token_expiry = DATE_ADD(NOW(), INTERVAL 24 HOUR)
        WHERE id = ?
    ");

    $updateStmt->execute([$token, $customer["id"]]);

    // Prepare customer data for response
    $customerData = [
        "id" => $customer["id"],
        "username" => $customer["username"],
        "email" => $customer["email"],
        "phone" => $customer["phone"],
        "phone_alternative" => $customer["phone_alternative"],
        "address" => $customer["address"],
        "city" => $customer["city"],
        "state" => $customer["state"],
        "country" => $customer["country"],
        "postal_code" => $customer["postal_code"],
        "created_at" => $customer["created_at"],
        "profile_photo" => $customer["profile_photo"],
        "theme" => $customer["theme"],
        "notification_enabled" => (bool)$customer["notification_enabled"],
        "token" => $token
    ];

    send_success("Login successful", ["customer" => $customerData]);

} catch (Exception $e) {
    error_log("Customer Login Error: " . $e->getMessage());
    send_error("Server error: " . $e->getMessage(), 500);
}
