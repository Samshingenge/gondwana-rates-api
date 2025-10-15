# PHP `DateTime::createFromFormat` Deep Dive

## Overview

`DateTime::createFromFormat(string $format, string $datetime, ?DateTimeZone $timezone = null): DateTime|false`  
Creates a mutable `DateTime` based on an exact format string—ideal for deterministic parsing (e.g., user-entered dates).

Key traits:
- Only parses tokens explicitly defined in `$format`.
- Exposes warnings and errors for malformed input via `DateTime::getLastErrors()`.
- Optional `$timezone` lets you interpret input in a specific region before converting elsewhere.

Contrast with `new DateTime($value)`, which heuristically interprets strings and may silently coerce unexpected input.

For immutable workflows, use `DateTimeImmutable::createFromFormat()`—same API, but guarantees the result cannot be mutated.

---

## Essential Format Tokens

Common tokens (same as `date()`):

| Token | Meaning                    | Example |
|-------|----------------------------|---------|
| `d`   | Day with leading zeros     | `01`–`31` |
| `j`   | Day without leading zeros  | `1`–`31`  |
| `m`   | Month with leading zeros   | `01`–`12` |
| `n`   | Month without leading zeros| `1`–`12`  |
| `Y`   | Four-digit year            | `2025`    |
| `H`   | Hour (00–23)               | `00`–`23` |
| `i`   | Minutes                    | `00`–`59` |
| `s`   | Seconds                    | `00`–`59` |
| `T`   | TZ abbreviation            | `UTC`     |
| `P`   | Offset `+00:00` style      |          |
| `U`   | Unix timestamp             |          |

Escape literal characters with `\` or quotes (e.g., `d/m/Y`).

---

## Handling Partial or Invalid Input

- Missing tokens fall back to defaults (1970-01-01, midnight).
- Unexpected characters trigger warnings or errors.
- Always inspect `DateTime::getLastErrors()` immediately after parsing.

Example:

```php
$dt = DateTime::createFromFormat('d/m/Y', '05/10/2025');
$errors = DateTime::getLastErrors(); // both counts 0 → safe
```

```php
$dt = DateTime::createFromFormat('d/m/Y', '05/10');
$errors = DateTime::getLastErrors();
// ['error_count' => 1, 'errors' => ['Data missing']]
```

---

## Optional Timezone Parameter

```php
$windhoek = new DateTimeZone('Africa/Windhoek');
$arrival  = DateTime::createFromFormat('d/m/Y H:i', '05/10/2025 14:00', $windhoek);
```

- Without `$timezone`, PHP uses the default timezone (from `date_default_timezone_set()` or `php.ini`).
- Specify the user’s region to interpret the local time before converting to storage/UTC.
- After parsing, call `setTimezone()` to convert to other zones.

---

## Detecting Warnings & Errors

```php
$dt = DateTime::createFromFormat('d/m/Y', $input);
$errors = DateTime::getLastErrors();

if ($dt === false || $errors['warning_count'] > 0 || $errors['error_count'] > 0) {
    // Invalid input -> report errors, log, or throw.
}
```

`warning_count`/`error_count` report superfluous characters, missing parts, or fatal parse failures. Always check immediately—buffer resets after another parse.

---

## Comparison Matrix

| Aspect                            | `new DateTime`               | `DateTime::createFromFormat`         | `DateTimeImmutable::createFromFormat` |
|-----------------------------------|------------------------------|--------------------------------------|---------------------------------------|
| Parsing strategy                  | Heuristic                    | Exact (format-driven)                | Exact + immutable result              |
| Error feedback                    | Minimal                      | Full via `getLastErrors()`           | Same (immutable)                      |
| Partial strings                   | Often coerced silently       | Warnings/errors surfaced             | Same                                  |
| Best for                          | Known/standardized strings   | User-supplied or rigid formats       | String parsing without mutations      |

---

## Annotated Best-Practice Example (Date Range)

```php
<?php

/**
 * Parse a dd/mm/yyyy string into an immutable UTC date.
 *
 * @throws InvalidArgumentException on invalid input.
 */
function parseUserDate(string $input, DateTimeZone $inputZone): DateTimeImmutable
{
    $input = trim($input);
    if ($input === '') {
        throw new InvalidArgumentException('Date is required.');
    }

    $format = 'd/m/Y';
    $dt = DateTimeImmutable::createFromFormat($format, $input, $inputZone);
    $errors = DateTimeImmutable::getLastErrors();

    if ($dt === false || $errors['warning_count'] > 0 || $errors['error_count'] > 0) {
        throw new InvalidArgumentException('Invalid date format.');
    }

    return $dt->setTime(0, 0)->setTimezone(new DateTimeZone('UTC'));
}

try {
    $arrivalRaw   = $_POST['arrival']   ?? '';
    $departureRaw = $_POST['departure'] ?? '';

    $localZone = new DateTimeZone('Africa/Windhoek');

    $arrival   = parseUserDate($arrivalRaw, $localZone);
    $departure = parseUserDate($departureRaw, $localZone);

    if ($departure < $arrival) {
        throw new InvalidArgumentException('Departure must be on or after arrival.');
    }

    $nights = max(1, (int) $arrival->diff($departure)->format('%a'));

    // Continue with normalized UTC dates ($arrival, $departure) and $nights.
} catch (InvalidArgumentException $ex) {
    // Surface a localized validation error.
}
```

**Localization considerations**

- Adjust `$format` per locale or use `IntlDateFormatter`.
- Present results with `IntlDateFormatter` after converting back to the user’s zone (`$arrival->setTimezone($userZone)`).
- Store canonical UTC timestamps to avoid daylight savings confusion.

---

## Summary

- Use `createFromFormat` when you know the exact layout of user input.
- Always inspect `DateTime::getLastErrors()` before trusting the result.
- Pass the correct timezone to interpret local entries accurately.
- Prefer `DateTimeImmutable` to keep parsed values side-effect free.
- Validate ranges by comparing UTC-normalized instants; convert back to locale only for display.
