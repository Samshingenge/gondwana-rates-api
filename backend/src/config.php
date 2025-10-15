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
feature/security-phase1-hardening
    'https://yourdomain.com', // Replace with your actual domain
    'https://www.yourdomain.com', // Replace with your actual domain

    '*', // dev wildcard
 main
]);

date_default_timezone_set('Africa/Windhoek');

feature/security-phase1-hardening
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


error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
$logDir = __DIR__ . '/../logs';
if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
ini_set('error_log', $logDir . '/php_errors.log');

if (!defined('APP_ENV')) {
    define('APP_ENV', 'development');
}
 main
