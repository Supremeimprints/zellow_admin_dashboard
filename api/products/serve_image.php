<?php
// Get image path from query parameter
$imagePath = isset($_GET['path']) ? $_GET['path'] : null;

if (!$imagePath) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'Image path is required']);
    exit;
}

// Ensure path starts with uploads/products
if (!str_starts_with($imagePath, 'uploads/products/')) {
    $imagePath = 'uploads/products/' . basename($imagePath);
}

// Clean up path to prevent directory traversal
$imagePath = str_replace(['..', '//', '\\'], ['', '/', '/'], $imagePath);
$imagePath = ltrim($imagePath, '/');

// Construct full path
$fullPath = __DIR__ . '/../../' . $imagePath;
$realPath = realpath($fullPath);

// Security checks
if (!$realPath || !file_exists($realPath)) {
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode(['error' => 'Image not found', 'path' => $imagePath]);
    exit;
}

// Verify it's within the uploads/products directory
$uploadsDir = realpath(__DIR__ . '/../../uploads/products');
if (strpos($realPath, $uploadsDir) !== 0) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

// Get file extension
$extension = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));

// Validate file type
$allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
if (!in_array($extension, $allowedTypes)) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type']);
    exit;
}

// Set proper content type
$contentTypes = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif'
];

// Set headers for image serving
header('Content-Type: ' . $contentTypes[$extension]);
header('Content-Length: ' . filesize($realPath));
header('Cache-Control: public, max-age=86400'); // Cache for 24 hours
header('Pragma: public');

// Output the file
readfile($realPath);
exit;
