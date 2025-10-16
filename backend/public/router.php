<?php
declare(strict_types=1);

// Check if this is a server start request
if (isset($_GET['start']) && $_GET['start'] === 'project') {
    start_project_servers();
    exit;
}

// Serve existing static files under backend/public directly
$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . $uri;
if ($uri !== '/' && is_file($file)) {
    return false; // let PHP built-in server serve it
}

// Route /api/* to API front controller (it handles CORS + JSON)
if (strncmp($uri, '/api', 4) === 0) {
    $apiEntry = __DIR__ . '/api/index.php';
    if (is_file($apiEntry)) {
        require $apiEntry;
        exit;
    }
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => ['message' => 'API entry not found', 'code' => 500]]);
    exit;
}

// Serve the frontend from repo /frontend (same-origin, no CORS needed)
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $frontendDir = realpath(__DIR__ . '/../../frontend');
    if ($frontendDir !== false) {
        $requestedPath = $uri === '/' ? '/index.html' : $uri;
        $candidate     = realpath($frontendDir . $requestedPath);

        if ($candidate !== false
            && strncmp($candidate, $frontendDir, strlen($frontendDir)) === 0
            && is_file($candidate)) {
            serve_frontend_file($candidate);
            exit;
        }
    }
}

// Not found
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo "Not found";

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
    header('Content-Type: ' . ($mimeMap[$ext] ?? 'application/octet-stream'));
    header('Cache-Control: public, max-age=60');
    readfile($file);
}

function start_project_servers(): void
{
    // Kill any existing servers on the required ports
    exec('lsof -ti:8000 | xargs kill -9 2>/dev/null || true');
    exec('lsof -ti:5500 | xargs kill -9 2>/dev/null || true');

    $backendDir = realpath(__DIR__ . '/../..');
    $frontendDir = $backendDir . '/frontend';
    $routerPath = __FILE__;

    // Start backend server (this current server)
    $backendCmd = "php -S localhost:8000 {$routerPath} > /dev/null 2>&1 &";

    // Start frontend server
    $frontendCmd = "cd {$frontendDir} && php -S localhost:5500 > /dev/null 2>&1 &";

    // Execute commands
    exec($backendCmd);
    exec($frontendCmd);

    // Return success response
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => 'Project servers started successfully',
        'servers' => [
            'backend' => 'http://localhost:8000',
            'frontend' => 'http://localhost:5500'
        ]
    ]);
}
