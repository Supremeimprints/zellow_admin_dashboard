<?php
session_start();
require_once 'config/database.php';
//require_once 'includes/auth_check.php'; // Ensure user is logged in

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: notifications.php");
    exit();
}

$db = (new Database())->getConnection();

try {
    // Update messages table with recipient check
    $stmt = $db->prepare("UPDATE messages 
                        SET is_read = 1 
                        WHERE id = ? 
                        AND (recipient_id = ? OR recipient_id IS NULL)");
    $stmt->execute([
        $_GET['id'],
        $_SESSION['id']
    ]);
    
    // Safe redirect back to previous page
    $referrer = $_SERVER['HTTP_REFERER'] ?? 'notifications.php';
    header("Location: " . filter_var($referrer, FILTER_VALIDATE_URL));
    exit();

} catch(PDOException $e) {
    error_log("Error marking message read: ".$e->getMessage());
    $_SESSION['error'] = "Failed to mark message as read";
    header("Location: notifications.php");
    exit();
}