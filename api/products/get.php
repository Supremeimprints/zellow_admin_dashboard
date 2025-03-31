<?php
try {
    $product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if (!$product_id) {
        send_error('Product ID is required', 400);
    }

    $query = "SELECT 
        p.*,
        c.category_name,
        s.name as supplier_name,
        COALESCE(
            (SELECT JSON_ARRAYAGG(
                JSON_OBJECT(
                    'id', pi.id,
                    'url', pi.image_url,
                    'is_primary', pi.is_primary
                )
            )
            FROM product_images pi 
            WHERE pi.product_id = p.product_id
            ), '[]'
        ) as images
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN suppliers s ON p.supplier_id = s.id
    WHERE p.product_id = :product_id AND p.active = 1";

    $stmt = $db->prepare($query);
    $stmt->bindValue(':product_id', $product_id, PDO::PARAM_INT);
    $stmt->execute();
    
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        send_error('Product not found', 404);
    }

    send_success('Product retrieved successfully', [
        'product' => $product
    ]);

} catch (Exception $e) {
    error_log("Product Detail Error: " . $e->getMessage());
    send_error('Error retrieving product: ' . $e->getMessage(), 500);
}
