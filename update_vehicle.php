<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$errorMsg = '';
$successMsg = '';
$driver_id = '';
$vehicle = null;

// First, check if we're getting driver_id from URL (for loading the form)
if (isset($_GET['driver_id'])) {
    $driver_id = $_GET['driver_id'];
    
    // Fetch existing vehicle data if any
    $stmt = $db->prepare("SELECT * FROM vehicles WHERE driver_id = ?");
    $stmt->execute([$driver_id]);
    $vehicle = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Fetch driver details
    $driverStmt = $db->prepare("SELECT name FROM drivers WHERE driver_id = ?");
    $driverStmt->execute([$driver_id]);
    $driver = $driverStmt->fetch(PDO::FETCH_ASSOC);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $driver_id = $_POST['driver_id'] ?? '';
        $vehicle_type = $_POST['vehicle_type'] ?? '';
        $vehicle_model = $_POST['vehicle_model'] ?? '';
        $registration = $_POST['registration'] ?? '';
        $vehicle_status = $_POST['vehicle_status'] ?? '';
        
        // Validation
        if (empty($driver_id) || empty($vehicle_type) || empty($vehicle_model) || 
            empty($registration) || empty($vehicle_status)) {
            throw new Exception("All fields are required");
        }
        
        // Check if vehicle exists for this driver
        $checkStmt = $db->prepare("SELECT vehicle_id FROM vehicles WHERE driver_id = ?");
        $checkStmt->execute([$driver_id]);
        $existingVehicle = $checkStmt->fetch();
        
        if ($existingVehicle) {
            // Update existing vehicle
            $stmt = $db->prepare("UPDATE vehicles SET 
                vehicle_type = ?, 
                vehicle_model = ?, 
                registration_number = ?, 
                vehicle_status = ? 
                WHERE driver_id = ?");
            
            $stmt->execute([
                $vehicle_type,
                $vehicle_model,
                $registration,
                $vehicle_status,
                $driver_id
            ]);
        } else {
            // Insert new vehicle
            $stmt = $db->prepare("INSERT INTO vehicles 
                (driver_id, vehicle_type, vehicle_model, registration_number, vehicle_status) 
                VALUES (?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $driver_id,
                $vehicle_type,
                $vehicle_model,
                $registration,
                $vehicle_status
            ]);
        }
        
        $successMsg = "Vehicle information updated successfully";
        
        // Redirect after successful update
        header("Location: dispatch.php?success=vehicle_updated");
        exit();
        
    } catch (Exception $e) {
        $errorMsg = "Vehicle update failed: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Vehicle Information</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/nav/navbar.php'; ?>
    
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="card-title mb-0">Update Vehicle Information</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($errorMsg): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
                        <?php endif; ?>
                        
                        <?php if ($successMsg): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="update_vehicle.php">
                            <input type="hidden" name="driver_id" value="<?= htmlspecialchars($driver_id) ?>">
                            
                            <?php if (isset($driver['name'])): ?>
                                <div class="alert alert-info">
                                    Updating vehicle for driver: <?= htmlspecialchars($driver['name']) ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="vehicle_type" class="form-label">Vehicle Type</label>
                                <select class="form-select" name="vehicle_type" id="vehicle_type" required>
                                    <option value="">Select Vehicle Type</option>
                                    <option value="Car" <?= ($vehicle['vehicle_type'] ?? '') === 'Car' ? 'selected' : '' ?>>Car</option>
                                    <option value="Motorcycle" <?= ($vehicle['vehicle_type'] ?? '') === 'Motorcycle' ? 'selected' : '' ?>>Motorcycle</option>
                                    <option value="Van" <?= ($vehicle['vehicle_type'] ?? '') === 'Van' ? 'selected' : '' ?>>Van</option>
                                    <option value="Truck" <?= ($vehicle['vehicle_type'] ?? '') === 'Truck' ? 'selected' : '' ?>>Truck</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="vehicle_model" class="form-label">Vehicle Model</label>
                                <input type="text" class="form-control" name="vehicle_model" id="vehicle_model"
                                    value="<?= htmlspecialchars($vehicle['vehicle_model'] ?? '') ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="registration" class="form-label">Registration Number</label>
                                <input type="text" class="form-control" name="registration" id="registration"
                                    value="<?= htmlspecialchars($vehicle['registration_number'] ?? '') ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="vehicle_status" class="form-label">Vehicle Status</label>
                                <select class="form-select" name="vehicle_status" id="vehicle_status" required>
                                    <option value="">Select Status</option>
                                    <option value="Available" <?= ($vehicle['vehicle_status'] ?? '') === 'Available' ? 'selected' : '' ?>>Available</option>
                                    <option value="In Use" <?= ($vehicle['vehicle_status'] ?? '') === 'In Use' ? 'selected' : '' ?>>In Use</option>
                                    <option value="Under Maintenance" <?= ($vehicle['vehicle_status'] ?? '') === 'Under Maintenance' ? 'selected' : '' ?>>Under Maintenance</option>
                                </select>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="dispatch.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary">Update Vehicle</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>