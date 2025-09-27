<?php
/**
 * Rates Controller (self-contained)
 * - No dependency on helpers.php (avoids getAvailableUnits() undefined)
 * - Reads unit mapping from UNIT_TYPE_MAPPING constant if defined (config.php)
 */

require_once __DIR__ . '/config.php';

class RatesController {

    private $logFile;

    public function __construct() {
        $this->logFile = dirname(__DIR__) . '/logs/rates.log';
        $this->ensureLogDirectory();
    }

    /**
     * Main rates endpoint handler
     */
    public function getRates() {
        try {
            $input = $this->getJsonInput();
            $this->logRequest('rates', $input);

            $validationErrors = $this->validateRateRequest($input);
            if (!empty($validationErrors)) {
                $this->sendError('Validation failed', 400, ['details' => $validationErrors]);
                return;
            }

            $payload = $this->transformPayload($input);
            $this->logDebug('Transformed payload', $payload);

            $remoteResponse = $this->callRemoteAPI($payload);

            if ($remoteResponse === false) {
                $this->logWarning('Remote API unavailable, returning mock data');
                $response = $this->getMockRatesResponse($input);
            } else {
                $response = $this->processRemoteResponse($remoteResponse, $input);
            }

            $this->sendSuccess($response, [
                'request_id' => uniqid(),
                'processed_at' => date('c'),
                'source' => $remoteResponse === false ? 'mock' : 'remote_api'
            ]);

        } catch (Exception $e) {
            $this->logError('getRates error', $e);
            $this->sendError($e->getMessage(), 500);
        }
    }

    /**
     * Test endpoint for debugging
     */
    public function testEndpoint() {
        try {
            $input = $this->getJsonInput();
            $this->logRequest('test', $input);

            $debugInfo = [
                'message' => 'Test endpoint reached successfully',
                'received_data' => $input,
                'server_info' => $this->getServerInfo(),
                'validation' => $this->validateRateRequest($input ?: []),
                'timestamp' => date('c')
            ];

            if (!empty($input)) {
                $debugInfo['transformed_payload'] = $this->transformPayload($input);
            }

            $this->sendSuccess($debugInfo);

        } catch (Exception $e) {
            $this->logError('testEndpoint error', $e);
            $this->sendError($e->getMessage(), 500);
        }
    }

    // -----------------------------
    // Input / Validation
    // -----------------------------
    private function getJsonInput() {
        $raw = file_get_contents('php://input') ?: '';
        $this->logDebug('Raw input', ['length' => strlen($raw), 'preview' => substr($raw, 0, 200)]);

        if ($raw === '') {
            throw new Exception('No input data received');
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON: ' . json_last_error_msg());
        }
        if (!is_array($decoded)) {
            throw new Exception('Input must be a JSON object');
        }
        return $decoded;
    }

    private function validateRateRequest($input) {
        $errors = [];

        $required = [
            'Unit Name' => 'string',
            'Arrival' => 'string',
            'Departure' => 'string',
            'Occupants' => 'integer',
            'Ages' => 'array'
        ];
        foreach ($required as $field => $type) {
            if (!array_key_exists($field, $input)) {
                $errors[] = "$field is required";
                continue;
            }
            if (!$this->validateFieldType($input[$field], $type)) {
                $errors[] = "$field must be of type $type";
            }
        }

        if (isset($input['Unit Name'])) {
            $validUnits = array_keys($this->availableUnits());
            if (!in_array($input['Unit Name'], $validUnits, true)) {
                $errors[] = 'Unit Name must be one of: ' . implode(', ', $validUnits);
            }
        }

        if (isset($input['Arrival']) && !$this->validateDateFormat($input['Arrival'])) {
            $errors[] = 'Arrival date must be in dd/mm/yyyy format';
        }
        if (isset($input['Departure']) && !$this->validateDateFormat($input['Departure'])) {
            $errors[] = 'Departure date must be in dd/mm/yyyy format';
        }

        if (isset($input['Arrival'], $input['Departure'])) {
            $a = DateTime::createFromFormat('d/m/Y', $input['Arrival']);
            $d = DateTime::createFromFormat('d/m/Y', $input['Departure']);
            if ($a && $d && $a >= $d) {
                $errors[] = 'Departure date must be after arrival date';
            }
        }

        if (isset($input['Occupants']) && (int)$input['Occupants'] <= 0) {
            $errors[] = 'Occupants must be greater than 0';
        }

        if (isset($input['Ages'])) {
            if (!is_array($input['Ages'])) {
                $errors[] = 'Ages must be an array';
            } else {
                foreach ($input['Ages'] as $age) {
                    if (!is_numeric($age) || $age < 0 || $age > 150) {
                        $errors[] = 'All ages must be between 0 and 150';
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

    private function validateFieldType($value, $type) {
        switch ($type) {
            case 'string':  return is_string($value);
            case 'integer': return is_int($value) || (is_numeric($value) && (int)$value == $value);
            case 'array':   return is_array($value);
            default:        return true;
        }
    }

    private function validateDateFormat($date) {
        $d = DateTime::createFromFormat('d/m/Y', $date);
        return $d && $d->format('d/m/Y') === $date;
    }

    // -----------------------------
    // Transform & Remote Call
    // -----------------------------
    private function transformPayload($input) {
        $arrivalDate = DateTime::createFromFormat('d/m/Y', $input['Arrival']);
        $departureDate = DateTime::createFromFormat('d/m/Y', $input['Departure']);

        $map = $this->availableUnits();
        $unitTypeId = $map[$input['Unit Name']] ?? reset($map);

        $guests = [];
        foreach ($input['Ages'] as $age) {
            $guests[] = ['Age Group' => ((int)$age >= 18 ? 'Adult' : 'Child')];
        }

        return [
            'Unit Type ID' => $unitTypeId,
            'Arrival'      => $arrivalDate->format('Y-m-d'),
            'Departure'    => $departureDate->format('Y-m-d'),
            'Guests'       => $guests
        ];
    }

    private function callRemoteAPI($payload) {
        $url = defined('REMOTE_API_URL') ? REMOTE_API_URL
                                         : 'https://dev.gondwana-collection.com/Web-Store/Rates/Rates.php';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: Gondwana-Rates-API/1.0'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false, // dev
            CURLOPT_FOLLOWLOCATION => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($error)) {
            $this->logError('Remote API call failed', new Exception("CURL Error: $error"));
            return false;
        }
        if ($httpCode !== 200) {
            $this->logWarning("Remote API returned HTTP $httpCode", ['response' => $response]);
            return false;
        }

        $decoded = json_decode($response, true);
        return $decoded !== null ? $decoded : false;
    }

    // -----------------------------
    // Response shaping
    // -----------------------------
    private function processRemoteResponse($remoteResponse, $originalInput) {
        if (!is_array($remoteResponse)) {
            return $this->getMockRatesResponse($originalInput);
        }

        // Attempt canonical extraction (Total Charge / EADR)
        $parsed = $this->extractRatePayload($remoteResponse);

        return [[
            'unit_name'   => $originalInput['Unit Name'],
            'rate'        => $parsed['rate'],
            'currency'    => $parsed['currency'],
            'date_range'  => [
                'arrival'   => $originalInput['Arrival'],
                'departure' => $originalInput['Departure'],
            ],
            'availability'=> $parsed['availability'],
            'original_response' => $parsed['raw']
        ]];
    }

    private function extractRatePayload($remote) : array {
        if (is_string($remote)) {
            return ['availability' => null, 'rate' => null, 'currency' => 'NAD', 'raw' => $remote];
        }

        $candidate = null;
        if (is_array($remote)) {
            // last associative element
            for ($i = count($remote) - 1; $i >= 0; $i--) {
                if (is_array($remote[$i]) && array_keys($remote[$i]) !== range(0, count($remote[$i]) - 1)) {
                    $candidate = $remote[$i];
                    break;
                }
            }
            if (!$candidate && array_keys($remote) !== range(0, count($remote) - 1)) {
                $candidate = $remote;
            }
        } elseif (is_object($remote)) {
            $candidate = (array)$remote;
        }

        if (!$candidate) {
            return ['availability' => null, 'rate' => null, 'currency' => 'NAD', 'raw' => $remote];
        }

        $err   = $candidate['Error Code'] ?? null;
        $avail = ($err === 0 || $err === '0');

        $total = $candidate['Total Charge'] ?? null;
        $eadr  = $candidate['Effective Average Daily Rate'] ?? null;

        $rateMinor = null;
        if (is_numeric($total))     $rateMinor = (int)$total;
        elseif (is_numeric($eadr))  $rateMinor = (int)$eadr;

        $rateMajor = is_int($rateMinor) ? ($rateMinor / 100.0) : null;

        return [
            'availability' => $avail,
            'rate'         => $rateMajor,
            'currency'     => 'NAD',
            'raw'          => $candidate,
        ];
    }

    private function getMockRatesResponse($input) {
        $baseRate = 1250.00;
        $nights   = $this->calculateNights($input['Arrival'], $input['Departure']);

        return [[
            'unit_name'  => $input['Unit Name'],
            'rate'       => $baseRate * $nights,
            'currency'   => 'NAD',
            'date_range' => [
                'arrival'   => $input['Arrival'],
                'departure' => $input['Departure'],
                'nights'    => $nights
            ],
            'availability' => true,
            'original_response' => ['mock' => true]
        ]];
    }

    // -----------------------------
    // Utilities
    // -----------------------------
    private function calculateNights($arrival, $departure) {
        $a = DateTime::createFromFormat('d/m/Y', $arrival);
        $d = DateTime::createFromFormat('d/m/Y', $departure);
        if (!$a || !$d) return 1;
        return max(1, $a->diff($d)->days);
    }

    private function availableUnits() : array {
        if (defined('UNIT_TYPE_MAPPING') && is_array(UNIT_TYPE_MAPPING)) {
            return UNIT_TYPE_MAPPING;
        }
        // Fallback mapping
        return [
            'Standard Unit' => -2147483637,
            'Deluxe Unit'   => -2147483456,
        ];
    }

    private function getServerInfo() {
        return [
            'php_version'   => phpversion(),
            'server_time'   => date('c'),
            'memory_usage'  => memory_get_usage(true),
            'request_method'=> $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'content_type'  => $_SERVER['CONTENT_TYPE'] ?? 'not set'
        ];
    }

    private function ensureLogDirectory() {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    private function logRequest($endpoint, $data) {
        $this->writeLog('INFO', "Request to $endpoint", ['data' => $data]);
    }

    private function logDebug($message, $context = []) {
        $this->writeLog('DEBUG', $message, $context);
    }

    private function logWarning($message, $context = []) {
        $this->writeLog('WARNING', $message, $context);
    }

    private function logError($message, $exception) {
        $this->writeLog('ERROR', $message, [
            'error' => $exception->getMessage(),
            'file'  => $exception->getFile(),
            'line'  => $exception->getLine()
        ]);
    }

    private function writeLog($level, $message, $context = []) {
        $entry = [
            'timestamp' => date('c'),
            'level'     => $level,
            'message'   => $message,
            'context'   => $context
        ];
        file_put_contents($this->logFile, json_encode($entry) . "\n", FILE_APPEND | LOCK_EX);
        if ($level === 'ERROR') {
            error_log("[$level] $message: " . json_encode($context));
        }
    }

    private function sendSuccess($data, $meta = []) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data'    => $data,
            'meta'    => array_merge([
                'timestamp'    => date('c'),
                'api_version'  => defined('API_VERSION') ? API_VERSION : '1.0',
            ], $meta)
        ], JSON_PRETTY_PRINT);
        exit();
    }

    private function sendError($message, $code = 500, $details = []) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error'   => [
                'message'   => $message,
                'code'      => $code,
                'timestamp' => date('c'),
                'details'   => $details
            ]
        ], JSON_PRETTY_PRINT);
        exit();
    }
}
