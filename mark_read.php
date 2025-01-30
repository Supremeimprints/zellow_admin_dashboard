<?php
require_once 'config/database.php';

if (!isset($_GET['id'])) {
    header("Location: notifications.php");
    exit();
}

$db = (new Database())->getConnection();

try {
    $stmt = $db->prepare("UPDATE notifications 
                        SET is_read = 1 
                        WHERE id = ? AND sender_id = ?");
    $stmt->execute([$_GET['id'], $_SESSION['id']]);
    
    header("Location: ".$_SERVER['HTTP_REFERER']);
    exit();

} catch(PDOException $e) {
    error_log("Error marking notification read: ".$e->getMessage());
    header("Location: index.php");
    exit();
}