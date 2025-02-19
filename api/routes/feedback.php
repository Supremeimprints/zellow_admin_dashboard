<?php
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../../config/database.php';

$database = new Database();
$db = $database->getConnection();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = $_REQUEST['user']->id;

        try {
            $db->beginTransaction();

            // Validate order_id exists if provided
            if (!empty($data['order_id'])) {
                $checkOrder = $db->prepare("SELECT order_id FROM orders WHERE order_id = ?");
                $checkOrder->execute([$data['order_id']]);
                if (!$checkOrder->fetch()) {
                    throw new Exception("Invalid order_id provided");
                }
                $orderId = $data['order_id'];
            } else {
                throw new Exception("order_id is required");
            }

            // Insert feedback with validated order_id
            $stmt = $db->prepare("
                INSERT INTO feedback (
                    user_id,
                    order_id,
                    rating,
                    comment,
                    created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $userId,
                $orderId,
                $data['rating'],
                $data['comment'] ?? null
            ]);

            $feedbackId = $db->lastInsertId();

            // Create notification for admin
            $notifStmt = $db->prepare("
                INSERT INTO notifications (
                    recipient_id,
                    sender_id,
                    message,
                    type,
                    priority,
                    created_at
                ) VALUES (
                    1,
                    ?,
                    ?,
                    'Message',
                    'medium',
                    NOW()
                )
            ");
            
            $notifStmt->execute([
                $userId,
                "New feedback received for Order #$orderId (Rating: {$data['rating']})"
            ]);

            $db->commit();
            ApiResponse::success(['feedback_id' => $feedbackId]);
            
        } catch (Exception $e) {
            $db->rollBack();
            ApiResponse::error('Error submitting feedback: ' . $e->getMessage());
        }
        break;
}
