<?php
try {
    $input = json_decode(file_get_contents("php://input"), true);
    
    // Validate required fields
    $required = ['product_name', 'price', 'category_id'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            send_error("Missing required field: {$field}", 400);
        }
    }

    $stmt = $db->prepare("
        INSERT INTO products (
            product_name,
            description,
            price,
            category_id,
            stock_quantity,
            image_url,
            main_image,
            supplier_id,
            min_stock_level,
            created_at,
            active
        ) VALUES (
            :name, :description, :price, :category_id, 
            :stock, :image, :main_image, :supplier_id,
            :min_stock, NOW(), 1
        )
    ");

    $stmt->execute([
        ':name' => $input['product_name'],
        ':description' => $input['description'] ?? null,
        ':price' => $input['price'],
        ':category_id' => $input['category_id'],
        ':stock' => $input['stock_quantity'] ?? 0,
        ':image' => $input['image_url'] ?? null,
        ':main_image' => $input['main_image'] ?? null,
        ':supplier_id' => $input['supplier_id'] ?? null,
        ':min_stock' => $input['min_stock_level'] ?? 0
    ]);

    $product_id = $db->lastInsertId();

    send_success('Product created successfully', [
        'product_id' => $product_id,
        'product_name' => $input['product_name']
    ]);

} catch (Exception $e) {
    error_log("Product Creation Error: " . $e->getMessage());
    send_error('Error creating product: ' . $e->getMessage(), 500);
}
