<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$successMsg = $errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $db->prepare("INSERT INTO suppliers (company_name, contact_person, email, phone, address, status) 
                             VALUES (?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $_POST['company_name'] ?? '',
            $_POST['contact_person'] ?? '',
            $_POST['email'] ?? '',
            $_POST['phone'] ?? '',
            $_POST['address'] ?? '',
            $_POST['status'] ?? 'Active'
        ]);

        header('Location: suppliers.php?success=added');
        exit();
    } catch (PDOException $e) {
        $errorMsg = "Error adding supplier: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Supplier - Zellow Enterprises</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <style>
        .form-section {
            background: var(--bs-white);
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .section-title {
            font-size: 1.5rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--bs-gray-300);
            color: inherit; /* This will inherit the theme text color */
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--bs-primary);
            box-shadow: 0 0 0 0.25rem rgba(var(--bs-primary-rgb), 0.25);
        }
        .form-label {
            font-weight: 500;
            color: var(--bs-gray-700);
        }
        .required::after {
            content: '*';
            color: var(--bs-danger);
            margin-left: 4px;
        }
        .button-container {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 2rem;
        }
        .button-container .btn {
            padding: 0.5rem 2rem;
            font-weight: 500;
        }
        .page-header {
            background: var(--bs-white);
            padding: 1rem 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        .page-title {
            color: var(--bs-gray-800);
            font-size: 1.75rem;
            margin: 0;
        }
    </style>
</head>
<?php include 'includes/theme.php'; ?>

<body class="admin-layout">
    <?php include 'includes/nav/collapsed.php'; ?>
    
    <div class="main-content">
        <div class="container mt-4">
            <div class="page-header">
                <h2 class="page-title">
                    <i class="fas fa-plus-circle me-2"></i>Add New Supplier
                </h2>
            </div>

            <?php if ($errorMsg): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
            <?php endif; ?>

            <form method="POST" class="needs-validation" novalidate>
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-building me-2"></i>Company Information
                    </h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="company_name" class="form-label required">Company Name</label>
                            <input type="text" class="form-control" id="company_name" name="company_name" required>
                        </div>
                        <div class="col-md-6">
                            <label for="contact_person" class="form-label required">Contact Person</label>
                            <input type="text" class="form-control" id="contact_person" name="contact_person" required>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-address-card me-2"></i>Contact Details
                    </h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="email" class="form-label required">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label for="phone" class="form-label required">Phone Number</label>
                            <input type="tel" class="form-control" id="phone" name="phone" required>
                        </div>
                        <div class="col-12">
                            <label for="address" class="form-label required">Business Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3" required></textarea>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-info-circle me-2"></i>Additional Information
                    </h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="status" class="form-label required">Supplier Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="button-container">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Supplier
                    </button>
                    <a href="suppliers.php" class="btn btn-danger">
                        <i class="fas fa-times me-2"></i>Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <?php include 'includes/nav/footer.php'; ?>
    <script>
        // Form validation
        (function() {
            'use strict';
            var forms = document.querySelectorAll('.needs-validation');
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
