<?php
require_once '../config/database.php';
require_once '../includes/functions/order_functions.php';

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();

    // Get the calculated totals from the client
    $clientTotals = json_decode($_POST['calculated_totals'], true);
    
    // Prepare order data for server-side calculation
    $orderData = [
        'db' => $db,
        'products' => $_POST['products'] ?? [],
        'services' => $_POST['services'] ?? [],
        'shipping_fee' => $_POST['shipping_fee'] ?? 0,
        'coupon_code' => $_POST['coupon_code'] ?? null,
        'customer_id' => $_POST['customer_id'] ?? null
    ];

    // Calculate totals server-side
    $serverTotals = calculateOrderTotals($orderData);

    // Compare totals (allow for small floating-point differences)
    $isValid = 
        abs($serverTotals['products_subtotal'] - $clientTotals['productsSubtotal']) < 0.01 &&
        abs($serverTotals['services_subtotal'] - $clientTotals['servicesSubtotal']) < 0.01 &&
        abs($serverTotals['shipping_fee'] - $clientTotals['shippingFee']) < 0.01 &&
        abs($serverTotals['discount_amount'] - $clientTotals['discount']) < 0.01 &&
        abs($serverTotals['final_total'] - $clientTotals['finalTotal']) < 0.01;

    echo json_encode([
        'valid' => $isValid,
        'server_totals' => $serverTotals,
        'client_totals' => $clientTotals
    ]);

} catch (Exception $e) {
    echo json_encode([
        'valid' => false,
        'error' => $e->getMessage()
    ]);
}
