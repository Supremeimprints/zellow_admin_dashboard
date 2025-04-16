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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $giftBoxId = $_POST['gift_box_id'] ?? null;
        
        if (!$giftBoxId) {
            throw new Exception('Gift box ID is required');
        }

        $updateData = [
            'gift_box_id' => $giftBoxId,
            'name' => $_POST['name'],
            'description' => $_POST['description'],
            'base_price' => $_POST['base_price']
        ];

        // Handle image upload if new image is provided
        if (!empty($_FILES['image']['name'])) {
            $uploadResult = handleGiftBoxImageUpload($_FILES['image']);
            if ($uploadResult['success']) {
                $updateData['image_path'] = $uploadResult['path'];
            } else {
                throw new Exception($uploadResult['message']);
            }
        }

        if (updateGiftBox($db, $updateData)) {
            $_SESSION['success'] = 'Gift box updated successfully';
        } else {
            throw new Exception('Failed to update gift box');
        }

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }

    header('Location: giftbox_manager.php');
    exit();
}

// Handle GET request to fetch gift box data
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $giftBoxId = $_GET['id'] ?? null;
        if (!$giftBoxId) {
            throw new Exception('Gift box ID is required');
        }

        $giftBox = getGiftBoxById($db, $giftBoxId);
        if (!$giftBox) {
            throw new Exception('Gift box not found');
        }

        header('Content-Type: application/json');
        echo json_encode($giftBox);
        exit();
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()]);
        exit();
    }
}
