<?php
session_start();
require_once 'config/database.php';
require_once 'includes/theme.php';
require_once 'includes/functions/utilities.php';

// Authentication check
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['id'];
$error = $success = '';

// Fetch user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found");
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Profile Update
        if (isset($_POST['update_profile'])) {
            $username = trim($_POST['username']);
            $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
            $theme = $_POST['theme'];
            $notification_enabled = isset($_POST['notification_enabled']) ? 1 : 0;

            // Validation
            if (empty($username) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid input data");
            }

            // Handle file upload
            $profile_photo = $user['profile_photo'];
            if (!empty($_FILES['profile_photo']['name'])) {
                $target_dir = "uploads/";
                $file_extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
                $new_filename = "user_" . $user_id . "_" . time() . "." . $file_extension;
                $target_file = $target_dir . $new_filename;

                // Validate file
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                if (!in_array(strtolower($file_extension), $allowed_types)) {
                    throw new Exception("Only JPG, JPEG, PNG & GIF files are allowed");
                }

                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file)) {
                    $profile_photo = $target_file;
                } else {
                    throw new Exception("File upload failed");
                }
            }

            // Update users table
            $stmt = $db->prepare("UPDATE users SET 
                username = ?, 
                email = ?, 
                profile_photo = ?, 
                theme = ?, 
                notification_enabled = ?, 
                updated_at = NOW() 
                WHERE id = ?");

            $stmt->execute([
                $username,
                $email,
                $profile_photo,
                $theme,
                $notification_enabled,
                $user_id
            ]);

            $_SESSION['email'] = $email;
            $_SESSION['profile_photo'] = $profile_photo;
            $_SESSION['theme'] = $theme;

            $success = "Profile updated successfully!";
            header("Refresh:0");
        }

        // Password Change
        if (isset($_POST['update_password'])) {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];

            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                throw new Exception("All password fields are required");
            }

            if ($new_password !== $confirm_password) {
                throw new Exception("New passwords do not match");
            }

            // Verify current password
            if (!password_verify($current_password, $user['password'])) {
                throw new Exception("Current password is incorrect");
            }

            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Update password in the database
            $stmt = $db->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);

            $success = "Password updated successfully!";
        }

        // Account Deletion
        if (isset($_POST['delete_account'])) {
            $confirm_password = $_POST['confirm_password'];

            if (empty($confirm_password)) {
                throw new Exception("Password is required to delete account");
            }

            // Verify password
            if (!password_verify($confirm_password, $user['password'])) {
                throw new Exception("Password is incorrect");
            }

            // Delete user from the database
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);

            // Log out the user
            session_destroy();
            header('Location: goodbye.php');
            exit();
        }

    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle photo deletion
if (isset($_GET['delete_photo'])) {
    $profile_photo = 'uploads/default-avatar.png';
    $stmt = $db->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
    $stmt->execute([$profile_photo, $user_id]);
    $_SESSION['profile_photo'] = $profile_photo;
    header("Location: profile_settings.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings</title>
    <script src="https://unpkg.com/feather-icons"></script>
    
    <!-- Existing stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/settings.css">
    <link rel="stylesheet" href="assets/css/badges.css">
    <link rel="stylesheet" href="assets/css/orders.css">
    <link rel="stylesheet" href="assets/css/collapsed.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/orders.css" rel="stylesheet">
    <link href="assets/css/badges.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/inventory.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/collapsed.css">
    <script src="https://unpkg.com/feather-icons"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/settings.css">
    <style>
        .profile-photo-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 2rem;
        }

        .profile-photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--border-color);
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            background-color: var(--container-bg);
        }

        .upload-btn {
            padding: 0.5rem 1rem;
            background: var(--container-bg);
            border: 1px solid var(--border-color);
            border-radius: 0.25rem;
            cursor: pointer;
            transition: all 0.2s;
            color: var(--text-color);
        }

        .delete-section {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-color);
        }

        .delete-section h3 {
            color: #dc3545;
            font-size: 1.2rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include 'includes/theme.php'; ?>
    <nav class="navbar">
        <?php include 'includes/nav/collapsed.php'; ?>
    </nav>

    <div class="container mt-3">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="settings-card">
                    <h2 class="settings-title">Profile Settings</h2>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?= $success ?></div>
                    <?php endif; ?>

                    <!-- Profile Update Form -->
                    <form method="POST" enctype="multipart/form-data">
                        <div class="mb-3 profile-photo-container">
                            <img src="<?= htmlspecialchars($user['profile_photo']) ?>" 
                                 alt="Profile Photo" 
                                 class="profile-photo" 
                                 id="profilePhotoPreview">
                            <input type="file" 
                                   class="profile-photo-upload" 
                                   id="profile_photo" 
                                   name="profile_photo" 
                                   accept="image/*" 
                                   style="display: none;">
                            <label for="profile_photo" class="upload-btn">
                                <i class="fas fa-camera"></i> Change Photo
                            </label>
                            <?php if ($user['profile_photo'] != 'uploads/default-avatar.png'): ?>
                                <button type="button" class="btn btn-link text-danger" onclick="confirmPhotoDelete()">
                                    <i class="fas fa-trash"></i> Remove Photo
                                </button>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="username" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="username" name="username"
                                   value="<?= htmlspecialchars($user['username']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="theme" class="form-label">Theme Preference</label>
                            <select class="form-select" id="theme" name="theme">
                                <option value="light" <?= $user['theme'] === 'light' ? 'selected' : '' ?>>Light</option>
                                <option value="dark" <?= $user['theme'] === 'dark' ? 'selected' : '' ?>>Dark</option>
                            </select>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="notification_enabled"
                                   name="notification_enabled" <?= $user['notification_enabled'] ? 'checked' : '' ?>>
                            <label class="form-check-label" for="notification_enabled">
                                Enable Email Notifications
                            </label>
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-primary">
                            Save Profile Changes
                        </button>
                    </form>

                    <!-- Password Change Form -->
                    <form method="POST" class="mt-4">
                        <h3>Change Password</h3>
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" 
                                   name="current_password" required>
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" 
                                   name="new_password" required>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" 
                                   name="confirm_password" required>
                        </div>

                        <button type="submit" name="update_password" class="btn btn-warning">
                            Change Password
                        </button>
                    </form>

                    <!-- Account Deletion -->
                    <form method="POST" class="delete-section">
                        <h3>Delete Account</h3>
                        <p class="text-muted">Warning: This action cannot be undone. All your data will be permanently deleted.</p>
                        <div class="mb-3">
                            <label for="delete_confirm_password" class="form-label">
                                Enter your password to confirm deletion
                            </label>
                            <input type="password" class="form-control" id="delete_confirm_password" 
                                   name="confirm_password" required>
                        </div>
                        <button type="submit" name="delete_account" class="btn btn-danger"
                                onclick="return confirm('Are you sure you want to delete your account? This action cannot be undone.')">
                            Delete Account Permanently
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/nav/footer.php'; ?>

<script>
    // Profile photo preview
    document.getElementById('profile_photo').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('profilePhotoPreview').src = e.target.result;
            }
            reader.readAsDataURL(file);
        }
    });

    // Confirm photo deletion
    function confirmPhotoDelete() {
        if (confirm('Are you sure you want to remove your profile photo?')) {
            window.location.href = '?delete_photo=1';
        }
    }

    // Password validation
    document.querySelector('form[name="password_form"]').addEventListener('submit', function(e) {
        const newPass = document.getElementById('new_password').value;
        const confirmPass = document.getElementById('confirm_password').value;
        
        if (newPass !== confirmPass) {
            e.preventDefault();
            alert('New passwords do not match!');
        }
    });
</script>
</body>
</html>
