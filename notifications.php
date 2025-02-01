<?php
session_start();
require_once 'config/database.php';

// Admin check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$db = (new Database())->getConnection();

// Handle message deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    $msg_id = $_POST['message_id'];
    $stmt = $db->prepare("DELETE FROM messages WHERE id = ? AND (recipient_id = ? OR recipient_id IS NULL)");
    $stmt->execute([$msg_id, $_SESSION['id']]);
    header("Location: notifications.php");
    exit();
}

// Handle marking message as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $msg_id = $_POST['message_id'];
    $stmt = $db->prepare("UPDATE messages SET is_read = 1 WHERE id = ? AND (recipient_id = ? OR recipient_id IS NULL)");
    $stmt->execute([$msg_id, $_SESSION['id']]);
    header("Location: notifications.php");
    exit();
}

// Get messages for current user
$messageQuery = "SELECT m.*, u.username as sender_name 
          FROM messages m
          JOIN users u ON m.sender_id = u.id
          WHERE (m.recipient_id IS NULL OR m.recipient_id = ?)
          ORDER BY m.created_at DESC";
$stmt = $db->prepare($messageQuery);
$stmt->execute([$_SESSION['id']]);
$messages = $stmt->fetchAll();

// Get recent feedback
$feedbackQuery = "SELECT f.*, u.username 
                 FROM feedback f
                 JOIN users u ON f.user_id = u.id
                 ORDER BY f.created_at DESC";
$stmt = $db->prepare($feedbackQuery);
$stmt->execute();
$feedbacks = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Notifications & Feedback</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="assets/css/notifications.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/nav/navbar.php'; ?>
<?php include 'includes/theme.php'; ?>
    <div class="container mt-4">
        <h2 class="container mt-5">Notifications & Feedback</h2>
        
        <div class="d-flex">
            <!-- Messages Section (3/4 of the screen) -->
            <div class="notification-section w-75 me-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="mb-3">Internal Messages</h3>
                    <a href="send_message.php" class="btn btn-primary">New Message</a>
                </div>

                <?php if (count($messages) > 0): ?>
                    <?php foreach ($messages as $msg): ?>
                        <div class="card message-card mb-3 <?= $msg['is_read'] ? '' : 'unread' ?> 
                            priority-<?= strtolower($msg['priority']) ?>">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="card-title">
                                            <?= htmlspecialchars($msg['sender_name']) ?>
                                            <?php if($msg['subject']): ?>
                                                <small class="text-muted">- <?= htmlspecialchars($msg['subject']) ?></small>
                                            <?php endif; ?>
                                        </h5>
                                        <p class="card-text"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                                        <div class="d-flex gap-2 align-items-center">
                                            <?php if($msg['priority']): ?>
                                                <span class="badge bg-<?= 
                                                    ($msg['priority'] == 'High') ? 'danger' : 
                                                    (($msg['priority'] == 'Medium') ? 'warning' : 'success') ?> 
                                                    status-badge">
                                                    <?= htmlspecialchars($msg['priority']) ?> Priority
                                                </span>
                                            <?php endif; ?>
                                            <?php if($msg['status'] && $msg['type'] === 'Task'): ?>
                                                <span class="badge bg-info status-badge">
                                                    <?= htmlspecialchars($msg['status']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted d-block">
                                            <?= date('M j, Y g:i a', strtotime($msg['created_at'])) ?>
                                        </small>
                                        <form method="POST" class="mt-2">
                                            <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                                            <button type="submit" name="mark_read" 
                                                class="btn btn-sm btn-success">✓ Read</button>
                                            <button type="submit" name="delete" 
                                                class="btn btn-sm btn-danger">× Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info">No messages found</div>
                <?php endif; ?>
            </div>

            <!-- Feedback Section (1/4 of the screen) -->
            <div class="feedback-section w-25">
                <h3 class="mb-3">Customer Feedback</h3>
                
                <?php if (count($feedbacks) > 0): ?>
                    <?php foreach ($feedbacks as $feedback): ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h5 class="card-title">
                                            <?= htmlspecialchars($feedback['username']) ?>
                                            <small class="text-muted">- Order #<?= $feedback['order_id'] ?></small>
                                        </h5>
                                        <div class="rating-stars mb-2">
                                            <?php for($i = 0; $i < 5; $i++): ?>
                                                <i class="fas fa-star<?= $i < $feedback['rating'] ? '' : '-empty' ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <?php if($feedback['comment']): ?>
                                            <p class="card-text"><?= nl2br(htmlspecialchars($feedback['comment'])) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted">
                                            <?= date('M j, Y g:i a', strtotime($feedback['created_at'])) ?>
                                        </small>
                                        <form method="POST" class="mt-2">
                                            <input type="hidden" name="feedback_id" value="<?= $feedback['id'] ?>">
                                            <button type="submit" name="reply" 
                                                class="btn btn-sm btn-primary">Reply</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info">No feedback available</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
<?php include 'includes/nav/footer.php'; ?>
</html>
