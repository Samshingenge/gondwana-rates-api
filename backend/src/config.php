<?php
define('API_VERSION', '1.0.0');
define('API_NAME', 'Gondwana Collection Rates API');

define('REMOTE_API_URL', 'https://dev.gondwana-collection.com/Web-Store/Rates/Rates.php');

// Toggle vendor mock to isolate failures (true = return fake success without calling vendor)
if (!defined('REMOTE_API_MOCK')) {
    define('REMOTE_API_MOCK', false);
}

define('UNIT_TYPE_MAPPING', [
    'Standard Unit' => -2147483637,
    'Deluxe Unit'   => -2147483456,
]);

define('ALLOWED_ORIGINS', [
    // Prefer same-origin: serve frontend from this backend and use data-api="/api"
    // Keep these for dev if you must run FE on another port:
    'https://stunning-space-bassoon-g76756xgj6pc64w-5500.app.github.dev',
    'http://127.0.0.1:5500',
    'http://localhost:5500',
    'http://localhost:3000',
    'http://localhost:8080',
    '*', // dev wildcard
]);

date_default_timezone_set('Africa/Windhoek');

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
ini_set('error_log', $logDir . '/php_errors.log');

if (!defined('APP_ENV')) {
    define('APP_ENV', 'development');
}
