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

$service_id = $_GET['id'] ?? null;
$error = '';
$success = '';

if (!$service_id) {
    header('Location: services.php');
    exit();
}

// Fetch service details
$stmt = $db->prepare("SELECT * FROM services WHERE id = ?");
$stmt->execute([$service_id]);
$service = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    header('Location: services.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $price = $_POST['price'] ?? '';

    if ($name && $description && $price) {
        $stmt = $db->prepare("UPDATE services SET name = ?, description = ?, price = ? WHERE id = ?");
        if ($stmt->execute([$name, $description, $price, $service_id])) {
            header('Location: services.php');
            exit();
        } else {
            $error = "Failed to update service.";
        }
    } else {
        $error = "All fields are required.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Service</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/services.css" rel="stylesheet">
</head>
<body>
<?php include 'includes/nav/navbar.php'; ?>
<?php include 'includes/theme.php'; ?>

<div class="container mt-5">
    <h2>Edit Service</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="edit_services.php?id=<?= htmlspecialchars($service_id) ?>">
        <div class="mb-3">
            <label for="name" class="form-label">Service Name</label>
            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($service['name']) ?>" required>
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description" rows="3" required><?= htmlspecialchars($service['description']) ?></textarea>
        </div>
        <div class="mb-3">
            <label for="price" class="form-label">Price (Ksh.)</label>
            <input type="number" class="form-control" id="price" name="price" value="<?= htmlspecialchars($service['price']) ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">Update Service</button>
    </form>

    <a href="services.php" class="btn btn-secondary mt-3">Return to Services</a>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php include 'includes/nav/footer.php'; ?>
</html>
