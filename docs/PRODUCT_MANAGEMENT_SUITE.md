# Product Management Suite

Master architecture document for the product management system. This ties together intake processing, marketplace integration, inventory sync, and the frontend experience into a cohesive view.

## Table of Contents

1. [System Overview](#system-overview)
2. [Data Flow](#data-flow)
3. [Product Lifecycle](#product-lifecycle)
4. [Marketplace Integration Model](#marketplace-integration-model)
5. [Current State vs Target State](#current-state-vs-target-state)
6. [Implementation Roadmap](#implementation-roadmap)
7. [Cross-References](#cross-references)

---

## System Overview

The platform follows a **product-centric architecture** where the `products` table is the single source of truth. Marketplace listings (eBay, Shopify, future platforms) are downstream records that derive their data from the product.

```
                              +---------------------+
                              |    Product Intake    |
                              |   (Mobile/Web/API)   |
                              +----------+----------+
                                         |
                                         v
                              +---------------------+
                              |  AI Research &       |
                              |  Enrichment Pipeline |
                              |  (ProcessQueueItem)  |
                              +----------+----------+
                                         |
                                         v
+----------------------------------------------------------------------------------------+
|                                                                                        |
|                           PRODUCTS TABLE (Source of Truth)                              |
|                                                                                        |
|   Fields: title, description, selling_price, available_quantity, condition,             |
|           images, brand, category, UPC/EAN, dimensions, weight                         |
|                                                                                        |
+-----+-------------------+-------------------+-------------------+---------------------+
      |                   |                   |                   |
      v                   v                   v                   v
+------------+    +---------------+    +---------------+    +--------------+
| eBay       |    | Shopify       |    | Future        |    | Future       |
| Listings   |    | Listings      |    | Marketplace   |    | Marketplace  |
| (ebay_     |    | (shopify_     |    | Listings      |    | Listings     |
|  listings) |    |  listings)    |    |               |    |              |
+-----+------+    +-------+------+    +-------+-------+    +------+-------+
      |                   |                   |                    |
      v                   v                   v                    v
  eBay API          Shopify API          Platform API         Platform API
```

### Core Principle: Outbound-Primary Sync

- **Price changes** originate on the Product, then push to all marketplace listings
- **Quantity changes** originate on the Product, then push to all marketplace listings
- **Inbound sync** is limited to: order import (to reserve inventory) and initial store import
- The Product is always authoritative; marketplace-side changes sync back only for orders

---

## Data Flow

### How a Product Moves Through the System

```
1. CAPTURE                     2. ENRICH                    3. CREATE PRODUCT
+------------------+          +-------------------+         +------------------+
| Barcode scan     |   --->   | ProcessQueueItem  |  --->   | Product record   |
| Manual entry     |          | (8-step pipeline) |         | created in DB    |
| Chrome extension |          | AI research       |         | UUID primary key |
| eBay store import|          | Photo processing  |         |                  |
+------------------+          | Confidence score  |         +--------+---------+
                              +-------------------+                  |
                                                                     v
4. VERIFY                      5. LIST                       6. ONGOING SYNC
+------------------+          +-------------------+         +------------------+
| VerificationWiz  |   --->   | CreateEbayListing |  --->   | ProductObserver   |
| User reviews AI  |          | ShopifyProduct    |         |  -> Price sync    |
| Edits fields     |          | Service.create()  |         |  -> Listing sync  |
| Sets price       |          | Creates listing   |         |  -> End listings  |
+------------------+          +-------------------+         | Order import     |
                                                            | Scheduled tasks  |
                                                            +------------------+
```

### Key Files Per Stage

| Stage | Key Files |
|-------|-----------|
| Capture | `app/Http/Controllers/Product/IntakeQueueController.php` |
| Enrich | `app/Jobs/ProcessQueueItem.php`, `app/Services/GeminiProductResearch.php` |
| Create Product | `app/Services/Inventory/ProductService.php` |
| Verify | `resources/js/Components/IntakeQueue/VerificationWizard.vue` |
| List on eBay | `app/Jobs/CreateEbayListingJob.php`, `app/Services/EbaySellService.php` |
| List on Shopify | `app/Services/Shopify/ShopifyProductService.php` |
| Inventory Sync | `app/Services/MarketplaceInventorySync.php` |
| Price Sync | `app/Services/MarketplacePriceSync.php` |
| Listing Sync | `app/Services/MarketplaceListingSync.php` |
| Auto-Dispatch | `app/Observers/Inventory/ProductObserver.php` |
| Order Import | `app/Services/Fulfillment/OrderSyncService.php` |

---

## Product Lifecycle

### Status States

Products move through these states in the intake queue:

```
pending
  |
  v
in_progress --> processing --> ready_for_listing (confidence >= 80%)
                            |-> needs_minor_review (60-80%)
                            |-> needs_review (40-60%)
                            |-> needs_major_review (<40%)
                            |-> failed --> pending (retry)
                            |
                            v
                        completed --> listed_on_ebay
```

### Listing Status on Marketplaces

Once a product is listed, its marketplace records have their own lifecycle:

**eBay Listings** (`EbayListing.status`):
```
draft -> in_progress -> listed_on_ebay (active)
                     -> retry_scheduled (retryable error)
                     -> needs_review (validation error)
                     -> listing_failed (permanent failure)
                     -> already_exists (conflict)
      -> ended (removed from eBay)
```

**Shopify Listings** (`ShopifyListing.status`):
```
draft -> active -> archived -> deleted
```

### Retiring/Archiving a Product

When a product is retired or archived, the `ProductObserver` automatically dispatches the `EndMarketplaceListings` job:

1. Product status changes to inactive/archived/deleted
2. `ProductObserver` detects the status change and dispatches `EndMarketplaceListings`
3. eBay: `EbayInventoryService::endListing()` called per listing
4. Shopify: `ShopifyProductService::archiveProduct()` called per listing
5. `available_quantity` set to 0 to prevent further sales

---

## Marketplace Integration Model

### Current Architecture: Concrete Services

The platform currently uses **marketplace-specific service classes** rather than an abstract adapter pattern. Each marketplace has dedicated services:

| Concern | eBay | Shopify |
|---------|------|---------|
| Product CRUD | `EbayInventoryService` | `ShopifyProductService` |
| Inventory | `EbayInventoryService` | `ShopifyInventoryService` |
| Price updates | `UpdateEbayInventory` (job) | `ShopifyProductService::updateProduct()` |
| Order import | `OrderSyncService` | `ProcessShopifyWebhook` (job) |
| OAuth | `EbayOAuthService` | Built into credential config |
| Scheduled sync | `ProcessEbayScheduledTasks` | N/A (webhook-driven) |
| Store import | `ImportEbayInventory` (job) | Shopify product import |

### Cross-Marketplace Orchestration

Three orchestrators handle different types of product changes, all triggered automatically by the `ProductObserver`:

**Inventory** (`MarketplaceInventorySync`): Pushes quantity to all marketplaces on order arrival.

```
Order arrives (eBay webhook / Shopify webhook)
  -> Product.decrementAvailableQuantity() (row lock)
  -> PushInventoryToMarketplaces (queued)
  -> MarketplaceInventorySync.pushQuantityToAll()
```

**Price** (`MarketplacePriceSync`): Pushes price to all marketplaces when `selling_price` changes.

```
Product.selling_price changes
  -> ProductObserver dispatches PushPriceToMarketplaces (queued, ShouldBeUnique 60s)
  -> MarketplacePriceSync.pushPriceToAll() (checks sync rules)
```

**Listing** (`MarketplaceListingSync`): Pushes title, description, images, condition, dimensions when listing fields change.

```
Product listing fields change
  -> ProductObserver dispatches PushListingToMarketplaces (queued, ShouldBeUnique 60s)
  -> MarketplaceListingSync.pushListingToAll() (checks sync rules + hash change detection)
```

**End Listings**: When a product is deactivated/archived/deleted, the observer dispatches `EndMarketplaceListings` to end all active marketplace listings.

### Target Architecture: Marketplace Adapter Pattern

See [MARKETPLACE_INTEGRATION_ARCHITECTURE.md](MARKETPLACE_INTEGRATION_ARCHITECTURE.md) for the planned adapter interface that will standardize marketplace integrations and make adding new platforms straightforward.

---

## Current State vs Target State

### What Exists Today

| Capability | Status | Notes |
|------------|--------|-------|
| Product intake + AI enrichment | Complete | 8-step pipeline, multiple research strategies |
| eBay listing creation | Complete | Full Sell API + Trading API fallback |
| eBay inventory sync | Complete | Quantity + price via `UpdateEbayInventory` |
| eBay order import | Complete | ECN webhooks + scheduled polling |
| eBay store import | Complete | Pull existing listings into app |
| Shopify product creation | Complete | Via `ShopifyProductService` |
| Shopify inventory sync | Complete | Via `MarketplaceInventorySync` |
| Shopify order import | Complete | Webhook-driven |
| Cross-marketplace quantity sync | Complete | `MarketplaceInventorySync.pushQuantityToAll()` |
| Product CRUD (backend) | Complete | `ProductService` + `ProductController` |
| Product Index/Show/Create UI | Complete | Vue pages exist |

### What's Been Built (Phases 1-5)

| Capability | Phase | Key Files |
|------------|-------|-----------|
| Product Edit UI page | 1 | `resources/js/Pages/Inventory/Products/Edit.vue` |
| Cross-marketplace price sync | 2 | `MarketplacePriceSync`, `PushPriceToMarketplaces`, `PricingService` |
| Marketplace Hub UI | 3 | `MarketplaceHubController`, `resources/js/Pages/Marketplaces/Index.vue` |
| Listing templates + pricing UI | 4 | `ListingTemplateController`, `PricingController`, template/pricing Vue pages |
| MarketplaceAdapter interface | 5 | `App\Contracts\MarketplaceAdapter`, `MarketplaceRegistry` |
| Price history tracking | 5 | `PriceHistory` model, `price_history` migration, observer hooks |
| Full listing sync (title, desc, images) | 5 | `MarketplaceListingSync`, `PushListingToMarketplaces`, `UpdateEbayListing` |
| ProductObserver auto-dispatch | 5 | `app/Observers/Inventory/ProductObserver.php` -- dispatches price, listing, and end-listing jobs on product changes |
| End marketplace listings on deactivation | 5 | `EndMarketplaceListings` job, triggered by observer when status changes to inactive/archived/deleted |

### What's Remaining

| Capability | Priority | Notes |
|------------|----------|-------|
| Shopify frontend pages | Low | Basic pages at `resources/js/Pages/Shopify/` (Products, SyncLogs, Settings) |

---

## Implementation Roadmap

### Phase 1: Foundation Fixes

**Goal**: Fill critical gaps in the existing product management workflow.

- Create `resources/js/Pages/Inventory/Products/Edit.vue`
- Add `bulkAction()` dispatch method to `ProductService`
- Enrich `ProductResource` with marketplace listing data (eBay status, Shopify status)
- Load marketplace relationships in `ProductController` show/edit responses

### Phase 2: Cross-Platform Price Sync

**Goal**: Price changes on a Product automatically propagate to all marketplace listings.

- Create `MarketplacePriceSync` service (mirrors `MarketplaceInventorySync` pattern)
- Create `PushPriceToMarketplaces` job (dispatches per-marketplace update jobs)
- Hook into `ProductService::update()` to detect price changes and auto-dispatch
- `UpdateEbayInventory` already supports `updateType='price'` -- just needs to be triggered
- Verify Shopify price update works via `ShopifyProductService::updateProduct()`

### Phase 3: Marketplace Hub UI

**Goal**: Unified dashboard showing all listings across all marketplaces.

- Create `MarketplaceHubController` with cross-platform aggregation queries
- Build Hub dashboard page: listings by platform, sync health, error summary
- Create cross-platform product table: each product with its status on every connected marketplace
- Create `MarketplaceStatsService` for aggregated metrics (total listings, revenue, error rates)

### Phase 4: Listing Templates and Pricing Management

**Goal**: Reusable templates and bulk pricing tools.

- Create `ProductListing` and `ListingTemplate` Eloquent models (tables already migrated)
- Create `ListingTemplateController` (CRUD operations)
- Create `PricingController` (bulk adjust, preview, apply)
- Create `PricingService` (markup rules, rounding, competitive pricing)
- Build template and pricing management Vue pages

### Phase 5: Platform-Agnostic Expansion (Complete)

**Goal**: Make adding new marketplaces a standardized, repeatable process. Auto-sync product changes to all marketplaces.

- [x] Define `MarketplaceAdapter` interface (see [MARKETPLACE_INTEGRATION_ARCHITECTURE.md](MARKETPLACE_INTEGRATION_ARCHITECTURE.md))
- [x] Refactor eBay and Shopify services to implement the interface
- [x] Create marketplace registration system (config-driven)
- [x] Add price history tracking (`PriceHistory` model + migration + observer)
- [x] Implement `ProductObserver` auto-dispatch layer (price, listing, and end-listing sync)
- [x] Implement `MarketplaceListingSync` with sync rule checks and hash-based change detection
- [x] Implement `MarketplacePriceSync` with sync rule checks
- [x] Implement `EndMarketplaceListings` for product deactivation
- [ ] Build "Add Marketplace" wizard in UI

---

## Cross-References

| Document | Scope |
|----------|-------|
| [MARKETPLACE_INTEGRATION_ARCHITECTURE.md](MARKETPLACE_INTEGRATION_ARCHITECTURE.md) | Multi-platform adapter pattern, adding new marketplaces |
| [PRICING_AND_INVENTORY_SYNC.md](PRICING_AND_INVENTORY_SYNC.md) | Cross-marketplace price and quantity sync details |
| [PRODUCT_MANAGEMENT_UI.md](PRODUCT_MANAGEMENT_UI.md) | Frontend pages, missing UI, component architecture |
| [INVENTORY_SYNC_SYSTEM.md](INVENTORY_SYNC_SYSTEM.md) | Inventory reservation lifecycle, oversell prevention |
| [INTAKE_QUEUE_SYSTEM.md](INTAKE_QUEUE_SYSTEM.md) | Product intake pipeline, AI enrichment, status machine |
| [EBAY_LISTING_SERVICE.md](EBAY_LISTING_SERVICE.md) | eBay listing creation flow |
| [EBAY_SYNC_AND_UPDATE_SYSTEM.md](EBAY_SYNC_AND_UPDATE_SYSTEM.md) | eBay scheduled sync, manual sync, store import |
| [PRODUCT_RESEARCH_ORCHESTRATOR.md](PRODUCT_RESEARCH_ORCHESTRATOR.md) | AI research strategies and orchestration |
| [CATEGORY_PIPELINE.md](CATEGORY_PIPELINE.md) | eBay category mapping pipeline |
