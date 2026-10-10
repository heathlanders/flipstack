# Cross-Marketplace Inventory Sync End-to-End Test

## Document ID: TC-E2E-004

**Systems Involved:** Web, eBay API (Fulfillment, Inventory), Shopify API (Webhooks, Inventory), Queue (Redis/Horizon), MarketplaceInventorySync, MarketplaceRegistry, Adapter Pattern
**Business Process:** A product is listed on multiple marketplaces (eBay and Shopify). When a sale occurs on one marketplace, the available_quantity is decremented and the updated quantity is pushed to all other marketplaces to prevent overselling. This test validates bidirectional sync, eventual consistency, oversell prevention, and concurrent sale handling.
**Last Updated:** 2026-04-15

---

## 1. Workflow Overview

This test validates the cross-marketplace inventory synchronization system that keeps Product.available_quantity as the single source of truth across all connected marketplaces. When any marketplace reports a sale, the system decrements available_quantity and dispatches PushInventoryToMarketplaces to update all other channels. The test covers eBay-to-Shopify sync, Shopify-to-eBay sync, zero-quantity oversell prevention, and concurrent sale race conditions.

```
  Initial State: Product.available_quantity = 10
  EbayListing (published, qty=10) <---> Product <---> ShopifyListing (active, qty=10)

  === Scenario A: eBay Sale ===

  eBay Buyer               OrderSyncService              Product              MarketplaceInventorySync
  purchases item ---------> importOrder() ----------> available_quantity     pushQuantityToAll()
  (qty=1)                   |                          10 -> 9                      |
                            v                              |               +--------+--------+
                    Order created                          |               |                 |
                    (status=pending)                        v               v                 v
                    reserveInventoryForOrder()        PushInventory    EbayAdapter       ShopifyAdapter
                    decrementAvailableQuantity(1)     ToMarketplaces  (skip: same      setInventoryLevel
                                                     dispatched       platform sale)   (location, item, 9)
                                                                                            |
                                                                                            v
                                                                                    Shopify qty = 9

  === Scenario B: Shopify Sale (immediately after) ===

  Shopify Buyer            ProcessShopifyWebhook         Product              MarketplaceInventorySync
  purchases item ---------> orders/create ----------> available_quantity     pushQuantityToAll()
  (qty=1)                   |                          9 -> 8                       |
                            v                              |               +--------+--------+
                    ShopifyOrderSyncService                |               |                 |
                    importOrder()                          v               v                 v
                    reserveInventoryForOrder()        PushInventory    ShopifyAdapter    EbayAdapter
                    decrementAvailableQuantity(1)     ToMarketplaces  (skip: same      UpdateEbayInventory
                                                     dispatched       platform sale)   dispatched (qty=8)
                                                                                            |
                                                                                            v
                                                                                    eBay qty = 8

  === Scenario C: Oversell Prevention ===

  Product.available_quantity = 0
       |
       v
  PushInventoryToMarketplaces
       |
       +-------> EbayAdapter: UpdateEbayInventory(qty=0)  --> eBay listing out_of_stock
       |
       +-------> ShopifyAdapter: setInventoryLevel(qty=0) --> Shopify shows "Sold Out"

  === Scenario D: Concurrent Sales ===

  eBay sale (qty=1)  ----+
                         |---> Both decrement available_quantity
  Shopify sale (qty=1) --+    (DB-level atomic decrement prevents race)
                         |
                         v
                    Two PushInventoryToMarketplaces jobs
                    (last one wins with final correct value)
```

**Trigger:** Sale on any connected marketplace (eBay order sync or Shopify orders/create webhook)
**End State:** Product.available_quantity reflects total sales across all marketplaces. All marketplace listing quantities match available_quantity. No overselling occurs. Eventual consistency achieved within queue processing time.

---

## 2. Preconditions

| # | Requirement | Setup Method |
|---|-------------|--------------|
| 1 | Organization with active subscription | Factory / Seeder |
| 2 | Product with available_quantity = 10 and UUID primary key | Factory |
| 3 | eBay IntegrationCredential (platform=ebay, is_active=true, auto_sync_enabled=true) with valid OAuthToken | Factory with sandbox credentials |
| 4 | Shopify IntegrationCredential (platform=shopify, is_active=true, auto_sync_enabled=true) with shop_domain and valid access_token, shopify_location_id in config | Factory with dev store credentials |
| 5 | EbayListing for Product (status=published, ebay_listing_id set, ebay_sku set) | Factory |
| 6 | ShopifyListing for Product (status=active, shopify_product_id set, shopify_variant_id set, shopify_inventory_item_id set) | Factory |
| 7 | Both marketplace listings showing available quantity = 10 (synced with Product.available_quantity) | Verify via marketplace APIs before test |
| 8 | MarketplaceRegistry returns both EbayMarketplaceAdapter and ShopifyMarketplaceAdapter for the organization | Verify adapters resolve correctly |
| 9 | No MarketplaceSyncRules blocking sync for this product (ruleEngine.shouldSync returns true) | Clean rule state or permissive rules |
| 10 | Redis and Horizon running with adequate supervisor concurrency | Docker environment |
| 11 | sync_direction_lock on ShopifyListing is NULL (no recent outbound sync lock) | Factory / Reset |
| 12 | No pending PushInventoryToMarketplaces jobs in queue for this product | Clean queue state |

---

## 3. Test Scenario

| Step | Actor | System | Action | Expected Result | Verification Method |
|------|-------|--------|--------|-----------------|---------------------|
| 1 | Tester | Database / API | Verify initial state: Product.available_quantity = 10, EbayListing.status = published, ShopifyListing.status = active. Verify eBay sandbox listing shows qty=10. Verify Shopify dev store shows inventory=10 at primary location | All quantities aligned at 10 across system and both marketplaces | DB: `SELECT available_quantity FROM products WHERE id = '{product_id}'` -- Assert 10. eBay API: verify listing qty. Shopify API: `GET /admin/api/inventory_levels.json?inventory_item_ids={item_id}` -- Assert available=10 |
| 2 | eBay Buyer | eBay Marketplace | Purchase 1 unit of the product on eBay. eBay creates order with orderId, lineItems containing the listing's ebay_listing_id | eBay order created in marketplace. Order visible in seller hub | eBay sandbox: confirm order exists |
| 3 | System (Cron/ECN) | Queue / OrderSyncService | OrderSyncService.syncOrders() polls eBay Fulfillment API and imports the new order. importOrder() creates Order record (status=pending, marketplace=ebay). matchOrderItemsToProducts() links OrderItem to Product via EbayListing. reserveInventoryForOrder() calls Product.decrementAvailableQuantity(1) | Product.available_quantity = 9 (decremented from 10). Order created with correct items. PushInventoryToMarketplaces dispatched with product ID | DB: `SELECT available_quantity FROM products WHERE id = '{product_id}'` -- Assert 9. `SELECT id, status, marketplace FROM orders WHERE marketplace_order_id = '{ebay_order_id}'` -- Assert status='pending', marketplace='ebay' |
| 4 | System | Queue / PushInventoryToMarketplaces | PushInventoryToMarketplaces job executes. MarketplaceInventorySync.pushQuantityToAll() iterates adapters from MarketplaceRegistry. For each adapter where auto_sync_enabled=true and ruleEngine.shouldSync()=true: gets active listings and calls updateQuantity() | Job completes. Both adapters invoked. For EbayMarketplaceAdapter: UpdateEbayInventory job dispatched with qty sync. For ShopifyMarketplaceAdapter: ShopifyInventoryService.setInventoryLevel() called with qty=9 at primary location, qty=0 at all other locations | Log: 'PushInventoryToMarketplaces: pushing quantity to all marketplaces' with available_quantity=9 |
| 5 | System | ShopifyMarketplaceAdapter | ShopifyMarketplaceAdapter.updateQuantity() resolves primary location from credential.config.shopify_location_id (or fetches + caches). Calls ShopifyInventoryService.setInventoryLevel(primaryLocationId, inventoryItemId, 9). Then zeros out all other locations to prevent double-counting | Shopify inventory level at primary location = 9. All other locations = 0. ShopifyListing.sync_direction_lock set to 'outbound' with timestamp (30-second loop prevention window) | Shopify API: `GET /admin/api/inventory_levels.json?inventory_item_ids={item_id}&location_ids={primary_loc_id}` -- Assert available=9 |
| 6 | System | EbayMarketplaceAdapter | EbayMarketplaceAdapter.updateQuantity() dispatches UpdateEbayInventory job with listing and type='quantity'. Job calls EbayInventoryService to update inventory item quantity to 9 | eBay listing quantity updated to 9 via Inventory API. EbayListing status remains 'published' (qty > 0) | eBay API: verify listing available quantity = 9. DB: `SELECT status FROM ebay_listings WHERE product_id = '{product_id}'` -- Assert 'published' |
| 7 | Shopify Buyer | Shopify Store | Purchase 1 unit of the product on the Shopify store. Shopify creates order and sends orders/create webhook to FlipStack webhook endpoint | Shopify order created. Webhook received at FlipStack API endpoint. ProcessShopifyWebhook job dispatched with topic='orders/create' | Application log: 'Processing Shopify webhook' with topic='orders/create' |
| 8 | System | Queue / ProcessShopifyWebhook | ProcessShopifyWebhook handles orders/create topic. Resolves IntegrationCredential by shop_domain. Calls ShopifyOrderSyncService.importOrder() with webhook payload. Order created (status=pending, marketplace=shopify). Items matched to Product via ShopifyListing. reserveInventoryForOrder() decrements available_quantity by 1 | Product.available_quantity = 8 (decremented from 9). Order created with marketplace='shopify'. PushInventoryToMarketplaces dispatched for the product | DB: `SELECT available_quantity FROM products WHERE id = '{product_id}'` -- Assert 8. `SELECT COUNT(*) FROM orders WHERE organization_id = '{org_id}' AND marketplace = 'shopify' AND status = 'pending'` -- Assert >= 1 |
| 9 | System | Queue / PushInventoryToMarketplaces | Second PushInventoryToMarketplaces job executes for the same product. MarketplaceInventorySync.pushQuantityToAll() pushes qty=8 to all marketplace adapters | ShopifyMarketplaceAdapter: setInventoryLevel called with qty=8. EbayMarketplaceAdapter: UpdateEbayInventory dispatched with qty=8 | Shopify API: Assert inventory = 8. eBay API: Assert listing qty = 8 |
| 10 | System | Shopify Webhook | Shopify may fire an inventory_levels/update webhook in response to Step 9's setInventoryLevel call. ProcessShopifyWebhook handles it. ShopifyListing's sync_direction_lock = 'outbound' with recent timestamp causes the handler to skip processing (loop prevention) | Webhook received but skipped: "Shopify webhook skipped - recent outbound sync" logged. No additional inventory changes. No additional PushInventoryToMarketplaces dispatched. Sync loop prevented | Log: 'Shopify webhook skipped - recent outbound sync' with listing_id |
| 11 | Tester | Database / API | Verify eventual consistency: Product.available_quantity = 8, eBay listing qty = 8, Shopify inventory = 8. All three values match | Complete consistency across system and both marketplaces. Total sales = 2 (1 eBay + 1 Shopify), starting qty was 10, current = 8 | DB: `SELECT available_quantity FROM products WHERE id = '{product_id}'` -- Assert 8. eBay API: listing qty = 8. Shopify API: inventory level = 8 |
| 12 | Tester | Simulation | Simulate oversell prevention: Manually set Product.available_quantity = 0 (or buy remaining stock). Trigger PushInventoryToMarketplaces | Product.available_quantity = 0. EbayMarketplaceAdapter dispatches UpdateEbayInventory with qty=0. EbayListing status may change to 'out_of_stock'. ShopifyMarketplaceAdapter sets inventory to 0 at all locations. Shopify product shows "Sold Out" | DB: `SELECT available_quantity FROM products WHERE id = '{product_id}'` -- Assert 0. `SELECT status FROM ebay_listings WHERE product_id = '{product_id}'` -- Assert 'out_of_stock' or 'published' (depends on eBay out-of-stock control). Shopify: inventory = 0 |
| 13 | Tester | Simulation | Simulate concurrent sales: Dispatch two OrderSyncService.importOrder() calls simultaneously (one eBay, one Shopify) for qty=1 each, starting from available_quantity=5 | Both orders created. Product.available_quantity = 3 (decremented atomically twice via DB-level decrement, not read-modify-write). Two PushInventoryToMarketplaces jobs dispatched. Last job to execute pushes final correct qty=3 to all marketplaces. No double-decrement or lost update | DB: `SELECT available_quantity FROM products WHERE id = '{product_id}'` -- Assert 3 (not 4, which would indicate a race condition). Both orders exist with correct items |

---

## 4. Data Flow Verification

| Checkpoint | After Step | Expected State | Verification Query/Check |
|------------|-----------|----------------|--------------------------|
| Initial Alignment | Step 1 | Product.available_quantity = 10, EbayListing qty = 10, ShopifyListing qty = 10 | DB + eBay API + Shopify API all return 10 |
| After eBay Sale | Step 3 | Product.available_quantity = 9. PushInventoryToMarketplaces job in queue or completed. Order exists with marketplace=ebay | `SELECT available_quantity FROM products WHERE id = '{product_id}'` -- 9. `SELECT COUNT(*) FROM orders WHERE marketplace_order_id = '{ebay_order_id}'` -- 1 |
| Shopify Receives Update | Step 5 | Shopify inventory level = 9 at primary location, 0 at other locations. ShopifySyncLog entry with operation='set_inventory', status='success' | Shopify API: `GET /admin/api/inventory_levels.json?inventory_item_ids={item_id}` -- primary location = 9. `SELECT status, operation FROM shopify_sync_logs WHERE product_id = '{product_id}' AND operation = 'set_inventory' ORDER BY created_at DESC LIMIT 1` -- status='success' |
| eBay Receives Update | Step 6 | eBay listing quantity = 9. EbaySyncLog entry for update_inventory (if created by adapter) | eBay API: verify listing qty. `SELECT * FROM ebay_sync_logs WHERE product_id = '{product_id}' AND operation = 'update_inventory' ORDER BY created_at DESC LIMIT 1` |
| After Shopify Sale | Step 8 | Product.available_quantity = 8. Second PushInventoryToMarketplaces dispatched. Shopify order exists | `SELECT available_quantity FROM products WHERE id = '{product_id}'` -- 8. `SELECT marketplace, status FROM orders WHERE organization_id = '{org_id}' ORDER BY created_at DESC LIMIT 2` -- one ebay, one shopify |
| Sync Loop Prevented | Step 10 | No additional decrement of available_quantity from Shopify inventory_levels/update webhook. Quantity remains 8, not 7 | `SELECT available_quantity FROM products WHERE id = '{product_id}'` -- Assert still 8 (not further decremented) |
| Final Consistency | Step 11 | All three values identical: Product.available_quantity = eBay qty = Shopify qty = 8 | Cross-reference DB, eBay API, and Shopify API |
| Oversell Prevention | Step 12 | All quantities = 0. No marketplace shows available stock. Buyers cannot purchase | eBay: listing ended or out_of_stock. Shopify: inventory = 0, cannot add to cart |
| Concurrent Safety | Step 13 | available_quantity = start - 2 (atomic decrements). Two orders exist. Two push jobs dispatched | DB: Assert available_quantity = 3. Assert 2 new orders exist. Assert final marketplace quantities = 3 |

---

## 5. Error Scenarios

| ID | Error Point | Error Condition | Expected Recovery | Verified |
|----|-------------|-----------------|-------------------|----------|
| E2E-4-ERR-01 | Step 5 | Shopify API returns 429 (Too Many Requests) during setInventoryLevel call | ShopifyMarketplaceAdapter throws Exception. Caught by MarketplaceInventorySync per-listing try/catch. Error logged: 'MarketplaceInventorySync: quantity update failed'. PushInventoryToMarketplaces job has tries=3, backoff=30s. Job retried with next available_quantity value (always reads fresh from Product). Shopify will eventually receive correct quantity | [ ] |
| E2E-4-ERR-02 | Step 6 | eBay API returns error during inventory quantity update (e.g., listing ended by eBay, account suspended) | UpdateEbayInventory job fails. EbaySyncLog created with status='error', error_message populated. Job retried per queue config. If listing was ended: EbayListing status should be updated to 'ended'. After max retries, org admins notified via MarketplaceInventorySyncFailedNotification | [ ] |
| E2E-4-ERR-03 | Step 4 | MarketplaceRegistry returns no adapters for the organization (no active credentials) | pushQuantityToAll() iterates empty adapters list. No API calls made. Job completes successfully (no-op). Quantities stay mismatched until credentials are activated | [ ] |
| E2E-4-ERR-04 | Step 4 | auto_sync_enabled = false on one or both credentials | MarketplaceInventorySync skips adapters with auto_sync_enabled=false. Logs: 'MarketplaceInventorySync: skipped, auto_sync_enabled is off'. Only enabled marketplaces receive quantity updates. Disabled marketplace quantities drift | [ ] |
| E2E-4-ERR-05 | Step 4 | MarketplaceSyncRuleEngine.shouldSync() returns false for product + platform combination | Adapter skipped with log: 'MarketplaceInventorySync: skipped by sync rule'. Quantity not pushed to that marketplace. Other marketplaces unaffected | [ ] |
| E2E-4-ERR-06 | Step 5 | ShopifyMarketplaceAdapter cannot resolve primary location (no shopify_location_id in config, getLocations() returns empty) | updateQuantity() logs warning: 'no location found' and returns false. Shopify inventory not updated. Other marketplace adapters still execute. Error surfaces in ShopifySyncLog | [ ] |
| E2E-4-ERR-07 | Step 8 | Shopify webhook received but no IntegrationCredential matches shop_domain | ProcessShopifyWebhook logs warning: 'No credential found for Shopify webhook'. Returns early. Order not imported. Inventory not reserved. Quantities drift until next sync | [ ] |
| E2E-4-ERR-08 | Step 3 | Network timeout during eBay order sync API call | OrderSyncService catches Exception in syncOrders() outer try/catch. Error added to errors array. Sync loop breaks. Partial results returned. Next scheduled sync will pick up missed orders | [ ] |
| E2E-4-ERR-09 | Step 13 | Concurrent decrements cause available_quantity to go negative | Product.decrementAvailableQuantity() uses DB-level `decrement()` (atomic). No floor check in current implementation. available_quantity may go negative. PushInventoryToMarketplaces pushes negative value. ShopifyMarketplaceAdapter passes negative to setInventoryLevel (Shopify API may reject or clamp to 0). EbayInventoryService may set listing to out_of_stock status | [ ] |
| E2E-4-ERR-10 | Step 9 | PushInventoryToMarketplaces job permanently fails after 3 retries | failed() method executes: EbaySyncLog entries created with status='error' for each published eBay listing. ShopifySyncLog entries created for each active Shopify listing. Org admins notified via MarketplaceInventorySyncFailedNotification. Job moved to failed_jobs table. Manual intervention required to resync | [ ] |
| E2E-4-ERR-11 | Step 5 | ShopifyMarketplaceAdapter fails to zero out secondary locations after setting primary | Primary location updated to correct qty. Secondary locations retain old quantities. Total Shopify available quantity may be higher than expected (sum of all locations). Risk: Shopify oversells because it sums all location quantities. Mitigation: error logged, manual correction needed | [ ] |

---

## 6. Performance Expectations

| Metric | Acceptable | Measured |
|--------|-----------|----------|
| Order import + inventory reservation (Steps 3/8) | < 3 seconds (DB transaction + decrement + job dispatch) | |
| PushInventoryToMarketplaces job execution | < 30 seconds (iterates all adapters, makes API calls) | |
| Shopify setInventoryLevel API call | < 3 seconds per call | |
| eBay UpdateEbayInventory job execution | < 10 seconds (single API call to Inventory API) | |
| Time from sale to all marketplaces updated (sync latency) | < 120 seconds (import + queue pickup + job execution + API calls) | |
| Shopify sync loop prevention check | < 1 millisecond (timestamp comparison) | |
| Concurrent sale resolution time | < 5 seconds (both atomic decrements complete, both push jobs dispatched) | |
| Queue job pickup latency (Horizon) | < 5 seconds from dispatch to processing start | |

---

## 7. Cleanup / Rollback

| Step | Action |
|------|--------|
| 1 | Restore Product.available_quantity to 10 (original test value): `UPDATE products SET available_quantity = 10 WHERE id = '{product_id}'` |
| 2 | Delete test Orders and OrderItems created during the test: `DELETE FROM orders WHERE organization_id = '{org_id}' AND created_at > '{test_start_time}'` |
| 3 | Reset eBay sandbox listing quantity to 10 via EbayInventoryService.updateInventoryQuantity() |
| 4 | Reset Shopify dev store inventory to 10 via ShopifyInventoryService.setInventoryLevel(locationId, inventoryItemId, 10) |
| 5 | Reset EbayListing.status to 'published' if changed to 'out_of_stock' during oversell test |
| 6 | Clear ShopifyListing.sync_direction_lock: `UPDATE shopify_listings SET sync_direction_lock = NULL WHERE product_id = '{product_id}'` |
| 7 | Delete EbaySyncLog entries from test: `DELETE FROM ebay_sync_logs WHERE product_id = '{product_id}' AND created_at > '{test_start_time}'` |
| 8 | Delete ShopifySyncLog entries from test: `DELETE FROM shopify_sync_logs WHERE product_id = '{product_id}' AND created_at > '{test_start_time}'` |
| 9 | Clear failed_jobs table entries: `DELETE FROM failed_jobs WHERE payload LIKE '%{product_id}%'` |
| 10 | Verify no orphaned PushInventoryToMarketplaces jobs remain in queue: check Horizon dashboard |
| 11 | Flush Redis queue if needed: `php artisan queue:clear` (only in test environment) |

---

## Notes

- **Single source of truth:** Product.available_quantity is the canonical inventory value. All marketplace quantities are derived from this value via PushInventoryToMarketplaces. Marketplace-side quantity changes (e.g., eBay admin edit) are NOT automatically synced back unless an order is created. This is intentional: FlipStack is the system of record.
- **Sync direction and loop prevention:** When FlipStack pushes inventory to Shopify (outbound), the ShopifyMarketplaceAdapter does NOT set sync_direction_lock directly. However, the ShopifyListing's last_synced_at is updated. When a Shopify inventory_levels/update webhook arrives, ProcessShopifyWebhook checks if sync_direction_lock = 'outbound' and last_synced_at is within 30 seconds. If so, the webhook is skipped. This prevents A->B->A->B infinite loops.
- **eBay does NOT have webhook-triggered sync loops:** eBay inventory updates do not trigger webhooks back to FlipStack (unlike Shopify). eBay Change Notifications (ECN) are configured only for orders, not inventory. So eBay quantity updates are fire-and-forget.
- **Atomic decrement is critical:** Product.decrementAvailableQuantity() must use Eloquent's `decrement()` method (which generates `UPDATE products SET available_quantity = available_quantity - 1 WHERE id = ?`), NOT a read-modify-write pattern. This prevents race conditions in concurrent sale scenarios.
- **Shopify multi-location zeroing:** ShopifyMarketplaceAdapter zeros all non-primary locations after setting the primary. This is because Shopify sums available quantities across all locations. Without zeroing, the total available would be incorrect. If zeroing fails, total Shopify quantity may be inflated.
- **Adapter pattern:** MarketplaceRegistry returns MarketplaceAdapter implementations (EbayMarketplaceAdapter, ShopifyMarketplaceAdapter) for each active credential. MarketplaceInventorySync is adapter-agnostic. Adding a new marketplace (Amazon, Walmart) requires implementing MarketplaceAdapter and registering in MarketplaceRegistry.
- **Queue ordering not guaranteed:** Two PushInventoryToMarketplaces jobs for the same product may execute in any order. Each job reads fresh available_quantity from the database at execution time (not at dispatch time). The last job to execute pushes the final correct value, making eventual consistency self-healing.
- **eBay listing status transitions on quantity:** When quantity > 0: EbayListing.status stays 'published'. When quantity = 0: EbayInventoryService may set status to 'out_of_stock' (if eBay out-of-stock control is enabled). When quantity goes from 0 back to positive: status returns to 'published'.
- **Testing concurrent sales:** Use database-level transactions or parallel HTTP requests to simulate concurrent eBay and Shopify sales. Verify with `SELECT available_quantity` that the final value equals initial - total_sold, not initial - max(sold_per_channel).
- **Negative quantity risk:** Current implementation does not enforce a floor of 0 on available_quantity. If concurrent sales exceed available stock, quantity goes negative. Marketplaces handle this differently: Shopify may reject negative values (API error), eBay clamps to 0. Monitor for negative quantities in production: `SELECT id, available_quantity FROM products WHERE available_quantity < 0`.
