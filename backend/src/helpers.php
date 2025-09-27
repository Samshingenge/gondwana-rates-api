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
