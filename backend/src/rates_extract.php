<?php
/**
 * Isolated rates parsing helper
 * Single source of truth for extracting availability + rate from vendor payload.
 */

if (!function_exists('extract_rate_payload')) {
    function extract_rate_payload($remote): array
    {
        // Handle primitives
        if (is_string($remote) || is_numeric($remote) || $remote === null) {
            return [
                'availability' => false,
                'rate'         => null,
                'currency'     => 'NAD',
                'note'         => 'Primitive response, no rate',
                'raw'          => $remote,
            ];
        }

        // Find associative candidate
        $candidate = null;

        if (is_array($remote)) {
            $isAssoc = array_keys($remote) !== range(0, count($remote) - 1);
            if ($isAssoc) {
                $candidate = $remote;
            } else {
                // find last associative element (vendor sends tokens first, object last)
                for ($i = count($remote) - 1; $i >= 0; $i--) {
                    if (is_array($remote[$i]) && array_keys($remote[$i]) !== range(0, count($remote[$i]) - 1)) {
                        $candidate = $remote[$i];
                        break;
                    }
                }
            }
        } elseif (is_object($remote)) {
            $candidate = (array) $remote;
        }

        if (!$candidate) {
            return [
                'availability' => false,
                'rate'         => null,
                'currency'     => 'NAD',
                'note'         => 'No associative rate object found',
                'raw'          => $remote,
            ];
        }

        // Availability rules (robust):
        // 1) If candidate has Error Code, use it
        // 2) Else if candidate Total Charge > 0, available
        // 3) Else if any Legs have Error Code == 0 or Total Charge > 0, available
        $availability = false;

        if (array_key_exists('Error Code', $candidate)) {
            $availability = ($candidate['Error Code'] === 0 || $candidate['Error Code'] === '0');
        }

        if (!$availability && isset($candidate['Total Charge']) && is_numeric($candidate['Total Charge'])) {
            $availability = ((int) $candidate['Total Charge'] > 0);
        }

        if (!$availability && !empty($candidate['Legs']) && is_array($candidate['Legs'])) {
            foreach ($candidate['Legs'] as $leg) {
                if (!is_array($leg)) continue;
                $legOk   = (isset($leg['Error Code']) && ($leg['Error Code'] === 0 || $leg['Error Code'] === '0'));
                $legCash = (isset($leg['Total Charge']) && is_numeric($leg['Total Charge']) && (int) $leg['Total Charge'] > 0);
                if ($legOk || $legCash) { $availability = true; break; }
            }
        }

        // Rate selection (minor units: cents)
        $total = $candidate['Total Charge'] ?? null;
        $eadr  = $candidate['Effective Average Daily Rate'] ?? null;

        $rateMinor = null;
        if (is_numeric($total))      $rateMinor = (int) $total;
        elseif (is_numeric($eadr))   $rateMinor = (int) $eadr;

        if ($rateMinor === null) {
            return [
                'availability' => false,
                'rate'         => null,
                'currency'     => 'NAD',
                'note'         => 'No valid rate returned by remote',
                'raw'          => $candidate,
            ];
        }

        return [
            'availability' => $availability,
            'rate'         => $rateMinor / 100.0, // convert to major units
            'currency'     => 'NAD',
            'raw'          => $candidate,
        ];
    }
}
