<?php
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (isset($_GET['id'])) {
            // Get single product
            $stmt = $db->prepare("
                SELECT p.*, c.category_name, i.stock_quantity 
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.category_id
                LEFT JOIN inventory i ON p.product_id = i.product_id
                WHERE p.product_id = ? AND p.is_active = 1
            ");
            $stmt->execute([$_GET['id']]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            ApiResponse::success($product);
        } else {
            // List products with filters
            $query = "SELECT p.*, c.category_name, i.stock_quantity 
                     FROM products p
                     LEFT JOIN categories c ON p.category_id = c.category_id
                     LEFT JOIN inventory i ON p.product_id = i.product_id
                     WHERE p.is_active = 1";
            
            // Apply filters
            if (isset($_GET['category'])) {
                $query .= " AND c.category_id = ?";
                $params[] = $_GET['category'];
            }
            if (isset($_GET['search'])) {
                $query .= " AND (p.product_name LIKE ? OR p.description LIKE ?)";
                $search = "%{$_GET['search']}%";
                $params[] = $search;
                $params[] = $search;
            }
            
            $stmt = $db->prepare($query);
            $stmt->execute($params ?? []);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            ApiResponse::success($products);
        }
        break;
}
