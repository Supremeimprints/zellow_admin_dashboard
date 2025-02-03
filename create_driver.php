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
    // Driver Information
    $driverName = $_POST['name'] ?? '';
    $driverEmail = $_POST['email'] ?? '';
    $driverPhone = $_POST['phone'] ?? '';
    $driverStatus = $_POST['status'] ?? 'Active';

    // Vehicle Information
    $vehicleType = $_POST['vehicle_type'] ?? '';
    $vehicleModel = $_POST['vehicle_model'] ?? '';
    $vehicleRegistration = $_POST['vehicle_registration'] ?? '';
    $vehicleStatus = $_POST['vehicle_status'] ?? 'Available';

    try {
        // Validate required fields
        $requiredFields = [
            'Driver Name' => $driverName,
            'Email' => $driverEmail,
            'Phone' => $driverPhone,
            'Vehicle Type' => $vehicleType,
            'Vehicle Model' => $vehicleModel,
            'Registration' => $vehicleRegistration
        ];

        foreach ($requiredFields as $field => $value) {
            if (empty($value)) {
                throw new Exception("$field is required");
            }
        }

        // Validate email format
        if (!filter_var($driverEmail, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }

        // Check for existing email
        $emailCheck = $db->prepare("SELECT COUNT(*) FROM drivers WHERE email = ?");
        $emailCheck->execute([$driverEmail]);
        if ($emailCheck->fetchColumn() > 0) {
            throw new Exception("Email already exists");
        }

        // Check for existing registration number
        $regCheck = $db->prepare("SELECT COUNT(*) FROM vehicles WHERE registration_number = ?");
        $regCheck->execute([$vehicleRegistration]);
        if ($regCheck->fetchColumn() > 0) {
            throw new Exception("Registration number already exists");
        }

        // Start transaction
        $db->beginTransaction();

        // Insert driver
        $driverStmt = $db->prepare("INSERT INTO drivers 
            (name, email, phone_number, status) 
            VALUES (?, ?, ?, ?)");

        $driverStmt->execute([
            $driverName,
            $driverEmail,
            $driverPhone,
            $driverStatus
        ]);

        $driverId = $db->lastInsertId();

        // Insert vehicle
        $vehicleStmt = $db->prepare("INSERT INTO vehicles 
            (driver_id, vehicle_type, vehicle_model, registration_number, vehicle_status) 
            VALUES (?, ?, ?, ?, ?)");

        $vehicleStmt->execute([
            $driverId,
            $vehicleType,
            $vehicleModel,
            $vehicleRegistration,
            $vehicleStatus
        ]);

        $db->commit();
        $successMessage = "Driver and vehicle created successfully!";
        $_POST = []; // Clear form fields
    } catch (Exception $e) {
        $db->rollBack();
        $errorMessage = $e->getMessage();
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
        }
        .form-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }
        h2, h4 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
        }
        .form-label {
            font-weight: 500;
        }
        .form-control, .form-select {
            font-family: 'Montserrat', sans-serif;
        }
    </style>
</head>

<body>
<?php include 'includes/nav/collapsed.php'; ?>
<?php include 'includes/theme.php'; ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 class="mb-4"><i class="bi bi-person-plus"></i> Create New Driver</h2>

                <?php if ($successMessage): ?>
                    <div class="alert alert-success"><?= $successMessage ?></div>
                <?php elseif ($errorMessage): ?>
                    <div class="alert alert-danger"><?= $errorMessage ?></div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <div class="form-section">
                        <h4 class="mb-3"><i class="bi bi-person-badge"></i> Driver Information</h4>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="name" name="name"
                                    value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email"
                                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone"
                                    value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="status" class="form-label">Driver Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="Active" <?= ($_POST['status'] ?? 'Active') === 'Active' ? 'selected' : '' ?>>Active</option>
                                    <option value="Inactive" <?= ($_POST['status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h4 class="mb-3"><i class="bi bi-truck"></i> Vehicle Information</h4>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="vehicle_type" class="form-label">Vehicle Type</label>
                                <select class="form-select" id="vehicle_type" name="vehicle_type" required>
                                    <option value="">Select Type</option>
                                    <option value="Car" <?= ($_POST['vehicle_type'] ?? '') === 'Car' ? 'selected' : '' ?>>
                                        Car</option>
                                    <option value="Van" <?= ($_POST['vehicle_type'] ?? '') === 'Van' ? 'selected' : '' ?>>
                                        Van</option>
                                    <option value="Truck" <?= ($_POST['vehicle_type'] ?? '') === 'Truck' ? 'selected' : '' ?>>Truck</option>
                                    <option value="Motorcycle" <?= ($_POST['vehicle_type'] ?? '') === 'Motorcycle' ? 'selected' : '' ?>>Motorcycle</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="vehicle_model" class="form-label">Vehicle Model</label>
                                <input type="text" class="form-control" id="vehicle_model" name="vehicle_model"
                                    value="<?= htmlspecialchars($_POST['vehicle_model'] ?? '') ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="vehicle_registration" class="form-label">Registration Number</label>
                                <input type="text" class="form-control" id="vehicle_registration"
                                    name="vehicle_registration"
                                    value="<?= htmlspecialchars($_POST['vehicle_registration'] ?? '') ?>" required>
                                <small class="form-text text-muted">Format: KAA 123A</small>
                            </div>

                            <div class="col-md-6">
                                <label for="vehicle_status" class="form-label">Vehicle Status</label>
                                <select class="form-select" id="vehicle_status" name="vehicle_status" required>
                                    <option value="Available" <?= ($_POST['vehicle_status'] ?? 'Available') === 'Available' ? 'selected' : '' ?>>Available</option>
                                    <option value="In Use" <?= ($_POST['vehicle_status'] ?? '') === 'In Use' ? 'selected' : '' ?>>In Use</option>
                                    <option value="Under Maintenance" <?= ($_POST['vehicle_status'] ?? '') === 'Under Maintenance' ? 'selected' : '' ?>>Under Maintenance</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-save"></i> Create Driver & Vehicle
                        </button>
                        <a href="admins.php" class="btn btn-danger btn-lg">
                            <i class="bi bi-arrow-left"></i> Back to Drivers
                        </a>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Phone number validation
        document.getElementById('phone').addEventListener('input', function (e) {
            this.value = this.value.replace(/[^0-9+]/g, '');
        });

        // Registration number formatting
        document.getElementById('vehicle_registration').addEventListener('input', function (e) {
            let value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
            if (value.length > 3) {
                value = value.substring(0, 3) + ' ' + value.substring(3);
            }
            this.value = value;
        });
    </script>
</body>
<?php include 'includes/nav/footer.php'; ?>

</html>