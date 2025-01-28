<?php
// Connect to the database
$pdo = new PDO('mysql:host=localhost;dbname=zellow_enterprises', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Fetch stats
$orders_sent_today = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_date = CURDATE()")->fetchColumn();
$pending_deliveries = $pdo->query("SELECT COUNT(*) FROM dispatch WHERE status = 'Pending'")->fetchColumn();
$completed_deliveries = $pdo->query("SELECT COUNT(*) FROM dispatch WHERE status = 'Delivered'")->fetchColumn();
$drivers_available = $pdo->query("SELECT COUNT(*) FROM drivers WHERE status = 'Available'")->fetchColumn();

// Fetch drivers list
$drivers = $pdo->query("SELECT driver_id, name, vehicle_type, status, assigned_orders FROM drivers")->fetchAll();

// Fetch order shipment progress
$shipments = $pdo->query("SELECT d.dispatch_id, d.status, d.delivery_date, d.tracking_number, o.order_id, dr.name as driver_name
                          FROM dispatch d
                          JOIN orders o ON d.order_id = o.order_id
                          JOIN drivers dr ON d.driver_id = dr.driver_id")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispatch Overview</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <!-- Stats Cards -->
        <div class="stats-cards">
            <div class="card">
                <h4>Orders Sent Today</h4>
                <p><?php echo $orders_sent_today; ?></p>
            </div>
            <div class="card">
                <h4>Pending Deliveries</h4>
                <p><?php echo $pending_deliveries; ?></p>
            </div>
            <div class="card">
                <h4>Completed Deliveries</h4>
                <p><?php echo $completed_deliveries; ?></p>
            </div>
            <div class="card">
                <h4>Drivers Available</h4>
                <p><?php echo $drivers_available; ?></p>
            </div>
        </div>

        <!-- Driver List Table -->
        <h2>Drivers List</h2>
        <?php if (count($drivers) > 0): ?>
        <table class="driver-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Vehicle Type</th>
                    <th>Status</th>
                    <th>Assigned Orders</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($drivers as $driver): ?>
                <tr>
                    <td><a href="view-driver.php?driver_id=<?php echo $driver['driver_id']; ?>"><?php echo $driver['name']; ?></a></td>
                    <td><?php echo $driver['vehicle_type']; ?></td>
                    <td><?php echo $driver['status']; ?></td>
                    <td><?php echo $driver['assigned_orders']; ?></td>
                    <td><a href="edit-driver.php?driver_id=<?php echo $driver['driver_id']; ?>">Edit</a> | <a href="delete-driver.php?driver_id=<?php echo $driver['driver_id']; ?>">Delete</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="alert">
            <p>No drivers found in the database.</p>
        </div>
        <?php endif; ?>

        <!-- Order Shipment Progress Table -->
        <h2>Order Shipment Progress</h2>
        <?php if (count($shipments) > 0): ?>
        <table class="shipment-progress">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Dispatch Status</th>
                    <th>Delivery Date</th>
                    <th>Tracking Number</th>
                    <th>Driver</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($shipments as $shipment): ?>
                <tr>
                    <td><?php echo $shipment['order_id']; ?></td>
                    <td><?php echo $shipment['status']; ?></td>
                    <td><?php echo $shipment['delivery_date']; ?></td>
                    <td><?php echo $shipment['tracking_number']; ?></td>
                    <td><?php echo $shipment['driver_name']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="alert">
            <p>No order shipment progress found in the database.</p>
        </div>
        <?php endif; ?>

        <!-- Button to Create New Driver or Assign Orders -->
        <div class="actions">
            <a href="create-driver.php" class="btn">Create New Driver</a>
            <a href="create-order.php" class="btn">Assign Orders</a>
        </div>
    </div>
</body>
</html>
