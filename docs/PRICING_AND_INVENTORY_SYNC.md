# Pricing and Inventory Sync

How price and quantity changes propagate across all connected marketplaces. Covers the inventory sync system, price sync system, full listing sync, the ProductObserver auto-dispatch layer, bulk operations, conflict resolution, and scheduled reconciliation.

## Table of Contents

1. [Inventory Sync Flow (Implemented)](#inventory-sync-flow-implemented)
2. [Price Sync Flow (Implemented)](#price-sync-flow-implemented)
3. [Full Listing Sync (Implemented)](#full-listing-sync-implemented)
4. [ProductObserver: Auto-Dispatch Layer (Implemented)](#productobserver-auto-dispatch-layer-implemented)
5. [Bulk Operations](#bulk-operations)
6. [Conflict Resolution](#conflict-resolution)
7. [Scheduled Sync and Drift Detection](#scheduled-sync-and-drift-detection)

---

## Inventory Sync Flow (Implemented)

Cross-marketplace inventory sync is fully implemented. See [INVENTORY_SYNC_SYSTEM.md](INVENTORY_SYNC_SYSTEM.md) for the complete deep dive. Summary below.

### Trigger: Order Arrival

```
eBay ECN Webhook / Shopify Webhook
  |
  v
OrderSyncService / ProcessShopifyWebhook
  |
  v
Product.decrementAvailableQuantity($qty)
  (DB transaction with row lock -- prevents race conditions)
  |
  v
Dispatch PushInventoryToMarketplaces job
  |
  v
MarketplaceInventorySync.pushQuantityToAll(Product)
  |
  +---> For each published EbayListing:
  |       Dispatch UpdateEbayInventory (updateType: 'quantity')
  |         -> Published listings (has offer_id): Offer API (PUT /offer/{offerId})
  |         -> Imported listings (no offer_id): Trading API (ReviseFixedPriceItem)
  |         -> Unpublished: Inventory Item API (PUT /inventory_item/{sku})
  |
  +---> For each active ShopifyListing:
          Resolve shopify_location_id (from credential config or API fetch + cache)
          ShopifyInventoryService::setInventoryLevel(locationId, inventoryItemId, qty)
          Zero out all non-primary locations (multi-location support)
```

### Key Design Decisions

- **`available_quantity`** is the single source of truth, not warehouse-level `InventoryLevel`
- Reservation happens **immediately** on order import, before fulfillment assignment
- Quantity push is **fire-and-forget** (queued) -- the product record is already correct
- eBay updates are dispatched as individual jobs per listing (parallelizable)
- Shopify updates are inline within the sync service (fast API calls)
- **`auto_sync_enabled` guard** -- All marketplace sync services (`pushQuantityToAll()`, `pushPriceToAll()`, `pushListingToAll()`) and `EndMarketplaceListings` skip adapters whose credential has `auto_sync_enabled=false`. `RetryFailedEbaySync` also respects this flag. This prevents unintended writes when a marketplace is managed by external tools. The flag defaults to `false`, making initial marketplace connection safe.

### Key Files

| File | Role |
|------|------|
| `app/Services/MarketplaceInventorySync.php` | Orchestrator: pushes quantity to all marketplaces |
| `app/Jobs/Inventory/PushInventoryToMarketplaces.php` | Queued wrapper that calls the orchestrator |
| `app/Jobs/Ebay/UpdateEbayInventory.php` | Per-listing eBay update (supports quantity, price, both) |
| `app/Services/Fulfillment/OrderSyncService.php` | eBay order import + inventory reservation |
| `app/Jobs/Shopify/ProcessShopifyWebhook.php` | Shopify order import + inventory reservation |

---

## Price Sync Flow (Implemented)

Price sync has a dedicated orchestrator that mirrors the inventory sync pattern. When `selling_price` changes on a Product, the `ProductObserver` automatically dispatches a `PushPriceToMarketplaces` job that pushes the new price to all connected marketplaces.

### Trigger: ProductObserver

```
Product.selling_price changes (via any save/update path)
  |
  v
ProductObserver::updated()
  |
  v
Dispatch PushPriceToMarketplaces job (ShouldBeUnique, 60s dedup)
  |
  v
MarketplacePriceSync.pushPriceToAll(Product)
  |
  +---> For each published EbayListing:
  |       Dispatch UpdateEbayInventory (updateType: 'price')
  |
  +---> For each active ShopifyListing:
          ShopifyProductService::updateProduct()
```

### Key Details

- `MarketplacePriceSync` checks sync rules before pushing, so only listings with sync enabled receive updates
- `PushPriceToMarketplaces` implements `ShouldBeUnique` with a 60-second uniqueness window to deduplicate rapid saves
- The `ProductObserver` also records price changes via the `PriceHistory` model for audit purposes
- `UpdateEbayInventory` supports `updateType='price'`, reading `Product.selling_price`
- Shopify price updates go through `ShopifyProductService::updateProduct()`

### Key Files

| File | Role |
|------|------|
| `app/Observers/Inventory/ProductObserver.php` | Detects `selling_price` changes, dispatches job |
| `app/Jobs/Inventory/PushPriceToMarketplaces.php` | Queued job (ShouldBeUnique, 60s dedup) |
| `app/Services/MarketplacePriceSync.php` | Orchestrator: pushes price to all marketplaces, checks sync rules |
| `app/Jobs/Ebay/UpdateEbayInventory.php` | Per-listing eBay update (supports quantity, price, both) |

---

## Full Listing Sync (Implemented)

When product metadata changes (name, description, images, condition, dimensions), the `ProductObserver` automatically dispatches a `PushListingToMarketplaces` job that pushes updated listing data to all connected marketplaces.

### Trigger: ProductObserver

```
Product listing fields change (name, description, gallery_images, condition,
  condition_description, weight, length, width, height)
  |
  v
ProductObserver::updated()
  |
  v
Dispatch PushListingToMarketplaces job (ShouldBeUnique, 60s dedup)
  |
  v
MarketplaceListingSync.pushListingToAll(Product)
  |
  +---> For each published EbayListing:
  |       Dispatch UpdateEbayListing
  |
  +---> For each active ShopifyListing:
          ShopifyProductService::updateProduct()
```

### Change Detection

Two layers of change detection work together:

**Layer 1: Dirty field detection (ProductObserver)**

The observer uses `wasChanged()` to check listing-relevant fields. If any changed, it dispatches `PushListingToMarketplaces`.

**Layer 2: Hash-based change detection (MarketplaceListingSync)**

`MarketplaceListingSync` computes a hash of the product's syncable fields and compares it against the last-synced hash stored on each marketplace listing record. This prevents unnecessary API calls when the observer fires but the listing data hasn't actually diverged from what was last pushed.

### Smart Deduplication

If both listing fields and `selling_price` change in the same save, the observer only dispatches `PushListingToMarketplaces` (not both jobs). The listing sync already includes price in its update payload, so a separate price push would be redundant.

### Ending Listings on Status Change

When a product's status changes to `inactive`, `archived`, or `deleted`, the observer dispatches `EndMarketplaceListings` instead of a listing update:

```
Product.status changes to inactive/archived/deleted
  |
  v
ProductObserver::updated()
  |
  v
Dispatch EndMarketplaceListings job
  |
  +---> End each published eBay listing
  +---> Archive each active Shopify listing
```

### Per-Marketplace Update

| Marketplace | Method | Notes |
|-------------|--------|-------|
| eBay | `EbayInventoryService::createOrUpdateInventoryItem()` + `updateOffer()` | Updates inventory item (title, description, images, aspects) and offer (price, policies) separately |
| Shopify | `ShopifyProductService::updateProduct()` | Single API call updates all product fields |

### Key Files

| File | Role |
|------|------|
| `app/Observers/Inventory/ProductObserver.php` | Detects listing field changes, dispatches job |
| `app/Jobs/Inventory/PushListingToMarketplaces.php` | Queued job (ShouldBeUnique, 60s dedup) |
| `app/Jobs/Inventory/EndMarketplaceListings.php` | Ends/archives listings when product deactivated |
| `app/Services/MarketplaceListingSync.php` | Orchestrator: pushes listing data, checks sync rules + hash change detection |

---

## ProductObserver: Auto-Dispatch Layer (Implemented)

The `ProductObserver` (`app/Observers/Inventory/ProductObserver.php`) is the central dispatch point that watches for changes on Product models and routes them to the appropriate sync job. It is registered on the `Product` model and fires on every `updated` event.

### Dispatch Logic

```
ProductObserver::updated(Product $product)
  |
  |-- Status changed to inactive/archived/deleted?
  |     YES --> Dispatch EndMarketplaceListings
  |     NO  |
  |         v
  |-- Listing fields changed? (name, description, gallery_images, condition,
  |   condition_description, weight, length, width, height)
  |     YES --> Dispatch PushListingToMarketplaces
  |             (includes price in its update payload, so no separate price job needed)
  |     NO  |
  |         v
  |-- selling_price changed?
  |     YES --> Dispatch PushPriceToMarketplaces
  |             Record PriceHistory entry
  |     NO  --> No marketplace sync needed
```

### Key Behaviors

- **Smart deduplication**: If listing fields AND price both change, only `PushListingToMarketplaces` is dispatched (it covers price too). This avoids duplicate API calls.
- **Price history**: Every `selling_price` change is recorded in the `PriceHistory` model regardless of which job is dispatched.
- **Job uniqueness**: Both `PushListingToMarketplaces` and `PushPriceToMarketplaces` implement `ShouldBeUnique` with a 60-second uniqueness window, preventing duplicate jobs from rapid consecutive saves.
- **Sync rule checks**: The downstream services (`MarketplaceListingSync`, `MarketplacePriceSync`) check sync rules before pushing, so listings with sync disabled are skipped.

### Key Files

| File | Role |
|------|------|
| `app/Observers/Inventory/ProductObserver.php` | Central dispatch: detects what changed, routes to correct job |
| `app/Jobs/Inventory/PushPriceToMarketplaces.php` | Price-only sync (ShouldBeUnique, 60s dedup) |
| `app/Jobs/Inventory/PushListingToMarketplaces.php` | Full listing sync (ShouldBeUnique, 60s dedup) |
| `app/Jobs/Inventory/EndMarketplaceListings.php` | Ends/archives listings on product deactivation |
| `app/Services/MarketplacePriceSync.php` | Price push orchestrator, checks sync rules |
| `app/Services/MarketplaceListingSync.php` | Listing push orchestrator, checks sync rules + hash change detection |

---

## Bulk Operations

### Bulk Price Adjustment

Apply a price change to multiple products at once, with preview before applying.

**Types of adjustment:**
- **Percentage**: Increase/decrease by X% (e.g., +10%, -15%)
- **Fixed amount**: Add/subtract a fixed amount (e.g., +$5, -$2)
- **Set to**: Override with a specific price
- **Round to**: Round to nearest .99, .95, or whole dollar after adjustment

**Flow:**
```
1. User selects products (or filters)
2. User chooses adjustment type and value
3. System calculates new prices (preview)
4. User confirms
5. Products updated in batch
6. PushPriceToMarketplaces dispatched per product (queued)
7. Progress reported via Pusher/Echo
```

**Implementation location:** `ProductService::bulkAdjustPrice()` dispatching individual `PushPriceToMarketplaces` jobs.

### Bulk Quantity Adjustment

Similar pattern to price adjustment for manual inventory corrections:
- Set absolute quantity
- Increment/decrement by amount
- Dispatches `PushInventoryToMarketplaces` per product after update

### Bulk Status Change

Activate or end listings across platforms:
- **Activate**: Push product to selected marketplaces (creates listings if needed)
- **End/Archive**: End listings on selected marketplaces
- **End All**: End all marketplace listings for selected products

Each action dispatches per-product, per-marketplace jobs to avoid timeouts.

---

## Conflict Resolution

### Product is Always Authoritative

The Product record is the canonical source for price and quantity. If a marketplace listing shows a different value, the Product's value wins.

### Marketplace-Side Changes

| Scenario | Behavior |
|----------|----------|
| User edits title on eBay directly | Not synced back. Product's title remains authoritative. Next scheduled sync overwrites eBay. |
| eBay Best Offer accepted | Order created at negotiated price. Product `selling_price` unchanged. Inventory reserved normally. |
| Shopify discount applied | Order created at discounted price. Product `selling_price` unchanged. |
| Marketplace auto-adjusts price | Ignored. Next sync push restores Product's price. |
| Manual quantity edit on marketplace | Overwritten on next sync push. |

### Order-Driven Updates

Orders are the one legitimate source of inbound state change:

```
Marketplace order arrives
  -> Reserve inventory on Product (immediate)
  -> Push updated quantity to all OTHER marketplaces
  -> Product.selling_price NOT changed (order price may differ due to offers/discounts)
```

### Audit Trail

All price and quantity changes are tracked:
- `inventory_movements` table records quantity changes with source/reason
- `price_history` table records price changes via the `PriceHistory` model, tracking `product_id`, `old_price`, `new_price`, `changed_by`, and `source` -- automatically populated by the `ProductObserver` on every `selling_price` change

---

## Scheduled Sync and Drift Detection

### Current Scheduled Tasks

| Task | Schedule | What It Does |
|------|----------|--------------|
| `ProcessEbayScheduledTasks` | Via Laravel scheduler | Polls eBay for order updates, syncs listing status, pushes inventory |

### Stale Detection (Planned)

Detect listings that haven't synced recently and may be out of date:

```sql
-- Listings not synced in 24+ hours
SELECT * FROM ebay_listings
WHERE status = 'published'
AND last_synced_at < NOW() - INTERVAL '24 hours';
```

Flag stale listings for priority sync on the next scheduled run.

### Periodic Reconciliation (Planned)

Full reconciliation job that runs daily (off-peak):

1. For each active marketplace listing:
   - Fetch current price and quantity from marketplace API
   - Compare against Product's `selling_price` and `available_quantity`
   - If different, push Product's values to marketplace
   - Log drift events for monitoring

2. Report drift statistics:
   - Number of listings with price drift
   - Number of listings with quantity drift
   - Average drift magnitude
   - Listings with persistent drift (may indicate API failures)

### Drift Alerts

If drift is detected above a threshold:
- Notify user via in-app notification
- Log to `SyncDriftLog` (planned table)
- Auto-correct by pushing Product values

---

## Related Documentation

- [INVENTORY_SYNC_SYSTEM.md](INVENTORY_SYNC_SYSTEM.md) - Deep dive on inventory reservation and cross-marketplace quantity push
- [EBAY_SYNC_AND_UPDATE_SYSTEM.md](EBAY_SYNC_AND_UPDATE_SYSTEM.md) - eBay scheduled sync, manual sync, store import
- [MARKETPLACE_INTEGRATION_ARCHITECTURE.md](MARKETPLACE_INTEGRATION_ARCHITECTURE.md) - Multi-platform adapter pattern
- [PRODUCT_MANAGEMENT_SUITE.md](PRODUCT_MANAGEMENT_SUITE.md) - Master architecture and implementation roadmap
