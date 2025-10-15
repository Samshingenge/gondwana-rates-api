<?php
/**
 * Security Middleware
 * Handles security headers, CORS, and other security measures
 */

class SecurityMiddleware {
    private $config;

    public function __construct() {
        require_once __DIR__ . '/env.php';
        EnvironmentConfig::load();
        $this->config = EnvironmentConfig::get('security_headers', []);
    }

    public function handle() {
        $this->setSecurityHeaders();
        $this->handleCors();
        $this->validateSecureConnection();
    }

    private function setSecurityHeaders() {
        foreach ($this->config as $header => $value) {
            header("$header: $value");
        }
    }

    private function handleCors() {
        $allowedOrigins = EnvironmentConfig::get('allowed_origins', []);
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Check if origin is allowed
        $allowOrigin = '*';
        if (!in_array('*', $allowedOrigins, true)) {
            if (in_array($origin, $allowedOrigins, true)) {
                $allowOrigin = $origin;
            } elseif (count($allowedOrigins) > 0) {
                $allowOrigin = $allowedOrigins[0];
            }
        }

        header("Access-Control-Allow-Origin: $allowOrigin");
        header('Vary: Origin');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400');

        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }

    private function validateSecureConnection() {
        // In production, ensure HTTPS is used
        if (EnvironmentConfig::get('debug') === false) {
            $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
            $isSecureProxy = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https';

            if (!$isHttps && !$isSecureProxy) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => [
                        'message' => 'HTTPS required',
                        'code' => 403,
                        'timestamp' => date('c')
                    ]
                ]);
                exit();
            }
        }
    }

    public function validateApiKey() {
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? null;

        if (EnvironmentConfig::get('debug') === false) {
            $expectedKey = getenv('API_KEY');
            if ($expectedKey && $apiKey !== $expectedKey) {
                $this->sendUnauthorized('Invalid API key');
            }
        }
    }

    public function validateContentType() {
        $method = $_SERVER['REQUEST_METHOD'];
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';

        // For POST/PUT requests, ensure proper content type
        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            if (strpos($contentType, 'application/json') === false &&
                strpos($contentType, 'multipart/form-data') === false) {
                $this->sendError('Content-Type must be application/json or multipart/form-data', 400);
            }
        }
    }

    private function sendUnauthorized($message) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => 401,
                'timestamp' => date('c')
            ]
        ]);
        exit();
    }

    private function sendError($message, $code) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $code,
                'timestamp' => date('c')
            ]
        ]);
        exit();
    }

    public static function sanitizeInput($data) {
        if (is_string($data)) {
            // Remove null bytes
            $data = str_replace("\0", '', $data);

            // Remove control characters except newlines and tabs
            $data = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $data);

            // Escape HTML entities
            return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        if (is_array($data)) {
            return array_map([__CLASS__, 'sanitizeInput'], $data);
        }

        if (is_object($data)) {
            return (object)array_map([__CLASS__, 'sanitizeInput'], (array)$data);
        }

        return $data;
    }

    public static function validateJsonInput() {
        $input = file_get_contents('php://input');

        if (empty($input)) {
            throw new Exception('No input data received');
        }

        $decoded = json_decode($input, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON: ' . json_last_error_msg());
        }

        if (!is_array($decoded)) {
            throw new Exception('Input must be a JSON object');
        }

        // Sanitize the input
        return self::sanitizeInput($decoded);
    }
}