<?php
session_start();
require_once 'config/database.php';
require_once 'includes/theme.php';
require_once 'includes/functions/settings_helpers.php';

// Authentication check
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Update marketing API settings
        if (isset($_POST['save_api'])) {
            $marketing_settings = [
                'google_ads_id' => $_POST['google_ads_id'],
                'google_analytics_id' => $_POST['google_analytics_id'],
                'facebook_pixel_id' => $_POST['facebook_pixel_id']
            ];

            foreach ($marketing_settings as $key => $value) {
                update_system_setting($db, $key, $value, 'marketing');
            }
            $success = "API settings updated successfully!";
        }

        // Update payment gateway settings
        if (isset($_POST['update_payment'])) {
            // Stripe settings
            $stripe_config = [
                'is_enabled' => isset($_POST['stripe_enabled']) ? 1 : 0,
                'config' => [
                    'public_key' => $_POST['stripe_public_key'],
                    'secret_key' => $_POST['stripe_secret_key']
                ],
                'sandbox_mode' => isset($_POST['stripe_sandbox']) ? 1 : 0
            ];
            update_payment_gateway($db, 'stripe', $stripe_config);

            // M-Pesa settings
            $mpesa_config = [
                'is_enabled' => isset($_POST['mpesa_enabled']) ? 1 : 0,
                'config' => [
                    'consumer_key' => $_POST['mpesa_consumer_key'],
                    'consumer_secret' => $_POST['mpesa_consumer_secret'],
                    'passkey' => $_POST['mpesa_passkey'],
                    'shortcode' => $_POST['mpesa_shortcode']
                ],
                'sandbox_mode' => $_POST['mpesa_environment'] === 'sandbox' ? 1 : 0
            ];
            update_payment_gateway($db, 'mpesa', $mpesa_config);

            $success = "Payment settings updated successfully!";
        }

        // Update shipping zones
        if (isset($_POST['update_shipping_rate'])) {
            $stmt = $db->prepare("UPDATE shipping_rates SET 
                                base_rate = ?, 
                                updated_at = CURRENT_TIMESTAMP 
                                WHERE id = ?");
            $stmt->execute([$_POST['rate'], $_POST['rate_id']]);
            $success = "Shipping rate updated successfully!";
        }

        // Add new shipping zone
        if (isset($_POST['add_zone'])) {
            try {
                $zone_name = trim($_POST['zone_name']);
                $zone_regions = explode(',', $_POST['zone_regions']);
                $zone_regions = array_map('trim', $zone_regions);
                
                $stmt = $db->prepare("INSERT INTO shipping_zones (zone_name, zone_regions) VALUES (?, ?)");
                $stmt->execute([$zone_name, json_encode($zone_regions)]);
                $success = "Shipping zone added successfully!";
            } catch (Exception $e) {
                $error = "Error adding zone: " . $e->getMessage();
            }
        }

        // Add new shipping rate
        if (isset($_POST['add_rate'])) {
            try {
                $stmt = $db->prepare("INSERT INTO shipping_rates (zone_id, shipping_method, base_rate, description) 
                                     VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['zone_id'],
                    $_POST['shipping_method'],
                    $_POST['base_rate'],
                    $_POST['description']
                ]);
                $success = "Shipping rate added successfully!";
            } catch (Exception $e) {
                $error = "Error adding rate: " . $e->getMessage();
            }
        }

    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch current settings
$marketing_settings = get_all_settings($db, 'marketing');
$payment_gateways = get_payment_gateways($db);
$shipping_zones = get_shipping_zones($db);

// Get gateway configs
$stripe_config = array_filter($payment_gateways, fn($g) => $g['gateway_code'] === 'stripe')[0] ?? null;
$mpesa_config = array_filter($payment_gateways, fn($g) => $g['gateway_code'] === 'mpesa')[0] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings</title>
    <script src="https://unpkg.com/feather-icons"></script>
    
    <!-- Existing stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/settings.css">
    <link rel="stylesheet" href="assets/css/badges.css">
    <link rel="stylesheet" href="assets/css/orders.css">
    <link rel="stylesheet" href="assets/css/collapsed.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/orders.css" rel="stylesheet">
    <link href="assets/css/badges.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/inventory.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/collapsed.css">
    <script src="https://unpkg.com/feather-icons"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/settings.css">
</head>
<body>
<div class="admin-layout">
    <?php include 'includes/theme.php'; ?>
    <nav class="navbar">
        <?php include 'includes/nav/collapsed.php'; ?>
    </nav>

    <div class="container-fluid mt-3">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>

        <div class="row">
            <!-- Marketing API Settings -->
            <div class="col-md-6">
                <div class="settings-card">
                    <h2 class="settings-title">Marketing API Settings</h2>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Google Ads API Key</label>
                            <input type="text" class="form-control" name="google_ads_id"
                                   value="<?= htmlspecialchars($marketing_settings['google_ads_id'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Google Analytics Key</label>
                            <input type="text" class="form-control" name="google_analytics_id"
                                   value="<?= htmlspecialchars($marketing_settings['google_analytics_id'] ?? '') ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Facebook Pixel ID</label>
                            <input type="text" class="form-control" name="facebook_pixel_id"
                                   value="<?= htmlspecialchars($marketing_settings['facebook_pixel_id'] ?? '') ?>">
                        </div>

                        <button type="submit" name="save_api" class="btn btn-primary w-100">
                            Save API Settings
                        </button>
                    </form>
                </div>
            </div>

            <!-- Payment Gateway Settings -->
            <div class="col-md-6">
                <div class="settings-card">
                    <h2 class="settings-title">Payment Settings</h2>
                    <form method="POST">
                        <!-- Stripe Settings -->
                        <div class="payment-section mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h3>Stripe Payments</h3>
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input" name="stripe_enabled"
                                           <?= ($stripe_config['is_enabled'] ?? false) ? 'checked' : '' ?>>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Public Key</label>
                                <input type="text" class="form-control" name="stripe_public_key"
                                       value="<?= htmlspecialchars($stripe_config['config']['public_key'] ?? '') ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Secret Key</label>
                                <input type="password" class="form-control" name="stripe_secret_key"
                                       value="<?= htmlspecialchars($stripe_config['config']['secret_key'] ?? '') ?>">
                            </div>

                            <div class="form-check mb-3">
                                <input type="checkbox" class="form-check-input" name="stripe_sandbox"
                                       <?= ($stripe_config['sandbox_mode'] ?? true) ? 'checked' : '' ?>>
                                <label class="form-check-label">Test Mode</label>
                            </div>
                        </div>

                        <!-- M-Pesa Settings -->
                        <div class="payment-section">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h3>M-Pesa Integration</h3>
                                <div class="form-check form-switch">
                                    <input type="checkbox" class="form-check-input" name="mpesa_enabled"
                                           <?= ($mpesa_config['is_enabled'] ?? false) ? 'checked' : '' ?>>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Consumer Key</label>
                                <input type="text" class="form-control" name="mpesa_consumer_key"
                                       value="<?= htmlspecialchars($mpesa_config['config']['consumer_key'] ?? '') ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Consumer Secret</label>
                                <input type="password" class="form-control" name="mpesa_consumer_secret"
                                       value="<?= htmlspecialchars($mpesa_config['config']['consumer_secret'] ?? '') ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Pass Key</label>
                                <input type="password" class="form-control" name="mpesa_passkey"
                                       value="<?= htmlspecialchars($mpesa_config['config']['passkey'] ?? '') ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Business Short Code</label>
                                <input type="text" class="form-control" name="mpesa_shortcode"
                                       value="<?= htmlspecialchars($mpesa_config['config']['shortcode'] ?? '') ?>">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Environment</label>
                                <select class="form-select" name="mpesa_environment">
                                    <option value="sandbox" 
                                        <?= ($mpesa_config['sandbox_mode'] ?? true) ? 'selected' : '' ?>>
                                        Sandbox
                                    </option>
                                    <option value="live" 
                                        <?= ($mpesa_config['sandbox_mode'] ?? false) ? '' : 'selected' ?>>
                                        Live
                                    </option>
                                </select>
                            </div>
                        </div>

                        <button type="submit" name="update_payment" class="btn btn-primary w-100">
                            Save Payment Settings
                        </button>
                    </form>
                </div>
            </div>

            <!-- Shipping Zones and Rates -->
            <div class="col-12 mt-4">
                <div class="settings-card">
                    <h2 class="settings-title">Shipping Zones & Rates</h2>
                    
                    <!-- Add New Zone Form -->
                    <div class="mb-4">
                        <h3>Add New Shipping Zone</h3>
                        <form method="POST" class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label">Zone Name</label>
                                <input type="text" class="form-control" name="zone_name" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Regions (comma-separated)</label>
                                <input type="text" class="form-control" name="zone_regions" 
                                       placeholder="Region 1, Region 2, Region 3" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" name="add_zone" class="btn btn-primary w-100">
                                    Add Zone
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Existing Zones and Rates -->
                    <?php foreach ($shipping_zones as $zone): ?>
                        <div class="zone-section mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h3><?= htmlspecialchars($zone['zone_name']) ?></h3>
                                <span class="badge bg-info">
                                    <?= count(json_decode($zone['zone_regions'], true)) ?> regions
                                </span>
                            </div>
                            
                            <!-- Regions List -->
                            <div class="mb-3">
                                <small class="text-muted">Regions: </small>
                                <?php foreach (json_decode($zone['zone_regions'], true) as $region): ?>
                                    <span class="badge bg-secondary me-1">
                                        <?= htmlspecialchars($region) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>

                            <!-- Add New Rate Form -->
                            <div class="mb-3">
                                <form method="POST" class="row g-3 align-items-end">
                                    <div class="col-md-3">
                                        <label class="form-label">Shipping Method</label>
                                        <input type="text" class="form-control" name="shipping_method" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Base Rate (KSH)</label>
                                        <input type="number" class="form-control" name="base_rate" 
                                               step="0.01" min="0" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Description</label>
                                        <input type="text" class="form-control" name="description">
                                    </div>
                                    <div class="col-md-2">
                                        <input type="hidden" name="zone_id" value="<?= $zone['zone_id'] ?>">
                                        <button type="submit" name="add_rate" class="btn btn-outline-primary w-100">
                                            Add Rate
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Existing Rates -->
                            <div class="existing-rates">
                                <?php 
                                $rates = get_zone_rates($db, $zone['zone_id']);
                                foreach ($rates as $rate): ?>
                                    <form method="POST" class="rate-form mb-2">
                                        <div class="row align-items-center">
                                            <div class="col-md-3">
                                                <strong><?= htmlspecialchars($rate['shipping_method']) ?></strong>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="input-group">
                                                    <span class="input-group-text">KSH</span>
                                                    <input type="number" name="rate" class="form-control"
                                                           value="<?= number_format($rate['base_rate'], 2) ?>"
                                                           step="0.01" min="0" required>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($rate['description'] ?? '') ?>
                                                </small>
                                            </div>
                                            <div class="col-md-2">
                                                <input type="hidden" name="rate_id" value="<?= $rate['id'] ?>">
                                                <button type="submit" name="update_shipping_rate" 
                                                        class="btn btn-sm btn-primary w-100">
                                                    Update
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/nav/footer.php'; ?>
</body>
</html>