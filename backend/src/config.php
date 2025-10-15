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
    'https://yourdomain.com', // Replace with your actual domain
    'https://www.yourdomain.com', // Replace with your actual domain
]);

date_default_timezone_set('Africa/Windhoek');

// Define APP_ENV early
if (!defined('APP_ENV')) {
    $env = getenv('APP_ENV');
    define('APP_ENV', $env ?: 'development');
}

// Security headers
define('SECURITY_HEADERS', [
    'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
    'X-Content-Type-Options' => 'nosniff',
    'X-Frame-Options' => 'DENY',
    'X-XSS-Protection' => '1; mode=block',
    'Referrer-Policy' => 'strict-origin-when-cross-origin',
    'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self'; connect-src 'self'",
    'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()'
]);

// Rate limiting
define('RATE_LIMIT_REQUESTS', 100); // requests per window
define('RATE_LIMIT_WINDOW', 300); // seconds (5 minutes)
define('RATE_LIMIT_ENABLED', true);

// Dev logging (only in development)
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ERROR | E_PARSE);
    ini_set('display_errors', 0);
}

ini_set('log_errors', 1);
if (!is_dir(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0755, true);
}
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');


// Load environment configuration
require_once __DIR__ . '/env.php';
EnvironmentConfig::load();

// ... other config

