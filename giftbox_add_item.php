<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions/giftbox_functions.php';

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: giftbox_manager.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    $itemData = [
        'gift_box_id' => $_POST['gift_box_id'],
        'product_id' => $_POST['product_id'],
        'quantity' => $_POST['quantity'],
        'price_override' => $_POST['price_override']
    ];

    if (addGiftBoxItem($db, $itemData)) {
        $_SESSION['success'] = 'Item added successfully';
    } else {
        $_SESSION['error'] = 'Failed to add item';
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}

header("Location: giftbox_items.php?id=" . $_POST['gift_box_id']);
exit();
