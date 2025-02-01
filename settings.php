<?php
session_start();

// Authentication check
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['id'];

// Fetch user data from users table
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found");
}

$error = $success = '';

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

            // Update session data
            $_SESSION['email'] = $email;

            $_SESSION['theme'] = $theme; // Keep session updated
            $stmt = $db->prepare("UPDATE users SET theme = ? WHERE id = ?");
            $stmt->execute([$theme, $user_id]);

            $success = "Profile updated successfully!";
            header("Refresh:0"); // Refresh to show changes
        }

        // Password Change
        if (isset($_POST['update_password'])) {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];

            if (empty($current_password) || empty($new_password) || $new_password !== $confirm_password) {
                throw new Exception("Passwords do not match or are empty");
            }

            if (!password_verify($current_password, $user['password'])) {
                throw new Exception("Incorrect current password");
            }

            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);

            $success = "Password updated successfully!";
        }

        // Account Deletion
        if (isset($_POST['delete_account'])) {
            // Verify password before deletion
            if (!password_verify($_POST['confirm_password'], $user['password'])) {
                throw new Exception("Incorrect password");
            }

            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);

            session_destroy();
            header('Location: login.php');
            exit();
        }

    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}


// Handle photo deletion
if (isset($_GET['delete_photo'])) {
    try {
        $default_photo = 'path/to/default-avatar.png';
        $stmt = $db->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
        $stmt->execute([$default_photo, $user_id]);
        $_SESSION['profile_photo'] = $default_photo;
        header("Location: settings.php");
        exit();
    } catch (Exception $e) {
        $error = "Error deleting photo: " . $e->getMessage();
    }
}
include 'includes/nav/navbar.php';
include 'includes/theme.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings</title>
    <link rel="stylesheet" href="assets/css/settings.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="settings-container">
        <div class="container mt-5">
            <h2>Admin Settings</h2>

            <!-- Display any error or success messages -->
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>

            <!-- Profile Update Form -->
            <form method="POST" enctype="multipart/form-data">
                <div class="mb-3 profile-photo-wrapper">
                    <div class="profile-photo-preview">
                        <img src="<?= $user['profile_photo'] ?>" alt="" class="profile-photo-preview">
                    </div>

                    <div class="photo-controls">
                        <div class="profile-photo-upload">
                            <label for="profile_photo">
                                <i class="bi bi-upload"></i> <!-- Bootstrap icon example -->
                                Upload Photo
                            </label>
                            <input type="file" id="profile_photo" name="profile_photo"
                                accept="image/png, image/jpeg, image/gif">
                            <button type="button" class="btn-delete-photo" onclick="confirmPhotoDelete()">
                                <i class="bi bi-trash"></i> <!-- Bootstrap icon example -->
                                Delete
                            </button>
                        </div>
                    </div>
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
                    <label for="theme" class="form-label">Theme</label>
                    <select class="form-control" id="theme" name="theme">
                        <option value="light" <?= $user['theme'] === 'light' ? 'selected' : '' ?>>Light</option>
                        <option value="dark" <?= $user['theme'] === 'dark' ? 'selected' : '' ?>>Dark</option>
                    </select>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="notification_enabled"
                        name="notification_enabled" <?= $user['notification_enabled'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="notification_enabled">Enable Notifications</label>
                </div>
                <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
            </form>
        </div>
        <!-- Password Change Form -->
        <div class="container mt-5">
            <form method="POST" class="mt-4">
                <h3>Change Password</h3>
                <div class="mb-3">
                    <label for="current_password" class="form-label">Current Password</label>
                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                </div>
                <div class="mb-3">
                    <label for="new_password" class="form-label">New Password</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" name="update_password" class="btn btn-primary">Update Password</button>
            </form>
        </div>
        <!-- Account Deletion -->
        <div class="container mt-5">
            <form method="POST" class="mt-4">
                <h3>Delete Account</h3>
                <p>This action is permanent.
                    All your data will be deleted.</p>
                <button type="submit" name="delete_account" class="btn btn-danger">Delete Account</button>
            </form>
        </div>
    </div>
</body>
<?php include 'includes/nav/footer.php'; ?>
</body>
<script>
    function confirmPhotoDelete() {
        if (confirm("Are you sure you want to delete your profile photo?")) {
            // Add logic to handle photo deletion
            window.location.href = '?delete_photo=1';
        }
    }
</script>

</html>