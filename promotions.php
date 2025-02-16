<?php
session_start();
require_once 'config/database.php';
require_once __DIR__ . '/includes/functions/marketing_functions.php';

if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['create_campaign'])) {
            // Create new campaign
            $trackingId = 'GA-' . uniqid(); // Generate unique tracking ID
            $stmt = $db->prepare("INSERT INTO marketing_campaigns (
                name, description, start_date, end_date, 
                budget, platform, tracking_id, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $_POST['name'],
                $_POST['description'],
                $_POST['start_date'],
                $_POST['end_date'],
                $_POST['budget'],
                $_POST['platform'],
                $trackingId,  // Used for Google Ads tracking
                'active'
            ]);
            $success = "Campaign created successfully!";
        }

        if (isset($_POST['create_coupon'])) {
            try {
                // Validate dates
                $startDate = new DateTime($_POST['start_date'] ?? 'now');
                $endDate = new DateTime($_POST['expiration_date']);
                $today = new DateTime();
                
                if ($endDate <= $today) {
                    throw new Exception("Expiration date must be in the future");
                }
                
                if ($startDate > $endDate) {
                    throw new Exception("Start date cannot be after expiration date");
                }
                
                // Generate unique coupon code if not provided
                $code = !empty($_POST['code']) ? strtoupper($_POST['code']) : 
                       'ZELLOW-' . strtoupper(substr(uniqid(), -6));
                
                // Validate discount value
                $discountValue = floatval($_POST['discount_value']);
                if ($discountValue <= 0) {
                    throw new Exception("Discount value must be greater than 0");
                }
                
                if ($_POST['discount_type'] === 'percentage' && $discountValue > 100) {
                    throw new Exception("Percentage discount cannot exceed 100%");
                }
                
                $stmt = $db->prepare("
                    INSERT INTO coupons (
                        code, 
                        discount_type,
                        discount_value,
                        discount_percentage,
                        start_date,
                        expiration_date,
                        min_order_amount,
                        usage_limit_total,
                        usage_limit_per_user,
                        status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                ");
                
                $stmt->execute([
                    $code,
                    $_POST['discount_type'],
                    $discountValue,
                    $_POST['discount_type'] === 'percentage' ? $discountValue : null,
                    $startDate->format('Y-m-d'),
                    $endDate->format('Y-m-d'),
                    floatval($_POST['min_order_amount'] ?? 0),
                    intval($_POST['usage_limit_total'] ?? 0),
                    intval($_POST['usage_limit_per_user'] ?? 0)
                ]);
                
                $success = "Coupon created successfully! Code: $code";
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

        if (isset($_POST['update_campaign'])) {
            // Update campaign
            $stmt = $db->prepare("UPDATE marketing_campaigns 
                                SET status = :status, 
                                    budget = :budget,
                                    end_date = :end_date 
                                WHERE campaign_id = :id");
            
            $stmt->execute([
                ':status' => $_POST['status'],
                ':budget' => $_POST['budget'],
                ':end_date' => $_POST['end_date'],
                ':id' => $_POST['campaign_id']
            ]);
            $success = "Campaign updated successfully!";
        }

        if (isset($_POST['delete_campaign'])) {
            try {
                $campaign_id = (int)$_POST['campaign_id'];
                $stmt = $db->prepare("DELETE FROM marketing_campaigns WHERE campaign_id = ?");
                if ($stmt->execute([$campaign_id])) {
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete campaign']);
                }
                exit;
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Fetch active campaigns
$campaigns_query = "SELECT * FROM marketing_campaigns ORDER BY created_at DESC";
$campaigns_stmt = $db->query($campaigns_query);
$campaigns = $campaigns_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch valid coupons
$coupons_query = "SELECT * FROM coupons WHERE expiration_date >= CURDATE() ORDER BY created_at DESC";
$coupons_stmt = $db->query($coupons_query);
$coupons = $coupons_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketing & Promotions</title>
    <script src="https://unpkg.com/feather-icons"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/collapsed.css">
    <link rel="stylesheet" href="assets/css/promotions.css">
</head>
<body>
<div class="admin-layout"> 
<?php include 'includes/theme.php'; ?>
    <div class="navbar">
    <?php include 'includes/nav/collapsed.php'; ?>
</div>
    
        <div class="container-fluid p-3">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?= $success ?></div>
            <?php endif; ?>

            <!-- Campaigns Section -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Marketing Campaigns</h5>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newCampaignModal">
                        New Campaign
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Platform</th>
                                    <th>Budget</th>
                                    <th>Status</th>
                                    <th>Duration</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($campaigns as $campaign): ?>
                                    <tr data-campaign-id="<?= $campaign['campaign_id'] ?>">
                                        <td><?= htmlspecialchars($campaign['name']) ?></td>
                                        <td><?= htmlspecialchars($campaign['platform']) ?></td>
                                        <td>Ksh. <?= number_format($campaign['budget'], 2) ?></td>
                                        <td>
                                            <span class="badge bg-<?= getStatusColor($campaign['status']) ?>">
                                                <?= ucfirst($campaign['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= date('M d', strtotime($campaign['start_date'])) ?> - 
                                            <?= date('M d, Y', strtotime($campaign['end_date'])) ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end align-items-center gap-2">
                                                <a href="campaign_analytics.php?id=<?= $campaign['campaign_id'] ?>" 
                                                   class="btn btn-sm btn-view-analytics">
                                                    <i class="fas fa-chart-line"></i> Analytics
                                                </a>
                                                <div class="dropdown">
                                                    <button type="button" 
                                                            class="btn btn-sm btn-actions"
                                                            data-bs-toggle="dropdown"
                                                            aria-expanded="false">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li>
                                                            <button type="button" 
                                                                    class="dropdown-item" 
                                                                    onclick="editCampaign(<?= htmlspecialchars(json_encode($campaign)) ?>)">
                                                                <i class="fas fa-edit me-2"></i> Edit
                                                            </button>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <button type="button" 
                                                                    class="dropdown-item text-danger" 
                                                                    onclick="deleteCampaign(<?= $campaign['campaign_id'] ?>)">
                                                                <i class="fas fa-trash-alt me-2"></i> Delete
                                                            </button>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Coupons Section -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Discount Coupons</h5>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newCouponModal">
                        New Coupon
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Discount</th>
                                    <th>Expires</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($coupons as $coupon): ?>
                                    <tr>
                                        <td><code><?= htmlspecialchars($coupon['code']) ?></code></td>
                                        <td><?= $coupon['discount_percentage'] ?>%</td>
                                        <td><?= date('M d, Y', strtotime($coupon['expiration_date'])) ?></td>
                                        <td>
                                            <?php
                                            $isExpired = strtotime($coupon['expiration_date']) < time();
                                            $status = $isExpired ? 'Expired' : 'Active';
                                            $statusClass = $isExpired ? 'danger' : 'success';
                                            ?>
                                            <span class="badge bg-<?= $statusClass ?>"><?= $status ?></span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-secondary" 
                                                    onclick="copyCode('<?= $coupon['code'] ?>')">
                                                Copy
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Add this after the coupons table section -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="mb-0">Coupon Analytics</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <h6>Total Coupons Used</h6>
                                    <?php
                                    $stmt = $db->query("SELECT COUNT(*) FROM coupon_usage");
                                    echo "<h3>" . $stmt->fetchColumn() . "</h3>";
                                    ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h6>Active Coupons</h6>
                                    <?php
                                    $stmt = $db->query("SELECT COUNT(*) FROM coupons 
                                        WHERE status = 'active' 
                                        AND (end_date >= CURRENT_DATE OR end_date IS NULL)");
                                    echo "<h3>" . $stmt->fetchColumn() . "</h3>";
                                    ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-body">
                                    <h6>Most Used Coupons</h6>
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Code</th>
                                                <th>Uses</th>
                                                <th>Total Discount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $stmt = $db->query("
                                                SELECT c.code, 
                                                    COUNT(cu.usage_id) as usage_count,
                                                    SUM(o.discount_amount) as total_discount
                                                FROM coupons c
                                                LEFT JOIN coupon_usage cu ON c.coupon_id = cu.coupon_id
                                                LEFT JOIN orders o ON cu.order_id = o.order_id
                                                GROUP BY c.coupon_id
                                                ORDER BY usage_count DESC
                                                LIMIT 5
                                            ");
                                            while ($row = $stmt->fetch()) {
                                                echo "<tr>
                                                    <td>{$row['code']}</td>
                                                    <td>{$row['usage_count']}</td>
                                                    <td>Ksh. " . number_format($row['total_discount'], 2) . "</td>
                                                </tr>";
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Include Modals -->
    <?php 
    include 'includes/modals/new_campaign_modal.php';
    include 'includes/modals/edit_campaign_modal.php';
    include 'includes/modals/new_coupon_modal.php';
    ?>

    <!-- Add Edit Campaign Modal -->
    <div class="modal fade" id="editCampaignModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Campaign</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editCampaignForm" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="campaign_id" id="edit_campaign_id">
                        <div class="mb-3">
                            <label class="form-label">Campaign Name</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <option value="active">Active</option>
                                <option value="paused">Paused</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Budget (KSH)</label>
                            <input type="number" class="form-control" id="edit_budget" name="budget" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" id="edit_end_date" name="end_date" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" name="update_campaign">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Add Delete Confirmation Modal -->
    <div class="modal fade" id="deleteCampaignModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Campaign</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete this campaign? This action cannot be undone.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    function editCampaign(campaign) {
        document.getElementById('edit_campaign_id').value = campaign.campaign_id;
        document.getElementById('edit_name').value = campaign.name;
        document.getElementById('edit_status').value = campaign.status;
        document.getElementById('edit_budget').value = campaign.budget;
        document.getElementById('edit_end_date').value = campaign.end_date;
        
        new bootstrap.Modal(document.getElementById('editCampaignModal')).show();
    }

    function deleteCampaign(campaignId) {
        if (confirm('Are you sure you want to delete this campaign?')) {
            fetch('promotions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'delete_campaign=1&campaign_id=' + campaignId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the row from the table
                    const row = document.querySelector(`tr[data-campaign-id="${campaignId}"]`);
                    if (row) row.remove();
                    
                    // Show success message
                    const alert = document.createElement('div');
                    alert.className = 'alert alert-success';
                    alert.textContent = 'Campaign deleted successfully';
                    document.querySelector('.container-fluid').prepend(alert);
                    
                    // Remove alert after 3 seconds
                    setTimeout(() => alert.remove(), 3000);
                } else {
                    alert('Error: ' + (data.message || 'Failed to delete campaign'));
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
            });
        }
    }
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/promotions.js"></script>
</body>
</html>

<style>
/* Add this to your existing styles */
.btn-outline-primary {
    border-color: #2196F3;
    color: #2196F3;
    font-weight: 500;
    padding: 0.25rem 0.75rem;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.btn-outline-primary:hover {
    background-color: #2196F3;
    color: white;
}

.btn-group {
    display: inline-flex;
    align-items: stretch;
}

.dropdown-toggle-split {
    padding-left: 0.5rem;
    padding-right: 0.5rem;
}

.dropdown-menu {
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    border: 1px solid rgba(0,0,0,0.08);
}

.dropdown-item {
    padding: 0.5rem 1rem;
    display: flex;
    align-items: center;
}

.dropdown-item i {
    width: 1rem;
    text-align: center;
}
</style>