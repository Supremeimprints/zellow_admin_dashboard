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
    $attributeData = [
        'gift_box_id' => $_POST['gift_box_id'],
        'name' => $_POST['name'],
        'input_type' => $_POST['input_type'],
        'is_required' => isset($_POST['is_required']),
        'options' => isset($_POST['options']) ? json_decode($_POST['options'], true) : []
    ];

    if (addGiftBoxAttribute($db, $attributeData)) {
        $_SESSION['success'] = 'Attribute added successfully';
    } else {
        $_SESSION['error'] = 'Failed to add attribute';
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}

header("Location: giftbox_attributes.php?id=" . $_POST['gift_box_id']);
exit();
