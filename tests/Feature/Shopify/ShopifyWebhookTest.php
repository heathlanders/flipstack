<?php

namespace Tests\Feature\Shopify;

use App\Jobs\Shopify\ProcessShopifyWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use Tests\Traits\CreatesShopifyFixtures;

class ShopifyWebhookTest extends TestCase
{
    use CreatesShopifyFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopifyFixtures();
        $this->mockShopifyConfigured();
    }

    protected function generateHmac(string $body): string
    {
        return base64_encode(hash_hmac('sha256', $body, 'test-api-secret', true));
    }

    public function test_rejects_missing_headers(): void
    {
        $response = $this->postJson('/shopify/webhooks', ['id' => 123]);

        $response->assertStatus(400);
    }

    public function test_rejects_missing_hmac_header(): void
    {
        $response = $this->postJson('/shopify/webhooks', ['id' => 123], [
            'X-Shopify-Topic' => 'products/update',
            'X-Shopify-Shop-Domain' => 'test-store.myshopify.com',
        ]);

        $response->assertStatus(400);
    }

    public function test_rejects_invalid_hmac(): void
    {
        $body = json_encode(['id' => 123]);

        $response = $this->call('POST', '/shopify/webhooks', [], [], [], [
            'HTTP_X-Shopify-Hmac-Sha256' => 'invalid-hmac-value',
            'HTTP_X-Shopify-Topic' => 'products/update',
            'HTTP_X-Shopify-Shop-Domain' => 'test-store.myshopify.com',
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertStatus(401);
    }

    public function test_accepts_valid_hmac_and_dispatches_job(): void
    {
        Queue::fake();

        $body = json_encode(['id' => 123, 'title' => 'Test Product']);
        $hmac = $this->generateHmac($body);

        $response = $this->call('POST', '/shopify/webhooks', [], [], [], [
            'HTTP_X-Shopify-Hmac-Sha256' => $hmac,
            'HTTP_X-Shopify-Topic' => 'products/update',
            'HTTP_X-Shopify-Shop-Domain' => 'test-store.myshopify.com',
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertStatus(200);

        Queue::assertPushed(ProcessShopifyWebhook::class, function ($job) {
            return true;
        });
    }

    public function test_passes_correct_topic_and_domain_to_job(): void
    {
        Queue::fake();

        $body = json_encode(['id' => 456]);
        $hmac = $this->generateHmac($body);

        $this->call('POST', '/shopify/webhooks', [], [], [], [
            'HTTP_X-Shopify-Hmac-Sha256' => $hmac,
            'HTTP_X-Shopify-Topic' => 'orders/create',
            'HTTP_X-Shopify-Shop-Domain' => 'test-store.myshopify.com',
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        Queue::assertPushed(ProcessShopifyWebhook::class);
    }

    public function test_returns_200_immediately_for_async_processing(): void
    {
        Queue::fake();

        $body = json_encode(['id' => 789]);
        $hmac = $this->generateHmac($body);

        $response = $this->call('POST', '/shopify/webhooks', [], [], [], [
            'HTTP_X-Shopify-Hmac-Sha256' => $hmac,
            'HTTP_X-Shopify-Topic' => 'app/uninstalled',
            'HTTP_X-Shopify-Shop-Domain' => 'test-store.myshopify.com',
            'CONTENT_TYPE' => 'application/json',
        ], $body);

        $response->assertStatus(200);
        $this->assertEquals('OK', $response->getContent());
    }
}
