<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../config/database.php';

try {
    $input = json_decode(file_get_contents("php://input"), true);
    
    // Validate input
    if (!isset($input['name']) || empty(trim($input['name']))) {
        send_error('Category name is required', 400);
    }

    $stmt = $db->prepare("
        INSERT INTO categories (
            category_name
        ) VALUES (?)
    ");

    $stmt->execute([$input['name']]);

    $categoryId = $db->lastInsertId();

    send_success('Category created successfully', [
        'category_id' => $categoryId,
        'category_name' => $input['name']
    ]);

} catch (Exception $e) {
    send_error('Error creating category: ' . $e->getMessage(), 500);
}
