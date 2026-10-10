<?php

namespace Tests\Traits;

use App\Models\Catalog\Product;
use App\Models\Integration\IntegrationCredential;
use App\Models\Integration\OAuthToken;
use App\Models\Organization\Organization;
use App\Models\Organization\Team;
use App\Models\Shopify\ShopifyListing;
use App\Models\System\OnboardingProgress;
use App\Models\User\Role;
use App\Models\User\User;

trait CreatesShopifyFixtures
{
    protected Organization $shopifyOrganization;

    protected Team $shopifyTeam;

    protected User $shopifyUser;

    protected Role $shopifyRole;

    protected IntegrationCredential $shopifyCredential;

    protected OAuthToken $shopifyToken;

    protected function setUpShopifyFixtures(): void
    {
        $this->shopifyOrganization = Organization::factory()->create([
            'sku_prefix' => 'SHOP',
            'sku_prefix_locked' => true,
        ]);

        $this->shopifyTeam = Team::factory()->create([
            'organization_id' => $this->shopifyOrganization->id,
        ]);

        $this->shopifyRole = Role::factory()->create([
            'name' => 'Admin',
            'slug' => 'admin',
            'is_system' => true,
            'permissions' => [
                'inventory.view',
                'products.view',
                'products.create',
                'products.edit',
            ],
        ]);

        $this->shopifyUser = User::factory()->create([
            'organization_id' => $this->shopifyOrganization->id,
        ]);

        $this->shopifyUser->roles()->attach($this->shopifyRole->id, [
            'organization_id' => $this->shopifyOrganization->id,
            'team_id' => null,
        ]);

        $this->shopifyUser->current_team_id = $this->shopifyTeam->id;
        $this->shopifyUser->save();

        $this->shopifyTeam->users()->attach($this->shopifyUser->id, ['role' => 'admin']);

        OnboardingProgress::create([
            'user_id' => $this->shopifyUser->id,
            'organization_id' => $this->shopifyOrganization->id,
            'started_at' => now()->subHour(),
            'completed_at' => now(),
            'current_step' => 'completed',
            'steps_completed' => ['welcome', 'profile', 'organization', 'team'],
        ]);

        $this->shopifyCredential = IntegrationCredential::create([
            'organization_id' => $this->shopifyOrganization->id,
            'team_id' => $this->shopifyTeam->id,
            'platform' => 'shopify',
            'environment' => 'production',
            'name' => 'Shopify Test Store',
            'is_active' => true,
            'client_id' => 'test-shopify-client-id',
            'client_secret' => 'test-shopify-client-secret',
            'config' => [
                'shop_domain' => 'test-store.myshopify.com',
                'shop_name' => 'Test Store',
                'shop_id' => 12345,
                'primary_location_id' => 67890,
            ],
        ]);

        $this->shopifyToken = OAuthToken::create([
            'organization_id' => $this->shopifyOrganization->id,
            'team_id' => $this->shopifyTeam->id,
            'integration_credential_id' => $this->shopifyCredential->id,
            'platform' => 'shopify',
            'environment' => 'production',
            'access_token' => 'shpat_test_token_123',
            'refresh_token' => null,
            'expires_at' => null,
            'is_active' => true,
        ]);
    }

    protected function createShopifyListing(Product $product, array $overrides = []): ShopifyListing
    {
        return ShopifyListing::create(array_merge([
            'product_id' => $product->id,
            'integration_credential_id' => $this->shopifyCredential->id,
            'team_id' => $this->shopifyTeam->id,
            'shopify_product_id' => rand(100000, 999999),
            'shopify_variant_id' => rand(100000, 999999),
            'shopify_inventory_item_id' => rand(100000, 999999),
            'shopify_sku' => $product->sku ?? 'SHOP-TEST-001',
            'shopify_handle' => 'test-product',
            'status' => ShopifyListing::STATUS_ACTIVE,
            'listed_price' => $product->selling_price ?? 29.99,
            'currency' => 'USD',
            'inventory_quantity' => $product->stock_quantity ?? 10,
            'last_synced_at' => now(),
            'consecutive_errors' => 0,
        ], $overrides));
    }

    protected function createTestProduct(array $overrides = []): Product
    {
        return Product::create(array_merge([
            'organization_id' => $this->shopifyOrganization->id,
            'name' => 'Test Product',
            'description' => 'A test product for Shopify integration testing',
            'sku' => 'SHOP-TEST-'.rand(1000, 9999),
            'selling_price' => 29.99,
            'cost_price' => 15.00,
            'stock_quantity' => 10,
            'available_quantity' => 10,
            'status' => 'active',
            'condition' => 'NEW',
            'manage_stock' => true,
            'created_by' => $this->shopifyUser->id,
            'gallery_images' => [],
        ], $overrides));
    }

    protected function mockShopifyConfigured(): void
    {
        $this->partialMock(\App\Services\Shopify\ShopifyConfigService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('getApiKey')->andReturn('test-api-key');
            $mock->shouldReceive('getApiSecret')->andReturn('test-api-secret');
            $mock->shouldReceive('getApiVersion')->andReturn('2024-10');
            $mock->shouldReceive('getScopesString')->andReturn('read_products,write_products,read_inventory,write_inventory,read_orders,read_locations,write_fulfillments');
            $mock->shouldReceive('getRedirectUri')->andReturn('https://localhost:8090/shopify/auth/callback');
        });
    }

    protected function mockShopifyRateLimiter(): void
    {
        $this->app->singleton(\App\Services\Shopify\ShopifyRateLimiter::class, function () {
            $mock = \Mockery::mock(\App\Services\Shopify\ShopifyRateLimiter::class);
            $mock->shouldReceive('waitIfNeeded')->andReturnNull();
            $mock->shouldReceive('recordResponse')->andReturnNull();
            $mock->shouldReceive('shouldThrottle')->andReturn(false);
            $mock->shouldReceive('getStatus')->andReturn([
                'used' => 0, 'max' => 40, 'available' => 40, 'percentage_used' => 0,
            ]);

            return $mock;
        });
    }

    protected static function shopifyProductResponse(array $overrides = []): array
    {
        return array_merge([
            'product' => [
                'id' => 1234567890,
                'title' => 'Test Product',
                'body_html' => '<p>A test product</p>',
                'vendor' => 'Test Brand',
                'product_type' => 'Electronics',
                'handle' => 'test-product',
                'status' => 'active',
                'variants' => [
                    [
                        'id' => 9876543210,
                        'product_id' => 1234567890,
                        'title' => 'Default Title',
                        'price' => '29.99',
                        'compare_at_price' => null,
                        'sku' => 'SHOP-TEST-001',
                        'barcode' => '012345678901',
                        'weight' => 2.5,
                        'weight_unit' => 'lb',
                        'inventory_item_id' => 5555555555,
                        'inventory_quantity' => 10,
                        'inventory_management' => 'shopify',
                        'requires_shipping' => true,
                    ],
                ],
                'images' => [
                    ['id' => 1, 'src' => 'https://cdn.shopify.com/test.jpg'],
                ],
                'image' => ['id' => 1, 'src' => 'https://cdn.shopify.com/test.jpg'],
            ],
        ], $overrides);
    }

    protected static function shopifyProductListResponse(int $count = 2): array
    {
        $products = [];
        for ($i = 1; $i <= $count; $i++) {
            $products[] = [
                'id' => 1000000 + $i,
                'title' => "Product {$i}",
                'body_html' => "<p>Description for product {$i}</p>",
                'vendor' => 'Test Brand',
                'product_type' => 'Electronics',
                'handle' => "product-{$i}",
                'status' => 'active',
                'variants' => [
                    [
                        'id' => 2000000 + $i,
                        'price' => (string) (19.99 + $i),
                        'compare_at_price' => null,
                        'sku' => "PROD-{$i}",
                        'barcode' => null,
                        'weight' => 1.0,
                        'weight_unit' => 'lb',
                        'inventory_item_id' => 3000000 + $i,
                        'inventory_quantity' => 5,
                        'inventory_management' => 'shopify',
                        'requires_shipping' => true,
                    ],
                ],
                'image' => ['src' => "https://cdn.shopify.com/product-{$i}.jpg"],
            ];
        }

        return ['products' => $products];
    }

    protected static function shopifyLocationsResponse(): array
    {
        return [
            'locations' => [
                [
                    'id' => 67890,
                    'name' => 'Main Warehouse',
                    'address1' => '123 Test St',
                    'city' => 'Testville',
                    'province' => 'CA',
                    'country' => 'US',
                    'active' => true,
                    'legacy' => false,
                ],
            ],
        ];
    }

    protected static function shopifyInventoryLevelResponse(int $available = 10): array
    {
        return [
            'inventory_level' => [
                'inventory_item_id' => 5555555555,
                'location_id' => 67890,
                'available' => $available,
                'updated_at' => now()->toIso8601String(),
            ],
        ];
    }

    protected static function shopifyOrderPayload(array $overrides = []): array
    {
        return array_merge([
            'id' => 820982911946154508,
            'name' => '#1001',
            'email' => 'customer@example.com',
            'financial_status' => 'paid',
            'fulfillment_status' => null,
            'line_items' => [
                [
                    'id' => 866550311766439020,
                    'variant_id' => 9876543210,
                    'product_id' => 1234567890,
                    'title' => 'Test Product',
                    'quantity' => 1,
                    'price' => '29.99',
                    'sku' => 'SHOP-TEST-001',
                ],
            ],
            'total_price' => '29.99',
            'currency' => 'USD',
            'created_at' => now()->toIso8601String(),
        ], $overrides);
    }

    protected static function shopifyShopResponse(array $overrides = []): array
    {
        return [
            'shop' => array_merge([
                'id' => 12345,
                'name' => 'Test Store',
                'email' => 'owner@test-store.com',
                'domain' => 'test-store.myshopify.com',
                'myshopify_domain' => 'test-store.myshopify.com',
                'plan_name' => 'developer',
                'plan_display_name' => 'Developer Preview',
                'shop_owner' => 'Test Owner',
                'country_name' => 'United States',
                'currency' => 'USD',
            ], $overrides),
        ];
    }

    protected static function shopifyWebhookResponse(string $topic = 'products/update'): array
    {
        return [
            'webhook' => [
                'id' => 901431826,
                'address' => 'https://flipstack.app/shopify/webhooks',
                'topic' => $topic,
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
                'format' => 'json',
                'api_version' => '2024-10',
            ],
        ];
    }
}
