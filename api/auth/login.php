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
    // Read JSON input from Flutter
    $data = json_decode(file_get_contents("php://input"), true);

    if (!isset($data["email"]) || !isset($data["password"])) {
        send_error("Email and password required", 400);
    }

    $database = new Database();
    $db = $database->getConnection();

    $stmt = $db->prepare("SELECT id, username, password, role, 
                         COALESCE(status, 'active') as status 
                         FROM users 
                         WHERE email = ? 
                         AND (status = 'active' OR status IS NULL)");
    $stmt->execute([$data["email"]]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($data["password"], $user["password"])) {
        send_error("Invalid credentials", 401);
    }

    // Generate secure token
    $token = bin2hex(random_bytes(32));

    // Prepare user data for response
    $userData = [
        "user" => [
            "id" => $user["id"],
            "username" => $user["username"],
            "role" => $user["role"],
            "token" => $token
        ]
    ];

    // Store token in database (optional but recommended)
    $stmt = $db->prepare("UPDATE users SET api_token = ?, token_expiry = DATE_ADD(NOW(), INTERVAL 24 HOUR) WHERE id = ?");
    $stmt->execute([$token, $user["id"]]);

    send_success("Login successful", $userData);

} catch (Exception $e) {
    error_log("Login API Error: " . $e->getMessage());
    send_error("Server error occurred", 500);
}
