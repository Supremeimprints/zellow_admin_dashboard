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
    // Validate inputs
    if (empty($_POST['name']) || empty($_POST['base_price']) || empty($_FILES['image'])) {
        throw new Exception('Please fill in all required fields');
    }

    // Handle file upload
    $uploadResult = handleGiftBoxImageUpload($_FILES['image']);
    if (!$uploadResult['success']) {
        throw new Exception($uploadResult['message']);
    }

    // Create gift box
    $giftBoxData = [
        'name' => $_POST['name'],
        'description' => $_POST['description'] ?? '',
        'base_price' => floatval($_POST['base_price']),
        'image_path' => $uploadResult['path']
    ];

    $result = createGiftBox($db, $giftBoxData);
    if ($result) {
        $_SESSION['success'] = 'Gift box created successfully';
    } else {
        throw new Exception('Failed to create gift box');
    }

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}

header('Location: giftbox_manager.php');
exit();
