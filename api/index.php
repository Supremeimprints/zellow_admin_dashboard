<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Access-Control-Allow-Methods, Authorization, X-Requested-With');

require_once __DIR__ . '/config/api_config.php';
require_once __DIR__ . '/middleware/auth_middleware.php';
require_once __DIR__ . '/utils/response.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Parse request URI
$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$request = str_replace('/zellow_admin/api', '', $request);

// Initialize auth middleware
$auth = new AuthMiddleware();

// Route requests
try {
    switch ($request) {
        // Root endpoint
        case '/':
            ApiResponse::success([
                'name' => 'Zellow Admin API',
                'version' => '1.0.0',
                'status' => 'active',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        // Auth routes (no middleware)
        case '/auth/login':
        case '/auth/register':
            require __DIR__ . '/routes/auth.php';
            break;
            
        // Protected routes (with middleware)    
        case '/user/profile':
        case '/user/orders':
            $auth->handleRequest();
            require __DIR__ . '/routes/user.php';
            break;
            
        // Orders endpoints
        case '/orders':
        case (preg_match('#^/orders/\d+$#', $request) ? true : false):
            $auth->handleRequest();
            require __DIR__ . '/routes/orders.php';
            break;
            
        case '/services/request':
        case '/services/list':
            $auth->handleRequest(); 
            require __DIR__ . '/routes/services.php';
            break;

        case '/feedback':
            $auth->handleRequest();
            require __DIR__ . '/routes/feedback.php'; 
            break;
            
        // ... other routes
            
        default:
            throw new Exception('Endpoint not found', 404);
    }
} catch (Exception $e) {
    ApiResponse::error($e->getMessage(), $e->getCode());
}
