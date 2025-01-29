<?php
session_start();

if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

// Ensure only admin can access this page
if ($_SESSION['role'] !== 'admin') {
    echo "Access denied. You do not have permission to dispatch orders.";
    exit();
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

// Check if order_id is provided
if (!isset($_GET['order_id'])) {
    header('Location: dispatch.php');
    exit();
}

$order_id = $_GET['order_id'];

// Fetch order details including payment status
try {
    $stmt = $db->prepare("SELECT * FROM orders WHERE order_id = :order_id");
    $stmt->bindParam(':order_id', $order_id, PDO::PARAM_INT);
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        header('Location: dispatch.php');
        exit();
    }

    // Check if order is eligible for dispatch (Paid or Pending payment status)
    if (!in_array($order['payment_status'], ['Paid', 'Pending'])) {
        $_SESSION['error'] = "Order cannot be dispatched - Invalid payment status";
        header('Location: dispatch.php');
        exit();
    }

} catch (Exception $e) {
    $_SESSION['error'] = "Error fetching order details: " . $e->getMessage();
    header('Location: dispatch.php');
    exit();
}

// Fetch available drivers (those with 'Available' vehicle status)
try {
    $stmt = $db->prepare("
        SELECT d.*, v.vehicle_type, v.vehicle_status 
        FROM drivers d
        LEFT JOIN vehicles v ON d.driver_id = v.driver_id
        WHERE d.status = 'Active' 
        AND (v.vehicle_status = 'Available' OR v.vehicle_status IS NULL)
    ");
    $stmt->execute();
    $available_drivers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Error fetching available drivers: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $driver_id = $_POST['driver_id'] ?? '';
    $tracking_number = '#' . date('Ymd') . rand(1000, 9999);

    if (empty($driver_id)) {
        $error = "Please select a driver";
    } else {
        try {
            $db->beginTransaction();

            // Update order status and assign driver
            $stmt = $db->prepare("
                UPDATE orders 
                SET status = 'Shipped',
                    driver_id = :driver_id,
                    tracking_number = :tracking_number
                WHERE order_id = :order_id
            ");
            $stmt->execute([
                ':driver_id' => $driver_id,
                ':tracking_number' => $tracking_number,
                ':order_id' => $order_id
            ]);

            // Update vehicle status to 'In Use'
            $stmt = $db->prepare("
                UPDATE vehicles 
                SET vehicle_status = 'In Use' 
                WHERE driver_id = :driver_id
            ");
            $stmt->execute([':driver_id' => $driver_id]);

            $db->commit();
            $_SESSION['success'] = "Order #$order_id has been dispatched successfully!";
            header('Location: dispatch.php');
            exit();

        } catch (Exception $e) {
            $db->rollBack();
            $error = "Error dispatching order: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispatch Order #<?= htmlspecialchars($order_id) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
<?php include 'includes/nav/navbar.php'; ?>
    <div class="container mt-4">
        <h2>Dispatch Order #<?= htmlspecialchars($order_id) ?></h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- Order Details Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Order Details</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Customer:</strong>
                            <?= !empty($order['username']) ? htmlspecialchars($order['username']) : 'Unknown' ?></p>
                        <p><strong>Shipping Address:</strong> <?= htmlspecialchars($order['shipping_address']) ?></p>
                        <p><strong>Payment Status:</strong> <?= htmlspecialchars($order['payment_status']) ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Order Date:</strong> <?= date('M j, Y H:i', strtotime($order['order_date'])) ?></p>
                        <p><strong>Total Amount:</strong> Ksh.<?= number_format($order['total_amount'], 2) ?></p>
                        <p><strong>Shipping Method:</strong> <?= htmlspecialchars($order['shipping_method']) ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Driver Assignment Form -->
        <form method="POST" class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Assign Driver</h5>
            </div>
            <div class="card-body">
                <?php if (empty($available_drivers)): ?>
                    <div class="alert alert-warning">No available drivers found.</div>
                <?php else: ?>
                    <div class="mb-3">
                        <label for="driver_id" class="form-label">Select Driver</label>
                        <select name="driver_id" id="driver_id" class="form-select" required>
                            <option value="">Choose a driver...</option>
                            <?php foreach ($available_drivers as $driver): ?>
                                <option value="<?= $driver['driver_id'] ?>">
                                    <?= htmlspecialchars($driver['name']) ?>
                                    (<?= htmlspecialchars($driver['vehicle_type'] ?? 'No vehicle assigned') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Dispatch Order</button>
                <?php endif; ?>
                <a href="dispatch.php" class="btn btn-secondary ms-2">Cancel</a>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php include 'includes/nav/footer.php'; ?>

</html>