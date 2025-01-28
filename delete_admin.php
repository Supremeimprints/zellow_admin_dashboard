<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

// Check if user has admin access
if ($_SESSION['role'] !== 'admin') {
    echo "Access denied. You do not have permission to perform this action.";
    exit();
}

// Initialize Database connection
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Validate admin ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "Invalid admin ID.";
    exit();
}

$id = (int)$_GET['id'];

// Check if the admin exists
$query = "SELECT id FROM users WHERE id = ? AND role IN ('admin', 'finance_manager', 'supply_manager', 'inventory_manager', 'dispatch_manager', 'service_manager')";
$stmt = $db->prepare($query);
$stmt->execute([$id]);

if ($stmt->rowCount() === 0) {
    echo "Admin not found.";
    exit();
}

// Delete the admin
$query = "DELETE FROM users WHERE id = ?";
$stmt = $db->prepare($query);

if ($stmt->execute([$id])) {
    echo "<script>
        alert('Admin deleted successfully.');
        window.location.href = 'users.php';
    </script>";
} else {
    echo "Error: Unable to delete admin. Please try again later.";
}
