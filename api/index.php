<?php
require_once __DIR__ . '/config/api_config.php';
require_once __DIR__ . '/middleware/auth_middleware.php';

$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);
$path = str_replace('/zellow_admin/api', '', $path);

// Initialize auth middleware
$auth = new AuthMiddleware();

// Route requests
switch ($path) {
    case '/auth/login':
    case '/auth/register':
        require __DIR__ . '/routes/auth.php';
        break;
        
    case '/cart':
    case '/cart/add':
    case '/cart/checkout':
        $auth->handleRequest();
        require __DIR__ . '/routes/cart.php';
        break;
        
    case '/products':
    case '/products/list':
        $auth->handleRequest();
        require __DIR__ . '/routes/products.php';
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Not Found']);
        break;
}
