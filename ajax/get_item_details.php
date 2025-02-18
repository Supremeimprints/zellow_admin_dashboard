<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
}

$database = new Database();
$db = $database->getConnection();

try {
    $po_id = $_GET['po_id'] ?? 0;
    $item_id = $_GET['item_id'] ?? 0;

    $query = "
        SELECT 
            poi.quantity as ordered_quantity,
            poi.unit_price,
            COALESCE(poi.quantity, 0) as received_quantity,
            p.product_name,
            po.status
        FROM purchase_order_items poi
        JOIN products p ON poi.product_id = p.product_id
        JOIN purchase_orders po ON poi.purchase_order_id = po.purchase_order_id
        WHERE poi.purchase_order_id = ? AND poi.item_id = ?
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([$po_id, $item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode($item);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
