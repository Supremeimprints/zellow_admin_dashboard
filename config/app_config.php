<?php
// Environment-specific configuration
define('ENV', 'development'); // Options: development, staging, production

// API URLs for different environments
$api_urls = [
    'development' => 'http://localhost/zellow_admin/api',
    'staging' => 'http://staging.example.com/zellow_admin/api',
    'production' => 'http://example.com/zellow_admin/api'
];

define('API_URL', $api_urls[ENV]);

// Other configuration constants
define('SITE_URL', 'http://localhost/zellow_admin');
define('UPLOADS_PATH', __DIR__ . '/../uploads');
