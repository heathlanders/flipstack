# Order to Delivery End-to-End Test

## Document ID: TC-E2E-001

**Systems Involved:** Web, API, eBay/Shopify, Queue (Redis/Horizon), ShippingEasy/EasyPost/ShipStation, Tracking Webhooks
**Business Process:** A marketplace order is placed by a buyer, imported into FlipStack, fulfilled through the pick/pack/ship workflow, and tracking is uploaded back to the originating marketplace through delivery confirmation.
**Last Updated:** 2026-04-15

---

## 1. Workflow Overview

This test validates the complete order lifecycle from marketplace sale through delivery. It exercises the OrderSyncService import pipeline, inventory reservation, cross-marketplace quantity push, the full fulfillment state machine (pending -> assigned -> picking -> picked -> packing -> packed -> shipped -> in_transit -> delivered), label purchase, tracking upload, and delivery confirmation.

```
  Marketplace Sale           Order Import              Inventory Reserved
  (eBay or Shopify)  ------> (webhook/sync) ---------> (available_qty decremented)
        |                         |                              |
        |                         v                              v
        |                  Order created               PushInventoryToMarketplaces
        |                  (status=pending)             (qty pushed to other channels)
        |                         |
        |                         v
        |                  Assign to Location
        |                  (status=assigned)
        |                         |
        |                         v
        |                  Start Picking -------> Pick Items -------> Complete Picking
        |                  (status=picking)   (per item picked)     (status=picked)
        |                         |
        |                         v
        |                  Start Packing -------> Set Dimensions ----> Complete Packing
        |                  (status=packing)                          (status=packed)
        |                         |
        |                         v
        |                  Get Shipping Rates
        |                  (EasyPost/ShippingEasy/ShipStation)
        |                         |
        |                         v
        |                  Purchase Label
        |                  (tracking_number assigned, label_url set)
        |                         |
        |                         v
        |                  Ship Order
        |                  (status=shipped, inventory_on_hand decremented)
        |                         |
        |                         v
        |                  Upload Tracking -------> UploadTrackingToEbay/Shopify job
        |                  (tracking_uploaded_to_marketplace=true)
        |                         |
        |                         v
        |                  Tracking Webhook (EasyPost/carrier)
        |                  (status=in_transit -> delivered)
        |                         |
        |                         v
        v                  Order status=delivered
  Buyer receives item      (actual_delivery_date set)
```

**Trigger:** Marketplace buyer completes checkout (eBay Fulfillment API poll or Shopify `orders/create` webhook)
**End State:** Order.status = `delivered`, Shipment.status = `delivered`, tracking uploaded to marketplace, inventory_on_hand decremented at warehouse location, available_quantity already decremented from reservation at import time

---

## 2. Preconditions

| # | Requirement | Setup Method |
|---|-------------|--------------|
| 1 | Organization with active subscription and configured SKU prefix | Factory / Seeder |
| 2 | eBay IntegrationCredential (platform=ebay, is_active=true) with valid OAuthToken | Factory with sandbox credentials |
| 3 | Shopify IntegrationCredential (platform=shopify, is_active=true) with shop_domain in config | Factory with sandbox credentials |
| 4 | Product exists with available_quantity >= 2, linked to EbayListing (status=published) and ShopifyListing (status=active) | Factory |
| 5 | InventoryLocation exists with address fields populated (for shipping origin) | Factory |
| 6 | InventoryLevel exists for Product at the InventoryLocation with quantity_on_hand >= 2 | Factory |
| 7 | User with fulfillment permissions (picker and packer roles) | Factory |
| 8 | Shipping provider configured in OrganizationSettings (fulfillment_shipping_provider = easypost/shippingeasy/shipstation) | Seeder / Manual |
| 9 | EasyPost/ShippingEasy/ShipStation API key configured for the Organization | Environment / Seeder |
| 10 | auto_sync_enabled = true on all IntegrationCredentials | Factory |
| 11 | Redis and Horizon running (queue jobs must execute) | Docker environment |
| 12 | CarrierAccount configured for the organization (if using EasyPost) | Factory / Seeder |

---

## 3. Test Scenario

| Step | Actor | System | Action | Expected Result | Verification Method |
|------|-------|--------|--------|-----------------|---------------------|
| 1 | Buyer | eBay Marketplace | Places order for Product A (qty=1) on eBay | eBay creates order with orderId, lineItems, pricing, and shipping address | eBay sandbox order confirmation |
| 2 | System (Cron/Webhook) | Queue / OrderSyncService | `OrderSyncService::syncOrders()` fetches recent orders from eBay Fulfillment API (`/sell/fulfillment/v1/order`) OR eBay ECN webhook triggers `ProcessEbayNotification` job | Order record created in `orders` table with status=`pending`, marketplace=`ebay`, marketplace_order_id set, buyer info populated, items_count matches line items | DB: `SELECT * FROM orders WHERE marketplace_order_id = '{ebay_order_id}'` -- Assert status='pending', marketplace='ebay' |
| 3 | System | OrderSyncService | `matchOrderItemsToProducts()` links order items to Product via EbayListing.ebay_listing_id match, then falls back to SKU match | OrderItem.product_id populated, OrderItem.ebay_listing_id populated, OrderItem.supplier_id set from product | DB: `SELECT oi.product_id, oi.ebay_listing_id FROM order_items oi WHERE oi.order_id = '{order_id}'` -- Assert product_id NOT NULL |
| 4 | System | OrderSyncService | `reserveInventoryForOrder()` decrements `Product.available_quantity` by ordered quantity and dispatches `PushInventoryToMarketplaces` for each affected product | Product.available_quantity decremented by 1 (from original value). PushInventoryToMarketplaces job dispatched on default queue | DB: `SELECT available_quantity FROM products WHERE id = '{product_id}'` -- Assert = original - 1. Queue: verify job dispatched via Horizon dashboard or `jobs` table |
| 5 | System | Queue / MarketplaceInventorySync | `PushInventoryToMarketplaces` job executes, calling `MarketplaceInventorySync::pushQuantityToAll()`. For each marketplace adapter (Ebay, Shopify) where auto_sync_enabled=true: EbayMarketplaceAdapter dispatches `UpdateEbayInventory` job; ShopifyMarketplaceAdapter calls `ShopifyInventoryService::setInventoryLevel()` directly | eBay inventory item quantity updated via Inventory API. Shopify inventory level set to new available_quantity at primary location, other locations zeroed | eBay: Verify via eBay sandbox that available quantity matches. Shopify: `GET /admin/api/inventory_levels.json` for the inventory_item_id -- Assert available = new qty |
| 6 | Fulfillment Manager | Web UI | Assigns order to InventoryLocation via `FulfillmentService::assignOrder()` | Order.status = `assigned`, Order.location_id set, all OrderItems.location_id set. FulfillmentLog entry with action=`order_assigned` | DB: `SELECT status, location_id FROM orders WHERE id = '{order_id}'` -- Assert status='assigned', location_id NOT NULL |
| 7 | Picker | Web UI / Mobile | Starts picking via `FulfillmentService::startPicking()`, then picks each item via `FulfillmentService::pickItem()`, then completes via `FulfillmentService::completePicking()` | Order progresses: status=`picking` -> items get pick_status=`picked`, picked_at set, picked_by set -> status=`picked`. FulfillmentLog entries for pick_started, item_picked, pick_completed | DB: `SELECT status FROM orders WHERE id = '{order_id}'` -- Assert 'picked'. `SELECT pick_status, picked_at, picked_by FROM order_items WHERE order_id = '{order_id}'` -- Assert all pick_status='picked' |
| 8 | Packer | Web UI / Mobile | Starts packing via `FulfillmentService::startPacking()`, then completes via `FulfillmentService::completePacking()` with package dimensions (weight_oz, length, width, height) | Order.status = `packed`. FulfillmentLog entries for pack_started, pack_completed with package dimensions | DB: `SELECT status FROM orders WHERE id = '{order_id}'` -- Assert 'packed' |
| 9 | Packer | Web UI | Creates shipment via `ShipmentService::createShipment()` then gets shipping rates via `ShipmentService::getRates()` | Shipment record created with status=`pending`, ship_from address from location. Rates returned from configured provider (EasyPost/ShippingEasy/ShipStation) with carrier, service, rate amount | DB: `SELECT * FROM shipments WHERE order_id = '{order_id}'` -- Assert status='pending'. UI: Verify rates array is non-empty with carrier and price fields |
| 10 | Packer | Web UI | Purchases shipping label via `ShipmentService::purchaseLabel()` with selected rate_id | Shipment updated: status=`label_purchased`, tracking_number set, tracking_url set, label_url set, carrier set, service set, shipping_cost set | DB: `SELECT status, tracking_number, label_url, carrier FROM shipments WHERE order_id = '{order_id}'` -- Assert status='label_purchased', tracking_number NOT NULL |
| 11 | Fulfillment Manager | Web UI | Ships order via `FulfillmentService::shipOrder()` | Order.status = `shipped`, Order.shipped_at set. InventoryLevel.quantity_on_hand decremented for each picked item. InventoryLevel.quantity_reserved decremented. `TrackingService::dispatchTrackingUpload()` dispatches `UploadTrackingToEbay` (or `UploadTrackingToShopify`) job | DB: `SELECT status, shipped_at FROM orders WHERE id = '{order_id}'` -- Assert status='shipped'. `SELECT quantity_on_hand FROM inventory_levels WHERE product_id = '{product_id}' AND location_id = '{location_id}'` -- Assert decremented. Queue: UploadTrackingToEbay job dispatched |
| 12 | System | Queue / TrackingService | `UploadTrackingToEbay` job executes `OrderSyncService::uploadTracking()`, POSTing to eBay `/sell/fulfillment/v1/order/{orderId}/shipping_fulfillment` with tracking number and carrier | Shipment.tracking_uploaded_to_marketplace = true, Shipment.tracking_uploaded_at set. eBay order shows tracking in seller hub | DB: `SELECT tracking_uploaded_to_marketplace, tracking_uploaded_at FROM shipments WHERE order_id = '{order_id}'` -- Assert tracking_uploaded_to_marketplace=true |
| 13 | System (Webhook) | TrackingService | EasyPost tracking webhook fires with status updates. `TrackingService::processTrackingUpdate()` maps status through `in_transit` -> `out_for_delivery` -> `delivered`. On `delivered`, `handleDelivery()` runs and `syncOrderStatus()` updates Order | Shipment.status progresses through in_transit -> delivered. Shipment.actual_delivery_date set. Order.status = `delivered`, Order.delivered_at set | DB: `SELECT status, actual_delivery_date FROM shipments WHERE order_id = '{order_id}'` -- Assert status='delivered'. `SELECT status, delivered_at FROM orders WHERE id = '{order_id}'` -- Assert status='delivered', delivered_at NOT NULL |

---

## 4. Data Flow Verification

| Checkpoint | After Step | Expected State | Verification Query/Check |
|------------|-----------|----------------|--------------------------|
| Order Created | Step 2 | Order exists with status=pending, marketplace=ebay, correct buyer info, items_count > 0, organization_id matches credential | `SELECT id, status, marketplace, marketplace_order_id, buyer_name, items_count, organization_id FROM orders WHERE marketplace_order_id = '{ebay_order_id}'` |
| Items Matched | Step 3 | All OrderItems have product_id linked via EbayListing match or SKU fallback | `SELECT id, product_id, ebay_listing_id, sku, marketplace_item_id FROM order_items WHERE order_id = '{order_id}' AND product_id IS NULL` -- Assert empty result set |
| Inventory Reserved | Step 4 | Product.available_quantity = original - order_quantity. No InventoryLevel changes yet (on_hand unchanged until ship) | `SELECT available_quantity FROM products WHERE id = '{product_id}'` -- Assert = original_qty - 1 |
| Cross-Marketplace Synced | Step 5 | Shopify listing inventory_quantity matches new available_quantity. eBay listing available quantity matches | Shopify API: `GET /admin/api/inventory_levels.json?inventory_item_ids={item_id}&location_ids={loc_id}` -- Assert available = new_qty. eBay sandbox: check item quantity |
| Pick Complete | Step 7 | Order.status = picked. All items: pick_status = picked OR short. FulfillmentLog has pick_completed action | `SELECT status FROM orders WHERE id = '{order_id}'` -- Assert 'picked'. `SELECT pick_status FROM order_items WHERE order_id = '{order_id}'` -- Assert all in ('picked', 'short') |
| Label Purchased | Step 10 | Shipment has tracking_number, label_url, carrier, service, shipping_cost. Shipment.status = label_purchased | `SELECT tracking_number, label_url, carrier, service, shipping_cost, status FROM shipments WHERE order_id = '{order_id}'` -- Assert all NOT NULL |
| Shipped & Tracked | Step 12 | Order.status = shipped. Shipment.tracking_uploaded_to_marketplace = true. inventory_levels.quantity_on_hand decremented | `SELECT o.status, s.tracking_uploaded_to_marketplace FROM orders o JOIN shipments s ON s.order_id = o.id WHERE o.id = '{order_id}'` -- Assert status='shipped', tracking_uploaded_to_marketplace=true |
| Delivered | Step 13 | Order.status = delivered, Order.delivered_at set. Shipment.status = delivered, Shipment.actual_delivery_date set | `SELECT o.status, o.delivered_at, s.status as shipment_status, s.actual_delivery_date FROM orders o JOIN shipments s ON s.order_id = o.id WHERE o.id = '{order_id}'` -- Assert all populated |

---

## 5. Error Scenarios

| ID | Error Point | Error Condition | Expected Recovery | Verified |
|----|-------------|-----------------|-------------------|----------|
| E2E-1-ERR-01 | Step 2 | eBay order payload is malformed (missing orderId, null lineItems, or invalid pricing) | `importOrder()` throws Exception, caught in syncOrders() loop. Error logged with order_id context. Other orders in batch continue processing. Error returned in sync result `errors` array | [ ] |
| E2E-1-ERR-02 | Step 3 | OrderItem cannot be matched to any Product (no EbayListing match, no SKU match) | OrderItem.product_id remains NULL. Order still created successfully. Item appears as "unmatched" in fulfillment UI. Inventory NOT reserved for unmatched items (guard: `if (! $item->product_id) continue`) | [ ] |
| E2E-1-ERR-03 | Step 4 | Product.available_quantity is already 0 or less when reservation attempted | `decrementAvailableQuantity()` may go negative (no floor guard in current implementation). PushInventoryToMarketplaces still dispatched. Other marketplaces receive qty=0 or negative (clamped at adapter level). Log warning emitted. Order import never fails due to reservation error (try/catch with continue) | [ ] |
| E2E-1-ERR-04 | Step 5 | Shopify API returns 429 (rate limited) during inventory push | ShopifyMarketplaceAdapter throws Exception, caught by MarketplaceInventorySync per-listing try/catch. Error logged. PushInventoryToMarketplaces job has tries=3, backoff=30s. Job retried automatically by queue | [ ] |
| E2E-1-ERR-05 | Step 5 | eBay API returns error during inventory update | UpdateEbayInventory job fails. EbaySyncLog created with status='error'. After 3 failures, org admins notified via MarketplaceInventorySyncFailedNotification | [ ] |
| E2E-1-ERR-06 | Step 7 | Item cannot be found at expected location (pick shorted) | Picker calls `FulfillmentService::shortItem()`. Item marked pick_status='short'. Pick can still complete (short items counted). FulfillmentLog records short reason. Order may proceed to packing with partial items | [ ] |
| E2E-1-ERR-07 | Step 10 | Label purchase fails (invalid address, carrier rejection, API timeout) | `ShipmentService::purchaseLabel()` throws Exception. Shipment stays in status='pending'. Error displayed in UI. User can correct address/dimensions and retry | [ ] |
| E2E-1-ERR-08 | Step 12 | Tracking upload to eBay fails (invalid fulfillment, API error) | `OrderSyncService::uploadTracking()` catches Exception, logs error, returns false. Shipment.tracking_uploaded_to_marketplace stays false. `TrackingService::retryFailedTrackingUploads()` (scheduled command) picks it up later for retry | [ ] |
| E2E-1-ERR-09 | Step 11 | Order shipped but FulfillmentService fails to dispatch tracking upload | shipOrder() catches Exception from TrackingService dispatch in try/catch, logs warning. Order still transitions to shipped. Tracking upload handled by scheduled retry (`retryFailedTrackingUploads`) | [ ] |
| E2E-1-ERR-10 | Step 2 | Duplicate order import (same marketplace_order_id already exists) | `importOrder()` finds existing order via `marketplace_order_id` WHERE clause. Updates existing record instead of creating new one. Returns `created=false`. No duplicate inventory reservation (guard: `if ($isNew)`) | [ ] |

---

## 6. Performance Expectations

| Metric | Acceptable | Measured |
|--------|-----------|----------|
| Order import (single order, Steps 2-4) | < 3 seconds (including DB transaction + inventory reservation + job dispatch) | |
| PushInventoryToMarketplaces job execution (Step 5) | < 30 seconds per product across all marketplace adapters | |
| Pick workflow (Steps 7, all items) | < 2 seconds per item pick action (DB update + audit log) | |
| Shipping rate retrieval (Step 9) | < 5 seconds (external API call to EasyPost/ShippingEasy) | |
| Label purchase (Step 10) | < 10 seconds (external API call, label generation) | |
| Tracking upload to marketplace (Step 12) | < 5 seconds (single eBay/Shopify API call) | |
| End-to-end completion (Steps 2-13, excluding buyer/carrier transit time) | < 15 minutes for all system-driven steps | |
| Queue job pickup latency (Horizon) | < 5 seconds from dispatch to processing start | |

---

## 7. Cleanup / Rollback

| Step | Action |
|------|--------|
| 1 | Delete test Order, OrderItems, Shipments, ShipmentItems from database (cascade via order_id) |
| 2 | Restore Product.available_quantity to original value |
| 3 | Restore InventoryLevel.quantity_on_hand and quantity_reserved to original values |
| 4 | Delete FulfillmentLog entries for the test order |
| 5 | If eBay sandbox listing was affected: restore inventory quantity via eBay Inventory API |
| 6 | If Shopify dev store listing was affected: reset inventory level via Shopify Admin API |
| 7 | Clear failed_jobs table entries related to test: `DELETE FROM failed_jobs WHERE payload LIKE '%{product_id}%'` |
| 8 | Clear EbaySyncLog and ShopifySyncLog entries created during test |
| 9 | Void/refund any purchased labels in shipping provider dashboard (EasyPost/ShippingEasy) to avoid charges |
| 10 | Flush Redis queue: `php artisan queue:clear` (only in test environment) |

---

## Notes

- **Timing dependency:** Steps 2-5 are tightly coupled. The inventory reservation (Step 4) and marketplace push (Step 5) happen synchronously within `importOrder()` for reservation, but the push is asynchronous via queue. Allow up to 60 seconds for the PushInventoryToMarketplaces job to complete before verifying Shopify/eBay quantities.
- **eBay rate limits:** eBay Inventory API is rate-limited. The `EbayRateLimiter` service throttles calls. In sandbox, limits are lower. Space eBay-touching steps by at least 1 second.
- **Shopify sync loop prevention:** The ShopifyMarketplaceAdapter sets `sync_direction_lock='outbound'` with a 30-second window. If a Shopify `inventory_levels/update` webhook fires within 30 seconds of an outbound push, it is skipped to prevent ping-pong loops.
- **Shipping provider variability:** The ShipmentService dynamically selects EasyPost, ShippingEasy, or ShipStation based on `OrganizationSettings.fulfillment_shipping_provider`. Test with the configured provider. Rate and label responses differ by provider.
- **Tracking webhook source:** EasyPost sends tracking webhooks to the configured webhook URL. In test, you may need to simulate this by calling `TrackingService::processTrackingUpdate()` directly with a mock payload.
- **Multi-tenant isolation:** All queries are organization-scoped via the `BelongsToOrganization` trait. Verify that no data from other organizations leaks into queries during this workflow.
- **Delivery status mapping:** `TrackingService::mapTrackingStatus()` maps carrier-specific status strings to Shipment status constants. The exact mapping depends on the carrier. Common: "delivered" -> STATUS_DELIVERED, "in_transit" -> STATUS_IN_TRANSIT.
- **Inventory double-decrement risk:** available_quantity is decremented at import (Step 4) for reservation. quantity_on_hand is decremented at ship (Step 11). These are intentionally separate: available_quantity reflects sellable stock across marketplaces, while quantity_on_hand reflects physical warehouse stock.
