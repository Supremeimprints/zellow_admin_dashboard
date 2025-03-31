<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_GET['path'])) {
    http_response_code(400);
    exit('Image path not specified');
}

// Get the absolute path to the zellow_admin directory
$adminRoot = realpath(__DIR__ . '/../../');
$requestedPath = trim($_GET['path'], '/');

// Combine paths and clean them
$fullPath = $adminRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $requestedPath);
$fullPath = realpath($fullPath);

// Security check - ensure the file is within the uploads directory
if (!$fullPath || strpos($fullPath, $adminRoot . DIRECTORY_SEPARATOR . 'uploads') !== 0) {
    error_log("Invalid file path requested: " . $_GET['path']);
    http_response_code(403);
    exit('Access denied');
}

if (!file_exists($fullPath)) {
    error_log("File not found: " . $fullPath);
    http_response_code(404);
    exit('Image not found');
}

// Validate file type
$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $fullPath);
finfo_close($finfo);

if (!in_array($mimeType, $allowedMimes)) {
    error_log("Invalid file type: " . $mimeType);
    http_response_code(400);
    exit('Invalid file type');
}

// Debug logging
error_log("Serving image: " . $fullPath);
error_log("Mime type: " . $mimeType);

// Set headers and serve the file
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: public, max-age=31536000');
header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 31536000));
header('Last-Modified: ' . gmdate('D, d M Y H:i:s \G\M\T', filemtime($fullPath)));

if (ob_get_level()) ob_end_clean();
readfile($fullPath);
