<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions/marketing_functions.php';

// Authentication check
if (!isset($_SESSION['id'])) {
    header('Location: login.php');
    exit();
}

// Get campaign ID from URL
$campaign_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$campaign_id) {
    header('Location: campaigns.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Fetch campaign details
$stmt = $db->prepare("SELECT * FROM marketing_campaigns WHERE campaign_id = ?");
$stmt->execute([$campaign_id]);
$campaign = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$campaign) {
    header('Location: campaigns.php');
    exit();
}

// Fetch campaign metrics
$metricsQuery = "SELECT 
    DATE(created_at) as date,
    SUM(impressions) as total_impressions,
    SUM(clicks) as total_clicks,
    SUM(conversions) as total_conversions,
    SUM(spend) as total_spend
    FROM campaign_metrics 
    WHERE campaign_id = ? 
    GROUP BY DATE(created_at)
    ORDER BY date DESC
    LIMIT 30";

$stmt = $db->prepare($metricsQuery);
$stmt->execute([$campaign_id]);
$metrics = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals
$totals = [
    'impressions' => 0,
    'clicks' => 0,
    'conversions' => 0,
    'spend' => 0
];

foreach ($metrics as $metric) {
    $totals['impressions'] += $metric['total_impressions'];
    $totals['clicks'] += $metric['total_clicks'];
    $totals['conversions'] += $metric['total_conversions'];
    $totals['spend'] += $metric['total_spend'];
}

// Calculate CTR and conversion rate
$ctr = $totals['impressions'] ? ($totals['clicks'] / $totals['impressions']) * 100 : 0;
$conversionRate = $totals['clicks'] ? ($totals['conversions'] / $totals['clicks']) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campaign Analytics - <?= htmlspecialchars($campaign['name']) ?></title>
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
    <script src="https://unpkg.com/feather-icons"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .metric-card {
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            background: var(--card-bg);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .metric-value {
            font-size: 2rem;
            font-weight: bold;
            margin: 0.5rem 0;
        }
        
        .metric-label {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        .chart-container {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 2rem;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <?php include 'includes/theme.php'; ?>
        <nav class="navbar">
            <?php include 'includes/nav/collapsed.php'; ?>
        </nav>

        <div class="main-content">
            <div class="container mt-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><?= htmlspecialchars($campaign['name']) ?> Analytics</h2>
                    <a href="promotions.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left"></i> Back to Campaigns
                    </a>
                </div>

                <!-- Campaign Details -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Campaign Details</h5>
                                <p><strong>Platform:</strong> <?= htmlspecialchars($campaign['platform']) ?></p>
                                <p><strong>Duration:</strong> <?= date('M d, Y', strtotime($campaign['start_date'])) ?> - <?= date('M d, Y', strtotime($campaign['end_date'])) ?></p>
                                <p><strong>Budget:</strong> KSH <?= number_format($campaign['budget'], 2) ?></p>
                                <p><strong>Status:</strong> <span class="badge bg-<?= $campaign['status'] === 'active' ? 'success' : 'warning' ?>"><?= ucfirst($campaign['status']) ?></span></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Metrics Summary -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="metric-card">
                            <div class="metric-label">Impressions</div>
                            <div class="metric-value"><?= number_format($totals['impressions']) ?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <div class="metric-label">Clicks</div>
                            <div class="metric-value"><?= number_format($totals['clicks']) ?></div>
                            <div class="metric-label">CTR: <?= number_format($ctr, 2) ?>%</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <div class="metric-label">Conversions</div>
                            <div class="metric-value"><?= number_format($totals['conversions']) ?></div>
                            <div class="metric-label">Conv. Rate: <?= number_format($conversionRate, 2) ?>%</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <div class="metric-label">Total Spend</div>
                            <div class="metric-value">KSH <?= number_format($totals['spend'], 2) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Performance Chart -->
                <div class="chart-container">
                    <canvas id="performanceChart"></canvas>
                </div>

                <!-- Metrics Table -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h5 class="card-title">Daily Metrics</h5>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Impressions</th>
                                        <th>Clicks</th>
                                        <th>CTR</th>
                                        <th>Conversions</th>
                                        <th>Conv. Rate</th>
                                        <th>Spend</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($metrics as $metric): 
                                        $daily_ctr = $metric['total_impressions'] ? ($metric['total_clicks'] / $metric['total_impressions']) * 100 : 0;
                                        $daily_conv_rate = $metric['total_clicks'] ? ($metric['total_conversions'] / $metric['total_clicks']) * 100 : 0;
                                    ?>
                                    <tr>
                                        <td><?= date('M d, Y', strtotime($metric['date'])) ?></td>
                                        <td><?= number_format($metric['total_impressions']) ?></td>
                                        <td><?= number_format($metric['total_clicks']) ?></td>
                                        <td><?= number_format($daily_ctr, 2) ?>%</td>
                                        <td><?= number_format($metric['total_conversions']) ?></td>
                                        <td><?= number_format($daily_conv_rate, 2) ?>%</td>
                                        <td>KSH <?= number_format($metric['total_spend'], 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Chart initialization
        const ctx = document.getElementById('performanceChart').getContext('2d');
        const data = {
            labels: <?= json_encode(array_map(fn($m) => date('M d', strtotime($m['date'])), array_reverse($metrics))) ?>,
            datasets: [
                {
                    label: 'Clicks',
                    data: <?= json_encode(array_map(fn($m) => $m['total_clicks'], array_reverse($metrics))) ?>,
                    borderColor: '#2196F3',
                    tension: 0.4
                },
                {
                    label: 'Conversions',
                    data: <?= json_encode(array_map(fn($m) => $m['total_conversions'], array_reverse($metrics))) ?>,
                    borderColor: '#4CAF50',
                    tension: 0.4
                }
            ]
        };

        new Chart(ctx, {
            type: 'line',
            data: data,
            options: {
                responsive: true,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    title: {
                        display: true,
                        text: 'Campaign Performance Over Time'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>
