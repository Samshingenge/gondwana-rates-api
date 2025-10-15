<?php
/**
 * Environment-based Configuration
 * Provides secure, environment-specific settings
 */

class EnvironmentConfig {
    private static $config = [];
    private static $loaded = false;

    public static function load() {
        if (self::$loaded) {
            return;
        }

        // Load .env file if it exists
        self::loadEnvFile();

        // Set environment-specific configurations
        self::$config = match(getenv('APP_ENV') ?: 'development') {
            'production' => self::getProductionConfig(),
            'testing' => self::getTestingConfig(),
            'development' => self::getDevelopmentConfig(),
            default => self::getDevelopmentConfig()
        };

        self::$loaded = true;
    }

    private static function loadEnvFile() {
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && !str_starts_with($line, '#')) {
                    [$key, $value] = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value, '"\'');
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                }
            }
        }
    }

    private static function getDevelopmentConfig() {
        return [
            'debug' => true,
            'log_level' => 'DEBUG',
            'allowed_origins' => [
                'http://127.0.0.1:5500',
                'http://localhost:5500',
                'http://localhost:3000',
                'http://localhost:8080',
            ],
            'rate_limiting' => [
                'enabled' => false,
                'requests' => 1000,
                'window' => 300
            ],
            'security_headers' => [
                'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'DENY',
                'X-XSS-Protection' => '1; mode=block',
                'Referrer-Policy' => 'strict-origin-when-cross-origin',
                'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self'; connect-src 'self' https:",
                'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()'
            ]
        ];
    }

    private static function getTestingConfig() {
        return [
            'debug' => true,
            'log_level' => 'DEBUG',
            'allowed_origins' => ['*'],
            'rate_limiting' => [
                'enabled' => false,
                'requests' => 10000,
                'window' => 60
            ],
            'security_headers' => [
                'X-Content-Type-Options' => 'nosniff',
                'X-XSS-Protection' => '1; mode=block'
            ]
        ];
    }

    private static function getProductionConfig() {
        return [
            'debug' => false,
            'log_level' => 'WARNING',
            'allowed_origins' => array_filter([
                getenv('FRONTEND_URL'),
                getenv('ADMIN_URL'),
                'https://' . (getenv('PRIMARY_DOMAIN') ?: 'yourdomain.com'),
                'https://www.' . (getenv('PRIMARY_DOMAIN') ?: 'yourdomain.com')
            ]),
            'rate_limiting' => [
                'enabled' => true,
                'requests' => 100,
                'window' => 300
            ],
            'security_headers' => [
                'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'DENY',
                'X-XSS-Protection' => '1; mode=block',
                'Referrer-Policy' => 'strict-origin-when-cross-origin',
                'Content-Security-Policy' => "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data: https:; font-src 'self'; connect-src 'self'",
                'Permissions-Policy' => 'geolocation=(), microphone=(), camera=(), payment=()',
                'Expect-CT' => 'max-age=86400, enforce'
            ]
        ];
    }

    public static function get($key, $default = null) {
        self::load();
        return self::$config[$key] ?? $default;
    }

    public static function set($key, $value) {
        self::load();
        self::$config[$key] = $value;
    }

    public static function has($key) {
        self::load();
        return isset(self::$config[$key]);
    }
}

// Helper functions for backward compatibility
function env($key, $default = null) {
    return EnvironmentConfig::get($key, $default);
}

function isProduction() {
    return (getenv('APP_ENV') ?: 'development') === 'production';
}

function isDevelopment() {
    return (getenv('APP_ENV') ?: 'development') === 'development';
}

function isTesting() {
    return (getenv('APP_ENV') ?: 'development') === 'testing';
}