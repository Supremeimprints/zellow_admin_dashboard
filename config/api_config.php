<?php
// Generate a secure random secret key if one doesn't exist
$jwt_secret_file = __DIR__ . '/jwt_secret.txt';
if (!file_exists($jwt_secret_file)) {
    $jwt_secret = bin2hex(random_bytes(32));
    file_put_contents($jwt_secret_file, $jwt_secret);
} else {
    $jwt_secret = file_get_contents($jwt_secret_file);
}

define('JWT_SECRET', $jwt_secret);
define('PERMISSIONS', [
    'admin' => ['*'],
    'user' => ['services', 'feedback', 'orders']
]);
