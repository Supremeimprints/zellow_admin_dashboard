<?php
try {
    // Get query parameters with validation
    $page = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
    $limit = max(1, min(50, isset($_GET['limit']) ? (int)$_GET['limit'] : 10));
    $offset = ($page - 1) * $limit;
    
    $category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
    $search = isset($_GET['search']) ? trim($_GET['search']) : null;
    $sort = isset($_GET['sort']) ? strtolower($_GET['sort']) : 'name';
    $order = isset($_GET['order']) && strtoupper($_GET['order']) === 'DESC' ? 'DESC' : 'ASC';

    // Build base query with proper LIMIT/OFFSET syntax
    $query = "SELECT 
        p.product_id,
        p.product_name,
        p.description,
        p.price,
        COALESCE(i.stock_quantity, 0) as stock_quantity,
        p.is_active,
        c.category_id,
        c.category_name,
        CASE 
            WHEN p.main_image LIKE 'uploads/products/%' THEN p.main_image
            WHEN p.main_image LIKE 'uploads/%' THEN CONCAT('uploads/products/', SUBSTRING(p.main_image, 8))
            ELSE CONCAT('uploads/products/', p.main_image)
        END as main_image,
        CASE 
            WHEN p.variant_image_1 LIKE 'uploads/products/%' THEN p.variant_image_1
            WHEN p.variant_image_1 LIKE 'uploads/%' THEN CONCAT('uploads/products/', SUBSTRING(p.variant_image_1, 8))
            WHEN p.variant_image_1 IS NOT NULL THEN CONCAT('uploads/products/', p.variant_image_1)
            ELSE NULL
        END as variant_image_1,
        CASE 
            WHEN p.variant_image_2 LIKE 'uploads/products/%' THEN p.variant_image_2
            WHEN p.variant_image_2 LIKE 'uploads/%' THEN CONCAT('uploads/products/', SUBSTRING(p.variant_image_2, 8))
            WHEN p.variant_image_2 IS NOT NULL THEN CONCAT('uploads/products/', p.variant_image_2)
            ELSE NULL
        END as variant_image_2
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN inventory i ON p.product_id = i.product_id
    WHERE 1=1";
    
    $queryParams = [];

    // Add filters
    if ($category_id) {
        $query .= " AND p.category_id = :category_id";
        $queryParams[':category_id'] = $category_id;
    }

    if ($search) {
        $query .= " AND (p.product_name LIKE :search OR p.description LIKE :search)";
        $queryParams[':search'] = "%{$search}%";
    }

    // Add sorting - using validated column names
    $sortColumn = in_array($sort, ['price', 'product_name', 'stock_quantity']) ? $sort : 'product_name';
    $query .= " ORDER BY p.{$sortColumn} {$order}";
    
    // Get total count before adding LIMIT
    $countStmt = $db->prepare(str_replace(['SELECT p.product_id,', 'ORDER BY'], ['SELECT COUNT(*) as total', 'GROUP BY'], $query));
    foreach ($queryParams as $key => $val) {
        $countStmt->bindValue($key, $val);
    }
    $countStmt->execute();
    $total_products = (int)$countStmt->fetchColumn();

    // Add pagination to main query
    $query .= " LIMIT :limit OFFSET :offset";
    
    // Prepare and execute main query
    $stmt = $db->prepare($query);
    
    // Bind all parameters including pagination
    foreach ($queryParams as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $products = array_map(function($product) {
        // Convert image paths to full URLs
        $baseUrl = rtrim(BASE_URL, '/');
        foreach (['main_image', 'variant_image_1', 'variant_image_2'] as $imageField) {
            if (!empty($product[$imageField])) {
                $product[$imageField] = $baseUrl . '/products/image?path=' . urlencode($product[$imageField]);
            }
        }
        return $product;
    }, $products);

    send_success('Products retrieved successfully', [
        'products' => $products,
        'meta' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total_products,
            'total_pages' => ceil($total_products / $limit),
            'category_id' => $category_id,
            'search' => $search,
            'sort' => $sort,
            'order' => $order
        ]
    ]);

} catch (Exception $e) {
    error_log("Product List Error: " . $e->getMessage());
    send_error('Error retrieving products: ' . $e->getMessage(), 500);
}
