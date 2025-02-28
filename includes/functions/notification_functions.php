<?php

/**
 * Create a new notification
 */
function createNotification($db, $params) {
    try {
        $stmt = $db->prepare("
            INSERT INTO notifications (
                recipient_id,
                sender_id,
                message,
                type,
                priority,
                link,
                created_at,
                is_read
            ) VALUES (
                :recipient_id,
                :sender_id,
                :message,
                :type,
                :priority,
                :link,
                CURRENT_TIMESTAMP,
                0
            )
        ");

        return $stmt->execute([
            ':recipient_id' => $params['recipient_id'] ?? null,
            ':sender_id' => $params['sender_id'] ?? null,
            ':message' => $params['message'],
            ':type' => $params['type'] ?? 'System',
            ':priority' => $params['priority'] ?? 'medium',
            ':link' => $params['link'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get notifications for a user
 */
function getUserNotifications($db, $userId, $limit = 10, $includeRead = false) {
    try {
        $query = "
            SELECT 
                n.*,
                s.username as sender_name,
                s.profile_image as sender_image
            FROM notifications n
            LEFT JOIN users s ON n.sender_id = s.id
            WHERE n.recipient_id = :user_id
            " . ($includeRead ? "" : "AND n.is_read = 0") . "
            ORDER BY n.created_at DESC 
            LIMIT :limit";

        $stmt = $db->prepare($query);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error fetching notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Mark notification as read
 */
function markNotificationRead($db, $notificationId, $userId) {
    try {
        $stmt = $db->prepare("
            UPDATE notifications 
            SET is_read = 1
            WHERE id = :notification_id 
            AND recipient_id = :user_id
        ");
        
        return $stmt->execute([
            ':notification_id' => $notificationId,
            ':user_id' => $userId
        ]);
    } catch (Exception $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Get unread notification count
 */
function getUnreadNotificationCount($db, $userId) {
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM notifications 
            WHERE recipient_id = :user_id 
            AND is_read = 0
        ");
        $stmt->execute([':user_id' => $userId]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Error counting unread notifications: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get notification priority class
 */
function getNotificationPriorityClass($priority) {
    return match($priority) {
        'high' => 'bg-danger',
        'medium' => 'bg-warning',
        'low' => 'bg-info',
        default => 'bg-secondary'
    };
}

/**
 * Format notification time
 */
function formatNotificationTime($datetime) {
    $now = new DateTime();
    $time = new DateTime($datetime);
    $interval = $now->diff($time);
    
    if ($interval->y > 0) return $interval->y . 'y ago';
    if ($interval->m > 0) return $interval->m . 'mo ago';
    if ($interval->d > 0) return $interval->d . 'd ago';
    if ($interval->h > 0) return $interval->h . 'h ago';
    if ($interval->i > 0) return $interval->i . 'm ago';
    return 'just now';
}

/**
 * Delete old notifications
 */
function cleanupOldNotifications($db, $daysOld = 30) {
    try {
        $stmt = $db->prepare("
            DELETE FROM notifications 
            WHERE created_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL :days DAY)
            AND is_read = 1
        ");
        return $stmt->execute([':days' => $daysOld]);
    } catch (Exception $e) {
        error_log("Error cleaning up notifications: " . $e->getMessage());
        return false;
    }
}
