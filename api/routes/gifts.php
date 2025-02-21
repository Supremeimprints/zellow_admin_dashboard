<?php
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (isset($_GET['id'])) {
            // Get single gift product with customization options
            $stmt = $db->prepare("
                SELECT p.*, gc.name as category_name,
                       GROUP_CONCAT(DISTINCT o.name) as occasions,
                       (
                           SELECT JSON_ARRAYAGG(
                               JSON_OBJECT(
                                   'id', co.id,
                                   'name', co.name,
                                   'type', co.type,
                                   'description', co.description,
                                   'additional_cost', co.additional_cost,
                                   'allowed_values', co.allowed_values
                               )
                           )
                           FROM product_customization_options pco
                           JOIN customization_options co ON pco.option_id = co.id
                           WHERE pco.product_id = p.product_id
                       ) as customization_options
                FROM products p
                LEFT JOIN gift_categories gc ON p.gift_category_id = gc.id
                LEFT JOIN product_occasions po ON p.product_id = po.product_id
                LEFT JOIN occasions o ON po.occasion_id = o.id
                WHERE p.product_id = ? AND p.is_gift = 1
                GROUP BY p.product_id
            ");
            
            $stmt->execute([$_GET['id']]);
            $gift = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($gift) {
                // Parse JSON fields
                $gift['customization_options'] = json_decode($gift['customization_options'], true);
                ApiResponse::success($gift);
            } else {
                ApiResponse::error("Gift not found", 404);
            }
        } else {
            // List all gift products
            $stmt = $db->prepare("
                SELECT p.*, gc.name as category_name
                FROM products p
                LEFT JOIN gift_categories gc ON p.gift_category_id = gc.id
                WHERE p.is_gift = 1
                ORDER BY p.created_at DESC
            ");
            
            $stmt->execute();
            ApiResponse::success($stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        break;

    // ... POST, PUT, DELETE methods for admin operations
}
