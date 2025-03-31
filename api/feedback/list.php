<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/api_response.php';

// Create database connection
$database = new Database();
$db = $database->getConnection();

// No authentication required for viewing feedback
$order_id = $_GET['order_id'] ?? null;
$product_id = $_GET['product_id'] ?? null;
$page = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$limit = max(1, min(50, isset($_GET['limit']) ? (int)$_GET['limit'] : 10));
$offset = ($page - 1) * $limit;

// Build query with placeholders
$query = "
    SELECT 
        f.id,
        f.user_id,
        f.order_id,
        f.rating,
        f.comment,
        f.created_at,
        f.product_id,
        f.admin_reply,
        f.replied_by,
        f.replied_at,
        u.username,
        p.product_name,
        p.main_image as product_image,
        admin.username as replied_by_username
    FROM feedback f
    JOIN users u ON f.user_id = u.id
    LEFT JOIN products p ON f.product_id = p.product_id
    LEFT JOIN users admin ON f.replied_by = admin.id
    WHERE 1=1
";

$params = [];

if ($order_id) {
    $query .= " AND f.order_id = :order_id";
    $params[':order_id'] = $order_id;
}

if ($product_id) {
    $query .= " AND f.product_id = :product_id";
    $params[':product_id'] = $product_id;
}

$query .= " ORDER BY f.created_at DESC LIMIT :limit OFFSET :offset";

// Prepare and execute with named parameters
$stmt = $db->prepare($query);

// Bind pagination parameters explicitly with correct types
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

// Bind other parameters if they exist
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->execute();
$feedback = $stmt->fetchAll(PDO::FETCH_ASSOC);

$feedback = array_map(function($item) {
    return [
        'id' => $item['id'],
        'order_id' => $item['order_id'],
        'product' => $item['product_id'] ? [
            'id' => $item['product_id'],
            'name' => $item['product_name'],
            'image' => $item['product_image']
        ] : null,
        'rating' => $item['rating'],
        'comment' => $item['comment'],
        'user' => [
            'id' => $item['user_id'],
            'username' => $item['username']
        ],
        'admin_reply' => $item['admin_reply'] ? [
            'text' => $item['admin_reply'],
            'by' => $item['replied_by_username'],
            'at' => $item['replied_at']
        ] : null,
        'created_at' => $item['created_at']
    ];
}, $feedback);

// Get total count
$countQuery = str_replace(['SELECT f.*,', 'LIMIT :limit OFFSET :offset'], ['SELECT COUNT(*)', ''], $query);
$countStmt = $db->prepare($countQuery);
array_pop($params); // Remove offset
array_pop($params); // Remove limit
$countStmt->execute($params);
$total = $countStmt->fetchColumn();

send_success("Feedback retrieved successfully", [
    'feedback' => $feedback,
    'meta' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'total_pages' => ceil($total / $limit)
    ]
]);
