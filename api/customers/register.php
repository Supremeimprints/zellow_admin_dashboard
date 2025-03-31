<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/api_response.php';
require_once __DIR__ . '/../../includes/utils/validation.php';

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    send_error("Invalid request method", 405);
}

try {
    $input = json_decode(file_get_contents("php://input"), true);
    
    // Validate required fields
    $required_fields = ['username', 'email', 'password', 'phone', 'address'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            send_error("Missing required field: {$field}", 400);
        }
    }

    // Validate email format
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        send_error("Invalid email format", 400);
    }

    // Validate phone number (basic validation)
    if (!preg_match("/^[0-9]{10,15}$/", preg_replace("/[^0-9]/", "", $input['phone']))) {
        send_error("Invalid phone number format", 400);
    }

    // Password strength validation
    if (strlen($input['password']) < 8) {
        send_error("Password must be at least 8 characters long", 400);
    }

    $database = new Database();
    $db = $database->getConnection();

    // Check if email already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$input['email']]);
    if ($stmt->fetch()) {
        send_error("Email already registered", 409);
    }

    // Check if phone already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->execute([$input['phone']]);
    if ($stmt->fetch()) {
        send_error("Phone number already registered", 409);
    }

    // Begin transaction
    $db->beginTransaction();

    try {
        // Insert user record with additional fields
        $stmt = $db->prepare("
            INSERT INTO users (
                username, 
                email, 
                password, 
                phone, 
                role,
                status,
                is_active,
                theme,
                notification_enabled,
                created_at
            ) VALUES (?, ?, ?, ?, 'customer', 'active', 1, 'light', 1, NOW())
        ");

        $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);
        
        $stmt->execute([
            $input['username'],
            $input['email'],
            $hashedPassword,
            $input['phone']
        ]);
        
        $userId = $db->lastInsertId();

        // Insert customer details
        $stmt = $db->prepare("
            INSERT INTO customer_details (
                id,
                address,
                city,
                state,
                country,
                postal_code,
                phone_alternative,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $userId,
            $input['address'],
            $input['city'] ?? null,
            $input['state'] ?? null,
            $input['country'] ?? 'Kenya',
            $input['postal_code'] ?? null,
            $input['phone_alternative'] ?? null
        ]);

        // Commit transaction
        $db->commit();

        // Fetch complete customer data for response
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
            WHERE u.id = ?
        ");
        
        $stmt->execute([$userId]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        // Remove sensitive data
        unset($customer['password']);
        
        send_success("Registration successful", [
            "customer" => $customer
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Customer Registration Error: " . $e->getMessage());
    send_error("Registration failed: " . $e->getMessage(), 500);
}

function send_welcome_email($email, $username) {
    // Implementation for sending welcome email
    // This can be implemented later or through a queue system
    return true;
}
