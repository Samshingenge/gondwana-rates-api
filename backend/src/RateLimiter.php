<?php
/**
 * Rate Limiter Class
 * Provides token bucket rate limiting for API endpoints
 */

class RateLimiter {
    private $cacheFile;
    private $requests;
    private $window;

    public function __construct($requests = 100, $window = 300) {
        $this->cacheFile = __DIR__ . '/../cache/rate_limit.json';
        $this->requests = $requests;
        $this->window = $window;
        $this->ensureCacheDirectory();
    }

    public function isAllowed($identifier) {
        if (!$this->isEnabled()) {
            return true;
        }

        $current = time();
        $data = $this->loadRateLimitData();

        // Clean up old entries
        $data = $this->cleanupOldEntries($data, $current);

        // Get or create bucket for this identifier
        if (!isset($data[$identifier])) {
            $data[$identifier] = [
                'tokens' => $this->requests,
                'last_refill' => $current
            ];
        }

        $bucket = &$data[$identifier];

        // Refill tokens based on elapsed time
        $this->refillTokens($bucket, $current);

        // Check if request is allowed
        if ($bucket['tokens'] >= 1) {
            $bucket['tokens']--;
            $this->saveRateLimitData($data);
            return true;
        }

        $this->saveRateLimitData($data);
        return false;
    }

    public function getRemainingRequests($identifier) {
        if (!$this->isEnabled()) {
            return $this->requests;
        }

        $current = time();
        $data = $this->loadRateLimitData();
        $data = $this->cleanupOldEntries($data, $current);

        if (!isset($data[$identifier])) {
            return $this->requests;
        }

        $bucket = &$data[$identifier];
        $this->refillTokens($bucket, $current);

        return max(0, (int)$bucket['tokens']);
    }

    public function getResetTime($identifier) {
        if (!$this->isEnabled()) {
            return time();
        }

        $current = time();
        $data = $this->loadRateLimitData();

        if (!isset($data[$identifier])) {
            return $current;
        }

        $bucket = &$data[$identifier];
        $this->refillTokens($bucket, $current);

        // Calculate when the next token will be available
        if ($bucket['tokens'] < 1) {
            $elapsed = $current - $bucket['last_refill'];
            $timeSinceLastRefill = $elapsed % $this->window;
            return $current + ($this->window - $timeSinceLastRefill);
        }

        return $current;
    }

    private function isEnabled() {
        return defined('RATE_LIMIT_ENABLED') ? RATE_LIMIT_ENABLED : true;
    }

    private function refillTokens(&$bucket, $current) {
        $elapsed = $current - $bucket['last_refill'];

        if ($elapsed > 0) {
            // Calculate tokens to add (1 token per window/requests interval)
            $tokensToAdd = min($this->requests, (int)($elapsed / $this->window) * $this->requests);
            $bucket['tokens'] = min($this->requests, $bucket['tokens'] + $tokensToAdd);
            $bucket['last_refill'] = $current;
        }
    }

    private function cleanupOldEntries($data, $current) {
        $cutoff = $current - ($this->window * 2); // Keep data for 2x window period
        $cleaned = [];

        foreach ($data as $identifier => $bucket) {
            if ($bucket['last_refill'] > $cutoff) {
                $cleaned[$identifier] = $bucket;
            }
        }

        return $cleaned;
    }

    private function loadRateLimitData() {
        if (!file_exists($this->cacheFile)) {
            return [];
        }

        $content = file_get_contents($this->cacheFile);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        return $data ?: [];
    }

    private function saveRateLimitData($data) {
        $content = json_encode($data);
        if ($content === false) {
            return;
        }

        file_put_contents($this->cacheFile, $content, LOCK_EX);
    }

    private function ensureCacheDirectory() {
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    public function reset($identifier = null) {
        if ($identifier === null) {
            // Reset all
            if (file_exists($this->cacheFile)) {
                unlink($this->cacheFile);
            }
        } else {
            // Reset specific identifier
            $data = $this->loadRateLimitData();
            unset($data[$identifier]);
            $this->saveRateLimitData($data);
        }
    }
}