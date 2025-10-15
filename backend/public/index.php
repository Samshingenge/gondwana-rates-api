<?php
// backend/public/index.php

// Load configuration and security classes
require_once __DIR__ . '/../src/config.php';
require_once __DIR__ . '/../src/env.php';
require_once __DIR__ . '/../src/helpers.php';
require_once __DIR__ . '/../src/rates_extract.php';
require_once __DIR__ . '/../src/SecurityMiddleware.php';
require_once __DIR__ . '/../src/RateLimiter.php';

// Initialize environment configuration
EnvironmentConfig::load();

// Initialize security middleware
$security = new SecurityMiddleware();
$security->handle();

// Initialize rate limiter
$rateLimiter = new RateLimiter(
    EnvironmentConfig::get('rate_limiting.requests', 100),
    EnvironmentConfig::get('rate_limiting.window', 300)
);

// DEBUG
error_log("=== INCOMING REQUEST DEBUG ===");
error_log("REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'UNDEFINED'));
error_log("REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'UNDEFINED'));
error_log("=============================");

// CORS
send_cors_headers();

// Handle preflight early
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

header('Content-Type: application/json');

// Log request
logRequest();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';

// Check rate limiting for API endpoints
$rateLimitEnabled = EnvironmentConfig::get('rate_limiting.enabled', false);
if ($rateLimitEnabled && str_starts_with($path, '/api/')) {
    if (!$rateLimiter->isAllowed($clientIp)) {
        http_response_code(429);
        header('X-RateLimit-Limit: ' . EnvironmentConfig::get('rate_limiting.requests', 100));
        header('X-RateLimit-Remaining: ' . $rateLimiter->getRemainingRequests($clientIp));
        header('X-RateLimit-Reset: ' . $rateLimiter->getResetTime($clientIp));
        header('Retry-After: ' . ($rateLimiter->getResetTime($clientIp) - time()));

        echo json_encode([
            'success' => false,
            'error' => [
                'message' => 'Rate limit exceeded',
                'code' => 429,
                'timestamp' => date('c'),
                'retry_after' => $rateLimiter->getResetTime($clientIp) - time()
            ]
        ]);
        exit();
    }
}

try {
    switch ($method) {
        case 'GET':
            if ($path === '/' || $path === '/api') {
                echo json_encode([
                    'message'   => 'Gondwana Collection Rates API',
                    'version'   => '1.0',
                    'endpoints' => [
                        'POST /api/rates' => 'Get accommodation rates',
                        'POST /api/test'  => 'Test payload echo'
                    ]
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint not found']);
            }
            break;

        case 'POST':
            if ($path === '/api/rates') {
                handleRatesRequest();
            } elseif ($path === '/api/test') {
                try {
                    $input = SecurityMiddleware::validateJsonInput();
                } catch (Exception $e) {
                    $input = null; // Allow empty input for test endpoint
                }
                echo json_encode([
                    'message'       => 'Test endpoint working',
                    'received_data' => $input,
                    'timestamp'     => date('c'),
                    'method'        => 'POST',
                    'path'          => $path
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint not found']);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

// ===== Handlers & helpers =====

function handleRatesRequest() {
    try {
        // Use security middleware for input validation and sanitization
        $input = SecurityMiddleware::validateJsonInput();

        $validationErrors = validateRateRequest($input);
        if (!empty($validationErrors)) {
            http_response_code(400);
            echo json_encode(['error' => 'Validation failed', 'details' => $validationErrors]);
            return;
        }

        $payload = transformPayload($input);
        $remote  = callRemoteAPI($payload);
        if ($remote === false) {
            throw new Exception('Failed to get rates from remote API');
        }

        $processed = processRemoteResponse($remote, $input);
        echo json_encode(['success' => true, 'data' => $processed]);

    } catch (Exception $e) {
        error_log("Rates Request Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}

function validateRateRequest($input) {
    $errors = [];

    $required = ['Unit Name', 'Arrival', 'Departure', 'Occupants', 'Ages'];
    foreach ($required as $f) {
        if (!isset($input[$f]) || $input[$f] === '' || $input[$f] === null) {
            $errors[] = "$f is required";
        }
    }

    if (isset($input['Arrival']) && !validateDateFormat($input['Arrival'], 'd/m/Y')) {
        $errors[] = 'Arrival date must be in dd/mm/yyyy format';
    }
    if (isset($input['Departure']) && !validateDateFormat($input['Departure'], 'd/m/Y')) {
        $errors[] = 'Departure date must be in dd/mm/yyyy format';
    }

    if (isset($input['Occupants']) && (!is_numeric($input['Occupants']) || (int)$input['Occupants'] <= 0)) {
        $errors[] = 'Occupants must be a positive integer';
    }

    if (isset($input['Ages'])) {
        if (!is_array($input['Ages'])) {
            $errors[] = 'Ages must be an array';
        } else {
            foreach ($input['Ages'] as $age) {
                if (!is_numeric($age) || (int)$age < 0) {
                    $errors[] = 'All ages must be non-negative integers';
                    break;
                }
            }
            if (isset($input['Occupants']) && count($input['Ages']) !== (int)$input['Occupants']) {
                $errors[] = 'Number of ages must match number of occupants';
            }
        }
    }

    return $errors;
}

function transformPayload($input) {
    $arrival   = DateTime::createFromFormat('d/m/Y', $input['Arrival']);
    $departure = DateTime::createFromFormat('d/m/Y', $input['Departure']);

    $guests = [];
    foreach ($input['Ages'] as $age) {
        $guests[] = ['Age Group' => ((int)$age >= 18) ? 'Adult' : 'Child'];
    }

    // Map unit names to IDs
    $unitTypeMapping = [
        'Standard Unit' => -2147483637,
        'Deluxe Unit'   => -2147483456,
    ];
    $unitTypeId = $unitTypeMapping[$input['Unit Name']] ?? -2147483637;

    return [
        'Unit Type ID' => $unitTypeId,
        'Arrival'      => $arrival   ? $arrival->format('Y-m-d')   : null,
        'Departure'    => $departure ? $departure->format('Y-m-d') : null,
        'Guests'       => $guests
    ];
}

function callRemoteAPI($payload) {
    $url = defined('REMOTE_API_URL') ? REMOTE_API_URL : 'https://dev.gondwana-collection.com/Web-Store/Rates/Rates.php';
    $options = [
        'http' => [
            'header'  => [
                'Content-Type: application/json',
                'User-Agent: PHP-API-Client/1.0'
            ],
            'method'  => 'POST',
            'content' => json_encode($payload),
            'timeout' => 30
        ]
    ];
    $context = stream_context_create($options);
    $result  = @file_get_contents($url, false, $context);

    if ($result === false) {
        $error = error_get_last();
        error_log("Remote API call failed: " . ($error['message'] ?? 'Unknown error'));
        return false;
    }
    return json_decode($result, true);
}

function processRemoteResponse($remoteResponse, $originalInput) {
    if ($remoteResponse === false || $remoteResponse === null) {
        return [[
            'unit_name'        => $originalInput['Unit Name'],
            'rate'             => null,
            'currency'         => 'NAD',
            'date_range'       => [
                'arrival'   => $originalInput['Arrival'],
                'departure' => $originalInput['Departure'],
            ],
            'availability'     => false,
            'original_response'=> $remoteResponse
        ]];
    }

    // Normalize vendor payload to a single canonical record
    $parsed = extract_rate_payload($remoteResponse);

    return [[
        'unit_name'        => $originalInput['Unit Name'],
        'rate'             => $parsed['rate'],                // null if no usable rate
        'currency'         => $parsed['currency'] ?? 'NAD',
        'date_range'       => [
            'arrival'   => $originalInput['Arrival'],
            'departure' => $originalInput['Departure'],
        ],
        'availability'     => (bool)$parsed['availability'],   // robust availability
        'original_response'=> $parsed['raw']
    ]];
}
