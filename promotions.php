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
            // Create new coupon
            $stmt = $db->prepare("INSERT INTO coupons (code, discount_percentage, expiration_date) 
                                VALUES (:code, :discount, :expiry)");
            
            $stmt->execute([
                ':code' => strtoupper($_POST['code']),
                ':discount' => $_POST['discount'],
                ':expiry' => $_POST['expiration_date']
            ]);
            $success = "Coupon created successfully!";
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
   
    <link rel="stylesheet" href="assets/css/collapsed.css">
</head>
<body>
<div class="admin-layout"> 
<?php include 'includes/theme.php'; ?>
    <nav class="navbar">
    <?php include 'includes/nav/collapsed.php'; ?>
    </nav>
    
    <div class="main-content">
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
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($campaigns as $campaign): ?>
                                    <tr>
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
                                        <td>
                                            <button class="btn btn-sm btn-primary" 
                                                    onclick="editCampaign(<?= htmlspecialchars(json_encode($campaign)) ?>)">
                                                Edit
                                            </button>
                                            <a href="campaign_analytics.php?id=<?= $campaign['campaign_id'] ?>" 
                                               class="btn btn-sm btn-info">Analytics</a>
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
        </div>
    </div>

    <!-- Include Modals -->
    <?php 
    include 'includes/modals/new_campaign_modal.php';
    include 'includes/modals/edit_campaign_modal.php';
    include 'includes/modals/new_coupon_modal.php';
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/promotions.js"></script>
</body>
</html>