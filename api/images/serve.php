<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Allow public access
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

if (!isset($_GET['path'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Image path not specified']);
    exit;
}

$adminRoot = realpath(__DIR__ . '/../../');
$requestedPath = trim($_GET['path'], '/');
$fullPath = $adminRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $requestedPath);
$fullPath = realpath($fullPath);

// Security check
if (!$fullPath || strpos($fullPath, $adminRoot . DIRECTORY_SEPARATOR . 'uploads') !== 0) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Access denied']);
    exit;
}

if (!file_exists($fullPath)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Image not found']);
    exit;
}

$mimeTypes = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp'
];

$extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
if (!isset($mimeTypes[$extension])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid image type']);
    exit;
}

// Set cache headers for better performance
$etag = md5_file($fullPath);
$lastModified = gmdate('D, d M Y H:i:s \G\M\T', filemtime($fullPath));

header("ETag: \"$etag\"");
header("Last-Modified: $lastModified");
header('Cache-Control: public, max-age=31536000');
header("Content-Type: " . $mimeTypes[$extension]);
header('Content-Length: ' . filesize($fullPath));

readfile($fullPath);
