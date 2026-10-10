# Inventory Sync System

## The Oversell Problem

Before this system was implemented, a gap existed between when an order arrived and when inventory was decremented. `Product.available_quantity` stayed unchanged until a staff member processed the order through the fulfillment workflow — potentially hours or days later. With multiple marketplace listings (eBay + Shopify) pointing at the same physical stock, the same unit could sell twice.

The fix has three parts:
1. **Immediate reservation** — inventory is decremented the moment an order is imported
2. **Cross-marketplace push** — updated quantity is sent to all marketplace listings right after reservation
3. **eBay ECN webhooks** — eBay orders arrive in near real-time (like Shopify already did)

---

## Inventory Fields: What Each Means

| Field | Where | Meaning |
|-------|-------|---------|
| `products.available_quantity` | Product model | Units available to sell across all marketplaces. The single source of truth for listing quantity. Decremented immediately on order import. **The product hub UI binds this column.** |
| `products.stock_quantity` | Product model | Legacy physical-on-hand column (pre-Jan 2026). Decremented on physical ship/pick. Mirrored on receiving (`ReceivingService`) and on manual adjustments (May 2026 — see "Manual adjustments" below). Do NOT bind this in the UI; it diverges from `available_quantity` between order placement and physical pick. |
| `products.quantity` | Product model | Legacy total-physical column kept for warehouse reports. Initialized equal to `available_quantity`. |
| `products.reserved_quantity` | Product model | Quantity reserved for unshipped orders. Informational. |
| `inventory_levels.quantity_available` | InventoryLevel | Warehouse-level available quantity at a specific location. |
| `inventory_levels.quantity_reserved` | InventoryLevel | Warehouse-level reserved quantity. Updated when order is assigned to a location. |

### Why `available_quantity` (not just `InventoryLevel`)

`Product.available_quantity` is the denormalized signal that both `UpdateEbayInventory` and `ShopifyInventoryService` read from. It is the single source of truth for cross-marketplace availability. `InventoryLevel.quantity_reserved` is warehouse-level tracking that only applies after an order is assigned to a specific location (which may come hours or days after the order is placed).

---

## Reservation Lifecycle

```
1. Order imported (eBay via ECN or polling, Shopify via webhook)
        ↓
2. matchOrderItemsToProducts() links order items to Product records
        ↓
3. reserveInventoryForOrder() called (OrderSyncService or ProcessShopifyWebhook)
   - Calls Product::decrementAvailableQuantity() per item (DB transaction + lock)
        ↓
4. PushInventoryToMarketplaces job dispatched per affected product
   - MarketplaceInventorySync::pushQuantityToAll()
   - UpdateEbayInventory job dispatched per published eBay listing
   - ShopifyInventoryService::setInventoryLevel() called per active Shopify listing
        ↓
5. Order assigned to warehouse location (fulfillment workflow)
   - InventoryLevel::reserve() called for the specific location
        ↓
6. Order shipped
   - InventoryLevel.quantity_on_hand decremented
```

---

## Manual adjustments

Manual stock changes (Adjust Stock modal on the product hub, the Movements / Create page, and the bulk Stock Adjustment job) all mirror their delta to **both** `stock_quantity` and `available_quantity`, then dispatch `PushInventoryToMarketplaces` for each affected product. This guarantees that a manual count correction propagates to eBay and Shopify within seconds.

| Writer | File | Mirrors `available_quantity`? | Dispatches push job? |
|---|---|---|---|
| Order import (eBay/Shopify/Amazon) | `app/Services/Fulfillment/*OrderSyncService.php` | direct write (only column touched) | yes (in `reserveInventoryForOrder`) |
| Receiving | `app/Services/Procurement/ReceivingService.php` | yes | yes |
| Adjust Stock modal | `ProductController::adjustStock` | yes | yes |
| Movements form | `InventoryMovementController::store` (`updateInventoryLevel`) | yes | yes |
| Bulk Stock Adjustment | `app/Jobs/Inventory/BulkStockAdjustment.php` | yes | yes (deduped per product) |
| Ship / pick | various | no (reduces `stock_quantity` only — `available_quantity` was already decremented at order placement) | n/a |

If you add a new writer, **mirror the delta to both columns and dispatch `PushInventoryToMarketplaces::dispatch($productId)`**, otherwise marketplace listings will silently drift.

---

## Delivery status: tracking provider chain

`Shipment.actual_delivery_date` is populated by `TrackingSyncService` from the first matching provider in `TrackingProviderRegistry`:

1. `EasyPostTrackingAdapter` — paid, universal carrier coverage. Active when `services.easypost.api_key` is set.
2. `UspsTrackingAdapter` — free USPS-only. Active when `services.usps.user_id` is set and shipment carrier is USPS.
3. `EbayTrackingAdapter` — marketplace-side fallback. Active for any shipment whose order originated on eBay and the org has an active eBay credential. Has two signals it consults in order:
   1. **Trading API `GetOrders` → `ShippingPackageInfo.ActualDeliveryTime`.** Only populated for eBay-purchased labels. Cached 6h per (orderId, day) when present; empty responses are NOT cached.
   2. **REST `getOrder` data already in `Order.marketplace_data` → `fulfillmentStartInstructions[0].maxEstimatedDeliveryDate`.** When `Order.marketplace_status = 'FULFILLED'` (eBay confirmed tracking uploaded) and the max estimated delivery date has elapsed, the adapter returns a synthetic `delivered` event with `deliveredAt = maxEstimatedDeliveryDate` and `description: 'Delivered (presumed — eBay max estimated delivery date elapsed, FULFILLED)'`. This mirrors how eBay's seller dashboard treats the order internally — once FULFILLED + max date passed, it's effectively complete pending the return window.

`SyncAllActiveShipmentTracking` cron (every 30 min, `routes/console.php:106`) iterates every active shipment with a tracking number and dispatches `SyncShipmentTracking` for each. The provider chain ensures sellers without a paid carrier API still get delivery confirmation for eBay-sourced shipments.

**Honest signal**: events written via the presumed-delivery path keep "presumed" in their `description`, so `shipment_tracking_events` rows make the source visible in any UI that displays the event log.

**Known limitation**: this is presumption, not carrier-confirmed truth. For sellers who need carrier-side scans (UPS/FedEx/precise USPS timestamps), wire `services.usps.user_id` (free USPS Web Tools) or `services.easypost.api_key` (paid universal) — those adapters take precedence over the eBay fallback.

---

## MarketplaceInventorySync

**File:** `app/Services/MarketplaceInventorySync.php`

Single method: `pushQuantityToAll(Product $product)`

- **eBay:** Dispatches `UpdateEbayInventory($listing, 'quantity')` for each published eBay listing. `UpdateEbayInventory` reads `Product.available_quantity` and pushes it via the **Offer API** (`PUT /sell/inventory/v1/offer/{offerId}`) for published listings, or the Trading API for imported listings without an offer_id.
- **Shopify:** Calls `ShopifyInventoryService::setInventoryLevel()` for each active Shopify listing that has a `shopify_inventory_item_id`. Resolves the Shopify location ID from `credential.config.shopify_location_id`, or fetches it from the API and caches it automatically. Also zeros out all non-primary locations to prevent double-counting across Shopify's multi-location inventory.

**Called by:** `PushInventoryToMarketplaces` job (queued, 3 retries, 30s backoff).

### auto_sync_enabled Guard

All marketplace write paths check `credential.auto_sync_enabled` before modifying listings. If the flag is `false`, the adapter is skipped and a log entry is written. This prevents unintended writes to marketplaces managed by external tools (e.g., a seller transitioning from another inventory management service).

**Guarded services/jobs:**

| Service/Job | Write operation | Log prefix |
|---|---|---|
| `MarketplaceInventorySync::pushQuantityToAll()` | Quantity updates | `MarketplaceInventorySync:` |
| `MarketplacePriceSync::pushPriceToAll()` | Price updates | `MarketplacePriceSync:` |
| `MarketplaceListingSync::pushListingToAll()` | Title, description, images | `MarketplaceListingSync:` |
| `EndMarketplaceListings::handle()` | End/withdraw listings | `EndMarketplaceListings:` |
| `RetryFailedEbaySync::shouldRetry()` | Retry failed operations | (silently skips) |

The flag defaults to `false` on new credentials, so connecting a marketplace is safe -- no writes occur until the user explicitly enables sync via the auto-sync toggle in the marketplace UI.

### Adding a new marketplace

Add a `protected function pushToNewMarketplace(Product $product)` method in `MarketplaceInventorySync` and call it from `pushQuantityToAll()`.

---

## eBay ECN Setup

### How it works

eBay Event Change Notification (ECN) is a push webhook system. Instead of polling the eBay API for new orders every few minutes, eBay pushes a notification to your endpoint the moment an order is created.

### Routes

```
GET  /api/webhooks/ebay   → EbayNotificationController@challenge
POST /api/webhooks/ebay   → EbayNotificationController@handle
```

These routes are outside the `auth:sanctum` middleware — eBay calls them without authentication.

### Registering with eBay

#### Option A: Automated (Notification API)

Use the bundled command. It generates a verification token, creates a destination, and subscribes to the topics — all per-credential:

```bash
docker exec ecommerce_app php artisan ebay:register-notifications --credential=<id> --dry-run
docker exec ecommerce_app php artisan ebay:register-notifications --credential=<id>
```

By default it subscribes to `MARKETPLACE_ACCOUNT_DELETION` (mandatory). Pass `--topics=MARKETPLACE_ACCOUNT_DELETION,ORDER_CREATED` to add more. The command writes `ebay_notification_token`, `ebay_notification_destination_id`, and `ebay_notification_subscriptions` into `IntegrationCredential.config`.

The per-credential webhook URL `https://flipstack.app/api/webhooks/ebay/{credential->id}` is what gets registered, so each credential has its own destination.

#### Option B: Manual / legacy topics

For topics only available on the legacy Trading API (`SetNotificationPreferences` — e.g., `ItemSold`, `AuctionCheckoutComplete`):

1. Go to the [eBay Developer Portal](https://developer.ebay.com) → Your Application → Notification Preferences
2. Register the endpoint URL: `https://flipstack.app/api/webhooks/ebay/<credential-id>` (or `/api/webhooks/ebay` for the default credential)
3. Generate a verification token and store it in the eBay `IntegrationCredential.config` JSON field:
   ```php
   $credential->update([
       'config' => array_merge($credential->config ?? [], [
           'ebay_notification_token' => 'your-secure-random-token',
       ]),
   ]);
   ```
4. Subscribe to the topics you need.

Topics handled by `ProcessEbayNotification`: any `ORDER_*`, any `ITEM_SOLD*`, plus `EBAY_ORDER_CREATED`, `EBAY_ORDER_UPDATED`, `AuctionCheckoutComplete`, `FixedPriceTransaction`, `ItemMarkedPaid`, and `MARKETPLACE_ACCOUNT_DELETION`. Order-bearing topics dispatch `SyncSingleEbayOrder`; if the payload omits an order id, the job falls back to the full poller.

### Challenge verification

When you register the endpoint, eBay sends:
```
GET /api/webhooks/ebay?challenge_code=<code>
```

The controller responds with:
```json
{"challengeResponse": "<sha256(challenge_code + verificationToken + endpointUrl)>"}
```

### Notification signature verification

Each POST notification includes an `X-EBAY-SIGNATURE` header containing a base64-encoded JSON object with a `signatureMessage` field. The controller verifies this as:

```
expected = base64(HMAC-SHA256(rawBody, verificationToken))
valid = hash_equals(expected, signatureMessage)
```

Invalid signatures are logged and return 200 (eBay requires 200 on all responses to stop retries).

### Verification token

The `ebay_notification_token` is stored in `integration_credentials.config` (JSON column). Access it via the `ebayNotificationToken` accessor on `IntegrationCredential`.

---

## Debugging

### Check inventory state

```bash
# Current available_quantity for a product
docker exec ecommerce_app php artisan tinker --execute="
App\Models\Catalog\Product::find('PRODUCT-UUID')->only(['available_quantity','quantity','reserved_quantity']);
"

# Check reservation count (items without shipment)
docker exec ecommerce_app php artisan tinker --execute="
App\Models\Fulfillment\Order::where('status','!=','shipped')
  ->with('items')
  ->get()
  ->pluck('items')
  ->flatten()
  ->where('product_id','PRODUCT-UUID')
  ->sum('quantity');
"
```

### Check failed marketplace pushes

```bash
# Failed jobs
docker exec ecommerce_app php artisan tinker --execute="
DB::table('failed_jobs')
  ->where('payload','like','%PushInventoryToMarketplaces%')
  ->orderByDesc('failed_at')
  ->take(5)
  ->get(['id','failed_at','exception']);
"
```

### Test ECN challenge endpoint

```bash
curl "https://yourapp.com/api/webhooks/ebay?challenge_code=test123"
# Returns: {"challengeResponse":"<sha256hash>"}
```

### Manually trigger inventory push

```bash
docker exec ecommerce_app php artisan tinker --execute="
App\Jobs\Inventory\PushInventoryToMarketplaces::dispatch('PRODUCT-UUID');
echo 'dispatched';
"
```

### View eBay ECN logs

```bash
docker exec ecommerce_app php artisan tinker --execute="
echo file_get_contents(storage_path('logs/laravel.log'));
" | grep -i 'EbayNotification\|ProcessEbayNotification' | tail -20
```
