<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/utils/api_response.php';

// Allow CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Log the request for debugging
error_log("API Request: " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI']);

// Get request path
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base_path = '/zellow_admin/api';
$path = str_replace($base_path, '', $request_uri);
$method = $_SERVER['REQUEST_METHOD'];

// API Routes
try {
    switch ($path) {
        // Admin Overview Endpoint
        case '/dashboard/overview':
            if ($method === 'GET') {
                require __DIR__ . '/dashboard/overview.php';
            } else {
                send_error('Method not allowed', 405);
            }
            break;

        // Orders endpoints
        case '/orders':
            switch ($method) {
                case 'GET':
                    require __DIR__ . '/orders/list.php';
                    break;
                case 'POST':
                    require __DIR__ . '/orders/create.php';
                    break;
                default:
                    send_error('Method not allowed', 405);
            }
            break;

        case '/orders/get':
            if ($method === 'GET') {
                require __DIR__ . '/orders/get.php';
            } else {
                send_error('Method not allowed', 405);
            }
            break;

        case '/orders/cancel':
            if ($method === 'POST') {
                require __DIR__ . '/orders/cancel.php';
            } else {
                send_error('Method not allowed', 405);
            }
            break;

        case '/orders/status':
            if ($method === 'PUT') {
                require __DIR__ . '/orders/update_status.php';
            } else {
                send_error('Method not allowed', 405);
            }
            break;

        // Authentication endpoints
        case '/auth/login':
            if ($method === 'POST') {
                require __DIR__ . '/auth/login.php';
            } else {
                send_error('Method not allowed', 405);
            }
            break;

        case '/auth/customer_login':
            if ($method === 'POST') {
                require __DIR__ . '/auth/customer_login.php';
            } else {
                send_error('Method not allowed', 405);
            }
            break;

        // Payment Methods endpoints
        case '/payments/methods':
            switch ($method) {
                case 'GET':
                    require __DIR__ . '/payments/methods.php';
                    break;
                case 'POST':
                    require __DIR__ . '/payments/methods.php';
                    break;
                case 'PUT':
                    require __DIR__ . '/payments/methods.php';
                    break;
                case 'DELETE':
                    require __DIR__ . '/payments/methods.php';
                    break;
                default:
                    send_error('Method not allowed', 405);
            }
            break;

        case '/payments/verify':
            if ($method === 'POST') {
                require __DIR__ . '/payments/verify.php';
            } else {
                send_error('Method not allowed', 405);
            }
            break;

        // Add this case to handle product image requests
        case '/products/image':
            if ($method === 'GET') {
                require __DIR__ . '/products/serve_image.php';
            } else {
                send_error('Method not allowed', 405);
            }
            break;

        // Shipping endpoints
        case '/shipping/regions':
            require __DIR__ . '/shipping/regions.php';
            break;

        case '/shipping/methods':
            require __DIR__ . '/shipping/methods.php';
            break;

        case '/shipping/rates':
            require __DIR__ . '/shipping/rates.php';
            break;

        default:
            send_error('Endpoint not found', 404);
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    send_error('Server error', 500);
}
