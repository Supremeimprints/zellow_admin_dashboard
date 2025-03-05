<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Log the request for debugging
error_log("API Request: " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI']);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/utils/api_response.php';

// Get the requested endpoint
$request_uri = $_SERVER['REQUEST_URI'];
$base_path = '/zellow_admin/api/';
$endpoint = str_replace($base_path, '', $request_uri);

// Remove query string if present
$endpoint = strtok($endpoint, '?');

// Route to appropriate handler
switch ($endpoint) {
    case 'auth/customer_login':
        require __DIR__ . '/auth/customer_login.php';
        break;
    
    case 'auth/login':
        require __DIR__ . '/auth/login.php';
        break;
        
    case 'dashboard/overview':
        require __DIR__ . '/dashboard/overview.php';
        break;
        
    default:
        send_error("Endpoint not found", 404);
}
