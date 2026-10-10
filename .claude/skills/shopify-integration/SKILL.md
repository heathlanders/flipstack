---
description: Shopify integration - OAuth, product sync, inventory management, order processing, webhooks, collections, metafields, and store import
user-invocable: true
---

# Shopify Integration Reference

Use this skill when working on any Shopify-related feature: product sync, inventory management, order handling, OAuth, webhooks, fulfillment, collections, metafields, or store import.

## Key Files

| Component | Path |
|-----------|------|
| Base HTTP Client | `app/Services/Shopify/ShopifyBaseService.php` |
| OAuth Service | `app/Services/Shopify/ShopifyOAuthService.php` |
| Product Service | `app/Services/Shopify/ShopifyProductService.php` |
| Inventory Service | `app/Services/Shopify/ShopifyInventoryService.php` |
| Order Service | `app/Services/Shopify/ShopifyOrderService.php` |
| Fulfillment Service | `app/Services/Shopify/ShopifyFulfillmentService.php` |
| Collection Service | `app/Services/Shopify/ShopifyCollectionService.php` |
| Metafield Service | `app/Services/Shopify/ShopifyMetafieldService.php` |
| Webhook Service | `app/Services/Shopify/ShopifyWebhookService.php` |
| Config Service | `app/Services/Shopify/ShopifyConfigService.php` |
| Rate Limiter | `app/Services/Shopify/ShopifyRateLimiter.php` |
| API Exception | `app/Services/Shopify/ShopifyApiException.php` |
| Store Import | `app/Services/Shopify/ShopifyStoreImportService.php` |
| Marketplace Adapter | `app/Services/Shopify/ShopifyMarketplaceAdapter.php` |
| Listing Model | `app/Models/Shopify/ShopifyListing.php` |
| Sync Log Model | `app/Models/Shopify/ShopifySyncLog.php` |
| OAuth Controller | `app/Http/Controllers/Shopify/ShopifyOAuthController.php` |
| Webhook Controller | `app/Http/Controllers/Shopify/ShopifyWebhookController.php` |
| Marketplace Controller | `app/Http/Controllers/Shopify/ShopifyMarketplaceController.php` |
| Sync Job | `app/Jobs/Shopify/SyncProductToShopify.php` |
| Bulk Sync Job | `app/Jobs/Shopify/BulkSyncToShopify.php` |
| Inventory Job | `app/Jobs/Shopify/UpdateShopifyInventory.php` |
| Import Job | `app/Jobs/Shopify/ImportShopifyProducts.php` |
| Retry Job | `app/Jobs/Shopify/RetryFailedShopifySync.php` |
| Webhook Job | `app/Jobs/Shopify/ProcessShopifyWebhook.php` |
| Routes | `routes/shopify.php` |
| Match Controller | `app/Http/Controllers/Shopify/ShopifyProductMatchController.php` |
| Match Job | `app/Jobs/Shopify/MatchShopifyProducts.php` |
| Match Model | `app/Models/Marketplace/MarketplaceProductMatch.php` |
| Matching Service | `app/Services/Marketplace/ProductMatchingService.php` |
| Review Page | `resources/js/Pages/Shopify/Match/Review.vue` |
| Frontend Dashboard | `resources/js/Pages/Marketplaces/Shopify/Settings/Index.vue` |
| Frontend Products | `resources/js/Pages/Marketplaces/Shopify/Products/Index.vue` |
| Frontend Logs | `resources/js/Pages/Marketplaces/Shopify/SyncLogs/Index.vue` |
| Full Docs | `docs/SHOPIFY_INTEGRATION.md` |
| API Roadmap | `docs/SHOPIFY_API_REFERENCE.md` |

## Service Architecture

All Shopify services extend `ShopifyBaseService` which provides:
- HTTP client (GET/POST/PUT/DELETE) with auto URL construction: `https://{shopDomain}/admin/api/{version}/{endpoint}.json`
- Cursor-based pagination via `paginateAll()` (Link header parsing, 250/page)
- Rate limiting via `ShopifyRateLimiter` (token bucket, 40 max, throttle at 80%)
- Automatic `ShopifySyncLog` creation for every API call
- Error wrapping into `ShopifyApiException`

Constructor pattern:
```php
public function __construct(IntegrationCredential $credential)
// Loads: shop_domain, access_token (from OAuthToken), api_version
```

## OAuth Flow

1. User enters `{store}.myshopify.com` on settings page
2. Domain validated: `/^[a-zA-Z0-9][a-zA-Z0-9\-]*\.myshopify\.com$/`
3. `IntegrationCredential` created/updated with `platform=shopify`
4. Encrypted state: `{organization_id, credential_id, shop_domain, timestamp}`
5. Redirect to Shopify authorize URL
6. Callback exchanges code for offline token (never expires, no refresh)
7. Post-auth: fetch shop info, cache location ID, register webhooks

Scopes: `read_products, write_products, read_inventory, write_inventory, read_orders, read_locations, write_fulfillments`

Token access: `$credential->getActiveShopifyToken()->decrypted_access_token`
Readiness: `$credential->isShopifyReady()` (platform=shopify + active + token exists)

## Product Data Mapping

`ShopifyProductService::toShopifyProductData(Product $product)` maps:

| Shopify Field | Source |
|---------------|--------|
| `title` | `Product.name` |
| `body_html` | `Product.description` or `short_description` |
| `vendor` | `Product.brand.name` |
| `product_type` | `Product.category.name` |
| `variants[0].price` | `Product.selling_price` |
| `variants[0].compare_at_price` | `Product.msrp` (if > selling_price) |
| `variants[0].sku` | `Product.sku` |
| `variants[0].barcode` | `Product.barcode` |
| `variants[0].weight` | `Product.weight` (lbs) |
| `variants[0].inventory_management` | `"shopify"` if `manage_stock` |
| `images` | `featured_image` + `gallery_images` |
| `metafields_global_title_tag` | `Product.seo_title` |
| `metafields_global_description_tag` | `Product.seo_description` |

Change detection: MD5 hash stored as `product_data_hash` on listing. Updates skip API if hash unchanged (unless `$force = true`).

## ShopifyListing Status Lifecycle

```
[pending] --sync--> [created] --confirms--> [active]
                                               |--update--> [active]
                                               |--archive-> [archived]
                                               |--delete--> [deleted]
[pending] --fail--> [error] --retry(<5)--> [pending]
                            --5+ errors--> [error] (stuck)
```

Statuses: `pending`, `created`, `active`, `draft`, `archived`, `error`, `deleted`

## Webhook Topics

| Topic | Handler | Action |
|-------|---------|--------|
| `products/create` | `handleProductUpdate()` | Update listing status, price, inventory |
| `products/update` | `handleProductUpdate()` | Update listing status, price, inventory |
| `products/delete` | `handleProductDelete()` | Mark listing as deleted |
| `orders/create` | `handleOrderUpdate()` | Reserve inventory, push to other marketplaces |
| `orders/updated` | `handleOrderUpdate()` | Reserve inventory on status change |
| `inventory_levels/update` | `handleInventoryUpdate()` | Update listing inventory_quantity |
| `app/uninstalled` | `handleAppUninstalled()` | Deactivate tokens and credential |

Feedback loop prevention: Skip inbound updates when `sync_direction_lock = 'outbound'` AND `last_synced_at` < 30s ago.

HMAC verification: `base64_encode(hash_hmac('sha256', $rawBody, $apiSecret, true)) === $hmacHeader`

## Inventory Sync

**Outbound** (App to Shopify):
1. `Product.available_quantity` changes (order import, manual adjustment)
2. `PushInventoryToMarketplaces` job dispatched
3. `MarketplaceInventorySync` checks `credential.auto_sync_enabled` (skips if off)
4. Calls `ShopifyMarketplaceAdapter::updateQuantity()`
5. Resolves location_id (cached in credential config or fetched)
6. `ShopifyInventoryService::setInventoryLevel(locationId, inventoryItemId, available_quantity)`
7. Zeros out all non-primary Shopify locations (prevents double-counting)

**CRITICAL rules**:
- Inventory is managed EXCLUSIVELY via `setInventoryLevel()`. Never include `inventory_quantity` in product variant payloads.
- Shopify auto-distributes inventory to all active locations on product creation. After creating a product, immediately set correct levels and zero out non-primary locations.
- `generateProductHash()` on ShopifyListing excludes `stock_quantity` to prevent phantom listing updates when only inventory changes.

**Inbound** (Shopify to App):
1. `inventory_levels/update` webhook received
2. Updates `listing.inventory_quantity` (tracking only)
3. Does NOT push back (prevents loops)

## Error Handling

### ShopifyApiException

| HTTP Status | Check Method | Retryable |
|-------------|-------------|-----------|
| 429 | `isRateLimited()` | Yes |
| 401 | `isUnauthorized()` | No |
| 404 | `isNotFound()` | No |
| 500, 502, 503, 504 | `isRetryable()` | Yes |

### Job Retry Configuration

| Job | Tries | Backoff | Queue |
|-----|-------|---------|-------|
| `SyncProductToShopify` | 3 | 60s | default |
| `UpdateShopifyInventory` | 3 | 30s | shopify-sync |
| `ProcessShopifyWebhook` | 3 | 30s | default |
| `ImportShopifyProducts` | 1 | - | default (600s timeout) |
| `RetryFailedShopifySync` | 1 | - | shopify-retry |

### Consecutive Error Tracking

- `ShopifyListing.consecutive_errors` increments on each failure
- Auto-retry (`RetryFailedShopifySync`) only retries < 5 consecutive errors
- Errors must be > 5 minutes old before retry
- Listing set to `error` status on permanent failure

## Rate Limiter

- Token bucket: 40 requests max, refills at 2 req/sec
- Header: `X-Shopify-Shop-Api-Call-Limit: "used/max"`
- Redis key: `shopify_rate_limit:{shopDomain}` (10s TTL)
- Throttle threshold: >= 80% (32/40) used
- Action: Sleep 500ms to allow bucket refill

## Store Import

```
ImportShopifyProducts job (600s timeout)
  -> ShopifyStoreImportService::importAllProducts()
  -> paginateAll('products') to fetch all active products
  -> For each: skip if listing exists, skip if SKU exists (optional)
  -> Create Product + ShopifyListing records
  -> Returns: {imported, skipped, failed, total}
```

## Marketplace Adapter

`ShopifyMarketplaceAdapter` implements `MarketplaceAdapter`:
- `updateQuantity()` -- Sets inventory via `setInventoryLevel()` to `Product.available_quantity`
- `updatePrice()` -- Updates product via `ShopifyProductService::updateProduct()`
- `updateListing()` -- Full product data update
- `endListing()` -- Archives product
- `isAvailable()` -- Checks `$credential->isShopifyReady()`
- `getActiveListings()` -- Queries active ShopifyListings for product
- `getPlatform()` -- Returns `'shopify'`

## Routes

```
# Public (HMAC verified, no auth)
POST /shopify/webhooks

# Auth required
GET  /shopify/auth/install
GET  /shopify/auth/callback
POST /shopify/auth/disconnect

GET    /marketplaces/shopify
GET    /marketplaces/shopify/products
POST   /marketplaces/shopify/products/{product}/sync
POST   /marketplaces/shopify/sync/bulk
POST   /marketplaces/shopify/import/start
GET    /marketplaces/shopify/logs
```

## Configuration

`ShopifyConfigService::getEnvironment()` auto-picks `sandbox` when `APP_ENV !== 'production'` (override via `SHOPIFY_USE_SANDBOX`). `getApiKey()` / `getApiSecret()` resolve from `services.shopify.{env}.api_key` (with legacy fallback). `IntegrationCredential.environment` + `OAuthToken.environment` are stamped at OAuth time — never hardcode `'production'`.

```env
# Per-env Partner apps (preferred — mirrors eBay pattern)
SHOPIFY_SANDBOX_API_KEY=
SHOPIFY_SANDBOX_API_SECRET=
SHOPIFY_PRODUCTION_API_KEY=
SHOPIFY_PRODUCTION_API_SECRET=

# Optional override
# SHOPIFY_USE_SANDBOX=true

# Legacy single-app keys (fallback only)
SHOPIFY_API_KEY=
SHOPIFY_API_SECRET=

SHOPIFY_API_VERSION=2026-01
SHOPIFY_SCOPES=read_products,write_products,read_inventory,write_inventory,read_orders,read_locations,write_fulfillments
SHOPIFY_REDIRECT_URI=     # Default: /shopify/auth/callback
```

See `docs/SHOPIFY_SANDBOX_TESTING.md` for develop-deploy + 2-app Partner setup.

## New Service Pattern

When adding a new Shopify service:

```php
<?php

namespace App\Services\Shopify;

use App\Models\Integration\IntegrationCredential;

class ShopifyNewService extends ShopifyBaseService
{
    // Constructor inherited from ShopifyBaseService
    // Provides: $this->get(), $this->post(), $this->put(), $this->delete(), $this->paginateAll()

    public function getResources(array $params = []): array
    {
        return $this->paginateAll('resources', 'resources', $params);
    }

    public function getResource(int $resourceId): array
    {
        $response = $this->get("resources/{$resourceId}");
        return $response['resource'];
    }

    public function createResource(array $data): array
    {
        $response = $this->post('resources', ['resource' => $data]);
        return $response['resource'];
    }

    public function updateResource(int $resourceId, array $data): array
    {
        $response = $this->put("resources/{$resourceId}", ['resource' => $data]);
        return $response['resource'];
    }

    public function deleteResource(int $resourceId): bool
    {
        $this->delete("resources/{$resourceId}");
        return true;
    }
}
```

## Fee Tracking

### Transaction Fee Extraction
During Shopify order import, `ShopifyOrderSyncService` extracts fee data from the order's `transactions` array. Fees are parsed and stored against the order record for profitability tracking.

### Fee Types
| Fee Type | Source | Description |
|----------|--------|-------------|
| `transaction` | `transaction.fee` | Shopify's per-transaction fee (varies by plan) |
| `payment_processing` | `transaction.gateway` fees | Payment gateway processing fees (Shopify Payments, third-party) |

These fees feed into the `FeeTrackingService` and P&L analytics for per-order and per-product margin calculations.

---

## Product Matching

Links existing Shopify listings to eBay-imported Products via waterfall: SKU > UPC/EAN > title (pg_trgm fuzzy). Uses `MarketplaceProductMatch` staging table, reviewed in UI before committing.

Key service: `ProductMatchingService::matchShopifyProducts()` -- creates `MarketplaceProductMatch` rows.
On confirm: `ShopifyProductMatchController::confirm()` creates `ShopifyListing`, dispatches `PushInventoryToMarketplaces`.
Quantity: `Product.available_quantity` wins (eBay source of truth).

---

## Debugging Commands

### Check credential status
```bash
docker exec ecommerce_app php artisan tinker --execute="
\$cred = App\Models\Integration\IntegrationCredential::where('platform', 'shopify')->where('is_active', true)->first();
echo 'ID: ' . \$cred?->id . PHP_EOL;
echo 'Shop: ' . (\$cred?->config['shop_domain'] ?? 'N/A') . PHP_EOL;
echo 'Ready: ' . (\$cred?->isShopifyReady() ? 'Yes' : 'No') . PHP_EOL;
echo 'Location: ' . (\$cred?->config['shopify_location_id'] ?? 'N/A') . PHP_EOL;
"
```

### Listing status distribution
```bash
docker exec ecommerce_app php artisan tinker --execute="
App\Models\Shopify\ShopifyListing::selectRaw(\"status, COUNT(*) as count\")
  ->groupBy('status')->get()->each(fn(\$r) => print(\$r->status . ': ' . \$r->count . PHP_EOL));
"
```

### Recent sync errors (24h)
```bash
docker exec ecommerce_app php artisan tinker --execute="
App\Models\Shopify\ShopifySyncLog::where('status', 'error')
  ->where('created_at', '>=', now()->subDay())
  ->selectRaw(\"error_code, COUNT(*) as count\")
  ->groupBy('error_code')
  ->orderByDesc('count')
  ->get()->each(fn(\$r) => print(\$r->error_code . ': ' . \$r->count . PHP_EOL));
"
```

### Rate limiter status
```bash
docker exec ecommerce_app php artisan tinker --execute="
\$cred = App\Models\Integration\IntegrationCredential::where('platform', 'shopify')->where('is_active', true)->first();
\$status = (new App\Services\Shopify\ShopifyRateLimiter())->getStatus(\$cred->config['shop_domain']);
print_r(\$status);
"
```

### Listings with excessive errors (>=5)
```bash
docker exec ecommerce_app php artisan tinker --execute="
App\Models\Shopify\ShopifyListing::where('consecutive_errors', '>=', 5)
  ->select('id', 'shopify_product_id', 'status', 'consecutive_errors', 'last_error_message')
  ->get()->each(fn(\$l) => print(\$l->id . ' | errors: ' . \$l->consecutive_errors . ' | ' . \$l->last_error_message . PHP_EOL));
"
```

### Manually retry a listing
```bash
docker exec ecommerce_app php artisan tinker --execute="
\$listing = App\Models\Shopify\ShopifyListing::find(LISTING_ID);
\$listing->update(['status' => 'pending', 'consecutive_errors' => 0]);
dispatch(new App\Jobs\Shopify\SyncProductToShopify(\$listing->product, \$listing->integrationCredential));
echo 'Dispatched sync for listing ' . \$listing->id;
"
```

### Failed Shopify jobs
```bash
docker exec ecommerce_app php artisan tinker --execute="
DB::table('failed_jobs')->where('payload', 'like', '%Shopify%')
  ->orderByDesc('failed_at')->limit(5)
  ->get(['id', 'exception', 'failed_at'])
  ->each(fn(\$j) => print(\$j->id . ' | ' . \$j->failed_at . ' | ' . Str::limit(\$j->exception, 200) . PHP_EOL));
"
```

## Notifications

Shopify sync events that trigger user notifications:

| Event | Notification Class | Wired In |
|-------|--------------------|----------|
| Product sync failed | `Shopify\ShopifySyncNotification` | `SyncProductToShopify::failed()` |
| Inventory sync failed | `Shopify\ShopifySyncNotification` | `UpdateShopifyInventory::failed()` |
| Import completed/failed | `Shopify\ShopifySyncNotification` | `ImportShopifyProducts::handle()` and `failed()` |

All notifications go to org admins/owners. See `docs/NOTIFICATION_SYSTEM.md` for the full notification architecture.

## Audit Logging

| Action | Category | Severity | Metadata keys |
|--------|----------|----------|---------------|
| `listing.shopify.published` | marketplace | info | shopify_product_id, sku |
| `listing.shopify.updated` | marketplace | info | changes, shopify_product_id |
| `listing.shopify.ended` | marketplace | warning | reason |
| `shopify.sync.ran` | marketplace | debug | listings_synced, source: cron|webhook |
| `shopify.sync.failed` | marketplace | error | error_code, http_status |
| `shopify.webhook.received` | marketplace | debug | topic, source: webhook |

Trait: models in this feature use `LogsAudit` (`app/Traits/LogsAudit.php`) for auto CRUD diff. Domain lifecycle events emitted from `app/Services/Shopify/ShopifyService`, `MarketplaceInventorySync` (when target=shopify), `app/Jobs/Shopify/ProcessShopifyWebhook` (wrap in `withAuditActor(null, ..., ['source' => 'webhook', 'webhook_topic' => ...])`); `ShopifyListing` model (`LogsAudit`) via `UserActivityLog::log(action, desc, $user, $metadata, $severity, $category)`.

KPI consumer: `UserProductivityDaily.listings_published_shopify` (from `listing.shopify.published`).

Spec: `docs/AUDIT_SYSTEM.md`. Enforced rule: `.claude/rules/audit-logging.md`. Tests: `use Tests\Support\AssertsAudits;` then `$this->assertAudited('action', $user, [...])`.
