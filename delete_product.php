<?php
// Include database connection and authentication check
include 'database.php';
include 'auth.php'; // Check if user is logged in


include 'navbar.php';

// Get the product ID from the URL
$productId = $_GET['id'] ?? null;

// Delete the product if the ID is valid
if ($productId) {
    $deleteQuery = "DELETE FROM products WHERE id = $productId";
    mysqli_query($conn, $deleteQuery);
}

// Redirect back to the product list
header('Location: products.php');
exit();
