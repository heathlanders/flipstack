# Marketplace Integration Architecture

How to add and manage any marketplace integration. Covers the current concrete service pattern, the target adapter interface, and a step-by-step guide for adding new platforms.

## Table of Contents

1. [Product as Source of Truth](#product-as-source-of-truth)
2. [Current Architecture](#current-architecture)
3. [Marketplace Adapter Pattern (Target)](#marketplace-adapter-pattern-target)
4. [Sync Direction](#sync-direction)
5. [Event-Driven Updates](#event-driven-updates)
6. [Adding a New Marketplace](#adding-a-new-marketplace)
7. [Rate Limiting Strategy](#rate-limiting-strategy)
8. [Error Handling and Retry](#error-handling-and-retry)
9. [Existing Implementations](#existing-implementations)

---

## Product as Source of Truth

The `products` table owns all canonical product data. Marketplace listings are downstream records that derive their data from the product.

### Key Product Fields

| Field | Purpose | Used By |
|-------|---------|---------|
| `title` | Product name | All marketplace listing titles |
| `description` | Full description | All marketplace listing descriptions |
| `selling_price` | Authoritative sell price | Pushed to all marketplace listing prices |
| `available_quantity` | Units available to sell | Pushed to all marketplace inventory levels |
| `condition` | Item condition | Mapped to marketplace-specific condition codes |
| `brand` | Brand name | Marketplace item specifics / attributes |
| `images` (via ProductImage) | Product photos | Pushed to marketplace image galleries |
| `weight`, `length`, `width`, `height` | Shipping dimensions | Used for shipping calculations |
| `upc`, `ean` | Product identifiers | Marketplace product identification |

### Marketplace Listing Tables

Each marketplace has a dedicated listing table that stores:
- Foreign key to `products.id`
- Foreign key to `integration_credentials.id` (which marketplace account)
- Marketplace-specific IDs (eBay listing ID, Shopify product ID, etc.)
- Marketplace-specific status
- Last sync timestamps
- Platform-specific metadata (eBay offer ID, Shopify variant ID, etc.)

### Product Model Relationships

```php
// app/Models/Catalog/Product.php

public function ebayListings(): HasMany       // All eBay listings for this product
public function shopifyListings(): HasMany    // All Shopify listings for this product

public function ebayListingFor(int $credentialId): ?EbayListing
public function shopifyListingFor(int $credentialId): ?ShopifyListing
```

---

## Current Architecture

The platform uses **concrete, marketplace-specific service classes** orchestrated by lightweight coordinator services.

### Service Structure

```
app/Services/
  +-- EbayInventoryService.php        # eBay Inventory API (items, offers, quantity)
  +-- EbaySellService.php             # eBay listing creation (high-level orchestrator)
  +-- EbayOAuthService.php            # eBay OAuth token management
  +-- EbayCategoryService.php         # eBay category lookup and matching
  +-- EbayBrowseService.php           # eBay Browse API (product research)
  +-- MarketplaceInventorySync.php    # Cross-marketplace quantity push
  +-- Shopify/
  |     +-- ShopifyProductService.php   # Shopify product CRUD
  |     +-- ShopifyInventoryService.php # Shopify inventory levels
  +-- Fulfillment/
        +-- OrderSyncService.php        # Order import + inventory reservation
```

### Job Structure

```
app/Jobs/
  +-- CreateEbayListingJob.php          # Create new eBay listing from product
  +-- Ebay/
  |     +-- UpdateEbayInventory.php       # Update price/quantity on eBay
  |     +-- ProcessEbayScheduledTasks.php # Scheduled sync (polling)
  |     +-- ProcessEbayNotification.php   # ECN webhook handler
  |     +-- ImportEbayInventory.php       # Pull existing eBay store listings
  +-- Shopify/
  |     +-- ProcessShopifyWebhook.php     # Shopify webhook handler (orders)
  +-- Inventory/
        +-- PushInventoryToMarketplaces.php # Cross-marketplace quantity push
```

### How MarketplaceInventorySync Works Today

`MarketplaceInventorySync::pushQuantityToAll()` contains marketplace-specific logic inline:

```
pushQuantityToAll(Product $product)
  |
  +---> For each eBay listing (status = published):
  |       Dispatch UpdateEbayInventory job (updateType: 'quantity')
  |
  +---> For each Shopify listing (status = active):
          Resolve location ID (from credential config or API)
          Call ShopifyInventoryService::setInventoryLevel()
```

This works but requires modifying the orchestrator each time a new marketplace is added.

---

## Marketplace Adapter Pattern (Target)

The target architecture defines a standard interface that each marketplace must implement. This allows the orchestrator to be marketplace-agnostic.

### Proposed Interface

```php
interface MarketplaceAdapter
{
    /**
     * Push a product to the marketplace as a new listing.
     */
    public function createListing(Product $product): MarketplaceListingResult;

    /**
     * Sync the current selling_price to the marketplace listing.
     */
    public function updatePrice(Product $product, Model $listing): bool;

    /**
     * Sync the current available_quantity to the marketplace listing.
     */
    public function updateQuantity(Product $product, Model $listing): bool;

    /**
     * Full listing update: title, description, images, condition, etc.
     */
    public function updateListing(Product $product, Model $listing): bool;

    /**
     * Remove listing from the marketplace.
     */
    public function endListing(Model $listing): bool;

    /**
     * Pull existing listings from the marketplace store into the app.
     */
    public function importListings(IntegrationCredential $credential): void;

    /**
     * Pull recent orders and reserve inventory.
     */
    public function importOrders(IntegrationCredential $credential): void;

    /**
     * Check if this adapter is configured and ready to use.
     */
    public function isAvailable(): bool;
}
```

### How the Existing Services Map

| Interface Method | eBay Implementation | Shopify Implementation |
|-----------------|---------------------|----------------------|
| `createListing()` | `EbaySellService::createDraftListing()` + publish flow | `ShopifyProductService::createProduct()` |
| `updatePrice()` | `UpdateEbayInventory` job with `updateType='price'` | `ShopifyProductService::updateProduct()` |
| `updateQuantity()` | `UpdateEbayInventory` job with `updateType='quantity'` | `ShopifyInventoryService::setInventoryLevel()` |
| `updateListing()` | `EbayInventoryService::createOrUpdateInventoryItem()` + `updateOffer()` | `ShopifyProductService::updateProduct()` |
| `endListing()` | `EbayInventoryService::endListing()` | `ShopifyProductService::archiveProduct()` |
| `importListings()` | `ImportEbayInventory` job | Shopify product import |
| `importOrders()` | `OrderSyncService` (eBay) | `ProcessShopifyWebhook` |

### Registration

Adapters would be registered in a config file or service provider:

```php
// config/marketplaces.php
return [
    'ebay' => [
        'adapter' => \App\Services\Ebay\EbayMarketplaceAdapter::class,
        'credential_type' => 'ebay',
        'listing_model' => \App\Models\Ebay\EbayListing::class,
        'supports_webhooks' => true,
    ],
    'shopify' => [
        'adapter' => \App\Services\Shopify\ShopifyMarketplaceAdapter::class,
        'credential_type' => 'shopify',
        'listing_model' => \App\Models\Shopify\ShopifyListing::class,
        'supports_webhooks' => true,
    ],
];
```

---

## Sync Direction

### Outbound (Product -> Marketplace) - Primary

All data originates on the Product and pushes outward:

```
Product.selling_price changes --> PushPriceToMarketplaces --> per-marketplace update jobs
Product.available_quantity changes --> PushInventoryToMarketplaces --> per-marketplace update jobs
Product.title/description changes --> PushListingToMarketplaces --> per-marketplace update jobs
```

### Inbound (Marketplace -> Product) - Limited

Inbound sync is restricted to two scenarios:

1. **Order import**: An order arrives from a marketplace. The system reserves inventory on the Product, then pushes the updated quantity to all other marketplaces.

2. **Initial store import**: When connecting an existing marketplace store, existing listings are pulled in and matched/created as Product records. After import, the Product becomes the source of truth going forward.

Marketplace-side edits (e.g., changing a title on eBay directly) are **not** synced back. The Product is authoritative. Users should make changes in the app and let the system push them out.

### Exception: Best Offer / Price Negotiation

When a marketplace supports buyer price negotiation (e.g., eBay Best Offer), an accepted offer should:
1. Create the order with the negotiated price
2. Reserve inventory on the Product
3. Push updated quantity to all marketplaces
4. The product's `selling_price` is NOT changed (the accepted offer is order-specific)

---

## Event-Driven Updates

### Current Trigger: Manual or Scheduled

Today, marketplace updates are triggered by:
- **Order webhooks** (eBay ECN, Shopify webhooks) trigger inventory reservation + cross-marketplace push
- **Scheduled tasks** (`ProcessEbayScheduledTasks`) poll and push periodically
- **User action** (manual sync buttons in UI)

### Target: Model Observer Pattern

When Product fields change, an observer detects which fields changed and dispatches the appropriate sync jobs:

```
Product updated
  |
  v
ProductObserver::updated()
  |
  +-- selling_price changed? --> Dispatch PushPriceToMarketplaces
  +-- available_quantity changed? --> Dispatch PushInventoryToMarketplaces
  +-- title/description/images changed? --> Dispatch PushListingToMarketplaces
  +-- No sync-relevant fields changed? --> No action
```

This eliminates the need for callers to manually dispatch sync jobs after updating a product.

---

## Cross-Marketplace Product Matching

When a user imports from eBay (source of truth) and then connects Shopify, the matching system links existing Shopify listings to Products rather than creating duplicates.

### Key Files

| File | Purpose |
|------|---------|
| `app/Services/Marketplace/ProductMatchingService.php` | Core matching engine (waterfall algorithm) |
| `app/Jobs/Shopify/MatchShopifyProducts.php` | Queue job: fetches Shopify products, runs matching |
| `app/Http/Controllers/Shopify/ShopifyProductMatchController.php` | 9 endpoints: start, review, status, confirm, reject, pair, ignore, search |
| `app/Models/Marketplace/MarketplaceProductMatch.php` | Staging table model with match type, confidence, status |
| `resources/js/Pages/Shopify/Match/Review.vue` | Review UI with tabs, bulk actions, product search modal |

### Matching Waterfall

```
For each Shopify product (first variant):
  1. Already linked? (ShopifyListing exists) -> skip
  2. SKU match (confidence 100) -> Product.sku = variant.sku
  3. UPC/EAN/barcode (confidence 95) -> variant.barcode vs Product.upc/ean/barcode + ProductIdentifier
  4. Title fuzzy (pg_trgm similarity > 0.4) -> confidence = similarity * 100
  5. No match -> flagged for user review
```

### Quantity Reconciliation

`Product.available_quantity` (eBay source of truth) always wins. After linking, `PushInventoryToMarketplaces` pushes Product qty to Shopify. Discrepancies shown in review UI.

### De-duplication Guard

If two Shopify products would match the same Product, both are cleared and flagged for manual review to prevent 2:1 linking.

---

## Adding a New Marketplace

Step-by-step guide for integrating a new marketplace platform (e.g., Amazon, Mercari, Poshmark).

### Step 1: Database

Create a migration for the marketplace listing table:

```bash
docker exec ecommerce_app php artisan make:migration create_{marketplace}_listings_table
```

Required columns:
- `id` (bigIncrements)
- `organization_id` (foreign key)
- `product_id` (UUID, foreign key to products)
- `integration_credential_id` (foreign key)
- `{marketplace}_product_id` (the remote platform's ID)
- `status` (string: draft, active, ended, error, etc.)
- `last_synced_at` (timestamp)
- `marketplace_url` (nullable string)
- `metadata` (JSON, for platform-specific data)
- Standard timestamps

### Step 2: Model

Create the Eloquent model at `app/Models/{Marketplace}/{Marketplace}Listing.php`:
- Define relationships: `belongsTo Product`, `belongsTo IntegrationCredential`, `belongsTo Organization`
- Add scopes: `active()`, `forCredential($id)`

Add the relationship to `Product.php`:
```php
public function {marketplace}Listings(): HasMany
```

### Step 3: Service Classes

Create service classes at `app/Services/{Marketplace}/`:

| Service | Purpose |
|---------|---------|
| `{Marketplace}ProductService` | Product CRUD against the marketplace API |
| `{Marketplace}InventoryService` | Inventory/quantity management (if separate API) |
| `{Marketplace}OAuthService` | OAuth flow (if applicable) |

Follow the **Service Constructor Pattern**: wrap credential loading in try-catch to handle null encrypted fields gracefully. Provide an `isAvailable()` method.

### Step 4: Job Classes

Create jobs at `app/Jobs/{Marketplace}/`:

| Job | Purpose |
|-----|---------|
| `Update{Marketplace}Inventory` | Push price/quantity update to a single listing |
| `Import{Marketplace}Listings` | Pull existing store inventory |
| `Process{Marketplace}Webhook` | Handle inbound webhooks (if supported) |

### Step 5: Update Orchestrators

Add the new marketplace to:
- `MarketplaceInventorySync::pushQuantityToAll()` - add a block for the new marketplace's listings
- `MarketplacePriceSync::pushPriceToAll()` (when it exists) - same pattern

### Step 6: Controller and Routes

- Create `app/Http/Controllers/Marketplaces/{Marketplace}MarketplaceController.php`
- Add routes in `routes/web.php` or a dedicated `routes/{marketplace}.php`
- Endpoints needed: dashboard, listings index, settings, import, sync actions

### Step 7: Frontend

- Create Vue pages at `resources/js/Pages/Marketplaces/{Marketplace}/` or `resources/js/Pages/{Marketplace}/`
- Minimum pages: Index (dashboard), Listings, Settings, Import
- Reuse existing marketplace UI components where possible

### Step 8: OAuth Setup

- Add the marketplace to `IntegrationCredential` types
- Create OAuth callback route and controller method
- Store credentials following the existing pattern (encrypted fields via accessors)

### Step 9: Webhooks (if supported)

- Create webhook endpoint (outside auth middleware, like eBay ECN)
- Implement signature verification per the marketplace's spec
- Queue inbound events for async processing

---

## Rate Limiting Strategy

### Current Implementation

eBay has a dedicated rate limiter (`EbayRateLimiter`) using Redis:
- Tracks API calls per credential per time window
- Respects eBay's published rate limits
- Backs off automatically when limits are approached

### Standard Pattern for New Marketplaces

Each marketplace should implement rate limiting using Redis with the following keys:

```
rate_limit:{marketplace}:{credential_id}:calls    # Call count in current window
rate_limit:{marketplace}:{credential_id}:window   # Window expiry timestamp
```

Configuration in `config/marketplaces.php`:
```php
'rate_limits' => [
    'calls_per_window' => 5000,
    'window_seconds' => 86400,  // 24 hours
    'burst_limit' => 25,        // Max calls per second
],
```

---

## Error Handling and Retry

### Standard Retry Pattern

All marketplace jobs should follow the `UpdateEbayInventory` pattern:

1. **Retryable errors** (rate limit, timeout, 5xx): Retry with exponential backoff
2. **Validation errors** (bad data, missing fields): Mark listing as `needs_review`, no retry
3. **Auth errors** (expired token): Attempt token refresh, then retry once
4. **Permanent failures** (listing removed, account suspended): Mark listing as `error`, no retry

### Consecutive Error Tracking

Each marketplace listing tracks consecutive errors:
- `consecutive_errors` integer field
- Incremented on failure, reset to 0 on success
- After N consecutive errors (configurable, default 5), the listing is flagged for manual review
- Prevents infinite retry loops on permanently broken listings

### Circuit Breaker (Per Credential)

If a marketplace credential accumulates too many errors in a short period:
1. Mark the credential as `degraded`
2. Reduce sync frequency (skip non-critical updates)
3. Alert the user via notification
4. Resume normal operation after a cooldown period with a successful API call

---

## Existing Implementations

### eBay (Full Integration)

Complete implementation covering listing creation, inventory sync, price sync, order import, store import, and scheduled sync.

**Key files:**
- Services: `app/Services/EbayInventoryService.php`, `app/Services/EbaySellService.php`
- Jobs: `app/Jobs/Ebay/UpdateEbayInventory.php`, `app/Jobs/Ebay/ProcessEbayScheduledTasks.php`
- Model: `app/Models/Ebay/EbayListing.php`
- Controller: `app/Http/Controllers/Marketplaces/EbayMarketplaceController.php`
- Frontend: `resources/js/Pages/Marketplaces/Ebay/`

**Docs:**
- [EBAY_LISTING_SERVICE.md](EBAY_LISTING_SERVICE.md) - Listing creation flow
- [EBAY_SYNC_AND_UPDATE_SYSTEM.md](EBAY_SYNC_AND_UPDATE_SYSTEM.md) - Sync, import, scheduled tasks
- [ebay-oauth-workflow.md](ebay-oauth-workflow.md) - OAuth flow

### Shopify (Full Integration)

Complete implementation covering product CRUD, inventory sync, order import via webhooks.

**Key files:**
- Services: `app/Services/Shopify/ShopifyProductService.php`, `app/Services/Shopify/ShopifyInventoryService.php`
- Jobs: `app/Jobs/Shopify/ProcessShopifyWebhook.php`
- Model: `app/Models/Shopify/ShopifyListing.php`
- Controller: `app/Http/Controllers/Shopify/ShopifyMarketplaceController.php`
- Frontend: `resources/js/Pages/Shopify/` (Products, SyncLogs, Settings)

### Generic Listing Tables (Migrated, Unused)

Two tables exist in the database but have no Eloquent models or application code:
- `product_listings` - Generic multi-platform listing record (`database/migrations/2026_01_01_052035_create_product_listings_table.php`)
- `listing_templates` - Reusable listing templates (`database/migrations/2026_01_01_052037_create_listing_templates_table.php`)

These were created as part of a planned abstraction layer. They can be activated by creating models and controllers (see Roadmap Phase 4 in [PRODUCT_MANAGEMENT_SUITE.md](PRODUCT_MANAGEMENT_SUITE.md)).
