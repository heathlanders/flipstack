<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Centralized Rate Limiter for ALL eBay API Services
 *
 * Coordinates rate limiting across:
 * - EbayBrowseService
 * - EbayFindingService
 * - EbaySoldListingsService
 * - EbayOAuthService
 *
 * eBay Rate Limits:
 * - Finding API: 5,000 calls/day
 * - Browse API: 5,000 calls/day (OAuth)
 * - Per second: ~5 calls/second recommended
 */
class EbayRateLimiter
{
    private const RATE_LIMIT_KEY = 'ebay_api_rate_limit';

    private const DAILY_LIMIT_KEY = 'ebay_api_daily_limit';

    // Rate limits
    private const MAX_REQUESTS_PER_SECOND = 5;

    private const MAX_REQUESTS_PER_DAY = 5000;

    // Backoff configuration
    private const INITIAL_BACKOFF_MS = 500;

    private const MAX_BACKOFF_MS = 30000; // 30 seconds

    /**
     * Check if we can make an API call, enforcing rate limits
     */
    public function allowRequest(string $service): bool
    {
        $redis = Redis::connection();

        // Check daily limit first
        $dailyKey = self::DAILY_LIMIT_KEY.':'.date('Y-m-d');
        $dailyCount = (int) $redis->get($dailyKey);

        if ($dailyCount >= self::MAX_REQUESTS_PER_DAY) {
            Log::warning('eBay daily rate limit reached', [
                'service' => $service,
                'count' => $dailyCount,
                'limit' => self::MAX_REQUESTS_PER_DAY,
            ]);

            return false;
        }

        // Check per-second limit using token bucket algorithm
        $bucketKey = self::RATE_LIMIT_KEY;
        $now = microtime(true);

        // Initialize bucket if doesn't exist
        if (! $redis->exists($bucketKey)) {
            $redis->set($bucketKey, self::MAX_REQUESTS_PER_SECOND);
            $redis->expire($bucketKey, 1);
        }

        $tokens = (int) $redis->get($bucketKey);

        if ($tokens > 0) {
            // Consume a token
            $redis->decr($bucketKey);

            // Increment daily counter
            $redis->incr($dailyKey);
            $redis->expire($dailyKey, 86400); // 24 hours

            return true;
        }

        // Rate limited
        Log::debug('eBay per-second rate limit hit', [
            'service' => $service,
            'remaining_tokens' => $tokens,
        ]);

        return false;
    }

    /**
     * Wait for rate limit to clear
     */
    public function waitForSlot(string $service, int $attempt = 1): void
    {
        $backoffMs = min(
            self::INITIAL_BACKOFF_MS * pow(2, $attempt - 1),
            self::MAX_BACKOFF_MS
        );

        Log::info('Rate limit hit, waiting', [
            'service' => $service,
            'attempt' => $attempt,
            'wait_ms' => $backoffMs,
        ]);

        usleep($backoffMs * 1000); // Convert to microseconds
    }

    /**
     * Execute API call with rate limiting and retries
     */
    public function executeWithRateLimit(
        string $service,
        callable $apiCall,
        int $maxAttempts = 3
    ) {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            // Check if we can make the request
            if ($this->allowRequest($service)) {
                try {
                    return $apiCall();
                } catch (\Exception $e) {
                    // If it's a rate limit error, wait and retry
                    if ($this->isRateLimitError($e)) {
                        if ($attempt < $maxAttempts) {
                            $this->waitForSlot($service, $attempt);

                            continue;
                        }
                    }

                    // Rethrow other errors
                    throw $e;
                }
            }

            // Rate limited, wait and retry
            if ($attempt < $maxAttempts) {
                $this->waitForSlot($service, $attempt);
            }
        }

        throw new \Exception("eBay rate limit exceeded after {$maxAttempts} attempts");
    }

    /**
     * Check if exception is a rate limit error
     */
    private function isRateLimitError(\Exception $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'rate limit')
            || str_contains($message, '429')
            || str_contains($message, 'too many requests');
    }

    /**
     * Get current rate limit status
     */
    public function getStatus(): array
    {
        $redis = Redis::connection();

        $bucketKey = self::RATE_LIMIT_KEY;
        $dailyKey = self::DAILY_LIMIT_KEY.':'.date('Y-m-d');

        $availableTokens = (int) ($redis->get($bucketKey) ?? self::MAX_REQUESTS_PER_SECOND);
        $dailyUsed = (int) ($redis->get($dailyKey) ?? 0);

        return [
            'per_second' => [
                'limit' => self::MAX_REQUESTS_PER_SECOND,
                'available' => $availableTokens,
                'used' => self::MAX_REQUESTS_PER_SECOND - $availableTokens,
            ],
            'daily' => [
                'limit' => self::MAX_REQUESTS_PER_DAY,
                'used' => $dailyUsed,
                'remaining' => self::MAX_REQUESTS_PER_DAY - $dailyUsed,
                'percentage_used' => round(($dailyUsed / self::MAX_REQUESTS_PER_DAY) * 100, 2),
            ],
            'date' => date('Y-m-d'),
        ];
    }

    /**
     * Reset rate limits (for testing)
     */
    public function reset(): void
    {
        $redis = Redis::connection();

        $redis->del(self::RATE_LIMIT_KEY);
        $redis->del(self::DAILY_LIMIT_KEY.':'.date('Y-m-d'));

        Log::info('eBay rate limits reset');
    }
}
