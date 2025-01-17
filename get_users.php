<?php
require_once 'config/database.php';

// Initialize Database connection
$database = new Database();
$db = $database->getConnection();

$term = $_GET['term'] ?? '';
$query = "SELECT username FROM users WHERE username LIKE ? LIMIT 10";

try {
    $stmt = $db->prepare($query);
    $stmt->execute(["%$term%"]);
    $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    header('Content-Type: application/json');
    echo json_encode($results);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([]);
}