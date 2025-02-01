<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized access");
}

$database = new Database();
$db = $database->getConnection();

$driver_id = $_POST['driver_id'];

try {
    // Toggle driver status
    $stmt = $db->prepare("
        UPDATE drivers 
        SET status = IF(status = 'Active', 'Inactive', 'Active') 
        WHERE driver_id = ?
    ");
    $stmt->execute([$driver_id]);
    
    header("Location: dispatch.php");
    exit();
} catch (PDOException $e) {
    die("Error updating status: " . $e->getMessage());
}