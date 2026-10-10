<?php

namespace Tests\Unit\Services;

use App\Services\Shopify\ShopifyRateLimiter;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class ShopifyRateLimiterTest extends TestCase
{
    protected ShopifyRateLimiter $rateLimiter;

    protected $redisMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rateLimiter = new ShopifyRateLimiter;

        // Mock Redis connection
        $this->redisMock = Mockery::mock();
        Redis::shouldReceive('connection')->andReturn($this->redisMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_should_throttle_returns_false_with_no_data(): void
    {
        $this->redisMock->shouldReceive('get')
            ->with('shopify_rate_limit:test.myshopify.com')
            ->andReturn(null);

        $this->assertFalse($this->rateLimiter->shouldThrottle('test.myshopify.com'));
    }

    public function test_should_throttle_returns_true_at_80_percent(): void
    {
        $this->redisMock->shouldReceive('set')->once();
        $this->redisMock->shouldReceive('expire')->once();

        $this->rateLimiter->recordResponse('test.myshopify.com', [
            'X-Shopify-Shop-Api-Call-Limit' => ['32/40'],
        ]);

        $this->redisMock->shouldReceive('get')
            ->with('shopify_rate_limit:test.myshopify.com')
            ->andReturn(json_encode(['used' => 32, 'max' => 40, 'updated_at' => microtime(true)]));

        $this->assertTrue($this->rateLimiter->shouldThrottle('test.myshopify.com'));
    }

    public function test_should_throttle_returns_false_below_threshold(): void
    {
        $this->redisMock->shouldReceive('set')->once();
        $this->redisMock->shouldReceive('expire')->once();

        $this->rateLimiter->recordResponse('test.myshopify.com', [
            'X-Shopify-Shop-Api-Call-Limit' => ['10/40'],
        ]);

        $this->redisMock->shouldReceive('get')
            ->with('shopify_rate_limit:test.myshopify.com')
            ->andReturn(json_encode(['used' => 10, 'max' => 40, 'updated_at' => microtime(true)]));

        $this->assertFalse($this->rateLimiter->shouldThrottle('test.myshopify.com'));
    }

    public function test_record_response_parses_call_limit_header(): void
    {
        $this->redisMock->shouldReceive('set')->once()->withArgs(function ($key, $value) {
            $data = json_decode($value, true);

            return $key === 'shopify_rate_limit:test.myshopify.com'
                && $data['used'] === 25
                && $data['max'] === 40;
        });
        $this->redisMock->shouldReceive('expire')->once()->with('shopify_rate_limit:test.myshopify.com', 10);

        $this->rateLimiter->recordResponse('test.myshopify.com', [
            'X-Shopify-Shop-Api-Call-Limit' => ['25/40'],
        ]);

        // Verify getStatus uses the stored data
        $this->redisMock->shouldReceive('get')
            ->with('shopify_rate_limit:test.myshopify.com')
            ->andReturn(json_encode(['used' => 25, 'max' => 40, 'updated_at' => microtime(true)]));

        $status = $this->rateLimiter->getStatus('test.myshopify.com');

        $this->assertEquals(25, $status['used']);
        $this->assertEquals(40, $status['max']);
        $this->assertEquals(15, $status['available']);
        $this->assertEquals(62.5, $status['percentage_used']);
    }

    public function test_get_status_returns_defaults_when_no_redis_data(): void
    {
        $this->redisMock->shouldReceive('get')
            ->with('shopify_rate_limit:unknown.myshopify.com')
            ->andReturn(null);

        $status = $this->rateLimiter->getStatus('unknown.myshopify.com');

        $this->assertEquals(0, $status['used']);
        $this->assertEquals(40, $status['max']);
        $this->assertEquals(40, $status['available']);
        $this->assertEquals(0, $status['percentage_used']);
    }
}
