<?php
$data = json_decode(file_get_contents("php://input"), true);
$user = authenticate();

if (!$user) {
    send_error("Authentication required", 401);
}

if (!isset($data['order_id'], $data['product_id'], $data['rating'], $data['comment'])) {
    send_error("Missing required fields: order_id, product_id, rating, and comment are required", 400);
}

try {
    // Verify order belongs to user and contains the product
    $orderStmt = $db->prepare("
        SELECT o.order_id 
        FROM orders o
        JOIN order_items oi ON o.order_id = oi.order_id
        WHERE o.order_id = ? 
        AND o.user_id = ?
        AND oi.product_id = ?
    ");
    $orderStmt->execute([$data['order_id'], $user->id, $data['product_id']]);
    
    if (!$orderStmt->fetch()) {
        send_error("Invalid order ID, product ID, or order doesn't belong to user", 403);
    }

    $stmt = $db->prepare("
        INSERT INTO feedback (
            user_id,
            order_id,
            product_id,
            rating,
            comment,
            created_at
        ) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");

    $stmt->execute([
        $user->id,
        $data['order_id'],
        $data['product_id'],
        $data['rating'],
        $data['comment']
    ]);

    send_success("Feedback submitted successfully", [
        'feedback_id' => $db->lastInsertId()
    ]);
} catch (Exception $e) {
    send_error("Failed to submit feedback", 500);
}
