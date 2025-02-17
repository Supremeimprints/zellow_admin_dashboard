<?php
require_once '../config/database.php';
require_once '../includes/functions/settings_helpers.php';

header('Content-Type: application/json');

try {
    if (!isset($_POST['region_id'])) {
        throw new Exception("Region ID is required");
    }

    $database = new Database();
    $db = $database->getConnection();

    if (toggleRegionStatus($db, $_POST['region_id'])) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception("Failed to update region status");
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
