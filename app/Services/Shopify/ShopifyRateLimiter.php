<?php

namespace App\Services\Shopify;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ShopifyRateLimiter
{
    private const BUCKET_KEY_PREFIX = 'shopify_rate_limit:';

    private const BUCKET_MAX = 40; // Shopify default bucket size

    private const THRESHOLD_PERCENT = 80;

    public function recordResponse(string $shopDomain, array $headers): void
    {
        $callLimit = $headers['X-Shopify-Shop-Api-Call-Limit'][0] ?? null;

        if (! $callLimit) {
            return;
        }

        // Format: "32/40"
        [$used, $max] = explode('/', $callLimit);

        $key = self::BUCKET_KEY_PREFIX.$shopDomain;
        $redis = Redis::connection();
        $redis->set($key, json_encode([
            'used' => (int) $used,
            'max' => (int) $max,
            'updated_at' => microtime(true),
        ]));
        $redis->expire($key, 10); // Short TTL, bucket refills at 2/s
    }

    public function shouldThrottle(string $shopDomain): bool
    {
        $key = self::BUCKET_KEY_PREFIX.$shopDomain;
        $redis = Redis::connection();
        $data = $redis->get($key);

        if (! $data) {
            return false;
        }

        $state = json_decode($data, true);
        $percentUsed = ($state['used'] / $state['max']) * 100;

        return $percentUsed >= self::THRESHOLD_PERCENT;
    }

    public function waitIfNeeded(string $shopDomain): void
    {
        if ($this->shouldThrottle($shopDomain)) {
            $sleepMs = 500; // Wait 500ms for bucket to refill (2 requests/second leak rate)
            Log::debug('Shopify rate limiter: throttling', [
                'shop' => $shopDomain,
                'sleep_ms' => $sleepMs,
            ]);
            usleep($sleepMs * 1000);
        }
    }

    public function getStatus(string $shopDomain): array
    {
        $key = self::BUCKET_KEY_PREFIX.$shopDomain;
        $redis = Redis::connection();
        $data = $redis->get($key);

        if (! $data) {
            return [
                'used' => 0,
                'max' => self::BUCKET_MAX,
                'available' => self::BUCKET_MAX,
                'percentage_used' => 0,
            ];
        }

        $state = json_decode($data, true);

        return [
            'used' => $state['used'],
            'max' => $state['max'],
            'available' => $state['max'] - $state['used'],
            'percentage_used' => round(($state['used'] / $state['max']) * 100, 2),
        ];
    }
}
