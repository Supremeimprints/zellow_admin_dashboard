<?php
session_start();
require_once 'config/database.php';
require_once 'includes/theme.php';
require_once 'includes/functions/settings_helpers.php';
require_once 'includes/functions/shipping_functions.php';
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

        // Add new shipping region
        if (isset($_POST['add_region'])) {
            try {
                $stmt = $db->prepare("INSERT INTO shipping_regions (name, description) VALUES (?, ?)");
                $stmt->execute([$_POST['region_name'], $_POST['region_description']]);
                $_SESSION['success'] = "Region added successfully";
            } catch (Exception $e) {
                $_SESSION['error'] = "Error adding region: " . $e->getMessage();
            }
        }

        // Update region shipping rates
        if (isset($_POST['update_region_rates'])) {
            try {
                $db->beginTransaction();
                
                $regionId = $_POST['region_id'];
                foreach ($_POST['rates'] as $methodId => $rate) {
                    // Insert or update rates
                    $stmt = $db->prepare("
                        INSERT INTO region_shipping_rates (region_id, shipping_method_id, base_rate, per_item_fee)
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE base_rate = ?, per_item_fee = ?
                    ");
                    $stmt->execute([
                        $regionId,
                        $methodId,
                        $rate['base'],
                        $rate['per_item'],
                        $rate['base'],
                        $rate['per_item']
                    ]);
                }
                
                $db->commit();
                $_SESSION['success'] = "Shipping rates updated successfully";
            } catch (Exception $e) {
                $db->rollBack();
                $_SESSION['error'] = "Error updating rates: " . $e->getMessage();
            }
        }

        // Handle region status toggle
        if (isset($_POST['toggle_region'])) {
            try {
                if (toggleRegionStatus($db, $_POST['region_id'])) {
                    $_SESSION['success'] = "Region status updated successfully";
                } else {
                    throw new Exception("Failed to update region status");
                }
            } catch (Exception $e) {
                $_SESSION['error'] = "Error updating region status: " . $e->getMessage();
            }
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }

        // Add new shipping zone
        if (isset($_POST['add_zone'])) {
            try {
                $stmt = $db->prepare("
                    INSERT INTO shipping_zones (zone_name, zone_regions) 
                    VALUES (?, ?)
                ");
                $stmt->execute([
                    $_POST['zone_name'],
                    $_POST['zone_regions']
                ]);
                $_SESSION['success'] = "Zone added successfully";
            } catch (Exception $e) {
                $_SESSION['error'] = "Error adding zone: " . $e->getMessage();
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
$regions = getRegions($db, false);
$shippingMethods = getShippingMethods($db, false);

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

            <!-- Shipping Regions and Rates -->
            <div class="col-12 mt-4">
                <div class="settings-card">
                    <h2 class="settings-title">Shipping Regions & Rates</h2>
                    
                    <!-- Add New Region Form -->
                    <form method="POST" class="mb-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Region Name</label>
                                <input type="text" name="region_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Description</label>
                                <input type="text" name="region_description" class="form-control">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label d-block">&nbsp;</label>
                                <button type="submit" name="add_region" class="btn btn-primary">Add Region</button>
                            </div>
                        </div>
                    </form>

                    <!-- Add New Zone Form -->
                    <form method="POST" class="mb-4 border-top pt-4">
                        <h5>Add Delivery Zone</h5>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Zone Name</label>
                                <input type="text" name="zone_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Areas (comma separated)</label>
                                <input type="text" name="zone_regions" class="form-control" 
                                       placeholder="e.g. Kilimani, Kileleshwa, Lavington">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label d-block">&nbsp;</label>
                                <button type="submit" name="add_zone" class="btn btn-primary">Add Zone</button>
                            </div>
                        </div>
                    </form>

                    <!-- Regions List -->
                    <?php foreach ($regions as $region): 
                        $zones = getRegionZones($db, $region['id']);
                    ?>
                        <div class="card mb-3">
                            <div class="card-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-0"><?= htmlspecialchars($region['name']) ?></h6>
                                        <small class="text-muted"><?= htmlspecialchars($region['description']) ?></small>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <form method="POST" class="me-2">
                                            <input type="hidden" name="region_id" value="<?= $region['id'] ?>">
                                            <button type="submit" name="toggle_region" 
                                                    class="btn btn-sm btn-<?= $region['is_active'] ? 'success' : 'danger' ?>">
                                                <?= $region['is_active'] ? 'Active' : 'Inactive' ?>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Zones for this region -->
                                <?php if (!empty($zones)): ?>
                                    <div class="mb-3">
                                        <label class="form-label">Delivery Zones:</label>
                                        <div>
                                            <?php foreach ($zones as $zone): ?>
                                                <span class="badge bg-info me-1 mb-1">
                                                    <?= htmlspecialchars($zone['zone_name']) ?>
                                                    <span class="text-muted small">
                                                        (<?= htmlspecialchars($zone['zone_regions']) ?>)
                                                    </span>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <!-- Shipping Rates Table -->
                                <form method="POST">
                                    <input type="hidden" name="region_id" value="<?= $region['id'] ?>">
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Shipping Method</th>
                                                    <th>Base Rate (Ksh)</th>
                                                    <th>Per Additional Item (Ksh)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $regionRates = getRegionRates($db, $region['id']);
                                                $ratesMap = array_column($regionRates, null, 'shipping_method_id');
                                                
                                                foreach ($shippingMethods as $method): 
                                                    $rate = $ratesMap[$method['id']] ?? ['base_rate' => 0, 'per_item_fee' => 0];
                                                ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($method['display_name']) ?></td>
                                                        <td>
                                                            <input type="number" 
                                                                   name="rates[<?= $method['id'] ?>][base]" 
                                                                   class="form-control form-control-sm"
                                                                   value="<?= $rate['base_rate'] ?>"
                                                                   step="0.01" min="0" required>
                                                        </td>
                                                        <td>
                                                            <input type="number" 
                                                                   name="rates[<?= $method['id'] ?>][per_item]" 
                                                                   class="form-control form-control-sm"
                                                                   value="<?= $rate['per_item_fee'] ?>"
                                                                   step="0.01" min="0" required>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="text-end mt-3">
                                        <button type="submit" name="update_region_rates" class="btn btn-primary btn-sm">
                                            Update Rates
                                        </button>
                                    </div>
                                </form>
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

<!-- Add some CSS for the zones -->
<style>
.badge {
    font-size: 0.85em;
    padding: 8px 12px;
}
.badge .text-muted {
    opacity: 0.7;
}
</style>