<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/api_response.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        include_once 'list.php';
        break;
    case 'POST':
        include_once 'register.php';
        break;
    case 'OPTIONS':
        http_response_code(200);
        exit();
    default:
        send_error("Method not allowed", 405);
}
