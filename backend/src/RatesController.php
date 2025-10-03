<?php
declare(strict_types=1);

class RatesController
{
    public function testEndpoint(): void
    {
        $input = json_decode(file_get_contents('php://input') ?: 'null', true);
        json_response([
            'success'       => true,
            'message'       => 'Test endpoint working',
            'received_data' => $input,
            'timestamp'     => gmdate('c'),
        ], 200);
    }

    public function getRates(): void
    {
        $raw   = file_get_contents('php://input') ?: '';
        $input = json_decode($raw, true);

        if (!is_array($input)) {
            json_response(['success'=>false, 'error'=>['message'=>'Invalid JSON payload','code'=>400]], 400);
            return;
        }

        $errors = $this->validateRateRequest($input);
        if (!empty($errors)) {
            json_response(['success'=>false, 'error'=>['message'=>'Validation failed','details'=>$errors,'code'=>400]], 400);
            return;
        }

        // Optional: vendor mock to isolate FE/BE
        if (defined('REMOTE_API_MOCK') && REMOTE_API_MOCK) {
            $mock = [[
                'unit_name'        => $input['Unit Name'],
                'rate'             => 1234.56,
                'currency'         => 'NAD',
                'date_range'       => ['arrival'=>$input['Arrival'], 'departure'=>$input['Departure']],
                'availability'     => true,
                'original_response'=> ['mock'=>true],
            ]];
            json_response(['success'=>true, 'data'=>$mock], 200);
            return;
        }

        error_log('[RATES] input='.json_encode($input, JSON_UNESCAPED_SLASHES));
        $payload = $this->transformPayload($input);
        error_log('[RATES] payload='.json_encode($payload, JSON_UNESCAPED_SLASHES));

        $remote = $this->callRemoteAPI($payload);
        if ($remote === false) {
            json_response(['success'=>false, 'error'=>['message'=>'Failed to get rates from remote API','code'=>502]], 502);
            return;
        }

        $processed = $this->processRemoteResponse($remote, $input);
        json_response(['success'=>true, 'data'=>$processed], 200);
    }

    private function validateRateRequest(array $input): array
    {
        $errors = [];
        $required = ['Unit Name','Arrival','Departure','Occupants','Ages'];
        foreach ($required as $f) {
            if (!isset($input[$f]) || $input[$f] === '' || $input[$f] === null) $errors[] = "$f is required";
        }

        if (isset($input['Arrival']) && !validateDateFormat((string)$input['Arrival'], 'd/m/Y')) {
            $errors[] = 'Arrival date must be in dd/mm/yyyy format';
        }
        if (isset($input['Departure']) && !validateDateFormat((string)$input['Departure'], 'd/m/Y')) {
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
                    if (!is_numeric($age) || (int)$age < 0) { $errors[] = 'All ages must be non-negative integers'; break; }
                }
                if (isset($input['Occupants']) && count($input['Ages']) !== (int)$input['Occupants']) {
                    $errors[] = 'Number of ages must match number of occupants';
                }
            }
        }

        return $errors;
    }

    private function transformPayload(array $input): array
    {
        $arrival   = DateTime::createFromFormat('d/m/Y', (string)$input['Arrival']);
        $departure = DateTime::createFromFormat('d/m/Y', (string)$input['Departure']);

        // Build vendor Guests from ages
        $guests = [];
        foreach ($input['Ages'] as $age) {
            $ageInt = (int)$age;
            $guests[] = ['Age Group' => ($ageInt >= 18) ? 'Adult' : 'Child'];
        }

        $map = defined('UNIT_TYPE_MAPPING') ? UNIT_TYPE_MAPPING : [];
        $unitTypeId = $map[$input['Unit Name']] ?? array_values($map)[0] ?? -2147483637;

        return [
            'Unit Type ID' => $unitTypeId,
            'Arrival'      => $arrival   ? $arrival->format('Y-m-d')   : null,
            'Departure'    => $departure ? $departure->format('Y-m-d') : null,
            'Guests'       => $guests,
        ];
    }

    private function callRemoteAPI(array $payload)
    {
        $url = defined('REMOTE_API_URL') ? REMOTE_API_URL : '';
        if ($url === '') { error_log('REMOTE_API_URL not defined'); return false; }

        // Prefer cURL (better errors)
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
            curl_setopt_array($ch, [
                CURLOPT_POST            => true,
                CURLOPT_HTTPHEADER      => [
                    'Content-Type: application/json',
                    'User-Agent: PHP-API-Client/1.0',
                    'Accept: application/json',
                ],
                CURLOPT_POSTFIELDS      => $body,
                CURLOPT_RETURNTRANSFER  => true,
                CURLOPT_CONNECTTIMEOUT  => 10,
                CURLOPT_TIMEOUT         => 30,
            ]);
            $resp = curl_exec($ch);
            if ($resp === false) { error_log('cURL error: '.curl_error($ch)); curl_close($ch); return false; }
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code < 200 || $code >= 300) {
                error_log("Remote API HTTP $code; body: ".substr($resp,0,500));
                // Optionally: return false;
            }
            $decoded = json_decode($resp, true);
            return is_array($decoded) ? $decoded : $resp;
        }

        // Fallback
        $opts = [
            'http' => [
                'header'  => [
                    'Content-Type: application/json',
                    'User-Agent: PHP-API-Client/1.0',
                    'Accept: application/json',
                ],
                'method'  => 'POST',
                'content' => json_encode($payload, JSON_UNESCAPED_SLASHES),
                'timeout' => 30,
            ],
        ];
        $res = @file_get_contents($url, false, stream_context_create($opts));
        if ($res === false) { $err = error_get_last(); error_log('Remote API call failed (fgc): '.($err['message'] ?? 'unknown')); return false; }
        $decoded = json_decode($res, true);
        return is_array($decoded) ? $decoded : $res;
    }

    private function processRemoteResponse($remoteResponse, array $input): array
{
    // Normalize via extractor; tolerate non-array vendor body
    if (is_array($remoteResponse) && function_exists('extract_rate_payload')) {
        $parsed = extract_rate_payload($remoteResponse);
    } else {
        $parsed = [
            'rate'         => null,
            'currency'     => 'NAD',
            'availability' => false,
            'raw'          => $remoteResponse,
        ];
    }

    $unitName   = $input['Unit Name'];
    $rate       = $parsed['rate'] ?? null;
    $currency   = $parsed['currency'] ?? 'NAD';
    $available  = (bool)($parsed['availability'] ?? false);

    // Business rule: Standard Unit is normally available.
    // If vendor didn't clearly mark it unavailable, prefer AVAILABLE.
    if (strcasecmp($unitName, 'Standard Unit') === 0) {
        // If we have a positive rate OR vendor availability is unknown/false,
        // promote to available (you can tighten this if needed).
        if ((is_float($rate) && $rate > 0) || $available === false) {
            $available = true;
        }
    } else {
        // For non-standard units, also fall back to available when a positive rate exists.
        if (!$available && is_float($rate) && $rate > 0) {
            $available = true;
        }
    }

    return [[
        'unit_name'        => $unitName,
        'rate'             => $rate,
        'currency'         => $currency,
        'date_range'       => [
            'arrival'   => $input['Arrival'],
            'departure' => $input['Departure'],
        ],
        'availability'     => $available,
        'original_response'=> $parsed['raw'] ?? $remoteResponse,
    ]];
}

}
