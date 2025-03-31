<?php
require_once __DIR__ . '/../auth/AuthManager.php';
require_once __DIR__ . '/../../config/database.php';

/**
 * Authenticate user from request headers
 * @return object|null User data if authenticated, null otherwise
 */
function authenticate() {
    $headers = getallheaders();
    $token = str_replace('Bearer ', '', $headers['Authorization'] ?? $headers['authorization'] ?? '');
    
    if (!$token) {
        send_error('No token provided', 401);
    }

    $database = new Database();
    $db = $database->getConnection();
    $authManager = new AuthManager();
    $authManager->setDatabase($db);
    
    $userData = $authManager->validateToken($token);
    
    if (!$userData) {
        send_error('Invalid or expired token', 401);
    }
    
    return $userData;
}

function authorize($requiredRole = null) {
    $userData = authenticate();
    
    if (!$userData) {
        send_error('Unauthorized access', 401);
    }
    
    if ($requiredRole && $userData->role !== $requiredRole) {
        send_error('Insufficient permissions', 403);
    }
    
    return $userData;
}

function checkPermission($permission) {
    $userData = authenticate();
    $permissions = $userData->permissions;
    return isset($permissions[$permission]) && $permissions[$permission] === true;
}
