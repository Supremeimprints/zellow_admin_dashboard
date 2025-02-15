<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['id']) || !isset($_POST['supplier_id']) || !isset($_POST['items'])) {
    header('Location: inventory.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    $db->beginTransaction();

    // Update the purchase order creation query
    $stmt = $db->prepare("
        INSERT INTO purchase_orders (
            supplier_id, 
            order_date,
            total_amount,
            status,
            created_by
        ) VALUES (?, NOW(), ?, 'pending', ?)
    ");

    $total_amount = array_sum(array_map(function($item) {
        return $item['quantity'] * $item['unit_price'];
    }, $_POST['items']));

    $stmt->execute([
        $_POST['supplier_id'],
        $total_amount,
        $_SESSION['id']
    ]);

    $purchase_order_id = $db->lastInsertId();

    // Create invoice
    $stmt = $db->prepare("
        INSERT INTO invoices (
            supplier_id,
            purchase_order_id,
            invoice_number,
            amount,
            status,
            due_date,
            created_at
        ) VALUES (?, ?, ?, ?, 'pending', DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())
    ");

    $invoice_number = 'INV-' . date('Ymd') . '-' . str_pad($purchase_order_id, 4, '0', STR_PAD_LEFT);

    $stmt->execute([
        $_POST['supplier_id'],
        $purchase_order_id,
        $invoice_number,
        $total_amount
    ]);

    $invoice_id = $db->lastInsertId();

    // Insert order items
    $itemStmt = $db->prepare("
        INSERT INTO purchase_order_items (
            purchase_order_id,
            product_id,
            quantity,
            unit_price
        ) VALUES (?, ?, ?, ?)
    ");

    foreach ($_POST['items'] as $item) {
        $itemStmt->execute([
            $purchase_order_id,
            $item['product_id'],
            $item['quantity'],
            $item['unit_price']
        ]);
    }

    $db->commit();
    $_SESSION['success'] = "Purchase order created and invoice generated successfully!";
    header("Location: invoices.php?id=" . $invoice_id);
    exit();

} catch (Exception $e) {
    $db->rollBack();
    $_SESSION['error'] = "Error creating purchase order: " . $e->getMessage();
    header("Location: inventory.php");
    exit();
}
?>
