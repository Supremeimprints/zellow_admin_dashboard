<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Remove updated_at from the query since it doesn't exist in the table
    $stmt = $db->prepare("
        UPDATE supplier_products 
        SET 
            product_name = :product_name,
            description = :description,
            unit_price = :unit_price,
            moq = :moq,
            lead_time = :lead_time
        WHERE id = :id
    ");
    
    $result = $stmt->execute([
        ':product_name' => $_POST['product_name'],
        ':description' => $_POST['description'],
        ':unit_price' => $_POST['unit_price'],
        ':moq' => $_POST['moq'],
        ':lead_time' => $_POST['lead_time'],
        ':id' => $_POST['product_id']
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
    } else {
        throw new Exception('Failed to update product');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
