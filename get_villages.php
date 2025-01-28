<?php
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

if (isset($_GET['town_id'])) {
    $town_id = $_GET['town_id'];

    // Fetch villages based on the selected town
    $query = "SELECT * FROM villages WHERE town_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$town_id]);
    $villages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Generate options for the Village dropdown
    echo "<option value=''>Select Village</option>";
    foreach ($villages as $village) {
        echo "<option value='{$village['id']}'>{$village['name']}</option>";
    }
}