<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions/giftbox_functions.php';

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$itemId = $_GET['id'] ?? null;
$boxId = $_GET['box_id'] ?? null;

if ($itemId && $boxId) {
    if (removeGiftBoxItem($db, $itemId)) {
        $_SESSION['success'] = 'Item removed successfully';
    } else {
        $_SESSION['error'] = 'Failed to remove item';
    }
}

header("Location: giftbox_items.php?id=" . $boxId);
exit();
