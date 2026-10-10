---
name: marketplace-sync
description: Cross-marketplace inventory/price sync, adapter pattern, and step-by-step guide for adding new marketplace integrations
user-invocable: true
---

# Marketplace Sync Reference

## Key Files

| Component | Path |
|-----------|------|
| Inventory Sync | `app/Services/MarketplaceInventorySync.php` |
| Push Job | `app/Jobs/Inventory/PushInventoryToMarketplaces.php` |
| eBay Update | `app/Jobs/Ebay/UpdateEbayInventory.php` |
| eBay Scheduled | `app/Jobs/Ebay/ProcessEbayScheduledTasks.php` |
| eBay Notification | `app/Jobs/Ebay/ProcessEbayNotification.php` |
| ECN Controller | `app/Http/Controllers/Ebay/EbayNotificationController.php` |
| Order Sync | `app/Services/Fulfillment/OrderSyncService.php` |
| Shopify Webhook | `app/Jobs/Shopify/ProcessShopifyWebhook.php` |
| Shopify Product | `app/Services/Shopify/ShopifyProductService.php` |
| Shopify Inventory | `app/Services/Shopify/ShopifyInventoryService.php` |
| eBay Inventory Svc | `app/Services/EbayInventoryService.php` |
| Product Model | `app/Models/Catalog/Product.php` |
| EbayListing Model | `app/Models/Ebay/EbayListing.php` |
| ShopifyListing Model | `app/Models/Shopify/ShopifyListing.php` |
| Shopify Adapter | `app/Services/Shopify/ShopifyMarketplaceAdapter.php` |
| Architecture Doc | `docs/MARKETPLACE_INTEGRATION_ARCHITECTURE.md` |
| Inventory Sync Doc | `docs/INVENTORY_SYNC_SYSTEM.md` |
| Shopify Full Doc | `docs/SHOPIFY_INTEGRATION.md` |
| Shopify API Roadmap | `docs/SHOPIFY_API_REFERENCE.md` |
| Matching Service | `app/Services/Marketplace/ProductMatchingService.php` |
| Match Model | `app/Models/Marketplace/MarketplaceProductMatch.php` |
| Match Job | `app/Jobs/Shopify/MatchShopifyProducts.php` |
| Match Controller | `app/Http/Controllers/Shopify/ShopifyProductMatchController.php` |

## Inventory Field Definitions

| Field | Location | Meaning |
|-------|---------|---------|
| `products.available_quantity` | Product | **Source of truth** for cross-marketplace sell qty. Decremented immediately on order import. **Bound by the product hub UI.** |
| `products.stock_quantity` | Product | Legacy physical-on-hand (pre-Jan 2026). Decremented on ship/pick; mirrored on receiving and manual adjustments. Do not bind in UI. |
| `products.quantity` | Product | Legacy total-physical (kept for warehouse reports) |
| `products.reserved_quantity` | Product | Reserved for unshipped orders (informational) |
| `inventory_levels.quantity_available` | InventoryLevel | Warehouse-level available at a specific location |
| `inventory_levels.quantity_reserved` | InventoryLevel | Warehouse-level reserved (after order assigned to location) |

**Why `available_quantity`**: It's the denormalized signal that both `UpdateEbayInventory` and `ShopifyInventoryService` read. `InventoryLevel.quantity_reserved` only applies after order assignment to a warehouse (may be hours/days later).

**Invariant**: every writer of `available_quantity` MUST dispatch `PushInventoryToMarketplaces::dispatch($productId)` after the change. This includes manual adjustments (Adjust Stock modal, Movements form, BulkStockAdjustment job) — all of which mirror their delta to `stock_quantity` AND `available_quantity` (May 2026). If you add a new writer that skips the push, marketplaces will silently drift. See `inventory-management` skill "Writers reference" table.

## Reservation Lifecycle (6 Steps)

> **Cross-reference**: Steps 5-6 are handled by the fulfillment system. See the `fulfillment` skill for the full pick/pack/ship workflow, order state machine, and shipping provider details.

```
1. Order imported (eBay ECN/polling, Shopify webhook)
2. matchOrderItemsToProducts() links items to Products
3. reserveInventoryForOrder() -> Product::decrementAvailableQuantity() (DB lock)
4. PushInventoryToMarketplaces job dispatched per product
   -> MarketplaceInventorySync::pushQuantityToAll()
   -> Skips adapters where credential.auto_sync_enabled=false
   -> UpdateEbayInventory per published eBay listing (if auto_sync on)
      -> Uses Offer API for published listings (has offer_id), Trading API for imported listings
   -> ShopifyInventoryService::setInventoryLevel() per active Shopify listing (if auto_sync on)
      -> Also zeros out all non-primary Shopify locations
5. Order assigned to warehouse (fulfillment) -> InventoryLevel::reserve()
   (see fulfillment skill: FulfillmentService::assignOrder -> auto/manual assignment)
6. Order shipped -> InventoryLevel.quantity_on_hand decremented
   (see fulfillment skill: FulfillmentService::shipOrder -> TrackingService::uploadTrackingToMarketplace)
```

## Product as Source of Truth

| Field | Used By |
|-------|---------|
| `title`, `description` | All marketplace listing titles/descriptions |
| `selling_price` | Pushed to all marketplace listing prices |
| `available_quantity` | Pushed to all marketplace inventory levels |
| `condition` | Mapped to marketplace-specific condition codes |
| `brand`, `images`, `weight/dimensions`, `upc/ean` | Marketplace specifics/attributes |

Product relationships: `ebayListings()`, `shopifyListings()`, `ebayListingFor($credentialId)`, `shopifyListingFor($credentialId)`

## Product Data Sync Pipeline

`ProductObserver` (registered in `AppServiceProvider`) auto-dispatches sync jobs when product fields change:

### Watched Fields -> Jobs
| Field(s) Changed | Job Dispatched |
|---|---|
| `name`, `description`, `gallery_images`, `condition`, `condition_description`, `weight`, `length`, `width`, `height` | `PushListingToMarketplaces` |
| `selling_price` (only if listing fields above did NOT also change) | `PushPriceToMarketplaces` |
| `status` -> `inactive` / `archived` / `deleted` | `EndMarketplaceListings` |

Smart deduplication: if listing fields and price change together, only `PushListingToMarketplaces` dispatches (it includes price in the full `updateListing()` call). Both push jobs implement `ShouldBeUnique` with 60s TTL per product.

### Sync Services
| Service | Method | Checks Sync Rules | `auto_sync_enabled` | Change Detection |
|---|---|---|---|---|
| `MarketplaceListingSync` | `pushListingToAll()` | Yes | Yes | Hash-based (`hasProductChanged()` -- excludes `stock_quantity` to avoid phantom updates) |
| `MarketplacePriceSync` | `pushPriceToAll()` | Yes | Yes | No (always pushes) |
| `MarketplaceInventorySync` | `pushQuantityToAll()` | Yes | Yes | No (always pushes) |
| `EndMarketplaceListings` | `handle()` | No | Yes | N/A |
| `RetryFailedEbaySync` | `shouldRetry()` | N/A | Yes | N/A |

All three iterate adapters from `MarketplaceRegistry::getAdaptersForOrganization()` which dynamically loads from `config/marketplaces.php`.

### Key Pipeline Files
| File | Purpose |
|---|---|
| `app/Observers/Inventory/ProductObserver.php` | Central dispatch on product save |
| `app/Jobs/Inventory/PushListingToMarketplaces.php` | Queued full listing sync |
| `app/Jobs/Inventory/PushPriceToMarketplaces.php` | Queued price-only sync |
| `app/Jobs/Inventory/EndMarketplaceListings.php` | End all listings on deactivation |
| `app/Services/MarketplaceListingSync.php` | Orchestrates listing push across adapters |
| `app/Services/MarketplacePriceSync.php` | Orchestrates price push across adapters |
| `app/Services/MarketplaceInventorySync.php` | Orchestrates quantity push across adapters |

## Sync Direction

### Outbound (Product -> Marketplace) - Primary
All data originates on Product and pushes out. Marketplace-side edits (e.g., title edit on eBay) are NOT synced back -- next scheduled sync overwrites.

### Inbound (Marketplace -> Product) - Limited
1. **Order import**: Reserve inventory, push updated qty to all other marketplaces
2. **Initial store import**: Pull existing listings, create Product records

**Exception**: Best Offer/price negotiation creates order at negotiated price, but `selling_price` unchanged.

## Conflict Resolution
Product is always authoritative. Marketplace changes overwritten on next sync push. Orders are the only legitimate inbound state change.

## eBay ECN Webhook Setup

Routes (outside auth middleware):
```
GET  /api/webhooks/ebay -> EbayNotificationController@challenge
POST /api/webhooks/ebay -> EbayNotificationController@handle
```

**Challenge verification**: SHA256(challenge_code + verificationToken + endpointUrl)

**Signature verification**: `X-EBAY-SIGNATURE` header, HMAC-SHA256(rawBody, verificationToken). Invalid signatures return 200 (eBay requires 200 to stop retries).

**Token storage**: `IntegrationCredential.config.ebay_notification_token` (accessor: `ebayNotificationToken`)

## MarketplaceAdapter Interface (Target)

```php
interface MarketplaceAdapter {
    public function createListing(Product $product): MarketplaceListingResult;
    public function updatePrice(Product $product, Model $listing): bool;
    public function updateQuantity(Product $product, Model $listing): bool;
    public function updateListing(Product $product, Model $listing): bool;
    public function endListing(Model $listing): bool;
    public function importListings(IntegrationCredential $credential): void;
    public function importOrders(IntegrationCredential $credential): void;
    public function isAvailable(): bool;
}
```

Registration target: `config/marketplaces.php` with adapter class, credential_type, listing_model, supports_webhooks.

## Cross-Marketplace Product Matching

When a user imports from eBay first then connects Shopify, the matching system links existing Shopify listings to Products without creating duplicates.

**Service**: `ProductMatchingService::matchShopifyProducts()` runs a waterfall:
1. SKU exact match (confidence 100)
2. UPC/EAN/barcode match (confidence 95) -- checks `Product.upc`, `Product.ean`, `Product.barcode`, `ProductIdentifier` table
3. Title fuzzy match via pg_trgm `similarity()` (confidence variable, threshold > 0.4)

**Staging**: Results stored in `marketplace_product_matches` table. User reviews in UI before confirming.
**On confirm**: `ShopifyListing` created, `PushInventoryToMarketplaces` dispatched. `Product.available_quantity` (eBay) wins.
**De-duplication**: If two Shopify products match the same Product, both flagged for manual review.

## Adding a New Marketplace (9 Steps)

### Step 1: Migration
```bash
docker exec ecommerce_app php artisan make:migration create_{marketplace}_listings_table
```
Required columns: id, organization_id, product_id (UUID FK), integration_credential_id, {marketplace}_product_id, status, last_synced_at, marketplace_url, metadata (JSON), timestamps.

### Step 2: Model
`app/Models/{Marketplace}/{Marketplace}Listing.php` - relationships (belongsTo Product, IntegrationCredential, Organization), scopes (active, forCredential). Follow `EbayListing.php` patterns.

### Step 3: Product Relationship
Add `{marketplace}Listings(): HasMany` to Product model.

### Step 4: Services
`app/Services/{Marketplace}/` - ProductService, InventoryService, OAuthService. Follow Service Constructor Pattern (try-catch credential loading, `isAvailable()` check).

### Step 5: Jobs
`app/Jobs/{Marketplace}/` - Update{Marketplace}Inventory, Import{Marketplace}Listings, Process{Marketplace}Webhook. Follow `UpdateEbayInventory` pattern for retries.

### Step 6: Update MarketplaceInventorySync
Add `pushTo{Marketplace}()` in `MarketplaceInventorySync::pushQuantityToAll()`.

### Step 7: Controller + Routes
`app/Http/Controllers/Marketplaces/{Marketplace}MarketplaceController.php` with dashboard, listings, settings, import, sync endpoints.

### Step 8: Frontend
`resources/js/Pages/Marketplaces/{Marketplace}/` or `resources/js/Pages/{Marketplace}/` - Index, Listings, Settings, Import pages.

### Step 9: Webhooks (if supported)
Webhook endpoint outside auth middleware, signature verification per marketplace spec, queue events for async processing.

## Rate Limiting Pattern

Standard pattern per marketplace using Redis:
```
rate_limit:{marketplace}:{credential_id}:calls    # call count
rate_limit:{marketplace}:{credential_id}:window   # window expiry
```

eBay: 5/sec, 5000/day via EbayRateLimiter. Exponential backoff 500ms -> 30s max.

## Error Handling Pattern

1. **Retryable** (rate limit, timeout, 5xx): Retry with exponential backoff
2. **Validation** (bad data): Mark needs_review, no retry
3. **Auth** (expired token): Refresh token, retry once
4. **Permanent** (listing removed, account suspended): Mark error, no retry

Consecutive error tracking: `consecutive_errors` field on listing, incremented on fail, reset on success. >=5 errors -> flagged for manual review.

## Fee Tracking

### Key Files
| File | Purpose |
|------|---------|
| `app/Services/Marketplace/FeeTrackingService.php` | Extracts and records marketplace fees from order data |

### Fee Types by Marketplace
| Marketplace | Fee Types |
|-------------|-----------|
| eBay | `final_value`, `insertion`, `promoted`, `payment_processing` |
| Shopify | `transaction`, `payment_processing` |
| Amazon | `referral`, `fba`, `closing`, `shipping` |

### Sync Mechanism
Fees are extracted during order import. `FeeTrackingService` parses the raw marketplace order data for fee line items and stores them against the order record. These fees feed into the P&L analytics for per-order and per-product profitability calculations.

---

## Cross-Listing

### Key Files
| File | Purpose |
|------|---------|
| `app/Services/Marketplace/CrossListingService.php` | Orchestrates listing creation across marketplaces from a single product |
| `app/Services/Marketplace/ListingDataTransformer.php` | Transforms product data into marketplace-specific formats |

### eBay-to-Shopify Field Mapping
`ListingDataTransformer` handles field mapping between marketplace formats. For example, eBay item specifics map to Shopify metafields, eBay condition codes map to Shopify tags, and eBay category paths map to Shopify `product_type` and collections.

### Price Adjustment Rules
Cross-listing supports per-marketplace price adjustments: fixed offset, percentage markup/markdown, or marketplace-specific pricing. The adjustment is applied by `CrossListingService` before dispatching the listing creation job for each target marketplace.

---

## Scheduled Listings

### Key Files
| File | Purpose |
|------|---------|
| `app/Models/Marketplace/ScheduledListing.php` | Scheduled listing with target marketplace, publish time, and snapshot |
| `app/Services/Marketplace/ListingSchedulerService.php` | Manages scheduling, snapshot creation, and dispatch |
| `app/Jobs/Marketplace/ProcessScheduledListings.php` | Cron-driven job that checks for due listings and publishes them |

### Scheduling Flow
1. **Create**: User schedules a listing for a future date/time on a target marketplace
2. **Snapshot**: `ListingSchedulerService` captures the product data at scheduling time, so later product edits do not affect the scheduled listing
3. **Cron Check**: `ProcessScheduledListings` job runs on a schedule, queries for listings where `scheduled_at <= now()` and status is `pending`
4. **Dispatch**: Each due listing is dispatched to the appropriate marketplace listing creation job (e.g., `CreateEbayListingJob`, `SyncProductToShopify`)

---

## Debugging Commands

```bash
# Check product inventory state
docker exec ecommerce_app php artisan tinker --execute="App\Models\Catalog\Product::find('UUID')->only(['available_quantity','quantity','reserved_quantity']);"

# Check failed marketplace pushes
docker exec ecommerce_app php artisan tinker --execute="DB::table('failed_jobs')->where('payload','like','%PushInventoryToMarketplaces%')->orderByDesc('failed_at')->take(5)->get(['id','failed_at','exception']);"

# Manually trigger inventory push
docker exec ecommerce_app php artisan tinker --execute="App\Jobs\Inventory\PushInventoryToMarketplaces::dispatch('PRODUCT-UUID');"

# Check eBay listing sync status
docker exec ecommerce_app php artisan tinker --execute="App\Models\Ebay\EbayListing::where('product_id','UUID')->first()?->only(['status','listed_quantity','listed_price','last_synced_at']);"

# Check Shopify listing status
docker exec ecommerce_app php artisan tinker --execute="App\Models\Shopify\ShopifyListing::where('product_id','UUID')->first()?->only(['status','shopify_product_id','last_synced_at']);"

# Trigger eBay stale sync
docker exec ecommerce_app php artisan tinker --execute="App\Jobs\Ebay\ProcessEbayScheduledTasks::dispatch('sync_quantities');"
```

## Notifications

Marketplace sync events that trigger user notifications:

| Event | Notification Class | Priority |
|-------|--------------------|----------|
| Shopify product sync failed | `Shopify\ShopifySyncNotification` | high |
| Shopify inventory sync failed | `Shopify\ShopifySyncNotification` | high |
| eBay listing created/failed | `Ebay\EbayListingCompleted` | medium/high |
| Inventory push to marketplaces failed | `Inventory\MarketplaceInventorySyncFailedNotification` | high |
| OAuth token refresh failed | `System\OAuthTokenFailedNotification` | critical |
| Order sync failed | `Fulfillment\OrderSyncFailedNotification` | high |

All failure notifications go to org admins/owners. See `docs/NOTIFICATION_SYSTEM.md` for the full architecture and how to add new notifications.

## Audit Logging

| Action | Category | Severity | Metadata keys |
|--------|----------|----------|---------------|
| `inventory.synced` | marketplace | debug | marketplace, listings_updated, source: job |
| `listing.sync.ran` | marketplace | debug | marketplace, listings_count, source: cron |
| `listing.sync.failed` | marketplace | error | marketplace, error_code, http_status |
| `marketplace.product_matched` | marketplace | info | match_strategy (sku|upc|title), confidence |

Trait: models in this feature use `LogsAudit` (`app/Traits/LogsAudit.php`) for auto CRUD diff. Domain lifecycle events emitted from `app/Services/MarketplaceInventorySync`, `app/Services/Marketplace/ProductMatchingService`; jobs wrap in `withAuditActor(null, ..., ['source' => 'cron|webhook|job'])` via `UserActivityLog::log(action, desc, $user, $metadata, $severity, $category)`.

Per-marketplace sync logs (`ebay_sync_logs`, `shopify_sync_logs`) remain authoritative for HTTP-level debugging; audit captures lifecycle for Hub timelines.

Spec: `docs/AUDIT_SYSTEM.md`. Enforced rule: `.claude/rules/audit-logging.md`. Tests: `use Tests\Support\AssertsAudits;` then `$this->assertAudited('action', $user, [...])`.
