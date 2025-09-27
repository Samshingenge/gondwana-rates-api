<?php
// Save this as backend/public/debug.php

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Log everything
$logFile = __DIR__ . '/../logs/debug.log';
$logDir = dirname($logFile);
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'headers' => getallheaders() ?: [],
    'get' => $_GET,
    'post' => $_POST,
    'content_length' => $_SERVER['CONTENT_LENGTH'] ?? 'not set',
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set'
];

// Try multiple ways to get input
$rawInput1 = file_get_contents('php://input');
$rawInput2 = '';

$handle = fopen('php://input', 'r');
if ($handle) {
    $rawInput2 = stream_get_contents($handle);
    fclose($handle);
}

$logData['raw_input_method1'] = [
    'content' => $rawInput1,
    'length' => strlen($rawInput1)
];

$logData['raw_input_method2'] = [
    'content' => $rawInput2,
    'length' => strlen($rawInput2)
];

// Log to file
file_put_contents($logFile, json_encode($logData, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

// Return response
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'message' => 'Debug endpoint working',
        'method' => 'GET'
    ]);
} else {
    $input = null;
    $jsonError = null;
    
    if (!empty($rawInput1)) {
        $input = json_decode($rawInput1, true);
        $jsonError = json_last_error();
    }
    
    echo json_encode([
        'message' => 'Debug endpoint received POST',
        'raw_input_length' => strlen($rawInput1),
        'raw_input_preview' => substr($rawInput1, 0, 100),
        'json_decoded' => $input,
        'json_error' => $jsonError,
        'json_error_msg' => json_last_error_msg(),
        'log_written' => true
    ]);
}
?>