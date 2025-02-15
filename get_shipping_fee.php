<?php
require_once 'config/database.php';
require_once 'includes/functions/shipping_functions.php';

$database = new Database();
$db = $database->getConnection();

$method = $_POST['method'] ?? 'Standard';
$subtotal = floatval($_POST['subtotal'] ?? 0);
$uniqueItemCount = intval($_POST['uniqueItemCount'] ?? 1);

$fee = calculateShippingFee($db, $method, $subtotal, $uniqueItemCount);

header('Content-Type: application/json');
echo json_encode(['fee' => $fee]);
