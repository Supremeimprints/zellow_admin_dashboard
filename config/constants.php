<?php
require_once 'database.php';

$database = new Database();
$db = $database->getConnection();

// Fetch settings
$query = "SELECT * FROM settings LIMIT 1";
$stmt = $db->prepare($query);
$stmt->execute();
$settings = $stmt->fetch(PDO::FETCH_ASSOC);

// Define constants
define('SITE_NAME', $settings['site_name']);
define('ADMIN_EMAIL', $settings['admin_email']);
define('THEME', $settings['theme']);
define('ITEMS_PER_PAGE', $settings['items_per_page']);
