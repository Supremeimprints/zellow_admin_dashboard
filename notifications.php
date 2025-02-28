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
    $_SESSION['success'] = "Message deleted successfully.";
    header("Location: " . $_SERVER['HTTP_REFERER']);
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
$messageQuery = "SELECT 
    m.*,
    u.username as sender_name,
    u.profile_photo as sender_photo
FROM messages m
JOIN users u ON m.sender_id = u.id
WHERE (m.recipient_id = ? OR m.recipient_id IS NULL)
ORDER BY m.created_at DESC";
$stmt = $db->prepare($messageQuery);
$stmt->execute([$_SESSION['id']]);
$messages = $stmt->fetchAll();

// Get recent feedback - Update the LIMIT to 3
$feedbackQuery = "SELECT f.*, u.username 
                 FROM feedback f
                 LEFT JOIN users u ON f.user_id = u.id
                 ORDER BY f.created_at DESC
                 LIMIT 3";  // Changed from 5 to 3
$stmt = $db->prepare($feedbackQuery);
$stmt->execute();
$feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get service request notifications
$serviceRequestQuery = "SELECT 
    sr.*,
    o.username,
    o.customization_type as service_name,
    sr.request_date AS created_at,
    u.profile_photo as sender_photo 
    FROM service_requests sr 
    JOIN orders o ON sr.id = o.order_id 
    LEFT JOIN users u ON o.username = u.username 
    WHERE sr.status = 'Pending' 
    ORDER BY sr.request_date DESC";
$stmt = $db->prepare($serviceRequestQuery);
$stmt->execute();
$serviceRequests = $stmt->fetchAll();

// Get stock alerts (Updated)
$stockAlertQuery = "SELECT 
                        i.id,
                        p.product_name,
                        i.stock_quantity,
                        i.min_stock_level
                    FROM inventory i
                    JOIN products p ON i.product_id = p.product_id
                    WHERE i.stock_quantity <= i.min_stock_level
                    ORDER BY i.stock_quantity ASC";
$stmt = $db->prepare($stockAlertQuery);
$stmt->execute();
$stockAlerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// LOW INVENTORY STATS
$query = "SELECT 
p.product_name,
i.stock_quantity,
i.min_stock_level
FROM inventory i
JOIN products p ON i.product_id = p.product_id
WHERE i.stock_quantity <= i.min_stock_level";
$stmt = $db->query($query);
$reportData['low_stock'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Add this before the HTML output to count unread notifications
$unreadQuery = "SELECT COUNT(*) FROM messages WHERE recipient_id = ? AND is_read = 0";
$stmt = $db->prepare($unreadQuery);
$stmt->execute([$_SESSION['id']]);
$unreadCount = $stmt->fetchColumn();

try {
    $query = "SELECT 
        n.*,
        u_sender.username as sender_name,
        u_sender.profile_photo as sender_photo,
        u_recipient.username as recipient_name,
        sr.service_request_id,
        o.order_id,
        o.customization_type,
        o.customization_details
    FROM notifications n
    LEFT JOIN users u_sender ON n.sender_id = u_sender.id
    LEFT JOIN users u_recipient ON n.recipient_id = u_recipient.id
    LEFT JOIN service_requests sr ON sr.service_request_id = CASE 
        WHEN n.type = 'Task' THEN CAST(n.message AS UNSIGNED)
        ELSE NULL
    END
    LEFT JOIN orders o ON sr.id = o.order_id
    WHERE n.recipient_id = :id OR n.recipient_id IS NULL
    ORDER BY n.created_at DESC";
              
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $_SESSION['id']]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Notification query error: ' . $e->getMessage());
    $notifications = [];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Notifications & Feedback</title>
     <!-- Feather Icons - Add this line -->
     <script src="https://unpkg.com/feather-icons"></script>
    
    <!-- Existing stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/badges.css">
    <link rel="stylesheet" href="assets/css/orders.css">
    <link rel="stylesheet" href="assets/css/collapsed.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/orders.css" rel="stylesheet">
    <link href="assets/css/badges.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <link href="assets/css/notifications.css" rel="stylesheet">
</head>
<body>
<div class="admin-layout"> 
<?php include 'includes/theme.php'; ?>
    <nav class="navbar">
    <?php include 'includes/nav/collapsed.php'; ?>
    </nav>
<!-- Add the alerts container -->
<div class="alerts-container">
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert custom-alert success">
            <div class="alert-content">
                <i class="fas fa-check-circle alert-icon"></i>
                <span><?= $_SESSION['success']; unset($_SESSION['success']); ?></span>
            </div>
            <button type="button" class="alert-close" onclick="this.parentElement.remove();">×</button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert custom-alert error">
            <div class="alert-content">
                <i class="fas fa-exclamation-circle alert-icon"></i>
                <span><?= $_SESSION['error']; unset($_SESSION['error']); ?></span>
            </div>
            <button type="button" class="alert-close" onclick="this.parentElement.remove();">×</button>
        </div>
    <?php endif; ?>
</div>

<!-- Add these styles to the existing <style> section or your CSS file -->
<style>
.alerts-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1050;
    width: 400px;
}

.custom-alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 10px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    display: flex;
    justify-content: space-between;
    align-items: center;
    animation: slideIn 0.3s ease-out;
}

.alert-content {
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-icon {
    font-size: 1.2rem;
}

.alert-close {
    background: none;
    border: none;
    color: inherit;
    font-size: 1.2rem;
    opacity: 0.7;
    cursor: pointer;
    padding: 0 5px;
}

.alert-close:hover {
    opacity: 1;
}

.custom-alert.success {
    background-color: #d4edda;
    border-color: #c3e6cb;
    color: #155724;
}

.custom-alert.error {
    background-color: #f8d7da;
    border-color: #f5c6cb;
    color: #721c24;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Auto-dismiss animation */
@keyframes fadeOut {
    from { opacity: 1; }
    to { opacity: 0; }
}

.alert-dismissing {
    animation: fadeOut 0.5s ease-out forwards;
}

/* Stock alert link color */
.stock-alert-link {
    color: var(--bs-dark);
}

.stock-alert-link:hover {
    color: var(--bs-dark);
}
</style>

<script>
// Auto-dismiss alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.custom-alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.classList.add('alert-dismissing');
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
});
</script>

<?php
// Display Messages
if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
<?php endif; ?>

    <div class="container mt-4">
        <h2 class="container mt-5">Notifications & Feedback</h2>
        
        <div class="d-flex">
            <!-- Messages Section (3/4 of the screen) -->
            <div class="notification-section w-75 me-3">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="mb-3">Internal Messages</h3>
                    <a href="send_message.php" class="btn btn-primary">New Message</a>
                </div>

                <ul class="nav nav-tabs" id="messageTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="unread-tab" data-bs-toggle="tab" data-bs-target="#unread" type="button" role="tab" aria-controls="unread" aria-selected="true">Unread</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="read-tab" data-bs-toggle="tab" data-bs-target="#read" type="button" role="tab" aria-controls="read" aria-selected="false">Read</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tasks-tab" data-bs-toggle="tab" data-bs-target="#tasks" type="button" role="tab" aria-controls="tasks" aria-selected="false">Tasks</button>
                    </li>
                </ul>
                <div class="tab-content" id="messageTabsContent">
                    <div class="tab-pane fade show active" id="unread" role="tabpanel" aria-labelledby="unread-tab">
                        <?php foreach ($messages as $msg): ?>
                            <?php if (!$msg['is_read']): ?>
                                <div class="card message-card mb-3 priority-<?= strtolower($msg['priority']) ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="card-title">
                                                    <img src="<?= htmlspecialchars($msg['sender_photo']) ?>" alt="Profile Photo" class="profile-photo">
                                                    <?= htmlspecialchars($msg['sender_name']) ?>
                                                    <?php if($msg['subject']): ?>
                                                        <small class="text-muted">- <?= htmlspecialchars($msg['subject']) ?></small>
                                                    <?php endif; ?>
                                                </h5>
                                                <p class="card-text"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                                                <div class="d-flex gap-2 align-items-center">
                                                    <?php if($msg['priority']): ?>
                                                        <span class="badge bg-<?php 
                                                            if($msg['priority'] == 'High') {
                                                                echo 'danger';
                                                            } elseif($msg['priority'] == 'Medium') {
                                                                echo 'warning';
                                                            } else {
                                                                echo 'success';
                                                            }
                                                        ?> status-badge">
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
                                                <div class="btn-group mt-2">
                                                    <form method="POST">
                                                        <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                                                        <button type="submit" name="mark_read" class="btn btn-sm btn-success">✓ Read</button>
                                                    </form>
                                                    <form method="POST">
                                                        <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                                                        <button type="submit" name="delete" class="btn btn-sm btn-danger">× Delete</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <div class="tab-pane fade" id="read" role="tabpanel" aria-labelledby="read-tab">
                        <?php foreach ($messages as $msg): ?>
                            <?php if ($msg['is_read']): ?>
                                <div class="card message-card mb-3 priority-<?= strtolower($msg['priority']) ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="card-title">
                                                    <img src="<?= htmlspecialchars($msg['sender_photo']) ?>" alt="Profile Photo" class="profile-photo">
                                                    <?= htmlspecialchars($msg['sender_name']) ?>
                                                    <?php if($msg['subject']): ?>
                                                        <small class="text-muted">- <?= htmlspecialchars($msg['subject']) ?></small>
                                                    <?php endif; ?>
                                                </h5>
                                                <p class="card-text"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                                                <div class="d-flex gap-2 align-items-center">
                                                    <?php if($msg['priority']): ?>
                                                        <span class="badge bg-<?php 
                                                            if($msg['priority'] == 'High') {
                                                                echo 'danger';
                                                            } elseif($msg['priority'] == 'Medium') {
                                                                echo 'warning';
                                                            } else {
                                                                echo 'success';
                                                            }
                                                        ?> status-badge">
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
                                                <div class="btn-group mt-2">
                                                    <form method="POST">
                                                        <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                                                        <button type="submit" name="delete" class="btn btn-sm btn-danger">× Delete</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <div class="tab-pane fade" id="tasks" role="tabpanel" aria-labelledby="tasks-tab">
                        <?php foreach ($messages as $msg): ?>
                            <?php if ($msg['type'] === 'Task'): ?>
                                <div class="card message-card mb-3 priority-<?= strtolower($msg['priority']) ?>">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h5 class="card-title">
                                                    <img src="<?= htmlspecialchars($msg['sender_photo']) ?>" alt="Profile Photo" class="profile-photo">
                                                    <?= htmlspecialchars($msg['sender_name']) ?>
                                                    <?php if($msg['subject']): ?>
                                                        <small class="text-muted">- <?= htmlspecialchars($msg['subject']) ?></small>
                                                    <?php endif; ?>
                                                </h5>
                                                <p class="card-text"><?= nl2br(htmlspecialchars($msg['message'])) ?></p>
                                                <div class="d-flex gap-2 align-items-center">
                                                    <?php if($msg['priority']): ?>
                                                        <span class="badge bg-<?php 
                                                            if($msg['priority'] == 'High') {
                                                                echo 'danger';
                                                            } elseif($msg['priority'] == 'Medium') {
                                                                echo 'warning';
                                                            } else {
                                                                echo 'success';
                                                            }
                                                        ?> status-badge">
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
                                                <div class="btn-group mt-2">
                                                    <form method="POST">
                                                        <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                                                        <button type="submit" name="mark_read" class="btn btn-sm btn-success">✓ Read</button>
                                                    </form>
                                                    <form method="POST">
                                                        <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                                                        <button type="submit" name="delete" class="btn btn-sm btn-danger">× Delete</button>
                                                    </form>
                                                    <form method="POST">
                                                        <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                                                        <button type="submit" name="mark_done" class="btn btn-sm btn-primary">✓ Done</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Alerts, Feedback, and Service Requests Section -->
            <div class="alerts-section">
                <!-- Stock Alerts Section -->
                <h3>Stock Alerts</h3>
                <div class="alert-list">
                    <?php if (count($stockAlerts) > 0): ?>
                        <?php foreach ($stockAlerts as $alert): ?>
                            <div class="alert alert-warning alert-dismissible fade show stock-alert" role="alert">
                                <a href="update_inventory.php?id=<?= $alert['id'] ?>" class="stock-alert-link">
                                    <strong><?= htmlspecialchars($alert['product_name']) ?></strong>
                                    <div class="small mt-1">
                                        Stock: <?= $alert['stock_quantity'] ?> / <?= $alert['min_stock_level'] ?>
                                    </div>
                                </a>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info" style="text-align: center;">No new alerts</div>
                    <?php endif; ?>
                </div>

                <!-- Feedback Section -->
                <h3>Customer Feedback</h3>
                <div class="feedback-list">
                    <?php if (!empty($feedbacks)): ?>
                        <?php foreach ($feedbacks as $feedback): ?>
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <h6 class="card-title mb-1">
                                            <?= htmlspecialchars($feedback['username'] ?? 'Anonymous') ?>
                                        </h6>
                                        <small class="text-muted"><?= date('M j', strtotime($feedback['created_at'])) ?></small>
                                    </div>
                                    <div class="rating-stars small mb-2">
                                        <?php 
                                        for($i = 0; $i < 5; $i++) {
                                            $starClass = $i < $feedback['rating'] ? '' : '-empty';
                                            echo '<i class="fas fa-star' . $starClass . '"></i>';
                                        }
                                        ?>
                                    </div>
                                    <?php if($feedback['comment']): ?>
                                        <p class="card-text small mb-0"><?= nl2br(htmlspecialchars($feedback['comment'])) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info">No feedback available</div>
                    <?php endif; ?>
                </div>
                <a href="feedback.php" class="btn btn-primary btn-sm w-100">View All Feedback</a>

                <!-- Service Requests Section -->
                <h3>Service Requests</h3>
                <div class="service-requests-list">
                    <?php if (count($serviceRequests) > 0): ?>
                        <?php foreach ($serviceRequests as $request): ?>
                            <div class="card service-request-card mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="card-title">
                                                <?php if ($request['sender_photo']): ?>
                                                    <img src="<?= htmlspecialchars($request['sender_photo']) ?>" alt="Profile" class="profile-photo">
                                                <?php endif; ?>
                                                <?= htmlspecialchars($request['username']) ?>
                                            </h6>
                                            <div class="service-details">
                                                <span class="badge bg-info"><?= htmlspecialchars($request['service_name']) ?></span>
                                                <small class="text-muted d-block mt-1">
                                                    Requested: <?= date('M j, Y g:i a', strtotime($request['created_at'])) ?>
                                                </small>
                                            </div>
                                        </div>
                                        <div>
                                            <a href="service_requests.php?id=<?= $request['id'] ?>" 
                                               class="btn btn-sm btn-outline-primary">View Details</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info" style="text-align: center;">No pending service requests</div>
                    <?php endif; ?>
                    <a href="service_requests.php" class="btn btn-primary btn-sm w-100">View All Requests</a>
                </div>
            </div>

        </div>
    </div>

</body>
<?php include 'includes/nav/footer.php'; ?>
</html>
