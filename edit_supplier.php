<?php
session_start();
require_once 'config/database.php';

// Check for admin authentication
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Initialize variables
$successMsg = $errorMsg = '';
$supplier = null;

// Fetch supplier details
if (isset($_GET['id'])) {
    try {
        $stmt = $db->prepare("SELECT * FROM suppliers WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $supplier = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$supplier) {
            $errorMsg = "Supplier not found.";
        }
    } catch (PDOException $e) {
        $errorMsg = "Error fetching supplier: " . $e->getMessage();
    }
}

// Handle supplier update
if (isset($_POST['update_supplier']) && isset($_POST['id'])) {
    try {
        $stmt = $db->prepare("UPDATE suppliers SET company_name = ?, contact_person = ?, email = ?, phone = ?, status = ? WHERE id = ?");
        $stmt->execute([
            $_POST['company_name'],
            $_POST['contact_person'],
            $_POST['email'],
            $_POST['phone'],
            $_POST['status'],
            $_POST['id']
        ]);
        $successMsg = "Supplier updated successfully";
        header("Location: suppliers.php");
        exit();
    } catch (PDOException $e) {
        $errorMsg = "Error updating supplier: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Supplier - Zellow Enterprises</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <style>
        .container-full {
            width: 100%;
            max-width: none;
            padding: 0 15px;
        }
        .card-full {
            width: 100%;
            max-width: 800px;
            margin: auto;
            overflow: hidden;
            border-radius: 8px;
        }
        .card-body {
            padding: 20px;
            max-height: none;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .card-footer {
            padding: 15px;
            display: flex;
            justify-content: space-between;
            background: #f8f9fa;
            border-top: 1px solid #ddd;
        }
        .alert-primary {
            max-width: 800px;
            margin: auto;
        }
    </style>
</head>

<?php include 'includes/theme.php'; ?>

<body class="admin-layout">
    <?php include 'includes/nav/collapsed.php'; ?>
    
    <div class="main-content">
        <div class="container container-full mt-5">
            <div class="alert alert-primary" role="alert">
                <h4 class="mb-0">Edit Supplier</h4>
            </div>

            <?php if ($successMsg): ?>
                <div class="alert alert-success" style="max-width: 800px; margin: auto;"><?= htmlspecialchars($successMsg) ?></div>
            <?php endif; ?>

            <?php if ($errorMsg): ?>
                <div class="alert alert-danger" style="max-width: 800px; margin: auto;"><?= htmlspecialchars($errorMsg) ?></div>
            <?php endif; ?>

            <?php if ($supplier): ?>
                <div class="card card-full shadow-sm">
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($supplier['id']) ?>">
                            
                            <div class="mb-3">
                                <label for="company_name" class="form-label">Company Name</label>
                                <input type="text" class="form-control" id="company_name" name="company_name" value="<?= htmlspecialchars($supplier['company_name']) ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="contact_person" class="form-label">Contact Person</label>
                                <input type="text" class="form-control" id="contact_person" name="contact_person" value="<?= htmlspecialchars($supplier['contact_person']) ?>">
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($supplier['email']) ?>">
                            </div>

                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($supplier['phone']) ?>">
                            </div>

                            <div class="mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="Active" <?= $supplier['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
                                    <option value="Inactive" <?= $supplier['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>

                            <div class="card-footer">
                                <button type="submit" name="update_supplier" class="btn btn-primary" style="align-self: flex-start;">Update Supplier</button>
                                <a href="suppliers.php" class="btn btn-danger" style="align-self: flex-end;">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <?php include 'includes/nav/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

