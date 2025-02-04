<?php
// Enable CORS 
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Origin, Content-Type, Accept, Authorization');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// API Constants
define('JWT_SECRET', 'your_jwt_secret_key_here'); // Change this!
define('JWT_EXPIRES', 3600); // 1 hour

// Role-based access permissions
define('PERMISSIONS', [
    'admin' => ['*'], // All access
    'finance_manager' => ['orders', 'transactions', 'reports'],
    'inventory_manager' => ['products', 'inventory', 'categories'],
    'dispatch_manager' => ['orders', 'shipping', 'drivers'],
    'service_manager' => ['services', 'feedback', 'support'],
    'supply_manager' => ['suppliers', 'inventory', 'purchase_orders']
]);
