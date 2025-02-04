<?php
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . 'config/database.php';

$database = new Database();
$db = $database->getConnection();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = $_REQUEST['user']->id;

        // Add review
        $stmt = $db->prepare("
            INSERT INTO reviews (id, product_id, rating, comment) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $data['product_id'],
            $data['rating'],
            $data['comment']
        ]);
        
        ApiResponse::success(null, 'Review added successfully');
        break;

    case 'GET':
        if (isset($_GET['product_id'])) {
            // Get reviews for a product
            $stmt = $db->prepare("
                SELECT r.*, u.username 
                FROM reviews r
                JOIN users u ON r.id = u.id
                WHERE r.product_id = ?
            ");
            $stmt->execute([$_GET['product_id']]);
            $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ApiResponse::success($reviews);
        }
        break;
}
