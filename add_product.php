<?php
session_start();

if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Admin verification
if ($_SESSION['role'] !== 'admin') {
    echo "Access denied. You do not have permission to view this page.";
    exit();
}

// Fetch available categories
$category_query = "SELECT * FROM categories";
$category_stmt = $db->prepare($category_query);
$category_stmt->execute();
$categories = $category_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch suppliers for dropdown
$suppliersQuery = "SELECT supplier_id, company_name FROM suppliers WHERE status = 'Active'";
$suppliers = $db->query($suppliersQuery)->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        $name = $_POST['product_name'];
        $description = $_POST['description'];
        $price = $_POST['price'];
        $category_id = $_POST['category_id'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // Create uploads directory if it doesn't exist
        $upload_dir = 'uploads/products/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Function to handle image upload
        function handleImageUpload($file) {
            global $upload_dir;
            if ($file['error'] === UPLOAD_ERR_OK) {
                $temp_name = $file['tmp_name'];
                $name = basename($file['name']);
                $file_ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $new_name = uniqid() . '.' . $file_ext;
                $destination = $upload_dir . $new_name;
                
                if (move_uploaded_file($temp_name, $destination)) {
                    return $destination;
                }
            }
            return null;
        }

        // Handle image uploads
        $main_image = isset($_FILES['main_image']) ? handleImageUpload($_FILES['main_image']) : null;
        $variant_image_1 = isset($_FILES['variant_image_1']) ? handleImageUpload($_FILES['variant_image_1']) : null;
        $variant_image_2 = isset($_FILES['variant_image_2']) ? handleImageUpload($_FILES['variant_image_2']) : null;

        // Insert product into database
        $stmt = $db->prepare("
            INSERT INTO products (
                product_name, description, price, 
                category_id, supplier_id, moq, lead_time,
                stock_quantity, main_image, variant_image_1, 
                variant_image_2, is_active, last_updated_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $_POST['product_name'],
            $_POST['description'],
            $_POST['price'],
            $_POST['category_id'],
            $_POST['supplier_id'],
            $_POST['moq'],
            $_POST['lead_time'],
            0, // Initial stock
            $main_image ?? null,
            $variant_image_1 ?? null,
            $variant_image_2 ?? null,
            isset($_POST['is_active']) ? 1 : 0,
            $_SESSION['id']
        ]);

        $product_id = $db->lastInsertId();

        // Create initial inventory record
        $inventoryStmt = $db->prepare("
            INSERT INTO inventory (
                product_id, 
                stock_quantity, 
                min_stock_level,
                last_restocked,
                updated_by
            ) VALUES (?, ?, ?, NOW(), ?)
        ");

        $inventoryStmt->execute([
            $product_id,
            0, // Initial stock quantity
            $_POST['min_stock_level'] ?? 0, // Get from form
            $_SESSION['id']
        ]);

        $db->commit();
        $_SESSION['success'] = "Product and inventory record added successfully!";
        header("Location: products.php");
        exit();

    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - Zellow Enterprises</title>
     <!-- Feather Icons - Add this line -->
     <script src="https://unpkg.com/feather-icons"></script>
    
    <!-- Existing stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/badges.css">
    <link rel="stylesheet" href="assets/css/orders.css">
    <link rel="stylesheet" href="assets/css/collapsed.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/orders.css" rel="stylesheet">
    <link href="assets/css/badges.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
        }
        .image-preview {
            width: 150px;
            height: 150px;
            margin: 10px 0;
            position: relative;
        }
        .image-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
        }
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 600;
        }
        .form-label {
            font-weight: 500;
        }
        .remove-image {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(255, 255, 255, 0.9);
            border: none;
            border-radius: 50%;
            padding: 5px;
            cursor: pointer;
        }
        .upload-placeholder {
            width: 150px;
            height: 150px;
            border: 2px dashed #ccc;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .upload-placeholder.main-image {
            width: 200px;
            height: 200px;
        }
        .image-preview.main-image {
            width: 200px;
            height: 200px;
        }
    </style>
</head>
<body>
<div class="admin-layout"> 
<?php include 'includes/theme.php'; ?>
    <nav class="navbar">
    <?php include 'includes/nav/collapsed.php'; ?>
    </nav>
<div class="container mt-5">
    <h2>Add Product</h2>
    
    <form method="POST" enctype="multipart/form-data">
        <div class="row mb-4">
            <div class="col-12">
                <label class="form-label">Media</label>
                <div class="d-flex gap-3 align-items-start">
                    <!-- Main Image -->
                    <div>
                        <input type="file" id="main_image" name="main_image" class="d-none" accept="image/*">
                        <div class="upload-placeholder main-image" onclick="document.getElementById('main_image').click()">
                            <span>+ Add Main Image</span>
                        </div>
                    </div>

                    <!-- Variant Image 1 -->
                    <div>
                        <input type="file" id="variant_image_1" name="variant_image_1" class="d-none" accept="image/*">
                        <div class="upload-placeholder" onclick="document.getElementById('variant_image_1').click()">
                            <span>+ Add Image</span>
                        </div>
                    </div>

                    <!-- Variant Image 2 -->
                    <div>
                        <input type="file" id="variant_image_2" name="variant_image_2" class="d-none" accept="image/*">
                        <div class="upload-placeholder" onclick="document.getElementById('variant_image_2').click()">
                            <span>+ Add Image</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label for="product_name" class="form-label">Product Name</label>
            <input type="text" class="form-control" id="product_name" name="product_name" required>
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
        </div>

        <div class="mb-3">
            <label for="price" class="form-label">Price</label>
            <input type="number" class="form-control" id="price" name="price" step="0.01" required>
        </div>

        <div class="mb-3">
            <label for="category_id" class="form-label">Category</label>
            <select class="form-select" id="category_id" name="category_id" required>
                <option value="">Select a category</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= $category['category_id']; ?>">
                        <?= htmlspecialchars($category['category_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="supplier_id" class="form-label">Supplier</label>
            <select class="form-select" id="supplier_id" name="supplier_id" required>
                <option value="">Select a supplier</option>
                <?php foreach ($suppliers as $supplier): ?>
                    <option value="<?= $supplier['supplier_id'] ?>">
                        <?= htmlspecialchars($supplier['company_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="moq" class="form-label">Minimum Order Quantity</label>
            <input type="number" class="form-control" id="moq" name="moq" value="1" min="1">
        </div>

        <div class="mb-3">
            <label for="lead_time" class="form-label">Lead Time (days)</label>
            <input type="number" class="form-control" id="lead_time" name="lead_time" value="0" min="0">
        </div>

        <div class="mb-3">
            <label for="min_stock_level" class="form-label">Minimum Stock Level</label>
            <input type="number" class="form-control" id="min_stock_level" 
                   name="min_stock_level" value="0" min="0" required>
            <div class="form-text">Set the minimum stock level for reorder notifications</div>
        </div>

        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" checked>
            <label class="form-check-label" for="is_active">Active</label>
        </div>

        <div class="d-flex justify-content-between">
            <button type="submit" class="btn btn-primary">Add Product</button>
            <a href="products.php" class="btn btn-danger">Cancel</a>
        </div>
    </form>
</div>

<script>
// Preview image before upload
document.querySelectorAll('input[type="file"]').forEach(input => {
    input.addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.createElement('div');
                preview.className = input.id === 'main_image' ? 'image-preview main-image' : 'image-preview';
                preview.innerHTML = `
                    <img src="${e.target.result}" alt="Preview">
                    <button type="button" class="remove-image" onclick="removeImage('${input.id}')">×</button>
                `;
                const placeholder = input.parentNode.querySelector('.upload-placeholder');
                if (placeholder) {
                    placeholder.parentNode.replaceChild(preview, placeholder);
                }
            }
            reader.readAsDataURL(this.files[0]);
        }
    });
});

function removeImage(inputId) {
    document.getElementById(inputId).value = '';
    const preview = event.target.closest('.image-preview');
    const placeholder = document.createElement('div');
    placeholder.className = 'upload-placeholder';
    placeholder.innerHTML = '<span>+ Add Image</span>';
    placeholder.onclick = () => document.getElementById(inputId).click();
    preview.parentNode.replaceChild(placeholder, preview);
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php include 'includes/nav/footer.php'; ?>
</html>