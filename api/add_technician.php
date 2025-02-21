<?php
session_start();
header('Content-Type: application/json');
require_once '../config/database.php';

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

try {
    // Validate inputs
    if (empty($_POST['name']) || empty($_POST['specialization'])) {
        throw new Exception('Name and specialization are required');
    }

    // Sanitize inputs
    $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
    $specialization = filter_var($_POST['specialization'], FILTER_SANITIZE_STRING);

    // Validate specialization value
    $validSpecializations = ['engraving', 'printing', 'both'];
    if (!in_array($specialization, $validSpecializations)) {
        throw new Exception('Invalid specialization value');
    }

    // Insert new technician
    $stmt = $db->prepare("
        INSERT INTO technicians (name, specialization) 
        VALUES (?, ?)
    ");

    $success = $stmt->execute([$name, $specialization]);

    if ($success) {
        $_SESSION['success'] = "Technician added successfully";
        header('Location: ../technicians.php');
        exit();
    } else {
        throw new Exception('Failed to add technician');
    }

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: ../technicians.php');
    exit();
}
