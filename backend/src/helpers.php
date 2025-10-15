<?php
/**
 * Helper Functions (compact)
 * Gondwana Collection Rates API
 */

function validateDateFormat($date, $format = 'd/m/Y') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

function getClientIp() {
    $headers = [
        'HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','HTTP_X_FORWARDED',
        'HTTP_FORWARDED_FOR','HTTP_FORWARDED','REMOTE_ADDR'
    ];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ips = explode(',', $_SERVER[$h]);
            return trim($ips[0]);
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function logRequest() {
    $logData = [
        'timestamp'     => date('c'),
        'method'        => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
        'uri'           => $_SERVER['REQUEST_URI'] ?? '',
        'ip'            => getClientIp(),
        'user_agent'    => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'referer'       => $_SERVER['HTTP_REFERER'] ?? '',
        'content_type'  => $_SERVER['CONTENT_TYPE'] ?? '',
        'content_length'=> $_SERVER['CONTENT_LENGTH'] ?? 0
    ];
    $logFile = __DIR__ . '/../logs/access.log';
    if (!is_dir(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    file_put_contents($logFile, json_encode($logData) . "\n", FILE_APPEND | LOCK_EX);
  
}

/**
 * CORS helper – single source of truth
 */
if (!function_exists('send_cors_headers')) {
    function send_cors_headers(): void {
        $allowed = defined('ALLOWED_ORIGINS') ? ALLOWED_ORIGINS : ['*'];
        $origin  = $_SERVER['HTTP_ORIGIN'] ?? '*';
        $allowOrigin = in_array('*', $allowed, true) ? '*' :
                       (in_array($origin, $allowed, true) ? $origin : reset($allowed));
        header("Access-Control-Allow-Origin: $allowOrigin");
        header('Vary: Origin');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
    }
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
