<?php
require_once __DIR__ . '/../auth/AuthManager.php';

function authenticate() {
    $auth = new AuthManager();
    $userData = $auth->verifyAuth();
    
    if (!$userData) {
        // Try session-based auth if JWT fails
        if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
            return (object)[
                'id' => $_SESSION['id'],
                'email' => $_SESSION['email'],
                'role' => $_SESSION['role'],
                'permissions' => $_SESSION['permissions'] ?? []
            ];
        }
        throw new Exception('Authentication failed');
    }
    
    return $userData;
}

function authorize($requiredRole = null) {
    try {
        $userData = authenticate();
        if ($requiredRole && $userData->role !== $requiredRole) {
            header('Location: /zellow_admin/unauthorized.php');
            exit();
        }
        return $userData;
    } catch (Exception $e) {
        error_log("Authorization error: " . $e->getMessage());
        header('Location: /zellow_admin/login.php');
        exit();
    }
}

function checkPermission($permission) {
    $userData = authenticate();
    $permissions = $userData->permissions;
    return isset($permissions[$permission]) && $permissions[$permission] === true;
}
