<?php
session_start();

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';
$driver = [];
$vehicle = [];

// Fetch driver details
if (isset($_GET['driver_id'])) {
    try {
        $stmt = $db->prepare("
            SELECT d.*, v.* 
            FROM drivers d
            LEFT JOIN vehicles v ON d.driver_id = v.driver_id
            WHERE d.driver_id = ?
        ");
        $stmt->execute([$_GET['driver_id']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $driver = $result;
            $vehicle = [
                'vehicle_type' => $result['vehicle_type'] ?? '',
                'vehicle_model' => $result['vehicle_model'] ?? '',
                'registration_number' => $result['registration_number'] ?? '',
                'vehicle_status' => $result['vehicle_status'] ?? 'Available'
            ];
        } else {
            header('Location: dispatch.php');
            exit();
        }
    } catch (PDOException $e) {
        $error = "Error fetching driver details: " . $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $driver_id = $_POST['driver_id'];
    
    // Validate input
    $required = ['name', 'email', 'phone_number'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            $error = "All required fields must be filled";
            break;
        }
    }
    
    if (!$error) {
        try {
            $db->beginTransaction();
            
            // Update driver
            $stmt = $db->prepare("
                UPDATE drivers SET 
                    name = ?,
                    email = ?,
                    phone_number = ?,
                    status = ?
                WHERE driver_id = ?
            ");
            $stmt->execute([
                $_POST['name'],
                $_POST['email'],
                $_POST['phone_number'],
                $_POST['status'],
                $driver_id
            ]);
            
            // Update vehicle
            $stmt = $db->prepare("
                INSERT INTO vehicles (
                    driver_id,
                    vehicle_type,
                    vehicle_model,
                    registration_number,
                    vehicle_status
                ) VALUES (
                    ?, ?, ?, ?, ?
                )
                ON DUPLICATE KEY UPDATE
                    vehicle_type = VALUES(vehicle_type),
                    vehicle_model = VALUES(vehicle_model),
                    registration_number = VALUES(registration_number),
                    vehicle_status = VALUES(vehicle_status)
            ");
            $stmt->execute([
                $driver_id,
                $_POST['vehicle_type'],
                $_POST['vehicle_model'],
                $_POST['registration_number'],
                $_POST['vehicle_status']
            ]);
            
            $db->commit();
            $success = "Driver updated successfully";
            header("Refresh:2; url=dispatch.php");
            
        } catch (PDOException $e) {
            $db->rollBack();
            $error = "Update failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Driver</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/dispatch.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/nav/navbar.php'; ?>
    <div class="container mt-4">
        <h2>Edit Driver: <?= htmlspecialchars($driver['name'] ?? '') ?></h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="driver_id" value="<?= $driver['driver_id'] ?? '' ?>">
            
            <div class="card mb-4">
                <div class="card-header">Driver Information</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="name" class="form-control" 
                                   value="<?= htmlspecialchars($driver['name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($driver['email'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone_number" class="form-control" 
                                   value="<?= htmlspecialchars($driver['phone_number'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" required>
                                <option value="Active" <?= ($driver['status'] ?? '') === 'Active' ? 'selected' : '' ?>>Active</option>
                                <option value="Inactive" <?= ($driver['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">Vehicle Information</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Vehicle Type</label>
                            <input type="text" name="vehicle_type" class="form-control" 
                                   value="<?= htmlspecialchars($vehicle['vehicle_type'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Model</label>
                            <input type="text" name="vehicle_model" class="form-control" 
                                   value="<?= htmlspecialchars($vehicle['vehicle_model'] ?? '') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Registration Number</label>
                            <input type="text" name="registration_number" class="form-control" 
                                   value="<?= htmlspecialchars($vehicle['registration_number'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Vehicle Status</label>
                            <select name="vehicle_status" class="form-select">
                                <option value="Available" <?= ($vehicle['vehicle_status'] ?? '') === 'Available' ? 'selected' : '' ?>>Available</option>
                                <option value="In Use" <?= ($vehicle['vehicle_status'] ?? '') === 'In Use' ? 'selected' : '' ?>>In Use</option>
                                <option value="Under Maintenance" <?= ($vehicle['vehicle_status'] ?? '') === 'Under Maintenance' ? 'selected' : '' ?>>Maintenance</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="dispatch.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>