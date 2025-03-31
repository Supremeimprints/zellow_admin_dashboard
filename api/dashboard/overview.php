<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/api_response.php';
require_once __DIR__ . '/../../includes/functions/auth_functions.php';
require_once __DIR__ . '/../../includes/functions/financial_functions.php';  // Add this line

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Improved token handling
$headers = getallheaders();
$token = null;

// Check multiple possible token locations
if (isset($headers['Authorization'])) {
    $token = str_replace('Bearer ', '', $headers['Authorization']);
} elseif (isset($headers['authorization'])) {
    $token = str_replace('Bearer ', '', $headers['authorization']);
} elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
}

if (!$token) {
    send_error("No authorization token provided", 401);
}

// Verify admin token with error details
try {
    if (!verify_admin_token($token)) {
        send_error("Invalid or expired token", 403);
    }
} catch (Exception $e) {
    error_log("Token verification error: " . $e->getMessage());
    send_error("Token verification failed", 403);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    // Prepare admin overview data with safe defaults
    $overviewData = [
        'totalUsers' => 0,
        'totalTechnicians' => 0,
        'totalDrivers' => 0,
        'totalSuppliers' => 0,
        'orderStats' => [],
        'revenueStats' => [
            'totalRevenue' => 0,
            'completedOrders' => 0,
            'avgOrderValue' => 0
        ],
        'inventoryStats' => [
            'totalItems' => 0,
            'totalStock' => 0,
            'lowStockCount' => 0
        ],
        'recentActivities' => [],
        'notifications' => []
    ];

    // Count Users by Role (wrapped in try-catch)
    try {
        $stmt = $db->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            switch ($row['role']) {
                case 'technician': $overviewData['totalTechnicians'] = $row['count']; break;
                case 'driver': $overviewData['totalDrivers'] = $row['count']; break;
                case 'supplier': $overviewData['totalSuppliers'] = $row['count']; break;
                default: $overviewData['totalUsers'] += $row['count']; break;
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching user counts: " . $e->getMessage());
    }

    // Order Statistics (wrapped in try-catch)
    try {
        $stmt = $db->query("SELECT status, COUNT(id) as count FROM orders GROUP BY status");
        $overviewData['orderStats'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Exception $e) {
        error_log("Error fetching order stats: " . $e->getMessage());
    }

    // Revenue Statistics (updated to use financial functions)
    try {
        // Get date range for last 30 days
        $endDate = date('Y-m-d H:i:s');
        $startDate = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        // Get financial metrics
        $financialMetrics = getFinancialMetrics($db, $startDate, $endDate);
        
        $overviewData['revenueStats'] = [
            'totalRevenue' => $financialMetrics['revenue'] ?? 0,
            'completedOrders' => $financialMetrics['total_orders'] ?? 0,
            'avgOrderValue' => $financialMetrics['avg_order_value'] ?? 0,
            'netProfit' => $financialMetrics['net_profit'] ?? 0,
            'revenueGrowth' => $financialMetrics['revenue_growth'] ?? 0
        ];
    } catch (Exception $e) {
        error_log("Error fetching revenue stats: " . $e->getMessage());
        // Keep default values from overviewData initialization
    }

    // Inventory Statistics (wrapped in try-catch)
    try {
        $stmt = $db->query("
            SELECT COUNT(id) as totalItems,
                   SUM(stock_quantity) as totalStock,
                   COUNT(CASE WHEN stock_quantity <= min_stock_level THEN 1 END) as lowStockCount
            FROM inventory
        ");
        $inventoryStats = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($inventoryStats) {
            $overviewData['inventoryStats'] = $inventoryStats;
        }
    } catch (Exception $e) {
        error_log("Error fetching inventory stats: " . $e->getMessage());
    }

    // Skip activity_logs and notifications queries as tables don't exist yet
    
    send_success("Admin Overview Retrieved", $overviewData);

} catch (Exception $e) {
    error_log("Admin Overview API Error: " . $e->getMessage());
    send_error("Server error: " . $e->getMessage(), 500);
}
?>
