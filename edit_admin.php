<?php
session_start();

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Initialize error and success variables at the top level
$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';

// Clear session messages after retrieving them
unset($_SESSION['error'], $_SESSION['success']);

// Get the ID from URL using isset
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No user ID provided";
    header('Location: admins.php');
    exit();
}

$id = $_GET['id'];
error_log("Editing user ID: " . $id);

// Define valid roles before the database query
$validRoles = [
    'admin' => 'Admin',
    'finance_manager' => 'Finance Manager',
    'supply_manager' => 'Supply Manager',
    'inventory_manager' => 'Inventory Manager',
    'dispatch_manager' => 'Dispatch Manager',
    'service_manager' => 'Service Manager'
];

// Fetch admin details with proper ID field and null checks
try {
    $stmt = $db->prepare("
        SELECT 
            id,
            employee_number,
            username,
            email,
            role,
            is_active
        FROM users 
        WHERE id = :id
        AND role IN ('" . implode("','", array_keys($validRoles)) . "')
        LIMIT 1
    ");

    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if admin data exists and contains required fields
    if (!$admin || !isset($admin['id'], $admin['username'], $admin['email'], $admin['role'])) {
        throw new Exception("Invalid or incomplete admin data");
    }

    // Set default values for optional fields
    $admin['employee_number'] = $admin['employee_number'] ?? 'Not Assigned';
    $admin['is_active'] = isset($admin['is_active']) ? (int)$admin['is_active'] : 0;

    // Debug output
    error_log("Fetched admin data: " . print_r($admin, true));

} catch (Exception $e) {
    $_SESSION['error'] = "Error loading admin data: " . $e->getMessage();
    header('Location: admins.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        // Update query using correct id field
        $stmt = $db->prepare("
            UPDATE users 
            SET 
                username = ?,
                email = ?,
                role = ?,
                is_active = ?
            WHERE id = ?
        ");

        $result = $stmt->execute([
            $_POST['username'],
            $_POST['email'],
            $_POST['role'],
            isset($_POST['is_active']) ? 1 : 0,
            $id  // Use the correct id here
        ]);

        if (!$result) {
            throw new Exception("Failed to update employee");
        }

        $db->commit();
        $_SESSION['success'] = "Employee updated successfully";
        header("Location: edit_admin.php?id=" . $id);
        exit();

    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
}

// After successful update, refresh the data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $stmt = $db->prepare("
        SELECT 
            id,
            employee_number,
            username,
            email,
            role,
            is_active
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$id]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Admin</title>
    <!-- Include your existing CSS files -->
      <!-- Feather Icons - Add this line -->
    <script src="https://unpkg.com/feather-icons"></script>
    
    <!-- Existing stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
    <link rel="stylesheet" href="assets/css/badges.css">
    <link rel="stylesheet" href="assets/css/orders.css">
    <link rel="stylesheet" href="assets/css/collapsed.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        .content-wrapper {
            padding: 1rem;
            display: flex;
            justify-content: center;
            margin-left: 78px;
            width: calc(100% - 78px);
            transition: all 0.3s ease;
        }

        .form-card {
            width: 100%;
            max-width: 800px;
            margin: 20px auto;
            background: none;
            border: none;
        }

        .card-header {
            background: none;
            border-bottom: 1px solid var(--form-border);
            padding: 1rem 0;
        }

        :root {
            --form-border: #dee2e6;
            --input-bg: #ffffff;
            --input-border: #ced4da;
        }

        [data-bs-theme="dark"] {
            --form-border: #444;
            --input-bg: #1a1d20;
            --input-border: #444;
        }

        .form-control {
            background-color: var(--input-bg);
            border-color: var(--input-border);
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include 'includes/theme.php'; ?>
    <?php include 'includes/nav/collapsed.php'; ?>

    <div class="content-wrapper">
        <div class="form-card card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Edit Employee Information</h5>
                    <a href="admins.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Debug output -->
                <?php if (isset($_SESSION['debug'])): ?>
                    <div class="alert alert-info">
                        <pre><?= htmlspecialchars(print_r($admin, true)) ?></pre>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <form method="POST" class="needs-validation" novalidate>
                    <div class="row g-3">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Employee Number</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($admin['employee_number'] ?? 'Not Assigned') ?>" readonly>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($admin['username'] ?? '') ?>" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($admin['email'] ?? '') ?>" required>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" required>
                                <?php foreach ($validRoles as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value) ?>" 
                                            <?= ($admin['role'] === $value) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input type="checkbox" 
                                       name="is_active" 
                                       class="form-check-input" 
                                       id="is_active" 
                                       <?= ($admin['is_active'] ?? 0) == 1 ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">Active Account</label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 d-flex justify-content-end gap-2">
                        <a href="admins.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Update Employee</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
const observer = new MutationObserver((mutations) => {
    mutations.forEach((mutation) => {
        if (mutation.attributeName === 'data-bs-theme') {
            updateThemeStyles();
        }
    });
});

function updateThemeStyles() {
    const root = document.documentElement;
    document.querySelectorAll('.form-card').forEach(card => {
        card.style.backgroundColor = getComputedStyle(root).getPropertyValue('--form-bg');
        card.style.borderColor = getComputedStyle(root).getPropertyValue('--form-border');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    observer.observe(document.documentElement, {
        attributes: true,
        attributeFilter: ['data-bs-theme']
    });
    updateThemeStyles();
});
</script>
</body>
</html>