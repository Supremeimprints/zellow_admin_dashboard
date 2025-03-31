<?php
try {
    // Get query parameters with proper type casting
    $include_products = isset($_GET['include_products']) && $_GET['include_products'] === 'true';
    $page = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
    $limit = max(1, isset($_GET['limit']) ? (int)$_GET['limit'] : 10);
    $offset = ($page - 1) * $limit;

    // Updated base category query to match your schema
    $query = "SELECT 
        c.category_id,
        c.category_name,
        COALESCE((SELECT COUNT(*) FROM products WHERE category_id = c.category_id AND active = 1), 0) as product_count
    FROM categories c
    WHERE c.category_name IS NOT NULL
    ORDER BY c.category_name ASC";

    $stmt = $db->query($query);
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fix the products query for each category
    if ($include_products) {
        foreach ($categories as &$category) {
            $productQuery = "SELECT 
                product_id,
                product_name,
                description,
                price,
                image_url,
                main_image,
                stock_quantity,
                is_active,
                created_at
            FROM products 
            WHERE category_id = :category_id 
            AND active = 1
            ORDER BY product_name ASC
            LIMIT :limit OFFSET :offset";

            $stmt = $db->prepare($productQuery);
            
            // Bind parameters with proper types
            $stmt->bindValue(':category_id', $category['category_id'], PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            $category['products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total products count with named parameter
            $countQuery = "SELECT COUNT(*) FROM products 
                          WHERE category_id = :category_id 
                          AND active = 1";
            $countStmt = $db->prepare($countQuery);
            $countStmt->bindValue(':category_id', $category['category_id'], PDO::PARAM_INT);
            $countStmt->execute();
            
            $category['total_products'] = (int)$countStmt->fetchColumn();
            $category['total_pages'] = ceil($category['total_products'] / $limit);
            $category['current_page'] = $page;
        }
    }

    send_success('Categories retrieved successfully', [
        'categories' => $categories,
        'meta' => [
            'include_products' => $include_products,
            'page' => $page,
            'limit' => $limit,
            'total_categories' => count($categories)
        ]
    ]);

} catch (Exception $e) {
    error_log("Category List Error: " . $e->getMessage());
    send_error('Error retrieving categories: ' . $e->getMessage(), 500);
}
