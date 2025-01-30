<?php
session_start();
require_once 'config/database.php';

// Simple admin check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$db = (new Database())->getConnection();

// Handle message deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $msg_id = $_POST['message_id'];
    $stmt = $db->prepare("DELETE FROM messages WHERE id = ?");
    $stmt->execute([$msg_id]);
    header("Location: notifications.php");
    exit();
}

// Handle marking message as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $msg_id = $_POST['message_id'];
    $stmt = $db->prepare("UPDATE messages SET status = 'is_read' WHERE id = ?");
    $stmt->execute([$msg_id]);
    header("Location: notifications.php");
    exit();
}

// Get messages for current user
$query = "SELECT m.*, u.username as sender_name 
          FROM messages m
          JOIN users u ON m.sender_id = u.id
          WHERE m.recipient_id IS NULL OR m.recipient_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['id']]);
$messages = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Messages</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/nav/navbar.php'; ?>
    <div class="container mt-4">
        <h1>Your Messages</h1>
        <a href="send_message.php" class="btn btn-primary mb-3">New Message</a>
        
        <?php foreach ($messages as $msg): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title">From: <?= htmlspecialchars($msg['sender_name']) ?></h5>
                    <p class="card-text"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                    <small class="text-muted">
                        <?= date('M j, Y g:i a', strtotime($msg['created_at'])) ?>
                    </small>
                    <form method="POST" class="mt-2">
                        <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                        <button type="submit" name="mark_read" class="btn btn-sm btn-success">Mark as Read</button>
                        <button type="submit" name="delete" class="btn btn-sm btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
<?php include 'includes/nav/footer.php'; ?>
</html>
