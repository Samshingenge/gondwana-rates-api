<?php
declare(strict_types=1);

/**
 * helpers.php
 * - Date validation
 * - Client IP detection (proxy-aware)
 * - Simple request logging
 * - CORS headers (+ preflight helper)
 * - Unit capacity helper & JSON response helper
 */

function validateDateFormat(string $date, string $format = 'd/m/Y'): bool {
    if ($date === '') return false;
    $dt = DateTime::createFromFormat($format, $date);
    if (!$dt) return false;
    $errors = DateTime::getLastErrors();
    if (!empty($errors['warning_count']) || !empty($errors['error_count'])) return false;
    return $dt->format($format) === $date;
}

function getClientIp(): string {
    $headerKeys = [
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR',
    ];
    foreach ($headerKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $parts = array_map('trim', explode(',', (string)$_SERVER[$key]));
            foreach ($parts as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE)) return $ip;
                if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

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
    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
    @file_put_contents($logFile, json_encode($logData, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
}

if (!function_exists('send_cors_headers')) {
    function send_cors_headers(): void {
        $allowed = defined('ALLOWED_ORIGINS') ? (array)ALLOWED_ORIGINS : ['*'];
        $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';

        if ($origin && (in_array('*', $allowed, true) || in_array($origin, $allowed, true))) {
            header("Access-Control-Allow-Origin: {$origin}");
            header('Access-Control-Allow-Credentials: true');
        } else {
            $first = in_array('*', $allowed, true) ? '*' : (reset($allowed) ?: '*');
            header("Access-Control-Allow-Origin: {$first}");
        }

        header('Vary: Origin');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Max-Age: 86400');
    }
}

function is_preflight(): bool {
    return (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS');
}

function get_unit_cap(string $unitName): int {
    static $caps = [
        'Standard Unit' => 2,
        'Deluxe Unit'   => 4,
    ];
    return $caps[$unitName] ?? 4;
}

function json_response(array $payload, int $status = 200): void {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($status);
    }
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
}

// Additional helper functions for testing and core functionality
function getAvailableUnits() {
    return defined('UNIT_TYPE_MAPPING') && is_array(UNIT_TYPE_MAPPING) ? UNIT_TYPE_MAPPING : [
        'Standard Unit' => -2147483637,
        'Deluxe Unit'   => -2147483456,
    ];
}

function formatCurrency($amount, $currency = 'NAD') {
    if (!is_numeric($amount)) {
        $amount = 0;
    }
    return number_format((float)$amount, 2) . ' ' . $currency;
}

function calculateNights($arrival, $departure) {
    if (!$arrival || !$departure) return 1;

    $a = DateTime::createFromFormat('d/m/Y', $arrival);
    $d = DateTime::createFromFormat('d/m/Y', $departure);

    if (!$a || !$d) return 1;
    return max(1, $a->diff($d)->days);
}

function sanitizeInput($data) {
    if (is_string($data)) {
        return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return $data;
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}


function validatePhoneNumber($phone) {
    // Basic Namibian phone validation - return formatted number or false
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    if (str_starts_with($phone, '0')) {
        $phone = '+264' . substr($phone, 1);
    }
    return preg_match('/^\+264[0-9]{8,9}$/', $phone) ? $phone : false;
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < 3) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

function isMobileDevice() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return preg_match('/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $userAgent) ? true : false;
}
