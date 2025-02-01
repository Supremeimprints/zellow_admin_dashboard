<?php
session_start();

// Check if user is logged in as an admin
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Handle form submissions for adding, editing, and deleting services
$action = $_GET['action'] ?? null;
$service_id = $_GET['id'] ?? null;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? 0;

    if ($action === 'add') {
        $stmt = $db->prepare("INSERT INTO services (name, description, price) VALUES (?, ?, ?)");
        $stmt->execute([$name, $description, $price]);
        $success = "Service added successfully!";
    } elseif ($action === 'edit' && $service_id) {
        $stmt = $db->prepare("UPDATE services SET name = ?, description = ?, price = ? WHERE id = ?");
        $stmt->execute([$name, $description, $price, $service_id]);
        $success = "Service updated successfully!";
    } elseif ($action === 'delete' && $service_id) {
        $stmt = $db->prepare("DELETE FROM services WHERE id = ?");
        $stmt->execute([$service_id]);
        $success = "Service deleted successfully!";
    }
}

// Fetch all services
$stmt = $db->prepare("SELECT * FROM services");
$stmt->execute();
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Services</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/services.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/nav/navbar.php'; ?>
<?php include 'includes/theme.php'; ?>
<div class="container mt-5">
    <h2>Manage Services</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <a href="services.php?action=add" class="btn btn-primary mb-3">Add New Service</a>

    <table class="table table-striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Description</th>
                <th>Price</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($services as $service): ?>
                <tr>
                    <td><?= htmlspecialchars($service['id']) ?></td>
                    <td><?= htmlspecialchars($service['name']) ?></td>
                    <td><?= htmlspecialchars($service['description']) ?></td>
                    <td><?= htmlspecialchars($service['price']) ?></td>
                    <td>
                        <a href="services.php?action=edit&id=<?= $service['id'] ?>" class="btn btn-warning btn-sm">Edit</a>
                        <form method="POST" action="services.php?action=delete&id=<?= $service['id'] ?>" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this service?');">
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($action === 'add' || ($action === 'edit' && $service_id)): ?>
        <?php
        $service = ['name' => '', 'description' => '', 'price' => 0];
        if ($action === 'edit' && $service_id) {
            $stmt = $db->prepare("SELECT * FROM services WHERE id = ?");
            $stmt->execute([$service_id]);
            $service = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        ?>
        <form method="POST" class="mt-4">
            <div class="mb-3">
                <label for="name" class="form-label">Service Name</label>
                <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($service['name']) ?>" required>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea class="form-control" id="description" name="description" required><?= htmlspecialchars($service['description']) ?></textarea>
            </div>
            <div class="mb-3">
                <label for="price" class="form-label">Price</label>
                <input type="number" class="form-control" id="price" name="price" value="<?= htmlspecialchars($service['price']) ?>" required>
            </div>
            <button type="submit" class="btn btn-primary"><?= $action === 'add' ? 'Add Service' : 'Update Service' ?></button>
            <a href="services.php" class="btn btn-secondary">Cancel</a>
        </form>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php include 'includes/nav/footer.php'; ?>
</html>
