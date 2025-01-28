<?php
session_start();

// Check if user is logged in as an admin
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

// Initialize Database connection
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get admin info
$query = "SELECT email FROM users WHERE id = ? AND role = 'admin'";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// If admin not found, logout
if (!$admin) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Check if product ID is provided
if (isset($_GET['id'])) {
    $product_id = $_GET['id'];  // Use 'id' as it is passed in the URL

    // Check if product exists
    $query = "SELECT * FROM products WHERE product_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo "Product not found.";
        exit();
    }

    // Start a transaction to ensure all actions are completed
    $db->beginTransaction();

    try {
        // Delete related inventory records first
        $delete_inventory_query = "DELETE FROM inventory WHERE product_id = ?";
        $delete_inventory_stmt = $db->prepare($delete_inventory_query);
        $delete_inventory_stmt->execute([$product_id]);

        // Now, delete the product
        $delete_product_query = "DELETE FROM products WHERE product_id = ?";
        $delete_product_stmt = $db->prepare($delete_product_query);
        $delete_product_stmt->execute([$product_id]);

        // Commit the transaction
        $db->commit();

        // Redirect to products page after deletion with a success message
        header("Location: products.php?message=Product deleted successfully");
        exit();
    } catch (Exception $e) {
        // If there was an error, roll back the transaction
        $db->rollBack();
        echo "Error occurred while deleting the product: " . $e->getMessage();
        exit();
    }
} else {
    echo "Invalid Product ID.";
    exit();
}

