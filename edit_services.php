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
    <!-- Feather Icons - Add this line -->
    <script src="https://unpkg.com/feather-icons"></script>

    <!-- Existing stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
    
    <link rel="stylesheet" href="assets/css/collapsed.css">
   
    <link href="assets/css/badges.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <style>
        .content-wrapper {
            padding: 1rem;
            display: flex;
            justify-content: center;
            margin-left: 78px; /* Adjust based on collapsed navbar width */
            width: calc(100% - 78px);
            transition: all 0.3s ease;
            overflow: none;
        }

        .form-card {
            width: 100%;
            overflow: none;
           min-height: none;
            background: none;
            border: none;
        }

        .card-header {
            background: none;
            border-bottom: 1px solid var(--form-border);
            padding: 1rem 0;
        }

       

        :root {
            --form-border: #dee2e6;
            --input-bg: #ffffff;
            --input-border: #ced4da;
        }

        [data-bs-theme="dark"] {
            --form-border: #444;
            --input-bg: #1a1d20;
            --input-border: #444;
        }

        .form-control {
            background-color: var(--input-bg);
            border-color: var(--input-border);
        }
    </style>
</head>

<body>
    <div class="admin-layout">
        <?php include 'includes/theme.php'; ?>
        <?php include 'includes/nav/collapsed.php'; ?>

        <div class="content-wrapper">
            <div class="form-card card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Edit Service</h5>
                        <a href="services.php" class="btn btn-secondary btn-sm">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mt-5">
                            <label for="name" class="form-label">Service Name</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="name" 
                                   name="name" 
                                   value="<?= htmlspecialchars($service['name']) ?>" 
                                   required>
                        </div>

                        <div class="mb-4">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" 
                                      id="description" 
                                      name="description" 
                                      rows="4" 
                                      required><?= htmlspecialchars($service['description']) ?></textarea>
                        </div>

                        <div class="mb-4">
                            <label for="price" class="form-label">Price (Ksh.)</label>
                            <input type="number" 
                                   class="form-control" 
                                   id="price" 
                                   name="price" 
                                   value="<?= htmlspecialchars($service['price']) ?>" 
                                   required>
                        </div>

                        <div class="d-flex justify-space-between gap-2">
                            <a href="services.php" class="btn btn-danger">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Service</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.attributeName === 'data-bs-theme') {
                    updateThemeStyles();
                }
            });
        });

        function updateThemeStyles() {
            const root = document.documentElement;
            document.querySelectorAll('.form-card').forEach(card => {
                card.style.backgroundColor = getComputedStyle(root).getPropertyValue('--form-bg');
                card.style.borderColor = getComputedStyle(root).getPropertyValue('--form-border');
            });
        }

        document.addEventListener('DOMContentLoaded', function () {
            observer.observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['data-bs-theme']
            });
            updateThemeStyles();
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php include 'includes/nav/footer.php'; ?>

</html>