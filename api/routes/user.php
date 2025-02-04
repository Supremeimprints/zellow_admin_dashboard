<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../config/database.php';

$database = new Database();
$db = $database->getConnection();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $userId = $_REQUEST['user']->id;

        if (strpos($_SERVER['REQUEST_URI'], '/api/user/orders') !== false) {
            // Get user's orders
            $stmt = $db->prepare("
                SELECT o.*, 
                       GROUP_CONCAT(CONCAT(p.product_name, ' x', oi.quantity)) as items
                FROM orders o
                JOIN order_items oi ON o.order_id = oi.order_id
                JOIN products p ON oi.product_id = p.product_id
                WHERE o.id = ?
                GROUP BY o.order_id
                ORDER BY o.order_date DESC
            ");
            $stmt->execute([$userId]);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ApiResponse::success($orders);
        }

        if (strpos($_SERVER['REQUEST_URI'], '/api/user/profile') !== false) {
            $stmt = $db->prepare("SELECT id, username, email, created_at FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            
            ApiResponse::success($profile);
        }
        break;

    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = $_REQUEST['user']->id;

        if (strpos($_SERVER['REQUEST_URI'], '/api/user/profile') !== false) {
            // Update user profile
            $stmt = $db->prepare("
                UPDATE users 
                SET username = ?, email = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['username'],
                $data['email'],
                $userId
            ]);
            
            ApiResponse::success(null, 'Profile updated successfully');
        }
        break;
}
