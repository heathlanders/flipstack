<?php

namespace App\Services\Amazon;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class AmazonRateLimiter
{
    private const KEY_PREFIX = 'amazon_rate_limit:';

    private const DEFAULT_RESTORE_RATE = 1; // 1 request/second default

    // SP-API rate limits per endpoint type
    private const RATE_LIMITS = [
        'listingsItems' => ['burst' => 5, 'restore' => 5],
        'catalogItems' => ['burst' => 2, 'restore' => 2],
        'inventory' => ['burst' => 2, 'restore' => 2],
        'pricing' => ['burst' => 10, 'restore' => 10],
        'orders' => ['burst' => 6, 'restore' => 0.0167], // 1 per minute
        'feeds' => ['burst' => 15, 'restore' => 15],
        'reports' => ['burst' => 15, 'restore' => 15],
        'default' => ['burst' => 1, 'restore' => 1],
    ];

    public function resolveEndpointType(string $endpoint): string
    {
        if (str_contains($endpoint, '/listings/')) {
            return 'listingsItems';
        }
        if (str_contains($endpoint, '/catalog/')) {
            return 'catalogItems';
        }
        if (str_contains($endpoint, '/inventory/')) {
            return 'inventory';
        }
        if (str_contains($endpoint, '/pricing/')) {
            return 'pricing';
        }
        if (str_contains($endpoint, '/orders/')) {
            return 'orders';
        }
        if (str_contains($endpoint, '/feeds/')) {
            return 'feeds';
        }
        if (str_contains($endpoint, '/reports/')) {
            return 'reports';
        }

        return 'default';
    }

    public function recordResponse(string $credentialId, string $endpoint, array $headers): void
    {
        $type = $this->resolveEndpointType($endpoint);
        $key = self::KEY_PREFIX.$credentialId.':'.$type;

        $rateLimit = $headers['x-amzn-RateLimit-Limit'][0] ?? null;

        $redis = Redis::connection();
        $redis->set($key, json_encode([
            'rate_limit' => $rateLimit ? (float) $rateLimit : self::RATE_LIMITS[$type]['restore'],
            'last_request_at' => microtime(true),
        ]));
        $redis->expire($key, 30);
    }

    public function shouldThrottle(string $credentialId, string $endpoint): bool
    {
        $type = $this->resolveEndpointType($endpoint);
        $key = self::KEY_PREFIX.$credentialId.':'.$type;

        $redis = Redis::connection();
        $requestKey = $key.':requests';
        $count = (int) $redis->get($requestKey);
        $limits = self::RATE_LIMITS[$type] ?? self::RATE_LIMITS['default'];

        return $count >= $limits['burst'];
    }

    public function waitIfNeeded(string $credentialId, string $endpoint): void
    {
        if ($this->shouldThrottle($credentialId, $endpoint)) {
            $type = $this->resolveEndpointType($endpoint);
            $limits = self::RATE_LIMITS[$type] ?? self::RATE_LIMITS['default'];
            $sleepMs = (int) ceil(1000 / max($limits['restore'], 0.1));

            Log::debug('Amazon rate limiter: throttling', [
                'credential_id' => $credentialId,
                'endpoint_type' => $type,
                'sleep_ms' => $sleepMs,
            ]);
            usleep($sleepMs * 1000);
        }

        // Increment request counter
        $type = $this->resolveEndpointType($endpoint);
        $requestKey = self::KEY_PREFIX.$credentialId.':'.$type.':requests';
        $redis = Redis::connection();
        $redis->incr($requestKey);
        $redis->expire($requestKey, 2);
    }

    public function getStatus(string $credentialId, string $endpoint): array
    {
        $type = $this->resolveEndpointType($endpoint);
        $key = self::KEY_PREFIX.$credentialId.':'.$type;
        $limits = self::RATE_LIMITS[$type] ?? self::RATE_LIMITS['default'];

        $redis = Redis::connection();
        $requestKey = $key.':requests';
        $count = (int) $redis->get($requestKey);

        return [
            'endpoint_type' => $type,
            'burst_limit' => $limits['burst'],
            'restore_rate' => $limits['restore'],
            'recent_requests' => $count,
        ];
    }
}
