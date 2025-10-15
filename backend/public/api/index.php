<?php
declare(strict_types=1);

require_once __DIR__ . '/../../src/config.php';
require_once __DIR__ . '/../../src/helpers.php';
require_once __DIR__ . '/../../src/rates_extract.php';
require_once __DIR__ . '/../../src/RatesController.php';

// Always return JSON on errors (dev)
set_exception_handler(function(Throwable $e) {
  error_log('[EXC] '.$e->getMessage().' @ '.$e->getFile().':'.$e->getLine());
  if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8', true, 500);
  }
  echo json_encode(['success'=>false,'error'=>['message'=>$e->getMessage(),'code'=>500]]);
});
set_error_handler(function($sev, $msg, $file, $line) {
  throw new ErrorException($msg, 0, $sev, $file, (int)$line);
});
register_shutdown_function(function() {
  $e = error_get_last();
  if ($e && in_array($e['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR], true)) {
    error_log('[FATAL] '.$e['message'].' @ '.$e['file'].':'.$e['line']);
    if (!headers_sent()) {
      header('Content-Type: application/json; charset=utf-8', true, 500);
    }
    echo json_encode(['success'=>false,'error'=>['message'=>'Fatal error','code'=>500]]);
  }
});

// API-only CORS
send_cors_headers();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
  http_response_code(204);
  exit;
}

header('Content-Type: application/json; charset=utf-8');
if (function_exists('logRequest')) { logRequest(); }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

$controller = new RatesController();

switch ($method) {
  case 'GET':
    if ($path === '/api' || $path === '/api/') {
      json_response([
        'message'   => defined('API_NAME') ? API_NAME : 'Gondwana Collection Rates API',
        'version'   => defined('API_VERSION') ? API_VERSION : '1.0.0',
        'endpoints' => [
          'POST /api/rates' => 'Get accommodation rates',
          'POST /api/test'  => 'Test payload echo',
        ],
      ], 200);
      break;
    }
    if ($path === '/api/test') {
      $controller->testEndpoint();
      break;
    }
    json_response(['success'=>false,'error'=>['message'=>'Endpoint not found','code'=>404]], 404);
    break;

  case 'POST':
    if ($path === '/api/rates') {
      $controller->getRates();
      break;
    }
    if ($path === '/api/test') {
      $controller->testEndpoint();
      break;
    }
    json_response(['success'=>false,'error'=>['message'=>'Endpoint not found','code'=>404]], 404);
    break;

  default:
    json_response(['success'=>false,'error'=>['message'=>'Method not allowed','code'=>405]], 405);
}
