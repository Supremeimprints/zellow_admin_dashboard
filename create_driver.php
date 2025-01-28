<?php
session_start();
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$errorMessage = '';
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $driverName = $_POST['name'] ?? '';
    $driverEmail = $_POST['email'] ?? '';
    $driverPhone = $_POST['phone_number'] ?? '';
    $vehicleType = $_POST['vehicle_type'] ?? '';
    $vehicleModel = $_POST['vehicle_model'] ?? '';
    $vehicleRegistration = $_POST['registration_number'] ?? '';
    $vehicleStatus = $_POST['vehicle_status'] ?? 'Available'; // Default to Available status
    $status = $_POST['status'] ?? 'Active'; // Default to Active driver status

    // Validate input fields
    if ($driverName && $driverPhone && $driverEmail && $vehicleType && $vehicleModel && $vehicleRegistration) {
        try {
            // Check if the email already exists
            $checkEmailQuery = "SELECT COUNT(*) FROM drivers WHERE email = :email";
            $stmt = $db->prepare($checkEmailQuery);
            $stmt->bindParam(':email', $driverEmail);
            $stmt->execute();
            $emailCount = $stmt->fetchColumn();

            if ($emailCount > 0) {
                $errorMessage = "The email address is already in use.";
            } else {
                // Insert new driver into the database
                $query = "INSERT INTO drivers (name, email, phone_number, status) VALUES (:name, :email, :phone_number, :status)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':name', $driverName);
                $stmt->bindParam(':email', $driverEmail);
                $stmt->bindParam(':phone_number', $driverPhone);
                $stmt->bindParam(':status', $status);

                if ($stmt->execute()) {
                    $driverId = $db->lastInsertId(); // Get the driver ID after insertion
                    
                    // Insert vehicle details for the new driver
                    $vehicleQuery = "INSERT INTO vehicles (driver_id, vehicle_type, vehicle_model, registration_number, vehicle_status) 
                                     VALUES (:driver_id, :vehicle_type, :vehicle_model, :registration_number, :vehicle_status)";
                    $vehicleStmt = $db->prepare($vehicleQuery);
                    $vehicleStmt->bindParam(':driver_id', $driverId);
                    $vehicleStmt->bindParam(':vehicle_type', $vehicleType);
                    $vehicleStmt->bindParam(':vehicle_model', $vehicleModel);
                    $vehicleStmt->bindParam(':registration_number', $vehicleRegistration);
                    $vehicleStmt->bindParam(':vehicle_status', $vehicleStatus);

                    if ($vehicleStmt->execute()) {
                        $successMessage = "Driver and vehicle created successfully!";
                    } else {
                        $errorMessage = "Failed to assign vehicle to driver.";
                    }
                } else {
                    $errorMessage = "Failed to create driver.";
                }
            }
        } catch (Exception $e) {
            $errorMessage = "Error: " . $e->getMessage();
        }
    } else {
        $errorMessage = "All fields are required.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Driver</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-5">
        <h2>Create New Driver</h2>

        <!-- Success/Error Message -->
        <?php if ($successMessage): ?>
            <div class="alert alert-success"><?= $successMessage ?></div>
        <?php elseif ($errorMessage): ?>
            <div class="alert alert-danger"><?= $errorMessage ?></div>
        <?php endif; ?>

        <!-- Driver Creation Form -->
        <form action="create_driver.php" method="POST">
            <div class="mb-3">
                <label for="name" class="form-label">Driver Name</label>
                <input type="text" name="name" id="name" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" name="email" id="email" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="phone_number" class="form-label">Phone Number</label>
                <input type="text" name="phone_number" id="phone_number" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="status" class="form-label">Driver Status</label>
                <select name="status" id="status" class="form-select">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>

            <!-- Vehicle Information -->
            <h4 class="mt-4">Vehicle Information</h4>
            <div class="mb-3">
                <label for="vehicle_type" class="form-label">Vehicle Type</label>
                <select name="vehicle_type" id="vehicle_type" class="form-select" required>
                    <option value="Car">Car</option>
                    <option value="Van">Van</option>
                    <option value="Truck">Truck</option>
                    <option value="Motorcycle">Motorcycle</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="vehicle_model" class="form-label">Vehicle Model</label>
                <input type="text" name="vehicle_model" id="vehicle_model" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="registration_number" class="form-label">Vehicle Registration Number</label>
                <input type="text" name="registration_number" id="registration_number" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="vehicle_status" class="form-label">Vehicle Status</label>
                <select name="vehicle_status" id="vehicle_status" class="form-select">
                    <option value="Available">Available</option>
                    <option value="In Use">In Use</option>
                    <option value="Under Maintenance">Under Maintenance</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Create Driver and Assign Vehicle</button>
            <a href="dispatch.php" class="btn btn-secondary">Cancel</a>
        </form>
        
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php include 'footer.php'; ?>
</html>
