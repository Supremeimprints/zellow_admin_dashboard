<?php
require_once 'config/database.php';

// Authenticate user
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

// Fetch user data (including theme)
$database = new Database();
$db = $database->getConnection();
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Apply theme to HTML element
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="<?= htmlspecialchars($user['theme'] ?? 'light') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Site Title</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/themes.css">
</head>
<script>
    // includes/nav/footer.php
document.querySelectorAll('[name="theme"]').forEach(radio => {
    radio.addEventListener('change', (e) => {
        document.documentElement.setAttribute('data-bs-theme', e.target.value);
    });
});
</script>
<body>