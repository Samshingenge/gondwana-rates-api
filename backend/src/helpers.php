<?php
declare(strict_types=1);

/**
 * helper.php
 * - Date validation
 * - Client IP detection (proxy-aware)
 * - Simple request logging
 * - CORS headers
 * - (Optional) Unit capacity helper for backend-side checks
 */

/**
 * Validate a date string matches a specific PHP date() format exactly.
 * Defaults to European dd/mm/YYYY (same as your UI).
 */
function validateDateFormat(string $date, string $format = 'd/m/Y'): bool {
    if ($date === '') return false;

    $dt = DateTime::createFromFormat($format, $date);
    if (!$dt) return false;

    // Ensure no parse warnings/errors and a strict re-format match
    $errors = DateTime::getLastErrors();
    if (!empty($errors['warning_count']) || !empty($errors['error_count'])) {
        return false;
    }
    return $dt->format($format) === $date;
}

/**
 * Best-effort client IP detection (proxy aware).
 * Reads common proxy headers but validates each token as an IP.
 * NOTE: For production, pair with a trusted-proxy allowlist to avoid spoofing.
 */
function getClientIp(): string {
    $headerKeys = [
        'HTTP_X_FORWARDED_FOR', // may contain "client, proxy1, proxy2"
        'HTTP_X_REAL_IP',
        'HTTP_CF_CONNECTING_IP', // Cloudflare
        'HTTP_X_FORWARDED',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR',
    ];

    foreach ($headerKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $raw = $_SERVER[$key];

            // X-Forwarded-For can be a comma-separated list (left-most is original client)
            $parts = array_map('trim', explode(',', (string)$raw));
            foreach ($parts as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
                // If you also want to allow RFC1918 addresses, drop FILTER_FLAG_NO_RES_RANGE:
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Append a single-line JSON record to logs/access.log.
 */
function logRequest(): void {
    $logData = [
        'timestamp'      => gmdate('c'),
        'method'         => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
        'uri'            => $_SERVER['REQUEST_URI'] ?? '',
        'ip'             => getClientIp(),
        'user_agent'     => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'referer'        => $_SERVER['HTTP_REFERER'] ?? '',
        'content_type'   => $_SERVER['CONTENT_TYPE'] ?? '',
        'content_length' => isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0,
    ];

    $logFile = __DIR__ . '/../logs/access.log';
    $dir = dirname($logFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($logFile, json_encode($logData, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Send CORS headers.
 * - If an Origin header is present and is allowed, echo it back and allow credentials.
 * - If no Origin, fall back to '*' (without credentials).
 *
 * Define ALLOWED_ORIGINS in a config if you want to lock this down, e.g.:
 *   define('ALLOWED_ORIGINS', ['https://yourdomain.com', 'http://localhost:5173']);
 */
if (!function_exists('send_cors_headers')) {
    function send_cors_headers(): void {
        $allowed = defined('ALLOWED_ORIGINS') ? (array)ALLOWED_ORIGINS : ['*'];
        $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';

        $allowOrigin = '*';

        if ($origin) {
            // If wildcard is not explicitly allowed, require a match in the allowlist
            if (in_array('*', $allowed, true) || in_array($origin, $allowed, true)) {
                $allowOrigin = $origin; // echo origin to allow credentials
                header("Access-Control-Allow-Origin: {$allowOrigin}");
                header('Access-Control-Allow-Credentials: true');
            } else {
                // Not in allowlist: return the first allowed origin or '*' (without creds)
                $first = reset($allowed) ?: '*';
                header("Access-Control-Allow-Origin: {$first}");
            }
        } else {
            // No Origin header (non-CORS or same-origin XHR)
            $first = in_array('*', $allowed, true) ? '*' : (reset($allowed) ?: '*');
            header("Access-Control-Allow-Origin: {$first}");
            // No credentials when using '*'
        }

        header('Vary: Origin');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Max-Age: 86400'); // 24h
    }
}

/**
 * If you want to short-circuit CORS preflight requests in your endpoints:
 *
 *   send_cors_headers();
 *   if (is_preflight()) { http_response_code(204); exit; }
 */
function is_preflight(): bool {
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS';
}

/**
 * Optional: backend-side unit capacity helper to mirror the frontend.
 * Use this on /rates to reject payloads that exceed the unit cap.
 */
function get_unit_cap(string $unitName): int {
    static $caps = [
        'Standard Unit' => 2,
        'Deluxe Unit'   => 4,
    ];
    return $caps[$unitName] ?? 4; // default safety cap
}

/**
 * Small JSON response helper.
 */
function json_response(array $payload, int $status = 200): void {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($status);
    }
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
}
