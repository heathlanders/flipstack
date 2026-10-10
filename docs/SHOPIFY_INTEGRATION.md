# Shopify Integration

Complete reference for the Shopify marketplace integration. Covers OAuth, product sync, inventory management, order processing, webhooks, and the marketplace adapter pattern.

## Architecture Overview

```
app/Services/Shopify/
  ShopifyBaseService.php          # Abstract HTTP client with rate limiting and sync logging
  ShopifyOAuthService.php         # OAuth 2.0 authorization and token management
  ShopifyProductService.php       # Product CRUD and data mapping
  ShopifyInventoryService.php     # Inventory level management
  ShopifyOrderService.php         # Order retrieval and management
  ShopifyFulfillmentService.php   # Fulfillment creation and tracking
  ShopifyCollectionService.php    # Product collection management
  ShopifyMetafieldService.php     # Custom metafield CRUD
  ShopifyWebhookService.php       # Webhook registration and management
  ShopifyConfigService.php        # Centralized env/config access
  ShopifyRateLimiter.php          # Redis-backed token bucket rate limiter
  ShopifyApiException.php         # Standardized API error handling
  ShopifyStoreImportService.php   # Bulk product import from Shopify
  ShopifyMarketplaceAdapter.php   # MarketplaceAdapter implementation for cross-marketplace sync

app/Models/Shopify/
  ShopifyListing.php              # Product-to-Shopify mapping with sync state
  ShopifySyncLog.php              # Audit trail for all API operations

app/Http/Controllers/Shopify/
  ShopifyOAuthController.php      # OAuth install/callback/disconnect
  ShopifyWebhookController.php    # Incoming webhook handler (HMAC verified)
  ShopifyMarketplaceController.php # Dashboard, product sync, import, logs UI

app/Jobs/Shopify/
  SyncProductToShopify.php        # Single product create/update
  BulkSyncToShopify.php           # Batch product sync dispatcher
  UpdateShopifyInventory.php      # Inventory level push
  ImportShopifyProducts.php       # Bulk store import
  RetryFailedShopifySync.php      # Auto-retry errored listings
  ProcessShopifyWebhook.php       # Async webhook topic handler

routes/shopify.php                # All Shopify routes
```

## Key Files Quick Reference

| Component | File | Purpose |
|-----------|------|---------|
| Base HTTP Client | `app/Services/Shopify/ShopifyBaseService.php` | Shared API client, rate limiting, sync logging |
| Product Sync | `app/Services/Shopify/ShopifyProductService.php` | Create/update/delete/archive products |
| Inventory Sync | `app/Services/Shopify/ShopifyInventoryService.php` | Set/adjust inventory levels |
| Marketplace Adapter | `app/Services/Shopify/ShopifyMarketplaceAdapter.php` | Cross-marketplace sync interface |
| Listing Model | `app/Models/Shopify/ShopifyListing.php` | Product-Shopify mapping |
| Sync Log Model | `app/Models/Shopify/ShopifySyncLog.php` | API operation audit trail |
| Webhook Handler | `app/Jobs/Shopify/ProcessShopifyWebhook.php` | Handles all 7 webhook topics |
| Store Import | `app/Services/Shopify/ShopifyStoreImportService.php` | Bulk import from Shopify |

---

## OAuth Flow

### Scopes Requested

```
read_products, write_products, read_inventory, write_inventory,
read_orders, read_locations, write_fulfillments
```

### 7-Step Authorization Process

1. User enters `{store}.myshopify.com` domain on settings page
2. `ShopifyOAuthController@install` validates domain regex: `/^[a-zA-Z0-9][a-zA-Z0-9\-]*\.myshopify\.com$/`
3. Creates or updates `IntegrationCredential` with `platform=shopify`
4. Generates encrypted state token: `{organization_id, credential_id, shop_domain, timestamp}`
5. Redirects to `https://{shop}/admin/oauth/authorize?client_id=...&scope=...&redirect_uri=...&state=...`
6. `ShopifyOAuthController@callback` exchanges authorization code for offline access token via `POST /admin/oauth/access_token`
7. Post-auth: fetches shop info, caches primary location ID, registers webhooks

### Token Behavior

- Shopify offline tokens **never expire** and do not need refresh
- Previous tokens are deactivated before creating new ones
- Tokens stored encrypted in `OAuthToken` model (Laravel AES-256-GCM)
- Access via `$credential->getActiveShopifyToken()->decrypted_access_token`

### Credential Readiness

```php
$credential->isShopifyReady()  // platform=shopify AND is_active AND has active token
```

---

## Product Sync System

### Outbound Sync Flow (App to Shopify)

```
User clicks "Sync" on Product
  |
  v
SyncProductToShopify job queued (3 tries, 60s backoff)
  |
  v
Find or create ShopifyListing for product + credential
  |
  v
Has shopify_product_id? --yes--> ShopifyProductService::updateProduct()
                        --no---> ShopifyProductService::createProduct()
  |
  v
Update listing: shopify_product_id, variant_id, inventory_item_id
Store product_data_hash (MD5) for change detection
Reset consecutive_errors to 0
```

### Product Data Mapping (toShopifyProductData)

| Shopify Field | Source | Notes |
|---------------|--------|-------|
| `title` | `Product.name` | |
| `body_html` | `Product.description` or `short_description` | |
| `vendor` | `Product.brand.name` | |
| `product_type` | `Product.category.name` | |
| `variants[0].price` | `Product.selling_price` | |
| `variants[0].compare_at_price` | `Product.msrp` | Only if > selling_price |
| `variants[0].sku` | `Product.sku` | Or generated |
| `variants[0].barcode` | `Product.barcode` | |
| `variants[0].weight` | `Product.weight` | In lbs |
| `variants[0].inventory_management` | `"shopify"` | If `Product.manage_stock` |
| `variants[0].requires_shipping` | `Product.requires_shipping` | |
| `images` | `featured_image` + `gallery_images` | Array of image URLs |
| `metafields_global_title_tag` | `Product.seo_title` | SEO |
| `metafields_global_description_tag` | `Product.seo_description` | SEO |

### Change Detection

`ShopifyProductService::updateProduct()` skips API calls when product data hasn't changed:

1. `ShopifyListing::generateProductHash()` creates MD5 of product fields
2. Compares against stored `product_data_hash`
3. Skips update if hashes match (unless `$force = true`)

### Bulk Sync

`BulkSyncToShopify` dispatches individual `SyncProductToShopify` jobs with 2-second delays between each to respect rate limits.

---

## Inventory Sync

### Outbound (App to Shopify)

```
Product.available_quantity changes (e.g., order import)
  |
  v
PushInventoryToMarketplaces job (via MarketplaceInventorySync)
  |
  v
ShopifyMarketplaceAdapter::updateQuantity()
  |
  v
Resolve location_id (from credential config cache or API fetch)
  |
  v
ShopifyInventoryService::setInventoryLevel(locationId, inventoryItemId, available_quantity)
  |
  v
Zero out all non-primary locations (prevents double-counting across locations)
```

**IMPORTANT**: Inventory is managed exclusively via `setInventoryLevel()`. Never include `inventory_quantity` in Shopify product variant payloads -- Shopify treats it as an adjustment, not an absolute set, causing double-counting.

**Multi-location**: Shopify auto-distributes inventory to all active locations on product creation. The sync must set the desired quantity at the primary location AND zero out all other locations, because the total shown to buyers = sum across all locations.

### Inbound (Shopify to App)

```
Shopify: inventory_levels/update webhook
  |
  v
ProcessShopifyWebhook::handleInventoryUpdate()
  |
  v
Update listing.inventory_quantity (tracking only)
  |
  v
NO push back to Shopify (prevents feedback loops via sync_direction_lock)
```

### Location Resolution

The Shopify Inventory API requires a `location_id`. Resolution order:

1. Check `IntegrationCredential.config['shopify_location_id']` (cached during OAuth)
2. If missing: call `ShopifyInventoryService::getLocations()` and use first location
3. Cache result back to credential config

### Inventory Service Methods

| Method | Endpoint | Description |
|--------|----------|-------------|
| `getLocations()` | `GET /locations` | Fetch all warehouse locations |
| `getInventoryLevels()` | `GET /inventory_levels` | Query inventory for location + items |
| `setInventoryLevel()` | `POST /inventory_levels/set` | Set absolute quantity |
| `adjustInventoryLevel()` | `POST /inventory_levels/adjust` | Adjust by delta (+/-) |
| `getInventoryItem()` | `GET /inventory_items/{id}` | Fetch inventory item metadata |

---

## Order Processing

### Inbound Order Handling (Webhook)

```
Shopify: orders/create webhook
  |
  v
ShopifyWebhookController: Verify HMAC-SHA256
  |
  v
ProcessShopifyWebhook job (async, 3 tries, 30s backoff)
  |
  v
handleOrderUpdate():
  For each line_item:
    Find ShopifyListing by shopify_variant_id
    Call product->decrementAvailableQuantity(quantity)
    Dispatch PushInventoryToMarketplaces
```

### Order Service Methods

| Method | Endpoint | Description |
|--------|----------|-------------|
| `getOrders()` | `GET /orders` | Fetch all orders (paginated) |
| `getOrder()` | `GET /orders/{id}` | Single order details |
| `getOrderCount()` | `GET /orders/count` | Count with filters |
| `closeOrder()` | `POST /orders/{id}/close` | Mark as closed |
| `cancelOrder()` | `POST /orders/{id}/cancel` | Cancel with optional reason |

### Fulfillment Service Methods

| Method | Endpoint | Description |
|--------|----------|-------------|
| `getFulfillmentOrders()` | `GET /orders/{id}/fulfillment_orders` | Get fulfillment orders |
| `createFulfillment()` | `POST /fulfillments` | Create fulfillment record |
| `updateTracking()` | `PUT /fulfillments/{id}/update_tracking` | Update tracking info |

---

## Webhook System

### Registered Topics

| Topic | Handler | Action |
|-------|---------|--------|
| `products/create` | `handleProductUpdate()` | Update listing status, price, inventory |
| `products/update` | `handleProductUpdate()` | Update listing status, price, inventory |
| `products/delete` | `handleProductDelete()` | Mark listing as deleted |
| `orders/create` | `handleOrderUpdate()` | Reserve inventory, push to other marketplaces |
| `orders/updated` | `handleOrderUpdate()` | Reserve inventory on status change |
| `inventory_levels/update` | `handleInventoryUpdate()` | Update listing inventory_quantity |
| `app/uninstalled` | `handleAppUninstalled()` | Deactivate tokens and credential |

### Webhook Security

1. **HMAC-SHA256 Verification**: Every incoming webhook is verified against `SHOPIFY_API_SECRET`
   ```php
   base64_encode(hash_hmac('sha256', $rawBody, $apiSecret, true)) === $hmacHeader
   ```
2. **Headers Used**: `X-Shopify-Hmac-Sha256`, `X-Shopify-Topic`, `X-Shopify-Shop-Domain`
3. **No Auth Middleware**: Webhook route is outside auth middleware (HMAC is the auth)

### Feedback Loop Prevention

`ProcessShopifyWebhook` checks `sync_direction_lock` before processing inbound updates:
- If listing has `sync_direction_lock = 'outbound'` AND `last_synced_at` < 30 seconds ago
- Skip the inbound update to prevent infinite sync loops

### Webhook Registration

Webhooks are auto-registered during OAuth callback. Can also be manually managed:

```php
$webhookService = new ShopifyWebhookService($credential);
$webhookService->registerWebhooks();  // Registers all 7 required topics
$webhookService->getWebhooks();       // List registered webhooks
$webhookService->deleteWebhook($id);  // Remove specific webhook
```

---

## Store Import

### Import Flow

```
User: "Start Import" on Shopify dashboard
  |
  v
ShopifyMarketplaceController@startImport
  |
  v
Creates BulkOperationJob record
  |
  v
ImportShopifyProducts job (1 try, 600s timeout)
  |
  v
ShopifyStoreImportService::importAllProducts()
  |
  v
For each active Shopify product:
  1. Skip if ShopifyListing already exists for this credential
  2. Skip if Product with same SKU exists (when skip_existing_skus=true)
  3. Create Product from Shopify data or link existing
  4. Create ShopifyListing record
  |
  v
Returns: {imported, skipped, failed, total}
```

### Import Options

| Option | Default | Description |
|--------|---------|-------------|
| `skip_existing_skus` | `true` | Reuse existing Product records with matching SKU |

---

## ShopifyListing Status Lifecycle

```
                         create
[pending] ----sync----> [created] ---API confirms---> [active]
    |                                                    |
    |                                                    |--update--> [active] (stays active)
    |                                                    |--archive-> [archived]
    |                                                    |--delete--> [deleted]
    |
    +----error--------> [error] ----retry (< 5)-------> [pending]
                                 ----5+ errors--------> [error] (stuck, manual intervention)
```

### Status Constants

| Status | Value | Description |
|--------|-------|-------------|
| `STATUS_PENDING` | `pending` | Not yet synced to Shopify |
| `STATUS_CREATED` | `created` | Just created on Shopify |
| `STATUS_ACTIVE` | `active` | Live on Shopify store |
| `STATUS_DRAFT` | `draft` | Unpublished on Shopify |
| `STATUS_ARCHIVED` | `archived` | Archived (soft delete) |
| `STATUS_ERROR` | `error` | Sync failed |
| `STATUS_DELETED` | `deleted` | Permanently deleted |

### Key Listing Fields

| Field | Type | Description |
|-------|------|-------------|
| `shopify_product_id` | int | Shopify product identifier |
| `shopify_variant_id` | int | Shopify variant identifier |
| `shopify_inventory_item_id` | int | Shopify inventory item identifier |
| `shopify_sku` | string | SKU on Shopify |
| `shopify_handle` | string | URL handle on Shopify |
| `product_data_hash` | string | MD5 for change detection |
| `sync_direction_lock` | string | `outbound` or `inbound` (loop prevention) |
| `consecutive_errors` | int | Error counter (>=5 stops auto-retry) |
| `last_synced_at` | datetime | Last successful sync timestamp |

---

## Rate Limiting

### Shopify's Token Bucket Model

- **Bucket Size**: 40 requests max
- **Leak Rate**: 2 requests/second
- **Header**: `X-Shopify-Shop-Api-Call-Limit: "32/40"` (used/max)

### ShopifyRateLimiter Implementation

- **Storage**: Redis with key `shopify_rate_limit:{shopDomain}`, 10s TTL
- **Threshold**: Throttle when >= 80% (32/40) of bucket used
- **Action**: Sleep 500ms to allow bucket refill (2 req/sec leak rate)
- **Called By**: `ShopifyBaseService::request()` before every API call

### Rate Limiter Methods

```php
$limiter = new ShopifyRateLimiter();
$limiter->recordResponse($shopDomain, $headers);  // Parse X-Shopify-Shop-Api-Call-Limit
$limiter->shouldThrottle($shopDomain);              // Check if >= 80%
$limiter->waitIfNeeded($shopDomain);                // Sleep 500ms if needed
$limiter->getStatus($shopDomain);                   // {used, max, available, percentage_used}
```

---

## Error Handling

### ShopifyApiException Classification

| HTTP Status | Method | Retryable | Description |
|-------------|--------|-----------|-------------|
| 429 | `isRateLimited()` | Yes | Rate limit exceeded |
| 401 | `isUnauthorized()` | No | Invalid/expired token |
| 404 | `isNotFound()` | No | Resource not found |
| 500, 502, 503, 504 | `isRetryable()` | Yes | Server errors |

### Job Retry Configuration

| Job | Tries | Backoff | Queue |
|-----|-------|---------|-------|
| `SyncProductToShopify` | 3 | 60s | default |
| `UpdateShopifyInventory` | 3 | 30s | shopify-sync |
| `ProcessShopifyWebhook` | 3 | 30s | default |
| `ImportShopifyProducts` | 1 | - | default (600s timeout) |
| `BulkSyncToShopify` | 1 | - | default |
| `RetryFailedShopifySync` | 1 | - | shopify-retry |

### Consecutive Error Tracking

- `ShopifyListing.consecutive_errors` increments on each failure
- `RetryFailedShopifySync` only retries listings with < 5 consecutive errors
- Listings with >= 5 errors require manual intervention
- Errors must be > 5 minutes old before retry

---

## Marketplace Adapter Pattern

`ShopifyMarketplaceAdapter` implements the `MarketplaceAdapter` interface for cross-marketplace inventory sync:

```php
interface MarketplaceAdapter {
    public function updatePrice(Product $product, Model $listing);
    public function updateQuantity(Product $product, Model $listing);
    public function updateListing(Product $product, Model $listing);
    public function endListing(Model $listing);
    public function isAvailable();
    public function getActiveListings(Product $product);
    public function getPlatform();
}
```

The adapter is used by `MarketplaceInventorySync` to push `Product.available_quantity` to all connected marketplaces whenever inventory changes.

---

## Database Schema

### shopify_listings

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `product_id` | uuid FK | References products |
| `integration_credential_id` | bigint FK | References integration_credentials |
| `team_id` | bigint FK | References teams |
| `shopify_product_id` | bigint | Shopify product ID |
| `shopify_variant_id` | bigint | Shopify variant ID |
| `shopify_inventory_item_id` | bigint | Shopify inventory item ID |
| `shopify_sku` | string | SKU on Shopify |
| `shopify_handle` | string | URL handle |
| `status` | string | Listing status |
| `listed_price` | decimal | Current price |
| `compare_at_price` | decimal | Compare at price |
| `currency` | string | Currency code |
| `inventory_quantity` | int | Tracked inventory qty |
| `product_data_hash` | string | MD5 change detection |
| `last_sync_payload` | jsonb | Last sent payload |
| `last_shopify_response` | jsonb | Last API response |
| `last_synced_at` | datetime | Last successful sync |
| `sync_direction_lock` | string | Loop prevention |
| `last_error_message` | text | Most recent error |
| `last_error_code` | string | Error code |
| `last_error_at` | datetime | Error timestamp |
| `consecutive_errors` | int | Error counter |
| `deleted_at` | datetime | Soft delete |

**Unique Constraints**: `(product_id, integration_credential_id)`, `(shopify_sku, integration_credential_id)`

**Indexes**: `(team_id, status)`, `shopify_product_id`, `(status, last_synced_at)`, `(status, consecutive_errors)`

### shopify_sync_logs

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint PK | |
| `integration_credential_id` | bigint FK | |
| `shopify_listing_id` | bigint FK | |
| `product_id` | uuid FK | |
| `team_id` | bigint FK | |
| `operation` | string | Operation type constant |
| `direction` | string | outbound or inbound |
| `shopify_sku` | string | |
| `shopify_product_id` | bigint | |
| `shopify_variant_id` | bigint | |
| `api_endpoint` | string | API URL called |
| `http_method` | string | GET/POST/PUT/DELETE |
| `request_payload` | jsonb | Sanitized request |
| `response_payload` | jsonb | Sanitized response |
| `http_status_code` | int | |
| `response_time_ms` | int | |
| `status` | string | success/error/warning/skipped/pending |
| `error_code` | string | |
| `error_message` | text | |
| `error_details` | jsonb | |
| `batch_id` | string | Grouped operations |
| `batch_sequence` | int | Order within batch |
| `retry_count` | int | |
| `next_retry_at` | datetime | |
| `is_retryable` | bool | |
| `initiated_by` | bigint FK | User who initiated |
| `started_at` | datetime | |
| `completed_at` | datetime | |

---

## Sync Log Operations

| Constant | Value | Description |
|----------|-------|-------------|
| `OP_CREATE_PRODUCT` | `create_product` | New product pushed to Shopify |
| `OP_UPDATE_PRODUCT` | `update_product` | Product data updated |
| `OP_DELETE_PRODUCT` | `delete_product` | Product permanently deleted |
| `OP_ARCHIVE_PRODUCT` | `archive_product` | Product archived |
| `OP_SET_INVENTORY` | `set_inventory` | Absolute inventory set |
| `OP_ADJUST_INVENTORY` | `adjust_inventory` | Inventory adjusted by delta |
| `OP_IMPORT_PRODUCTS` | `import_products` | Bulk import from store |
| `OP_FULFILL_ORDER` | `fulfill_order` | Fulfillment created |
| `OP_REGISTER_WEBHOOK` | `register_webhook` | Webhook registered |
| `OP_GET_PRODUCT` | `get_product` | Product data fetched |
| `OP_GET_LOCATIONS` | `get_locations` | Locations fetched |

---

## Routes

### Public (no auth, HMAC verified)

```
POST /shopify/webhooks -> ShopifyWebhookController@handle
```

### Auth Required

```
GET  /shopify/auth/install               -> ShopifyOAuthController@install
GET  /shopify/auth/callback              -> ShopifyOAuthController@callback
POST /shopify/auth/disconnect            -> ShopifyOAuthController@disconnect

GET    /marketplaces/shopify             -> ShopifyMarketplaceController@index
GET    /marketplaces/shopify/products    -> ShopifyMarketplaceController@products
POST   /marketplaces/shopify/products/{product}/sync -> ShopifyMarketplaceController@syncProduct
POST   /marketplaces/shopify/sync/bulk   -> ShopifyMarketplaceController@syncBulk
POST   /marketplaces/shopify/import/start -> ShopifyMarketplaceController@startImport
GET    /marketplaces/shopify/logs        -> ShopifyMarketplaceController@logs
```

---

## Configuration

### Environment Variables

```
SHOPIFY_API_KEY=          # App API key from Shopify Partner Dashboard
SHOPIFY_API_SECRET=       # App API secret (for HMAC and OAuth)
SHOPIFY_API_VERSION=2026-01  # Shopify REST API version
SHOPIFY_SCOPES=read_products,write_products,read_inventory,write_inventory,read_orders,read_locations,write_fulfillments
SHOPIFY_REDIRECT_URI=     # OAuth callback URL (default: /shopify/auth/callback)
```

### Config File (config/services.php)

```php
'shopify' => [
    'api_key' => env('SHOPIFY_API_KEY'),
    'api_secret' => env('SHOPIFY_API_SECRET'),
    'api_version' => env('SHOPIFY_API_VERSION', '2026-01'),
    'scopes' => env('SHOPIFY_SCOPES', 'read_products,write_products,read_inventory,write_inventory,read_orders,read_locations,write_fulfillments'),
    'redirect_uri' => env('SHOPIFY_REDIRECT_URI'),
],
```

---

## Security

| Concern | Implementation |
|---------|---------------|
| API Secret | Never logged; used only for HMAC and OAuth exchange |
| Webhook Auth | HMAC-SHA256 verified on every request |
| Token Storage | Laravel encryption (AES-256-GCM) via OAuthToken model |
| Payload Logging | Sync logs redact: access_token, refresh_token, client_secret, password, api_key, api_secret |
| OAuth State | Encrypted state parameter with timestamp (prevents CSRF) |
| Rate Limiting | Redis-backed token bucket tracking at 80% threshold |

---

## Product Matching / Linking

When users import eBay products first, then connect Shopify, the matching system links existing Shopify listings to imported Products instead of creating duplicates.

### Key Files

| Component | Path |
|-----------|------|
| Matching Service | `app/Services/Marketplace/ProductMatchingService.php` |
| Match Job | `app/Jobs/Shopify/MatchShopifyProducts.php` |
| Match Controller | `app/Http/Controllers/Shopify/ShopifyProductMatchController.php` |
| Match Model | `app/Models/Marketplace/MarketplaceProductMatch.php` |
| Review UI | `resources/js/Pages/Shopify/Match/Review.vue` |

### Matching Waterfall

1. **SKU match** (confidence 100) -- exact `Product.sku` vs Shopify `variant.sku`
2. **UPC/EAN/barcode** (confidence 95) -- Shopify `variant.barcode` vs `Product.upc`, `Product.ean`, `Product.barcode`, `ProductIdentifier` table
3. **Title fuzzy** (confidence variable) -- PostgreSQL `pg_trgm` `similarity()`, threshold > 0.4
4. **No match** -- flagged for user review

### Flow

1. User imports eBay products (existing flow)
2. Connects Shopify, goes to Settings > "Link Products"
3. `MatchShopifyProducts` job fetches Shopify products, runs waterfall matching
4. User reviews matches in Review page (high confidence / needs review / unmatched tabs)
5. On confirm: `ShopifyListing` created, `PushInventoryToMarketplaces` dispatched
6. Quantity reconciliation: `Product.available_quantity` (eBay) wins -- Shopify qty overwritten

### Routes

```
POST   /marketplaces/shopify/match/start          # Trigger matching job
GET    /marketplaces/shopify/match/review          # Review page (Inertia)
GET    /marketplaces/shopify/match/status/{bulkJob} # Job progress (JSON)
POST   /marketplaces/shopify/match/confirm         # Confirm selected matches
POST   /marketplaces/shopify/match/confirm-all     # Confirm all high-confidence
POST   /marketplaces/shopify/match/{match}/reject  # Reject a match
POST   /marketplaces/shopify/match/{match}/pair    # Manual pair
POST   /marketplaces/shopify/match/{match}/ignore  # Ignore item
GET    /marketplaces/shopify/match/search-products # Product search (JSON)
```

---

## Debugging Commands

### Check Shopify credential status

```bash
docker exec ecommerce_app php artisan tinker --execute="
\$cred = App\Models\Integration\IntegrationCredential::where('platform', 'shopify')->where('is_active', true)->first();
echo 'Credential: ' . \$cred?->id . PHP_EOL;
echo 'Shop: ' . (\$cred?->config['shop_domain'] ?? 'N/A') . PHP_EOL;
echo 'Ready: ' . (\$cred?->isShopifyReady() ? 'Yes' : 'No') . PHP_EOL;
echo 'Location: ' . (\$cred?->config['shopify_location_id'] ?? 'N/A') . PHP_EOL;
"
```

### Check listing status distribution

```bash
docker exec ecommerce_app php artisan tinker --execute="
App\Models\Shopify\ShopifyListing::selectRaw(\"status, COUNT(*) as count\")
  ->groupBy('status')->get()->each(fn(\$r) => print(\$r->status . ': ' . \$r->count . PHP_EOL));
"
```

### Check recent sync errors (24h)

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

### Check rate limiter status

```bash
docker exec ecommerce_app php artisan tinker --execute="
\$cred = App\Models\Integration\IntegrationCredential::where('platform', 'shopify')->where('is_active', true)->first();
\$status = (new App\Services\Shopify\ShopifyRateLimiter())->getStatus(\$cred->config['shop_domain']);
print_r(\$status);
"
```

### Check listings with excessive errors

```bash
docker exec ecommerce_app php artisan tinker --execute="
App\Models\Shopify\ShopifyListing::where('consecutive_errors', '>=', 5)
  ->select('id', 'shopify_product_id', 'status', 'consecutive_errors', 'last_error_message')
  ->get()->each(fn(\$l) => print(\$l->id . ' | errors: ' . \$l->consecutive_errors . ' | ' . \$l->last_error_message . PHP_EOL));
"
```

### Manually retry a failed listing

```bash
docker exec ecommerce_app php artisan tinker --execute="
\$listing = App\Models\Shopify\ShopifyListing::find(LISTING_ID);
\$listing->update(['status' => 'pending', 'consecutive_errors' => 0]);
dispatch(new App\Jobs\Shopify\SyncProductToShopify(\$listing->product, \$listing->integrationCredential));
echo 'Dispatched sync job for listing ' . \$listing->id;
"
```

### Check failed Shopify jobs

```bash
docker exec ecommerce_app php artisan tinker --execute="
DB::table('failed_jobs')
  ->where('payload', 'like', '%Shopify%')
  ->orderByDesc('failed_at')
  ->limit(5)
  ->get(['id', 'exception', 'failed_at'])
  ->each(fn(\$j) => print(\$j->id . ' | ' . \$j->failed_at . ' | ' . Str::limit(\$j->exception, 200) . PHP_EOL));
"
```

### Manually push inventory for a product

```bash
docker exec ecommerce_app php artisan tinker --execute="
\$product = App\Models\Catalog\Product::find('PRODUCT_UUID');
dispatch(new App\Jobs\Inventory\PushInventoryToMarketplaces(\$product->id));
echo 'Dispatched inventory push for ' . \$product->name;
"
```
