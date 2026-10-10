# TC-MKT-012: Marketplace Sync — Full E2E Lifecycle

| Field              | Value                                                                 |
|--------------------|-----------------------------------------------------------------------|
| **Document ID**    | TC-MKT-012                                                            |
| **Domain**         | Marketplace Integration — Cross-Marketplace Inventory Sync             |
| **Related Test Plan** | [Feature Testing Plan - Phase 1](../../project_feature_testing_plan.md), `marketplace-ebay-test-plan.md`, `marketplace-shopify-test-plan.md` |
| **Architecture Doc** | `docs/INVENTORY_SYNC_SYSTEM.md` |
| **Last Updated**   | 2026-04-27                                                            |

---

## Overview

End-to-end scenario validating that `Product.available_quantity` remains the single source of truth across the eBay + Shopify lifecycle: list -> publish -> order arrives -> reserve -> push back -> verify no oversell. Exercises:

- `OrderSyncService::reserveInventoryForOrder()` (eBay path)
- `ProcessShopifyWebhook` (Shopify path)
- `PushInventoryToMarketplaces` queue job
- `MarketplaceInventorySync::pushQuantityToAll()`
- `EbayMarketplaceAdapter` Offer API quantity update
- `ShopifyInventoryService::setInventoryLevel()` with multi-location zeroing

This case **chains** the scenarios covered by TC-MKT-002, TC-MKT-003, TC-MKT-007, and TC-MKT-008 into a single golden-path workflow. Failures here may also surface as failures in those individual cases — file the bug against the most specific TC.

---

## TC-MKT-012-01: List, Push, Order, Reconcile (Happy Path)

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-012-01                                                      |
| **Priority**   | Critical (P0)                                                      |
| **Type**       | E2E                                                                |
| **Platform**   | Web + eBay + Shopify + Queue                                       |
| **Automated**  | No (manual; partial automation possible via feature test)          |
| **Test File**  | `tests/Feature/Marketplaces/MarketplaceSyncLifecycleTest.php` (to create) |

### Preconditions

1. Organization has active eBay `IntegrationCredential` with `auto_sync_enabled=true` and a valid `OAuthToken`.
2. Organization has active Shopify `IntegrationCredential` with `auto_sync_enabled=true`, `config.shopify_location_id` set, and at least 2 Shopify locations (primary + secondary).
3. Product factory: `{title: "E2E Sync Widget", sku: "E2E-001", price: 49.99, quantity: 10, available_quantity: 10, condition: "new"}` with at least one image and an eBay category mapping.
4. No existing `EbayListing` or `ShopifyListing` for the product.
5. `Http::fake()` configured (or sandbox credentials) for eBay Inventory + Sell APIs and Shopify Admin API.
6. Queue running (`sync` driver in tests, real queue in staging).

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Cross-list product to eBay (`SyncProductToEbay` job). | `EbayListing.status=published`, `ebay_listing_id` set. eBay receives quantity=10 via `createOffer`. (See TC-MKT-002-01) |
| 2 | Cross-list product to Shopify (`SyncProductToShopify` job). | `ShopifyListing.status=active`, `shopify_product_id` + `shopify_variant_id` + `shopify_inventory_item_id` populated. Inventory at primary location = 10, secondary = 0. (See TC-MKT-007-01, TC-MKT-007-08) |
| 3 | Inject mock eBay order via `OrderSyncService` for `quantity=2` of SKU `E2E-001`. | Local `Order` created. `Product.available_quantity` decrements 10 -> 8 inside a DB transaction. |
| 4 | Verify `PushInventoryToMarketplaces` job dispatched for the product UUID. | Job in queue (or executed sync). |
| 5 | Job runs `MarketplaceInventorySync::pushQuantityToAll()`. | eBay Offer API receives `availableQuantity=8` for the offerId from Step 1. Shopify primary location set to 8, secondary forced to 0. |
| 6 | Inject mock Shopify webhook (topic `orders/create`) for `quantity=3` of SKU `E2E-001`. | `ProcessShopifyWebhook` creates local `Order`, `Product.available_quantity` decrements 8 -> 5. |
| 7 | Verify second `PushInventoryToMarketplaces` job dispatched. | Job in queue. |
| 8 | Job runs again. | eBay Offer API receives `availableQuantity=5`. Shopify primary = 5, secondary = 0. |
| 9 | Verify final state matches expected reconciliation. | `Product.available_quantity = 5`. `Product.reserved_quantity = 5` (2+3). `EbayListing` last quantity push = 5. `ShopifyListing` last quantity push = 5 at primary, 0 at secondary. No `failed_jobs` entries. |
| 10 | Verify no oversell condition possible. | Sum across listings (5 + 5) is misleading — that's both reflecting the same available 5. Confirm via Step 9 totals only. eBay `quantity` field equals Shopify primary `available` equals `Product.available_quantity`. |

### Test Data

```
Product:
  sku: E2E-001
  starting quantity: 10
  starting available_quantity: 10

Mock eBay order:
  buyer_username: ebay-buyer-test
  line_items: [{sku: E2E-001, quantity: 2, price: 49.99}]

Mock Shopify webhook payload:
  topic: orders/create
  line_items: [{sku: E2E-001, quantity: 3, price: 49.99}]
```

### Postconditions

- 2 local `Order` records (one per marketplace).
- `Product.available_quantity = 5`, `Product.reserved_quantity = 5`, `Product.quantity = 10` (unchanged — physical stock not yet shipped).
- eBay listing reflects qty 5 via Offer API.
- Shopify listing reflects qty 5 at primary location, 0 at all others.
- No entries in `failed_jobs`.
- `EbaySyncLog` and `MarketplaceMessage` (if applicable) records reflect successful pushes.

### Notes

- This case is the **acceptance gate** for the inventory sync v1 cycle. If it fails, do not proceed to release. File any sub-failure against TC-MKT-002, 003, 007, or 008 with cross-reference back to TC-MKT-012-01.
- For automation: `tests/Feature/Marketplaces/MarketplaceSyncLifecycleTest.php` should use `Http::fake()` and `Queue::fake()` strategically (let the inventory push jobs run sync via `Queue::push` simulation but assert dispatched events).

---

## TC-MKT-012-02: Listing Updates Fan Out to Both Marketplaces

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-012-02                                                      |
| **Priority**   | High (P1)                                                          |
| **Type**       | E2E                                                                |
| **Platform**   | Web + eBay + Shopify                                               |
| **Automated**  | No                                                                 |

### Preconditions

- TC-MKT-012-01 has been run successfully (or equivalent setup: product cross-listed on both marketplaces).

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Edit product price from $49.99 to $39.99 in the app. | Product saved; `MarketplaceListingSync` triggered. |
| 2 | Verify eBay receives price update via Offer API `updateOffer`. | `EbayListing.last_synced_at` updated, data hash refreshed, status remains `published`. |
| 3 | Verify Shopify receives price update via `PUT /admin/api/2024-01/products/{id}.json`. | `ShopifyListing.last_synced_at` updated, hash refreshed. |
| 4 | Edit title and primary image; trigger sync. | Both marketplaces receive update. No phantom updates if data is identical (hash guard). |
| 5 | Toggle `auto_sync_enabled=false` on Shopify credential, edit price again. | eBay receives update, Shopify push is skipped, log records the skip reason. |

### Postconditions

- Both marketplace listings reflect the latest product data.
- `auto_sync_enabled=false` correctly suppresses writes for the affected marketplace only.

---

## TC-MKT-012-03: Marketplace Outage Does Not Block Other Marketplace

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-012-03                                                      |
| **Priority**   | High (P1)                                                          |
| **Type**       | E2E (resilience)                                                   |
| **Platform**   | Web + eBay + Shopify + Queue                                       |
| **Automated**  | Partial                                                            |

### Preconditions

- Product cross-listed on both marketplaces (per TC-MKT-012-01 starting state).
- eBay API mocked to return HTTP 500.
- Shopify API mocked to return HTTP 200.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Trigger `PushInventoryToMarketplaces` job (e.g., adjust qty to 7). | Job runs. |
| 2 | Verify eBay push fails. | `EbaySyncLog` records HTTP 500 error. Shopify push still attempted. |
| 3 | Verify Shopify push succeeds. | Shopify primary location = 7. `ShopifyListing.last_synced_at` updated. |
| 4 | Verify the parent job did not fail. | No entry in `failed_jobs`; per-marketplace failures logged, not thrown. |
| 5 | Restore eBay mock to HTTP 200, retry via `RetryFailedEbaySync`. | eBay catches up to 7. Both marketplaces in sync. |

### Postconditions

- Transient eBay outage logged but does not corrupt Shopify state.
- Retry mechanism brings the lagging marketplace back in line.

### Notes

- Mirror this test by swapping which marketplace fails first.

---

## Verification Queries

Run on the test DB after each sub-case:

```sql
-- Product state
SELECT sku, quantity, available_quantity, reserved_quantity FROM products WHERE sku = 'E2E-001';

-- Listing states
SELECT marketplace, status, last_synced_at, ebay_listing_id, shopify_product_id
FROM ebay_listings el FULL OUTER JOIN shopify_listings sl USING (product_id)
WHERE product_id = '<uuid>';

-- Order state
SELECT marketplace_order_id, marketplace, status, total_amount FROM orders WHERE product_sku = 'E2E-001';

-- Failed jobs
SELECT id, queue, payload->>'displayName' AS job, exception FROM failed_jobs ORDER BY failed_at DESC LIMIT 5;
```
