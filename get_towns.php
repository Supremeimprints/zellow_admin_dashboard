<?php
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

if (isset($_GET['county_id'])) {
    $county_id = $_GET['county_id'];

    // Fetch towns based on the selected county
    $query = "SELECT * FROM towns WHERE county_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$county_id]);
    $towns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Generate options for the Town dropdown
    echo "<option value=''>Select Town</option>";
    foreach ($towns as $town) {
        echo "<option value='{$town['id']}'>{$town['name']}</option>";
    }
}

