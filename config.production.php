<?php
define('DB_HOST', 'your_production_db_host');
define('DB_USER', 'your_production_db_user');
define('DB_PASS', 'your_production_db_password');
define('DB_NAME', 'your_production_db_name');

define('SITE_URL', 'https://your-domain.com');
define('ENVIRONMENT', 'production');

// Security settings
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', 'error.log');
