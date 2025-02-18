<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if (!isset($_POST['product_id']) || empty($_POST['product_id'])) {
    echo json_encode(['success' => false, 'message' => 'Product ID not provided']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $db->beginTransaction();
    
    $product_id = $_POST['product_id'];

    // Debug log
    error_log("Attempting to delete product ID: " . $product_id);

    // 1. Delete from inventory
    $stmt = $db->prepare("DELETE FROM inventory WHERE product_id = ?");
    $stmt->execute([$product_id]);
    error_log("Deleted from inventory: " . $stmt->rowCount() . " rows");

    // 2. Delete from order_items
    $stmt = $db->prepare("DELETE FROM order_items WHERE product_id = ?");
    $stmt->execute([$product_id]);
    error_log("Deleted from order_items: " . $stmt->rowCount() . " rows");

    // 3. Delete from purchase_order_items
    $stmt = $db->prepare("DELETE FROM purchase_order_items WHERE product_id = ?");
    $stmt->execute([$product_id]);
    error_log("Deleted from purchase_order_items: " . $stmt->rowCount() . " rows");

    // 4. Delete from supplier_products if exists
    $checkStmt = $db->prepare("SELECT id FROM supplier_products WHERE id = ?");
    $checkStmt->execute([$product_id]);
    $exists = $checkStmt->fetch();

    if ($exists) {
        $stmt = $db->prepare("DELETE FROM supplier_products WHERE id = ?");
        $stmt->execute([$product_id]);
        error_log("Deleted supplier product with id: " . $product_id);
    }

    // 5. Finally delete the product
    $stmt = $db->prepare("DELETE FROM products WHERE product_id = ?");
    $result = $stmt->execute([$product_id]);
    error_log("Deleted from products: " . $stmt->rowCount() . " rows");
    
    if ($result) {
        $db->commit();
        error_log("Transaction committed successfully");
        echo json_encode([
            'success' => true,
            'message' => 'Product and related records deleted successfully'
        ]);
    } else {
        throw new Exception("Failed to delete product");
    }
    
} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
        error_log("Transaction rolled back due to error: " . $e->getMessage());
    }
    echo json_encode([
        'success' => false,
        'message' => 'Error deleting product: ' . $e->getMessage()
    ]);
}

