<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

// Check if user has admin access
if ($_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Access denied. You do not have permission to perform this action.";
    header('Location: admins.php');
    exit();
}

// Initialize Database connection
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Validate admin ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid admin ID.";
    header('Location: admins.php');
    exit();
}

$id = (int)$_GET['id'];

try {
    // Update messages and notifications first
    $queries = [
        "UPDATE messages SET sender_id = NULL WHERE sender_id = ?",
        "UPDATE messages SET recipient_id = NULL WHERE recipient_id = ?",
        "UPDATE notifications SET sender_id = NULL WHERE sender_id = ?",
        "UPDATE notifications SET recipient_id = NULL WHERE recipient_id = ?",
        "DELETE FROM users WHERE id = ?"
    ];

    foreach ($queries as $query) {
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
    }

    $_SESSION['success'] = "Admin deleted successfully.";
} catch (PDOException $e) {
    $_SESSION['error'] = "Error deleting admin: " . $e->getMessage();
}

header("Location: admins.php");
exit();