<?php

/**
 * Assign a technician to a service request
 */
function assignTechnician($db, $serviceRequestId, $technicianId) {
    try {
        $stmt = $db->prepare("
            INSERT INTO technician_assignments (
                service_request_id,
                technician_id,
                assigned_at,
                status
            ) VALUES (?, ?, CURRENT_TIMESTAMP, 'pending')
        ");
        
        return $stmt->execute([$serviceRequestId, $technicianId]);
    } catch (Exception $e) {
        error_log("Error assigning technician: " . $e->getMessage());
        return false;
    }
}

/**
 * Send email using PHP mailer
 */
function sendEmail($to, $subject, $body) {
    try {
        require_once __DIR__ . '/../../vendor/autoload.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth = true;
        $mail->Username = $_ENV['SMTP_USERNAME'];
        $mail->Password = $_ENV['SMTP_PASSWORD'];
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $_ENV['SMTP_PORT'];

        $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;

        return $mail->send();
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Format date in a consistent way
 */
function formatDate($date, $format = 'Y-m-d H:i:s') {
    return date($format, strtotime($date));
}

/**
 * Format currency amounts
 */
function formatCurrency($amount, $currency = 'Ksh') {
    return $currency . ' ' . number_format($amount, 2);
}

/**
 * Sanitize output
 */
function sanitizeOutput($value) {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a unique reference number
 */
function generateReference($prefix = 'REF') {
    return $prefix . '-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
}

/**
 * Log system activity
 */
function logActivity($db, $userId, $action, $details) {
    try {
        $stmt = $db->prepare("
            INSERT INTO activity_logs (
                user_id,
                action,
                details,
                ip_address,
                created_at
            ) VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        
        return $stmt->execute([
            $userId,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR']
        ]);
    } catch (Exception $e) {
        error_log("Error logging activity: " . $e->getMessage());
        return false;
    }
}

/**
 * Validate dates
 */
function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Convert string to URL friendly slug
 */
function createSlug($string) {
    return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $string), '-'));
}

/**
 * Check if user has specific permission
 */
function hasPermission($db, $userId, $permission) {
    try {
        $stmt = $db->prepare("
            SELECT 1 FROM user_permissions up
            JOIN permissions p ON up.permission_id = p.id
            WHERE up.user_id = ? AND p.name = ?
        ");
        $stmt->execute([$userId, $permission]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        error_log("Permission check failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get configuration value
 */
function getConfig($key, $default = null) {
    static $config = null;
    
    if ($config === null) {
        $configFile = __DIR__ . '/../../config/app.php';
        $config = file_exists($configFile) ? require $configFile : [];
    }
    
    return $config[$key] ?? $default;
}

/**
 * Format file size
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < 4) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * Get service notification template
 */
function getServiceNotificationTemplate($details) {
    ob_start();
    include __DIR__ . '/../email_templates/service_notification.php';
    return ob_get_clean();
}
