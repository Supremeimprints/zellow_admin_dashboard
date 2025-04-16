<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions/giftbox_functions.php';
require_once 'includes/head.php';

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Fetch existing gift boxes
$giftBoxes = getGiftBoxes($db);
$products = getAllProducts($db);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gift Box Manager - Zellow Admin</title>
    
    <!-- Stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   
    <link rel="stylesheet" href="assets/css/badges.css">
    <link rel="stylesheet" href="assets/css/orders.css">
    <link rel="stylesheet" href="assets/css/collapsed.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --bg-light: #f8f9fc;
        }
        
        body {
            background-color: var(--bg-light);
        }
        
        .page-header {
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .gift-box-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            overflow: hidden;
            height: 100%;
        }
        
        .gift-box-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.12);
        }
        
        .gift-box-image {
            height: 220px;
            object-fit: cover;
            width: 100%;
        }
        
        .card-img-overlay-bottom {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.7), transparent);
            padding: 1rem;
            color: white;
        }
        
        .action-btn {
            border-radius: 50px;
            padding: 0.5rem 1rem;
            transition: all 0.2s;
            font-weight: 500;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
        }
        
        .action-btn i {
            margin-right: 5px;
        }
        
        .box-price {
            font-size: 1.1rem;
            font-weight: 600;
            display: inline-block;
            padding: 0.25rem 0.75rem;
            background-color: rgba(255,255,255,0.9);
            color: #333;
            border-radius: 50px;
            margin-top: 0.5rem;
        }
        
        .btn-create {
            background-color: var(--primary-color);
            color: white;
            border-radius: 50px;
            padding: 0.6rem 1.5rem;
            font-weight: 500;
            box-shadow: 0 4px 10px rgba(78, 115, 223, 0.3);
            transition: all 0.2s;
        }
        
        .btn-create:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 14px rgba(78, 115, 223, 0.4);
            background-color: #3a5ecc;
            color: white;
        }
        
        .btn-create i {
            margin-right: 8px;
        }
        
        .action-buttons {
            background-color: rgba(255,255,255,0.9);
            border-radius: 12px;
            padding: 0.75rem;
            margin-top: 1rem;
            display: flex;
            justify-content: space-between;
        }
        
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .modal-header {
            border-bottom: 1px solid rgba(0,0,0,0.05);
            background-color: #f8f9fc;
        }
        
        .modal-footer {
            border-top: 1px solid rgba(0,0,0,0.05);
        }
        
        .form-control {
            border-radius: 8px;
            padding: 0.6rem 1rem;
        }
        
        .custom-file-input {
            border-radius: 8px;
            padding: 0.6rem 1rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            background-color: white;
            border-radius: 12px;
            margin-top: 2rem;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--secondary-color);
            opacity: 0.5;
            margin-bottom: 1rem;
        }
        
        .container-fluid {
            padding: 2rem;
        }
        
        .box-details {
            padding: 1.25rem;
        }
        
        .box-description {
            color: #6c757d;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        
        .card-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 0.35rem 0.65rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/nav/collapsed.php'; ?>
        <?php include 'includes/theme.php'; ?>
       
        
        <div class="container mt-5">
            <!-- Page Header -->
            <div class="page-header d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1">Gift Box Manager</h2>
                    <p class="text-muted mb-0">Create and manage custom gift boxes for your customers</p>
                </div>
                <button class="btn btn-create" data-bs-toggle="modal" data-bs-target="#createGiftBoxModal">
                    <i class="fas fa-plus"></i> Create New Gift Box
                </button>
            </div>

            <!-- Gift Boxes Grid -->
            <div class="row g-4">
                <?php if (empty($giftBoxes)): ?>
                    <div class="col-12">
                        <div class="empty-state">
                            <i class="fas fa-gift"></i>
                            <h4>No Gift Boxes Created Yet</h4>
                            <p class="text-muted">Create your first gift box to get started</p>
                            <button class="btn btn-create mt-3" data-bs-toggle="modal" data-bs-target="#createGiftBoxModal">
                                <i class="fas fa-plus"></i> Create New Gift Box
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($giftBoxes as $box): ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="gift-box-card">
                                <div class="position-relative">
                                    <img src="<?= htmlspecialchars($box['image_path']) ?>" 
                                         class="gift-box-image" 
                                         alt="<?= htmlspecialchars($box['name']) ?>">
                                    
                                    <?php if (isset($box['status']) && $box['status'] === 'featured'): ?>
                                        <span class="card-badge bg-warning">Featured</span>
                                    <?php endif; ?>
                                    
                                    <div class="card-img-overlay-bottom">
                                        <h5 class="card-title mb-0 text-white"><?= htmlspecialchars($box['name']) ?></h5>
                                        <span class="box-price">Ksh. <?= number_format($box['base_price'], 2) ?></span>
                                    </div>
                                </div>
                                
                                <div class="box-details">
                                    <p class="box-description">
                                        <?= !empty($box['description']) ? htmlspecialchars($box['description']) : 'No description available' ?>
                                    </p>
                                    
                                    <div class="action-buttons">
                                        <a href="giftbox_items.php?id=<?= $box['gift_box_id'] ?>" 
                                           class="btn btn-sm btn-outline-primary action-btn">
                                            <i class="fas fa-box-open"></i> Manage Items
                                        </a>
                                        <a href="giftbox_attributes.php?id=<?= $box['gift_box_id'] ?>" 
                                           class="btn btn-sm btn-outline-secondary action-btn">
                                            <i class="fas fa-cog"></i> Customize
                                        </a>
                                        <button class="btn btn-sm btn-outline-success action-btn"
                                                onclick="editGiftBox(<?= $box['gift_box_id'] ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Create Gift Box Modal -->
    <div class="modal fade" id="createGiftBoxModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-gift me-2 text-primary"></i>
                        Create New Gift Box
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="giftbox_create.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Gift Box Name</label>
                            <input type="text" name="name" class="form-control" 
                                   placeholder="Enter a descriptive name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" 
                                      placeholder="Describe what makes this gift box special"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Base Price (Ksh)</label>
                            <div class="input-group">
                                <span class="input-group-text">Ksh.</span>
                                <input type="number" step="0.01" name="base_price" class="form-control" 
                                       placeholder="0.00" required>
                            </div>
                            <small class="text-muted">This is the starting price before adding items</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Upload Image</label>
                            <input type="file" name="image" class="form-control" accept=".jpg,.png,.jpeg" required>
                            <small class="text-muted">Recommended size: 800x600 pixels</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check me-1"></i> Create Gift Box
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Gift Box Modal -->
    <div class="modal fade" id="editGiftBoxModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2 text-success"></i>
                        Edit Gift Box
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="giftbox_update.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" id="edit_gift_box_id" name="gift_box_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Gift Box Name</label>
                            <input type="text" id="edit_name" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea id="edit_description" name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Base Price (Ksh)</label>
                            <div class="input-group">
                                <span class="input-group-text">Ksh.</span>
                                <input type="number" step="0.01" id="edit_base_price" name="base_price" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Update Image (Optional)</label>
                            <input type="file" name="image" class="form-control" accept=".jpg,.png,.jpeg">
                            <small class="text-muted">Leave empty to keep current image</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-1"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize feather icons if they're being used
        if (typeof feather !== 'undefined') {
            feather.replace();
        }
        
        function editGiftBox(id) {
            fetch(`giftbox_edit.php?id=${id}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    document.getElementById('edit_gift_box_id').value = data.gift_box_id;
                    document.getElementById('edit_name').value = data.name;
                    document.getElementById('edit_description').value = data.description;
                    document.getElementById('edit_base_price').value = data.base_price;
                    
                    const editModal = new bootstrap.Modal(document.getElementById('editGiftBoxModal'));
                    editModal.show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load gift box data');
                });
        }
    </script>
</body>
<?php include 'includes/nav/footer.php'; ?>
</html>