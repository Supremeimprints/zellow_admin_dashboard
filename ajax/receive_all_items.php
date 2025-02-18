<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
}

$database = new Database();
$db = $database->getConnection();

try {
    $po_id = $_POST['po_id'] ?? 0;

    $db->beginTransaction();

    // Get all pending items for this PO
    $itemsQuery = "
        SELECT 
            poi.item_id,
            poi.product_id,
            poi.quantity,
            COALESCE(poi.received_quantity, 0) as received_quantity
        FROM purchase_order_items poi
        WHERE poi.purchase_order_id = ?
    ";
    $itemsStmt = $db->prepare($itemsQuery);
    $itemsStmt->execute([$po_id]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as $item) {
        $remaining = $item['quantity'] - $item['received_quantity'];
        if ($remaining <= 0) continue;

        // Update received quantity
        $updateItemStmt = $db->prepare("
            UPDATE purchase_order_items 
            SET received_quantity = quantity
            WHERE item_id = ?
        ");
        $updateItemStmt->execute([$item['item_id']]);

        // Update inventory
        $updateInventoryStmt = $db->prepare("
            UPDATE inventory 
            SET 
                stock_quantity = stock_quantity + ?,
                last_restocked = NOW(),
                updated_by = ?
            WHERE product_id = ?
        ");
        $updateInventoryStmt->execute([
            $remaining,
            $_SESSION['id'],
            $item['product_id']
        ]);
    }

    // Update PO status
    $updatePoStmt = $db->prepare("
        UPDATE purchase_orders 
        SET status = 'received'
        WHERE purchase_order_id = ?
    ");
    $updatePoStmt->execute([$po_id]);

    $db->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
