<?php
session_start();
require_once 'config/database.php'; // Your existing DB connection
 // Include this file to check if user is logged in
// Simple admin check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get all admins
$db = (new Database())->getConnection();
$admins = $db->query("SELECT id, username FROM users WHERE role = 'admin'")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipient = $_POST['recipient_id'] == 0 ? NULL : (int)$_POST['recipient_id'];
    $message = $_POST['message'];
    
    $stmt = $db->prepare("INSERT INTO messages (sender_id, recipient_id, message) 
                        VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['id'], $recipient, $message]);
    
    header("Location: notifications.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Send Message</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/nav/navbar.php'; ?>
<div class="container mt-4">
    <h1>Send Message</h1>
    <form method="POST">
        <div class="mb-3">
            <label>To:</label>
            <select name="recipient_id" class="form-control">
                <option value="0">All Admins</option>
                <?php foreach ($admins as $admin): ?>
                    <option value="<?= $admin['id'] ?>"><?= $admin['username'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <textarea name="message" class="form-control" rows="3" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Send</button>
    </form>
</body>
<?php include 'includes/nav/footer.php'; ?>
</html>