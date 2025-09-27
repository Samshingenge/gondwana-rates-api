<?php
define('API_VERSION', '1.0.0');
define('API_NAME', 'Gondwana Collection Rates API');
define('REMOTE_API_URL', 'https://dev.gondwana-collection.com/Web-Store/Rates/Rates.php');

define('UNIT_TYPE_MAPPING', [
    'Standard Unit' => -2147483637,
    'Deluxe Unit'   => -2147483456,
    // Add more as needed
]);

define('ALLOWED_ORIGINS', [
    'http://127.0.0.1:5500',
    'http://localhost:5500',
    'http://localhost:3000',
    'http://localhost:8080',
    '*', // dev
]);

date_default_timezone_set('Africa/Windhoek');

// Dev logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
if (!is_dir(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0755, true);
}
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');


// ... other config

if (!defined('APP_ENV')) {
    define('APP_ENV', 'development'); // or 'production'
}

