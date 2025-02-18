<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
}

$database = new Database();
$db = $database->getConnection();

try {
    $item_id = $_POST['item_id'] ?? 0;
    $quantity = (int)$_POST['quantity'];

    if ($quantity <= 0) {
        throw new Exception("Please enter a valid quantity");
    }

    $db->beginTransaction();

    // Get current item details with explicit current stock
    $itemQuery = "
        SELECT 
            poi.*,
            po.purchase_order_id,
            p.product_id,
            i.stock_quantity as current_stock,
            i.id as inventory_id
        FROM purchase_order_items poi
        JOIN purchase_orders po ON poi.purchase_order_id = po.purchase_order_id
        JOIN products p ON poi.product_id = p.product_id
        JOIN inventory i ON p.product_id = i.product_id
        WHERE poi.item_id = ?
    ";
    
    $stmt = $db->prepare($itemQuery);
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception("Item not found");
    }

    // Validate remaining quantity
    $remaining = $item['quantity'] - ($item['received_quantity'] ?? 0);
    if ($quantity > $remaining) {
        throw new Exception("Cannot receive more than remaining quantity ($remaining)");
    }

    // Log for debugging
    error_log("Current stock before update: " . $item['current_stock']);
    error_log("Quantity to add: " . $quantity);

    // Simply update received quantity in purchase_order_items
    $updateItemStmt = $db->prepare("
        UPDATE purchase_order_items 
        SET 
            received_quantity = COALESCE(received_quantity, 0) + ?,
            last_received_date = NOW()
        WHERE item_id = ?
    ");
    $updateItemStmt->execute([$quantity, $item_id]);

    // Update inventory with exact quantity received
    $updateInventoryStmt = $db->prepare("
        UPDATE inventory 
        SET 
            stock_quantity = ?,
            last_restocked = NOW(),
            updated_by = ?
        WHERE id = ?
    ");
    
    // Calculate new stock level
    $newStockLevel = $item['current_stock'] + $quantity;
    
    $updateInventoryStmt->execute([
        $newStockLevel,
        $_SESSION['id'],
        $item['inventory_id']
    ]);

    // Verify the update
    $verifyStmt = $db->prepare("SELECT stock_quantity FROM inventory WHERE id = ?");
    $verifyStmt->execute([$item['inventory_id']]);
    $newStock = $verifyStmt->fetch(PDO::FETCH_ASSOC);

    error_log("New stock after update: " . $newStock['stock_quantity']);

    // Update order status
    $orderStmt = $db->prepare("
        SELECT 
            SUM(quantity) as total_ordered,
            SUM(COALESCE(received_quantity, 0)) as total_received
        FROM purchase_order_items
        WHERE purchase_order_id = ?
    ");
    
    $orderStmt->execute([$item['purchase_order_id']]);
    $totals = $orderStmt->fetch(PDO::FETCH_ASSOC);

    $newStatus = 'pending';
    if ($totals['total_received'] >= $totals['total_ordered']) {
        $newStatus = 'received';
    } elseif ($totals['total_received'] > 0) {
        $newStatus = 'partial';
    }

    $updateOrderStmt = $db->prepare("
        UPDATE purchase_orders 
        SET status = ?
        WHERE purchase_order_id = ?
    ");
    $updateOrderStmt->execute([$newStatus, $item['purchase_order_id']]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => "Received $quantity items. New stock level: {$newStock['stock_quantity']}",
        'status' => $newStatus,
        'new_stock' => $newStock['stock_quantity'],
        'progress' => ($totals['total_received'] / $totals['total_ordered']) * 100,
        'remaining' => $remaining - $quantity,
        'received' => $totals['total_received']
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
