<?php
session_start();

// Check if user is logged in as an admin
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

// Initialize Database connection
require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Get admin info
$query = "SELECT email FROM users WHERE id = ? AND role = 'admin'";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['id']]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

// If admin not found, logout
if (!$admin) {
    session_destroy();
    header('Location: login.php');
    exit();
}

// Fetch product details
if (isset($_GET['id'])) {
    $product_id = $_GET['id'];

    // Fetch the product data
    $query = "SELECT * FROM products WHERE product_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo "Product not found.";
        exit();
    }
} else {
    echo "Invalid Product ID.";
    exit();
}

// Fetch available categories
$category_query = "SELECT * FROM categories";
$category_stmt = $db->prepare($category_query);
$category_stmt->execute();
$categories = $category_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['product_name'];
    $description = $_POST['description'];
    $price = $_POST['price'];
    $category_id = $_POST['category_id'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Handle image uploads
    $main_image_path = $product['main_image']; // Default to existing main image
    $variant_image_1_path = $product['variant_image_1']; // Default to existing variant 1
    $variant_image_2_path = $product['variant_image_2']; // Default to existing variant 2

    if (isset($_FILES['main_image']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
        $main_image_path = 'uploads/' . basename($_FILES['main_image']['name']);
        move_uploaded_file($_FILES['main_image']['tmp_name'], $main_image_path);
    }

    if (isset($_FILES['variant_images']['name'][0]) && $_FILES['variant_images']['error'][0] === UPLOAD_ERR_OK) {
        $variant_image_1_path = 'uploads/' . basename($_FILES['variant_images']['name'][0]);
        move_uploaded_file($_FILES['variant_images']['tmp_name'][0], $variant_image_1_path);
    }

    if (isset($_FILES['variant_images']['name'][1]) && $_FILES['variant_images']['error'][1] === UPLOAD_ERR_OK) {
        $variant_image_2_path = 'uploads/' . basename($_FILES['variant_images']['name'][1]);
        move_uploaded_file($_FILES['variant_images']['tmp_name'][1], $variant_image_2_path);
    }

    // Update query
    $update_query = "UPDATE products 
                     SET main_image = ?, variant_image_1 = ?, variant_image_2 = ?, product_name = ?, description = ?, price = ?, category_id = ?, is_active = ? 
                     WHERE product_id = ?";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->execute([$main_image_path, $variant_image_1_path, $variant_image_2_path, $name, $description, $price, $category_id, $is_active, $product_id]);

    // Redirect to products page after successful update
    header("Location: products.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - Zellow Enterprises</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
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
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container mt-4">
    <h1>Edit Product</h1>
    <form method="POST" enctype="multipart/form-data">
        <div class="row mb-4">
            <div class="col-12">
                <label class="form-label">Media</label>
                <div class="d-flex gap-3">
                    <!-- Main Image -->
                    <div>
                        <input type="file" id="main_image" name="main_image" class="d-none" accept="image/*">
                        <?php if (!empty($product['main_image'])): ?>
                            <div class="image-preview">
                                <img src="<?= htmlspecialchars($product['main_image']); ?>" alt="Main Image">
                                <button type="button" class="remove-image" onclick="removeImage('main_image')">×</button>
                            </div>
                        <?php else: ?>
                            <div class="upload-placeholder" onclick="document.getElementById('main_image').click()">
                                <span>+ Add Image</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Variant Image 1 -->
                    <div>
                        <input type="file" id="variant_image_1" name="variant_image_1" class="d-none" accept="image/*">
                        <?php if (!empty($product['variant_image_1'])): ?>
                            <div class="image-preview">
                                <img src="<?= htmlspecialchars($product['variant_image_1']); ?>" alt="Variant Image 1">
                                <button type="button" class="remove-image" onclick="removeImage('variant_image_1')">×</button>
                            </div>
                        <?php else: ?>
                            <div class="upload-placeholder" onclick="document.getElementById('variant_image_1').click()">
                                <span>+ Add Image</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Variant Image 2 -->
                    <div>
                        <input type="file" id="variant_image_2" name="variant_image_2" class="d-none" accept="image/*">
                        <?php if (!empty($product['variant_image_2'])): ?>
                            <div class="image-preview">
                                <img src="<?= htmlspecialchars($product['variant_image_2']); ?>" alt="Variant Image 2">
                                <button type="button" class="remove-image" onclick="removeImage('variant_image_2')">×</button>
                            </div>
                        <?php else: ?>
                            <div class="upload-placeholder" onclick="document.getElementById('variant_image_2').click()">
                                <span>+ Add Image</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="mb-3">
            <label for="product_name" class="form-label">Product Name</label>
            <input type="text" class="form-control" id="product_name" name="product_name" value="<?= htmlspecialchars($product['product_name']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description" rows="3" required><?= htmlspecialchars($product['description']); ?></textarea>
        </div>
        <div class="mb-3">
            <label for="price" class="form-label">Price</label>
            <input type="number" class="form-control" id="price" name="price" value="<?= htmlspecialchars($product['price']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="category_id" class="form-label">Category</label>
            <select class="form-select" id="category_id" name="category_id" required>
                <option value="">Select a category</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= $category['category_id']; ?>" <?= $category['category_id'] == $product['category_id'] ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($category['category_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="is_active" name="is_active" <?= $product['is_active'] ? 'checked' : ''; ?>>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="products.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>
<script>
function removeImage(inputId) {
    document.getElementById(inputId).value = '';
    const preview = event.target.closest('.image-preview');
    const placeholder = document.createElement('div');
    placeholder.className = 'upload-placeholder';
    placeholder.innerHTML = '<span>+ Add Image</span>';
    placeholder.onclick = () => document.getElementById(inputId).click();
    preview.parentNode.replaceChild(placeholder, preview);
}

// Preview image before upload
document.querySelectorAll('input[type="file"]').forEach(input => {
    input.addEventListener('change', function(e) {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.createElement('div');
                preview.className = 'image-preview';
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
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
<?php include 'footer.php'; ?>
</html>
