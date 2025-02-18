<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    die('Unauthorized access');
}

$database = new Database();
$db = $database->getConnection();

$po_id = $_GET['po_id'] ?? 0;

$query = "
    SELECT 
        poi.item_id,
        p.product_name,
        poi.quantity as ordered_quantity,
        COALESCE(poi.received_quantity, 0) as received_quantity,
        poi.unit_price,
        (poi.quantity - COALESCE(poi.received_quantity, 0)) as remaining_quantity,
        i.stock_quantity as current_stock,
        poi.last_received_date
    FROM purchase_order_items poi
    JOIN products p ON poi.product_id = p.product_id
    JOIN inventory i ON p.product_id = i.product_id
    WHERE poi.purchase_order_id = ?
    ORDER BY p.product_name
";

$stmt = $db->prepare($query);
$stmt->execute([$po_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Return only the tbody content
?>
<?php foreach ($items as $item): ?>
    <tr>
        <td><?= htmlspecialchars($item['product_name']) ?></td>
        <td class="text-center"><?= $item['ordered_quantity'] ?></td>
        <td class="text-center">
            <span class="current-received"><?= $item['received_quantity'] ?></span>
            <br>
            <small class="text-muted">Stock: <?= $item['current_stock'] ?></small>
            <?php if ($item['last_received_date']): ?>
                <br>
                <small class="text-muted">Last Received: <?= date('Y-m-d H:i', strtotime($item['last_received_date'])) ?></small>
            <?php endif; ?>
        </td>
        <td class="text-center">
            <span class="remaining-quantity"><?= $item['remaining_quantity'] ?></span>
        </td>
        <td>Ksh. <?= number_format($item['unit_price'], 2) ?></td>
        <td>
            <?php if ($item['remaining_quantity'] > 0): ?>
                <div class="input-group input-group-sm">
                    <input type="number" class="form-control received-quantity"
                           min="1" max="<?= $item['remaining_quantity'] ?>"
                           style="width: 80px;">
                    <button class="btn btn-success btn-sm update-received"
                            data-item-id="<?= $item['item_id'] ?>">
                        Update
                    </button>
                </div>
            <?php else: ?>
                <span class="badge bg-success">Complete</span>
            <?php endif; ?>
        </td>
    </tr>
<?php endforeach; ?>
