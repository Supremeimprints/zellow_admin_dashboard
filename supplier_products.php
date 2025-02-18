<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$error = $success = '';

// Get supplier details
$supplier_id = $_GET['id'] ?? 0;
$supplier = null;

try {
    $stmt = $db->prepare("
        SELECT supplier_id, company_name 
        FROM suppliers 
        WHERE supplier_id = ? AND is_active = 1
    ");
    $stmt->execute([$supplier_id]);
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$supplier) {
        header('Location: suppliers.php');
        exit();
    }

    // Update query to get products from products table
    $stmt = $db->prepare("
        SELECT 
            p.product_id,
            p.product_name,
            p.description,
            p.price as unit_price,
            p.moq,
            p.lead_time,
            p.is_active,
            p.main_image,
            p.supplier_id
        FROM products p
        WHERE p.supplier_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$supplier_id]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Handle product form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_product'])) {
        try {
            $stmt = $db->prepare("
                INSERT INTO supplier_products (
                    supplier_id, 
                    product_name, 
                    description, 
                    unit_price, 
                    moq, 
                    lead_time
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $supplier_id,
                $_POST['product_name'],
                $_POST['description'],
                $_POST['unit_price'],
                $_POST['moq'],
                $_POST['lead_time']
            ]);
            
            $success = "Product added successfully";
            header("Refresh:0"); // Refresh the page
            
        } catch (PDOException $e) {
            $error = "Error adding product: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Products - <?= htmlspecialchars($supplier['company_name']) ?></title>
    <?php include 'includes/theme.php'; ?>
    <script src="https://unpkg.com/feather-icons"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/themes.css">
    <link rel="stylesheet" href="assets/css/collapsed.css">
    <link rel="stylesheet" href="assets/css/badges.css">
    <link rel="stylesheet" href="assets/css/admins.css">
    
    <style>
        .content-wrapper {
            padding: 2rem;
            margin-left: 78px;
            min-height: 100vh;
            
        }

        /* Dark mode colors */
        [data-bs-theme="dark"] .content-wrapper {
            --background: #15202B;
        }

        [data-bs-theme="dark"] .table thead th {
            background-color: #2d3748;
            border-bottom-color: #2d3748;
            color: #e2e8f0;
        }

        [data-bs-theme="dark"] .table {
            color: #e2e8f0;
        }

        [data-bs-theme="dark"] .table tbody td {
            border-color: #2d3748;
        }

        [data-bs-theme="dark"] .table-hover tbody tr:hover {
            background-color: #2d3748;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .action-buttons .btn {
            padding: 0.4rem 0.6rem;
            border-radius: 8px;
            transition: all 0.2s ease;
            border: none;
            font-size: 0.875rem;
        }

        /* Soft button styles */
        .btn-soft-primary {
            color: #0d6efd !important;
            background-color: rgba(13, 110, 253, 0.1) !important;
        }

        .btn-soft-primary:hover {
            color: #fff !important;
            background-color: #0d6efd !important;
        }

        .btn-soft-warning {
            color: #ffc107 !important;
            background-color: rgba(255, 193, 7, 0.1) !important;
        }

        .btn-soft-warning:hover {
            color: #fff !important;
            background-color: #ffc107 !important;
        }

        .btn-soft-danger {
            color: #dc3545 !important;
            background-color: rgba(220, 53, 69, 0.1) !important;
        }

        .btn-soft-danger:hover {
            color: #fff !important;
            background-color: #dc3545 !important;
        }

        /* Dark mode adjustments */
        [data-bs-theme="dark"] .btn-soft-primary {
            color: #4d94ff !important;
            background-color: rgba(77, 148, 255, 0.15) !important;
        }

        [data-bs-theme="dark"] .btn-soft-warning {
            color: #ffcd39 !important;
            background-color: rgba(255, 205, 57, 0.15) !important;
        }

        [data-bs-theme="dark"] .btn-soft-danger {
            color: #ff4d4d !important;
            background-color: rgba(255, 77, 77, 0.15) !important;
        }

        .page-header {
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .table thead th {
            background-color: #f8f9fa;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .price-column {
            font-family: 'Roboto Mono', monospace;
        }

        /* Back button */
        .back-button {
            margin-right: 1rem;
            color: #fff;
            background-color: #dc3545;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            transition: all 0.2s ease;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            border: none;
        }

        .back-button:hover {
            color: #fff;
            background-color: #bb2d3b;
            text-decoration: none;
        }

        [data-bs-theme="dark"] .back-button {
            background-color: #dc3545;
            color: #fff;
        }

        [data-bs-theme="dark"] .back-button:hover {
            background-color: #bb2d3b;
            color: #fff;
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include 'includes/nav/collapsed.php'; ?>
    
    <div class="content-wrapper">
        <div class="container mt-5">
            <!-- Updated header section -->
            <div class="page-header">
                <div class="d-flex align-items-center">
                    <a href="suppliers.php" class="back-button">
                        <i class="fas fa-arrow-left"></i>
                        <span>Back to Suppliers</span>
                    </a>
                    <h2 class="mb-0"><?= htmlspecialchars($supplier['company_name']) ?> - Products</h2>
                </div>
                <button type="button" 
                        class="btn btn-primary" 
                        data-bs-toggle="modal" 
                        data-bs-target="#addProductModal">
                    <i class="fas fa-plus"></i>
                    <span>Add Product</span>
                </button>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Description</th>
                            <th class="price-column">Unit Price</th>
                            <th>MOQ</th>
                            <th>Lead Time</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="fas fa-box fa-3x mb-3"></i>
                                        <p class="h6">No products found for this supplier</p>
                                        <p class="small">Click the "Add Product" button to add products</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <tr data-product-id="<?= htmlspecialchars($product['product_id']) ?>">
                                    <td class="fw-medium"><?= htmlspecialchars($product['product_name']) ?></td>
                                    <td><?= htmlspecialchars($product['description']) ?></td>
                                    <td class="price-column">KES <?= number_format($product['unit_price'], 2) ?></td>
                                    <td><?= htmlspecialchars($product['moq']) ?> units</td>
                                    <td><?= htmlspecialchars($product['lead_time']) ?> days</td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-soft-warning"
                                                    onclick="editProduct(<?= $product['product_id'] ?>)"
                                                    title="Edit Product">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-soft-danger"
                                                    onclick="deleteProduct(<?= $product['product_id'] ?>)"
                                                    title="Delete Product">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addProductForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="supplier_id" value="<?= $supplier_id ?>">
                    <div class="mb-3">
                        <label class="form-label">Product Name</label>
                        <input type="text" name="product_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Unit Price (KES)</label>
                        <input type="number" name="unit_price" class="form-control" step="0.01" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Minimum Order Quantity</label>
                        <input type="number" name="moq" class="form-control" value="1" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Lead Time (days)</label>
                        <input type="number" name="lead_time" class="form-control" min="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_product" class="btn btn-primary">Add Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editProductForm" method="POST">
                <input type="hidden" name="product_id" id="edit_product_id">
                <div class="modal-body">
                    <!-- Same fields as Add Product modal -->
                    <div class="mb-3">
                        <label class="form-label">Product Name</label>
                        <input type="text" name="product_name" id="edit_product_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Unit Price (KES)</label>
                        <input type="number" name="unit_price" id="edit_unit_price" class="form-control" step="0.01" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Minimum Order Quantity</label>
                        <input type="number" name="moq" id="edit_moq" class="form-control" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Lead Time (days)</label>
                        <input type="number" name="lead_time" id="edit_lead_time" class="form-control" min="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_product" class="btn btn-primary">Update Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// JavaScript functions for handling products
function editProduct(productId) {
    fetch(`get_product.php?id=${productId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Populate edit form
                document.getElementById('edit_product_id').value = data.product.id;
                document.getElementById('edit_product_name').value = data.product.product_name;
                document.getElementById('edit_description').value = data.product.description;
                document.getElementById('edit_unit_price').value = data.product.unit_price;
                document.getElementById('edit_moq').value = data.product.moq;
                document.getElementById('edit_lead_time').value = data.product.lead_time;
                
                // Show modal
                new bootstrap.Modal(document.getElementById('editProductModal')).show();
            } else {
                alert('Error loading product details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading product details');
        });
}

function deleteProduct(productId) {
    if (!productId) {
        alert('Invalid product ID');
        return;
    }
    
    if (confirm('Are you sure you want to delete this product?')) {
        // Add console.log for debugging
        console.log('Deleting product:', productId);
        
        const formData = new FormData();
        formData.append('product_id', productId);

        fetch('delete_product.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            console.log('Response:', data); // Debug response
            if (data.success) {
                const row = document.querySelector(`tr[data-product-id="${productId}"]`);
                if (row) {
                    row.remove();
                    alert('Product deleted successfully');
                }
            } else {
                throw new Error(data.message || 'Failed to delete product');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert(error.message || 'Error deleting product');
        });
    }
}

// Form submission handlers
document.getElementById('editProductForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    
    fetch('update_product.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.message || 'Error updating product');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating product');
    });
});
</script>
</body>
</html>
