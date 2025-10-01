<?php
// Always send CORS (dev)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowOrigin = $origin ?: '*';

header("Access-Control-Allow-Origin: " . $allowOrigin);
header('Vary: Origin');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Max-Age: 86400');
// Only allow credentials when we have a concrete Origin (not '*')
if ($origin) {
    header('Access-Control-Allow-Credentials: true');
}

header('X-Router: on'); // debug marker

// Preflight short-circuit
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Serve assets from backend/public directly when present
$docFile = __DIR__ . $uri;
if ($uri !== '/' && is_file($docFile)) {
    return false; // allow PHP dev server to stream the file
}

// Attempt to serve the frontend bundle from ../frontend for same-origin SPA hosting
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $frontendDir = realpath(__DIR__ . '/../../frontend');
    if ($frontendDir !== false) {
        $requestedPath = $uri === '/' ? '/index.html' : $uri;
        $candidate = realpath($frontendDir . $requestedPath);

        if ($candidate && str_starts_with($candidate, $frontendDir) && is_file($candidate)) {
            serve_frontend_file($candidate);
            exit;
        }
    }
}

// Everything else → API front controller
require __DIR__ . '/index.php';

function serve_frontend_file(string $file): void
{
    static $mimeMap = [
        'html' => 'text/html; charset=utf-8',
        'htm'  => 'text/html; charset=utf-8',
        'js'   => 'application/javascript; charset=utf-8',
        'css'  => 'text/css; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
    ];

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $contentType = $mimeMap[$ext] ?? 'application/octet-stream';

    header('Content-Type: ' . $contentType);
    header('Cache-Control: public, max-age=60');

    readfile($file);
}
