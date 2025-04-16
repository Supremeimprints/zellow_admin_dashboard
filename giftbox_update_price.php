<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions/giftbox_functions.php';

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('HTTP/1.1 401 Unauthorized');
    exit(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

$database = new Database();
$db = $database->getConnection();

$giftBoxId = $_POST['gift_box_id'] ?? null;
$basePrice = $_POST['base_price'] ?? null;

if (!$giftBoxId || !$basePrice) {
    exit(json_encode(['success' => false, 'message' => 'Missing required fields']));
}

$success = updateGiftBoxBasePrice($db, $giftBoxId, $basePrice);
echo json_encode(['success' => $success]);
