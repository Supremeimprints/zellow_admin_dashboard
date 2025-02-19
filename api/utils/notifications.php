<?php

function createServiceNotification($db, $serviceRequestId, $userId) {
    try {
        $stmt = $db->prepare("
            INSERT INTO notifications (
                type,
                message,
                related_id,
                user_id,
                created_at,
                status
            ) VALUES (
                'service_request',
                'New service request submitted',
                ?,
                ?,
                NOW(),
                'unread'
            )
        ");
        
        return $stmt->execute([
            $serviceRequestId,
            $userId
        ]);
    } catch (Exception $e) {
        error_log('Error creating service notification: ' . $e->getMessage());
        return false;
    }
}
