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
    $item_id = $_POST['item_id'] ?? 0;
    $quantity = (int)$_POST['quantity'];

    $db->beginTransaction();

    // Get item details and validate
    $itemQuery = "
        SELECT 
            poi.*,
            po.status as po_status,
            po.supplier_id,
            po.total_amount,
            i.invoice_id,
            i.amount as invoice_amount
        FROM purchase_order_items poi
        JOIN purchase_orders po ON poi.purchase_order_id = po.purchase_order_id
        LEFT JOIN invoices i ON po.purchase_order_id = i.purchase_order_id
        WHERE poi.item_id = ?
    ";
    
    $stmt = $db->prepare($itemQuery);
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception("Item not found");
    }

    // Validate quantity
    $remaining = $item['quantity'] - ($item['received_quantity'] ?? 0);
    if ($quantity > $remaining) {
        throw new Exception("Cannot receive more than ordered quantity");
    }

    // Update received quantity
    $updateItemStmt = $db->prepare("
        UPDATE purchase_order_items 
        SET 
            received_quantity = COALESCE(received_quantity, 0) + ?,
            last_received_date = NOW()
        WHERE item_id = ?
    ");
    
    $updateItemStmt->execute([$quantity, $item_id]);

    // Check PO completion status
    $checkQuery = "
        SELECT 
            SUM(quantity) as total_quantity,
            SUM(COALESCE(received_quantity, 0)) as total_received
        FROM purchase_order_items
        WHERE purchase_order_id = ?
    ";
    
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([$po_id]);
    $totals = $checkStmt->fetch(PDO::FETCH_ASSOC);

    // Update PO status
    $newStatus = $totals['total_received'] >= $totals['total_quantity'] ? 
                'received' : 
                ($totals['total_received'] > 0 ? 'partial' : 'pending');

    $updatePoStmt = $db->prepare("
        UPDATE purchase_orders 
        SET status = ?
        WHERE purchase_order_id = ?
    ");
    $updatePoStmt->execute([$newStatus, $po_id]);

    // Create invoice if doesn't exist
    if (!$item['invoice_id']) {
        $invoiceNumber = 'INV-' . date('Y') . '-' . str_pad($po_id, 3, '0', STR_PAD_LEFT);
        
        $createInvoiceStmt = $db->prepare("
            INSERT INTO invoices (
                invoice_number,
                supplier_id,
                amount,
                due_date,
                status,
                purchase_order_id
            ) VALUES (?, ?, ?, DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY), 'Unpaid', ?)
        ");
        
        $createInvoiceStmt->execute([
            $invoiceNumber,
            $item['supplier_id'],
            $item['total_amount'],
            $po_id
        ]);
    }

    $db->commit();
    
    echo json_encode([
        'success' => true,
        'status' => $newStatus,
        'remaining' => $remaining - $quantity,
        'received' => ($item['received_quantity'] ?? 0) + $quantity
    ]);

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
