<?php
session_start();

// Move all includes to the top
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

// Initialize variables
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

            // Update session data
            $_SESSION['email'] = $email;
            $_SESSION['profile_photo'] = $profile_photo;
            $_SESSION['theme'] = $theme; // Keep session updated

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

        // Update marketing API settings
        if (isset($_POST['google_ads_id'])) {
            update_setting('google_ads_id', $_POST['google_ads_id']);
        }
        if (isset($_POST['google_analytics_id'])) {
            update_setting('google_analytics_id', $_POST['google_analytics_id']);
        }
        if (isset($_POST['facebook_pixel_id'])) {
            update_setting('facebook_pixel_id', $_POST['facebook_pixel_id']);
        }

        // Update shipping rates
        if (isset($_POST['update_shipping_rate'])) {
            $method = $_POST['method'];
            $rate = $_POST['rate'];
            
            if (updateShippingRate($db, $method, $rate)) {
                $success = "Shipping rate updated successfully!";
            } else {
                $error = "Error updating shipping rate.";
            }
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings</title>
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
    <link rel="stylesheet" href="assets/css/settings.css">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .profile-photo-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 2rem;
        }

        .profile-photo {
            width: 100px;  /* Changed from 150px */
            height: 100px; /* Changed from 150px */
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

        .upload-btn:hover {
            background: var(--feedback-bg);
        }

        .settings-container {
            background-color: var(--container-bg);
            color: var(--text-color);
        }

        .form-control, .form-select {
            background-color: var(--input-bg);
            border-color: var(--border-color);
            color: var(--text-color);
        }

        .form-control:focus, .form-select:focus {
            background-color: var(--input-bg);
            border-color: var(--primary-accent);
            color: var(--text-color);
        }
    </style>
</head>

<body>
<div class="admin-layout"> 
<?php include 'includes/theme.php'; ?>
    <nav class="navbar">
    <?php include 'includes/nav/collapsed.php'; ?>
    </nav>

    <div class="container-fluid mt-3">
        <div class="row gx-5"> <!-- Added gutter between columns -->
            <!-- Left Column - Main Settings -->
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
                                <i class="bi bi-camera"></i> Upload Photo
                            </label>
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

                    <!-- Password Change Form -->
                    <form method="POST" class="mt-4">
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

                    <!-- Account Deletion -->
                    <form method="POST" class="mt-4 delete-section">
                        <h3>Delete Account</h3>
                        <p>This action is permanent.
                            All your data will be deleted.</p>
                        <button type="submit" name="delete_account" class="btn btn-danger">Delete Account</button>
                    </form>
                </div>
            </div>

            <!-- Right Column - API & Payment Settings -->
            <div class="col-md-4">
                <!-- Marketing API Settings -->
                <div class="settings-card">
                    <h2 class="settings-title">API Settings</h2>
                    <form method="POST">
                        <div class="settings-form-group mb-3">
                            <label class="form-label">Google Ads API Key</label>
                            <input type="text" 
                                   class="form-control" 
                                   name="google_ads_id"
                                   value="<?= htmlspecialchars(get_setting('google_ads_id')) ?>">
                            <div class="form-text">Used for ad campaign tracking</div>
                        </div>

                        <div class="settings-form-group mb-3">
                            <label class="form-label">Google Analytics Key</label>
                            <input type="text" 
                                   class="form-control" 
                                   name="google_analytics_id"
                                   value="<?= htmlspecialchars(get_setting('google_analytics_id')) ?>">
                            <div class="form-text">Used for website analytics</div>
                        </div>

                        <div class="settings-form-group mb-3">
                            <label class="form-label">Facebook Pixel ID</label>
                            <input type="text" 
                                   class="form-control" 
                                   name="facebook_pixel_id"
                                   value="<?= htmlspecialchars(get_setting('facebook_pixel_id')) ?>">
                            <div class="form-text">Used for Facebook ad tracking</div>
                        </div>

                        <button type="submit" name="save_api" class="btn btn-primary w-100">
                            Save API Settings
                        </button>
                    </form>
                </div>

                <!-- Payment Settings -->
                <div class="settings-card mt-4">
                    <h2 class="settings-title">Payment Settings</h2>
                    
                    <!-- Credit Card Settings -->
                    <div class="payment-section mb-4">
                        <div class="payment-header">
                            <h3>Credit Card Payment</h3>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="enableStripe" name="stripe_enabled"
                                       <?= get_setting('stripe_enabled') ? 'checked' : '' ?>>
                                <label class="form-check-label" for="enableStripe">Enable</label>
                            </div>
                        </div>
                        <div class="payment-body">
                            <div class="mb-3">
                                <label class="form-label">Publishable Key</label>
                                <input type="text" class="form-control" name="stripe_public_key"
                                       value="<?= htmlspecialchars(get_setting('stripe_public_key')) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Secret Key</label>
                                <input type="password" class="form-control" name="stripe_secret_key"
                                       value="<?= htmlspecialchars(get_setting('stripe_secret_key')) ?>">
                            </div>
                        </div>
                    </div>

                    <!-- M-Pesa Settings -->
                    <div class="payment-section">
                        <div class="payment-header">
                            <h3>M-Pesa Integration</h3>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="enableMpesa" name="mpesa_enabled"
                                       <?= get_setting('mpesa_enabled') ? 'checked' : '' ?>>
                                <label class="form-check-label" for="enableMpesa">Enable</label>
                            </div>
                        </div>
                        <div class="payment-body">
                            <div class="mb-3">
                                <label class="form-label">Consumer Key</label>
                                <input type="text" class="form-control" name="mpesa_consumer_key"
                                       value="<?= htmlspecialchars(get_setting('mpesa_consumer_key')) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Consumer Secret</label>
                                <input type="password" class="form-control" name="mpesa_consumer_secret"
                                       value="<?= htmlspecialchars(get_setting('mpesa_consumer_secret')) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Pass Key</label>
                                <input type="password" class="form-control" name="mpesa_passkey"
                                       value="<?= htmlspecialchars(get_setting('mpesa_passkey')) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Business Short Code</label>
                                <input type="text" class="form-control" name="mpesa_shortcode"
                                       value="<?= htmlspecialchars(get_setting('mpesa_shortcode')) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Environment</label>
                                <select class="form-select" name="mpesa_environment">
                                    <option value="sandbox" <?= get_setting('mpesa_environment') === 'sandbox' ? 'selected' : '' ?>>Sandbox</option>
                                    <option value="live" <?= get_setting('mpesa_environment') === 'live' ? 'selected' : '' ?>>Live</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="update_payment" class="btn btn-primary w-100 mt-4">
                        Save Payment Settings
                    </button>
                </div>

                <!-- Shipping Rates -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Shipping Rates</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $shippingRates = getShippingRates($db);
                        foreach ($shippingRates as $rate): ?>
                            <form method="POST" class="row mb-3 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label"><?= htmlspecialchars($rate['shipping_method']) ?></label>
                                    <p class="text-muted small mb-0"><?= htmlspecialchars($rate['description']) ?></p>
                                </div>
                                <div class="col-md-4">
                                    <div class="input-group">
                                        <span class="input-group-text">Ksh.</span>
                                        <input type="number" name="rate" class="form-control" 
                                               value="<?= number_format($rate['base_rate'], 2) ?>" 
                                               step="0.01" min="0" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <input type="hidden" name="method" value="<?= htmlspecialchars($rate['shipping_method']) ?>">
                                    <button type="submit" name="update_shipping_rate" class="btn btn-primary">
                                        Update Rate
                                    </button>
                                </div>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/nav/footer.php'; ?>
</body>
<script>
    function confirmPhotoDelete() {
        if (confirm("Are you sure you want to delete your profile photo?")) {
            // Add logic to handle photo deletion
            window.location.href = '?delete_photo=1';
        }
    }

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
</script>

</html>