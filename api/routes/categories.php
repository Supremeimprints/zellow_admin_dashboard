<?php
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // Fetch all categories
        $stmt = $db->prepare("SELECT * FROM categories");
        $stmt->execute();
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        ApiResponse::success($categories);
        break;

    case 'POST':
        // Add a new category
        $data = json_decode(file_get_contents('php://input'), true);
        $stmt = $db->prepare("INSERT INTO categories (name) VALUES (?)");
        $stmt->execute([$data['name']]);
        
        ApiResponse::success(null, 'Category added successfully');
        break;

    // ...other methods (PUT, DELETE) if needed...
}
