<?php
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/notifications.php';

$database = new Database();
$db = $database->getConnection();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        $userId = $_REQUEST['user']->id;

        try {
            $db->beginTransaction();

            // Validate required fields
            if (!isset($data['service_id'])) {
                throw new Exception("service_id is required");
            }

            // Validate service exists
            $checkService = $db->prepare("SELECT id FROM services WHERE id = ? AND status = 'active'");
            $checkService->execute([$data['service_id']]);
            if (!$checkService->fetch()) {
                throw new Exception("Invalid or inactive service");
            }

            // Insert service request with correct fields
            $stmt = $db->prepare("
                INSERT INTO service_requests (
                    id,
                    service_id,
                    status,
                    request_date,
                    created_at
                ) VALUES (?, ?, 'pending', NOW(), NOW())
            ");
            
            $stmt->execute([
                $userId,
                $data['service_id']
            ]);
            
            $requestId = $db->lastInsertId();

            // Create notification for admin dashboard
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
                    'Task',
                    'medium',
                    NOW()
                )
            ");
            
            $notifStmt->execute([
                $userId,
                "New service request received #$requestId"
            ]);

            $db->commit();
            ApiResponse::success(['request_id' => $requestId]);
            
        } catch (Exception $e) {
            $db->rollBack();
            ApiResponse::error('Error creating service request: ' . $e->getMessage());
        }
        break;

    case 'GET':
        // For customers - get their requests
        if (isset($_REQUEST['user'])) {
            $stmt = $db->prepare("
                SELECT sr.*, s.name as service_name, s.price
                FROM service_requests sr
                JOIN services s ON sr.service_id = s.id
                WHERE sr.id = ?
                ORDER BY sr.request_date DESC
            ");
            $stmt->execute([$_REQUEST['user']->id]);
            
            ApiResponse::success($stmt->fetchAll(PDO::FETCH_ASSOC));
        }
        break;
}
