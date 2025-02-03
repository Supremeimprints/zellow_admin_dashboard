<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

// Check if user has admin access
if ($_SESSION['role'] !== 'admin') {
    echo "Access denied. You do not have permission to view this page.";
    exit();
}

// Initialize Database connection
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Add this after database connection
$current_user_query = "SELECT profile_photo FROM users WHERE id = ?";
$current_user_stmt = $db->prepare($current_user_query);
$current_user_stmt->execute([$_SESSION['id']]);
$current_user = $current_user_stmt->fetch(PDO::FETCH_ASSOC);

// Store current user's profile photo in session if not already set
if (!isset($_SESSION['profile_photo']) && $current_user) {
    $_SESSION['profile_photo'] = $current_user['profile_photo'];
}

// Check if the admin ID is provided
if (!isset($_GET['id'])) {
    echo "No admin ID specified.";
    exit();
}

$id = $_GET['id'];

// Fetch admin details
$query = "SELECT id, username, email, phone, address, role, is_active, profile_photo FROM users WHERE id = ? AND role IN ('admin', 'finance_manager', 'supply_manager', 'inventory_manager', 'dispatch_manager', 'service_manager')";
$stmt = $db->prepare($query);
$stmt->execute([$id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    echo "Admin not found.";
    exit();
}

// Initialize variables for form submission handling
$username = $email = $phone = $address = $role = $isActive = $hashedPassword = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Capture POST data
    $username = $_POST['username'] ?? $admin['username'];  // Default to current value if not posted
    $username = ucwords(strtolower(trim($_POST['username'])));
    $email = $_POST['email'] ?? $admin['email'];
    $phone = $_POST['phone'] ?? $admin['phone'];
    $address = $_POST['address'] ?? $admin['address'];
    $role = $_POST['role'] ?? $admin['role'];
    $isActive = isset($_POST['is_active']) ? 1 : 0;

    // Email format validation based on role
    switch ($role) {
        case 'admin':
            if (!preg_match('/^[a-zA-Z0-9._%+-]+@zellow\.com$/', $email)) {
                $error = "Invalid email format. The email must be in the format 'xyz@zellow.com' for admin roles.";
            }
            break;
        default:
            if (!preg_match('/^[a-zA-Z0-9._%+-]+@admin\.com$/', $email)) {
                $error = "Invalid email format. The email must be in the format 'xyz@admin.com' for other admin roles.";
            }
            break;
    }

    // If no error, proceed with updating the admin
    if (!isset($error)) {
        // Check if password is set for update
        $hashedPassword = (!empty($_POST['password'])) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;

        // Handle profile photo upload
        $profilePhoto = $admin['profile_photo'];
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $targetDir = "uploads/profile_photos/";
            $targetFile = $targetDir . basename($_FILES['profile_photo']['name']);
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $targetFile)) {
                $profilePhoto = $targetFile;
            } else {
                $error = "Failed to upload profile photo.";
            }
        }

        // Update query
        $updateQuery = "UPDATE users SET username = ?, email = ?, phone = ?, address = ?, password = ?, role = ?, is_active = ?, profile_photo = ? WHERE id = ?";
        $updateStmt = $db->prepare($updateQuery);

        // Bind values for update query
        if ($updateStmt->execute([$username, $email, $phone, $address, $hashedPassword, $role, $isActive, $profilePhoto, $id])) {
            header('Location: admins.php');
            exit();
        } else {
            $error = "Failed to update admin.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--background);
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .container {
            background-color: var(--background);
            padding: 2rem;
            margin-top: 2rem;
            flex: 1;
        }

        .form-section {
            background: var(--container-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
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
            box-shadow: 0 0 0 0.2rem rgba(var(--primary-rgb), 0.25);
        }

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

        .profile-photo-upload {
            display: none;
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

        .form-label {
            color: var(--text-color);
            font-weight: 500;
        }

        .btn-primary {
            background-color: var(--primary-accent);
            border-color: var(--primary-accent);
        }

        .btn-danger {
            background-color: var(--priority-high);
            border-color: var(--priority-high);
        }

        .form-check-input:checked {
            background-color: var(--primary-accent);
            border-color: var(--primary-accent);
        }

        h2, h4 {
            color: var(--text-color);
            font-weight: 600;
        }

        /* Remove any footer specific margins/padding */
        footer {
            margin-top: auto;
            background: var(--container-bg);
            border-top: 1px solid var(--border-color);
        }

        /* Remove box shadows and adjust spacing */
        .form-section + div:not(.form-section) {
            background: transparent;
            border: none;
            padding: 0;
            margin-top: 2rem;
        }

        /* Adjust main heading spacing */
        h2 {
            margin-bottom: 2rem;
        }
    </style>
    <link href="assets/css/admins.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/nav/collapsed.php'; ?>
<?php include 'includes/theme.php'; ?>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h2>Edit Admin</h2>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-section">
                    <h4 class="mb-3"><i class="bi bi-person-badge"></i> Admin Information</h4>
                    
                    <div class="profile-photo-container">
                        <img src="<?= htmlspecialchars($admin['profile_photo'] ?: 'assets/images/default-profile.png') ?>" 
                             alt="Profile Photo" 
                             class="profile-photo" 
                             id="profilePhotoPreview">
                        <input type="file" 
                               class="profile-photo-upload" 
                               id="profile_photo" 
                               name="profile_photo" 
                               accept="image/*">
                        <label for="profile_photo" class="upload-btn">
                            <i class="bi bi-camera"></i> Upload Photo
                        </label>
                    </div>

                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($admin['username']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="phone" class="form-label">Phone</label>
                        <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($admin['phone']); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($admin['address']); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="role" class="form-label">Role</label>
                        <select class="form-select" id="role" name="role" required>
                            <option value="admin" <?php echo $admin['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="finance_manager" <?php echo $admin['role'] === 'finance_manager' ? 'selected' : ''; ?>>Finance Manager</option>
                            <option value="supply_manager" <?php echo $admin['role'] === 'supply_manager' ? 'selected' : ''; ?>>Supply Manager</option>
                            <option value="inventory_manager" <?php echo $admin['role'] === 'inventory_manager' ? 'selected' : ''; ?>>Inventory Manager</option>
                            <option value="dispatch_manager" <?php echo $admin['role'] === 'dispatch_manager' ? 'selected' : ''; ?>>Dispatch Manager</option>
                            <option value="service_manager" <?php echo $admin['role'] === 'service_manager' ? 'selected' : ''; ?>>Service Manager</option>
                        </select>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" <?php echo $admin['is_active'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="is_active">
                            Active
                        </label>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Password (Leave blank to keep current)</label>
                        <input type="password" class="form-control" id="password" name="password">
                    </div>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-save"></i> Save Changes
                    </button>
                    <a href="admins.php" class="btn btn-danger btn-lg">
                        <i class="bi bi-x-circle"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
</script>
<?php include 'includes/nav/footer.php'; ?>
</body>
</html>
