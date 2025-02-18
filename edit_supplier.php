<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$error = $success = '';

// Initialize message variables
$successMsg = $_SESSION['success'] ?? '';
$errorMsg = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Fetch supplier details
if (!isset($_GET['supplier_id']) || !is_numeric($_GET['supplier_id'])) {
    header('Location: suppliers.php');
    exit();
}

$supplier_id = (int)$_GET['supplier_id'];

try {
    // Fetch supplier data
    $stmt = $db->prepare("
        SELECT 
            supplier_id,
            company_name,
            contact_person,
            email,
            phone,
            address,
            status,
            created_at
        FROM suppliers 
        WHERE supplier_id = ? 
        AND is_active = 1
        LIMIT 1
    ");
    
    $stmt->execute([$supplier_id]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$supplier) {
        $_SESSION['error'] = "Supplier not found";
        header('Location: suppliers.php');
        exit();
    }

} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header('Location: suppliers.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        $stmt = $db->prepare("
            UPDATE suppliers 
            SET 
                company_name = :company_name,
                contact_person = :contact_person,
                email = :email,
                phone = :phone,
                address = :address,
                status = :status,
                updated_at = CURRENT_TIMESTAMP
            WHERE supplier_id = :supplier_id
        ");

        $result = $stmt->execute([
            ':company_name' => $_POST['company_name'],
            ':contact_person' => $_POST['contact_person'],
            ':email' => $_POST['email'],
            ':phone' => $_POST['phone'],
            ':address' => $_POST['address'],
            ':status' => $_POST['status'],
            ':supplier_id' => $supplier_id
        ]);

        if (!$result) {
            throw new Exception("Failed to update supplier");
        }

        $db->commit();
        $_SESSION['success'] = "Supplier updated successfully";
        header("Location: suppliers.php");
        exit();

    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Supplier - Zellow Enterprises</title>
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

<div class="admin-layout"> 
<?php include 'includes/theme.php'; ?>
    <nav class="navbar">
    <?php include 'includes/nav/collapsed.php'; ?>
    </nav>
    
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
                            <input type="hidden" name="supplier_id" value="<?= htmlspecialchars($supplier['supplier_id']) ?>">
                            
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Company Name</label>
                                    <input type="text" 
                                           class="form-control" 
                                           name="company_name" 
                                           value="<?= htmlspecialchars($supplier['company_name']) ?>" 
                                           required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Contact Person</label>
                                    <input type="text" 
                                           class="form-control" 
                                           name="contact_person" 
                                           value="<?= htmlspecialchars($supplier['contact_person'] ?? '') ?>">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" 
                                           class="form-control" 
                                           name="email" 
                                           value="<?= htmlspecialchars($supplier['email'] ?? '') ?>">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Phone</label>
                                    <input type="tel" 
                                           class="form-control" 
                                           name="phone" 
                                           value="<?= htmlspecialchars($supplier['phone'] ?? '') ?>">
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Address</label>
                                    <textarea class="form-control" 
                                              name="address" 
                                              rows="3"><?= htmlspecialchars($supplier['address'] ?? '') ?></textarea>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status" required>
                                        <option value="Active" <?= $supplier['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
                                        <option value="Inactive" <?= $supplier['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>

                            <div class="card-footer mt-4">
                                <button type="submit" class="btn btn-primary">Update Supplier</button>
                                <a href="suppliers.php" class="btn btn-danger">Cancel</a>
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

