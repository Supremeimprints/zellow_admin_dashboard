
<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

function requireAuth($requiredRole = null) {
    if (!isset($_SESSION['id'])) {
        header('Location: /zellow_admin/login.php');
        exit();
    }
    
    if ($requiredRole && $_SESSION['role'] !== $requiredRole) {
        header('Location: /zellow_admin/unauthorized.php');
        exit();
    }
}