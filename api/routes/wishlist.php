<?php
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = $_REQUEST['user']->id;

        // Add to wishlist
        $stmt = $db->prepare("
            INSERT INTO wishlist (user_id, product_id) 
            VALUES (?, ?)
        ");
        $stmt->execute([$userId, $data['product_id']]);
        
        ApiResponse::success(null, 'Added to wishlist');
        break;

    case 'GET':
        $userId = $_REQUEST['user']->id;

        // Get wishlist items
        $stmt = $db->prepare("
            SELECT w.*, p.product_name, p.price, p.main_image 
            FROM wishlist w
            JOIN products p ON w.product_id = p.product_id
            WHERE w.user_id = ?
        ");
        $stmt->execute([$userId]);
        $wishlistItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        ApiResponse::success($wishlistItems);
        break;

    case 'DELETE':
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = $_REQUEST['user']->id;

        // Remove from wishlist
        $stmt = $db->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$userId, $data['product_id']]);
        
        ApiResponse::success(null, 'Removed from wishlist');
        break;
}
