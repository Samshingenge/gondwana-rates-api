<?php
declare(strict_types=1);

/**
 * Normalize vendor response to a canonical shape:
 * [
 *   'rate'         => float|null,   // total stay amount (NAD)
 *   'currency'     => string,       // default 'NAD'
 *   'availability' => bool,
 *   'per_night'    => float|null,   // optional convenience (NAD)
 *   'raw'          => mixed         // original vendor body
 * ]
 *
 * Handles vendor payloads like:
 * {
 *   "Total Charge": 130000,                    // cents
 *   "Legs": [{
 *      "Effective Average Daily Rate": 65000,  // cents
 *      "Total Charge": 130000
 *   }]
 * }
 */
function extract_rate_payload($vendor): array
{
    $out = [
        'rate'         => null,    // total for the stay (NAD)
        'currency'     => 'NAD',
        'availability' => false,
        'per_night'    => null,
        'raw'          => $vendor,
    ];

    // Convert vendor "cents" → NAD where values look like large integers
    $toNad = static function ($num): float {
        $n = (float)$num;
        return ($n >= 1000) ? ($n / 100.0) : $n;
    };

    if (!is_array($vendor)) {
        // Non-JSON or unknown shape
        return $out;
    }

    $totalNad      = null;
    $perNightNad   = null;
    $explicitAvail = null;

    // --- Top-level totals ---
    if (isset($vendor['Total Charge']) && is_numeric($vendor['Total Charge'])) {
        $totalNad = $toNad($vendor['Total Charge']);
    }
    if (array_key_exists('available', $vendor)) {
        $explicitAvail = (bool)$vendor['available'];
    } elseif (array_key_exists('availability', $vendor)) {
        $explicitAvail = (bool)$vendor['availability'];
    }
    if (isset($vendor['Effective Average Daily Rate']) && is_numeric($vendor['Effective Average Daily Rate'])) {
        $perNightNad = $toNad($vendor['Effective Average Daily Rate']);
    }

    // --- Legs array: sum totals; pick a reasonable per-night (first leg ADR) ---
    if (isset($vendor['Legs']) && is_array($vendor['Legs']) && !empty($vendor['Legs'])) {
        $sum = 0.0;
        foreach ($vendor['Legs'] as $leg) {
            if (isset($leg['Total Charge']) && is_numeric($leg['Total Charge'])) {
                $sum += $toNad($leg['Total Charge']);
            }
            if ($perNightNad === null
                && isset($leg['Effective Average Daily Rate'])
                && is_numeric($leg['Effective Average Daily Rate'])) {
                $perNightNad = $toNad($leg['Effective Average Daily Rate']);
            }
            // respect explicit availability if present at leg level
            if ($explicitAvail === null) {
                if (array_key_exists('available', $leg)) {
                    $explicitAvail = (bool)$leg['available'];
                } elseif (array_key_exists('availability', $leg)) {
                    $explicitAvail = (bool)$leg['availability'];
                }
            }
        }
        if ($sum > 0) {
            $totalNad = $sum;
        }
    }

    // --- Decide availability ---
    if ($explicitAvail !== null) {
        $out['availability'] = $explicitAvail;
    } else {
        // Friendly default: any positive priced value implies availability
        $out['availability'] = ($totalNad !== null && $totalNad > 0)
            || ($perNightNad !== null && $perNightNad > 0);
    }

    // --- Finalize rate fields ---
    // Prefer total stay as "rate", fallback to per-night if no total
    if ($totalNad !== null && $totalNad > 0) {
        $out['rate'] = $totalNad;
    } elseif ($perNightNad !== null && $perNightNad > 0) {
        $out['rate'] = $perNightNad;
    }

    if ($perNightNad !== null && $perNightNad > 0) {
        $out['per_night'] = $perNightNad;
    }

    return $out;
}
