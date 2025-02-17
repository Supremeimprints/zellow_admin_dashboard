<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$db = (new Database())->getConnection();
$error = '';
$success = '';

// Get admin users and roles
$users = $db->query("SELECT id, username, role FROM users WHERE role IN ('admin', 'finance_manager', 'supply_manager', 'dispatch_manager', 'service_manager', 'inventory_manager')")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $recipient = $_POST['recipient_id'] == 0 ? NULL : (int)$_POST['recipient_id'];
        
        $stmt = $db->prepare("INSERT INTO messages 
                            (sender_id, recipient_id, message, subject, priority, type) 
                            VALUES (?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $_SESSION['id'],
            $recipient,
            $_POST['message'],
            $_POST['subject'],
            $_POST['priority'],
            $_POST['type']
        ]);
        
        $_SESSION['success'] = "Message sent successfully!";
        header("Location: notifications.php");
        exit();
        
    } catch(PDOException $e) {
        error_log("Message send error: ".$e->getMessage());
        $error = "Failed to send message. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Send Message</title>
     <!-- Feather Icons - Add this line -->
     <script src="https://unpkg.com/feather-icons"></script>
    
    <!-- Existing stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/collapsed.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/orders.css" rel="stylesheet">
    <link href="assets/css/badges.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="assets/css/dispatch.css" rel="stylesheet">
    <style>
        .message-form-card {
            max-width: 800px;
            margin: 2rem auto;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
            border-radius: 1rem;
        }
        .form-icon {
            font-size: 1.5rem;
            margin-right: 0.5rem;
        }
        .alert-primary {
            max-width: 800px;
            margin: 2rem auto;
            border-radius: 1rem;
        }
    </style>
</head>
<body>
<div class="admin-layout"> 
<?php include 'includes/theme.php'; ?>
    <nav class="navbar">
    <?php include 'includes/nav/collapsed.php'; ?>
    </nav>

<div class="container mt-5">
    <div class="alert alert-primary" role="alert">
        <h4 class="mb-0"><i class="fas fa-envelope form-icon"></i>Compose Message</h4>
    </div>

    <?php if($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <?php
    // Display Messages
    if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="message-form-card card">
        <div class="card-body">
            <form method="POST">
                <div class="row g-3">
                    <!-- Recipient Selection -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Recipient</label>
                        <select name="recipient_id" class="form-select" required>
                            <option value="0">All Admins</option>
                            <?php foreach ($users as $user): ?>
                                <?php if($user['id'] != $_SESSION['id']): ?>
                                    <option value="<?= $user['id'] ?>">
                                        <?= htmlspecialchars($user['username']) ?> (<?= $user['role'] ?>)
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Message Type -->
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Message Type</label>
                        <select name="type" class="form-select" required>
                            <option value="Message">General Message</option>
                            <option value="Task">Task</option>
                            <option value="Approval">Approval Request</option>
                        </select>
                    </div>

                    <!-- Priority -->
                    <div class="col-md-4">
                        <label class="form-label fw-bold">Priority</label>
                        <select name="priority" class="form-select">
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                        </select>
                    </div>

                    <!-- Subject -->
                    <div class="col-md-8">
                        <label class="form-label fw-bold">Subject</label>
                        <input type="text" name="subject" class="form-control" 
                            placeholder="Enter message subject" required>
                    </div>

                    <!-- Message Content -->
                    <div class="col-12">
                        <label class="form-label fw-bold">Message</label>
                        <textarea name="message" class="form-control" rows="6" 
                            placeholder="Write your message here..." required></textarea>
                    </div>

                    <!-- Buttons -->
                    <div class="col-12 d-flex justify-content-between">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-paper-plane me-2"></i>Send Message
                        </button>
                        <a href="notifications.php" class="btn btn-danger btn-lg">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/nav/footer.php'; ?>
</body>
</html>
