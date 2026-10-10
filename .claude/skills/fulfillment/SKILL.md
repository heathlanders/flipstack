---
name: fulfillment
description: Order import, pick/pack/ship workflow, shipping providers, tracking upload, returns processing, and dropship fulfillment
user-invocable: false
---

# Fulfillment System

## Key Files

| Area | File | Purpose |
|------|------|---------|
| **Models** | `app/Models/Fulfillment/Order.php` | Order with 13 statuses, priority, fulfillment mode |
| | `app/Models/Fulfillment/OrderItem.php` | Line items with pick status (pending/picked/short) |
| | `app/Models/Fulfillment/Shipment.php` | Shipment with 10 statuses, provider-specific IDs |
| | `app/Models/Fulfillment/ShipmentItem.php` | Items packed into a shipment |
| | `app/Models/Fulfillment/ReturnRequest.php` | RMA with 8 statuses, source tracking |
| | `app/Models/Fulfillment/CarrierAccount.php` | USPS/UPS/FedEx/DHL carrier configs |
| | `app/Models/Fulfillment/PackagePreset.php` | Saved box/envelope/soft_pack/custom dimensions |
| | `app/Models/Fulfillment/ShippingRate.php` | Cached rate quotes (1-hour TTL) |
| | `app/Models/Fulfillment/FulfillmentLog.php` | Polymorphic audit trail (30+ action types) |
| | `app/Models/Fulfillment/ShippingEasySyncLog.php` | Audit trail for all ShippingEasy API operations |
| | `app/Models/Fulfillment/SupplierFulfillmentRequest.php` | Dropship routing with 8 statuses |
| **Services** | `app/Services/Fulfillment/OrderSyncService.php` | eBay order import, product matching, inventory reservation, refund |
| | `app/Services/Fulfillment/ShopifyOrderSyncService.php` | Shopify order import, product matching, inventory reservation, fulfillment upload |
| | `app/Services/Fulfillment/AmazonOrderSyncService.php` | Amazon order import, product matching, inventory reservation, feed-based tracking |
| | `app/Services/Fulfillment/FulfillmentService.php` | Pick/pack/ship workflow, auto-assignment, dropship routing |
| | `app/Services/Fulfillment/ShipmentService.php` | Label purchase/void, rate shopping, shipment splitting |
| | `app/Services/Fulfillment/TrackingService.php` | Webhook processing, status sync, marketplace tracking upload |
| | `app/Services/Fulfillment/EasyPostService.php` | EasyPost API: rates, labels, address verification, trackers |
| | `app/Services/Fulfillment/ShipStationService.php` | ShipStation API: rates, labels, order creation |
| | `app/Services/Fulfillment/ShippingEasyService.php` | ShippingEasy API: rates, orders, labels, carriers, HMAC-signed, sync-logged |
| | `app/Services/Fulfillment/ReturnService.php` | RMA lifecycle: create, approve, receive, inspect, refund, restock |
| | `app/Services/Fulfillment/FulfillmentAuditService.php` | Polymorphic logging for all fulfillment actions |
| | `app/Services/Fulfillment/FulfillmentPrintService.php` | Packing slips, pick lists, label printing (network printers) |
| **Controllers** | `app/Http/Controllers/Fulfillment/OrderController.php` | Order CRUD, assignment, hold/release, cancel |
| | `app/Http/Controllers/Fulfillment/PickController.php` | Start picking, pick/short items, complete pick |
| | `app/Http/Controllers/Fulfillment/PackController.php` | Start packing, rate shopping, label purchase, complete pack |
| | `app/Http/Controllers/Fulfillment/ShipController.php` | Ship order, void label, split shipment |
| | `app/Http/Controllers/Fulfillment/ReturnController.php` | RMA workflow: create through refund/deny |
| | `app/Http/Controllers/Fulfillment/SettingsController.php` | Shipping provider, workflow, label, return policy settings |
| | `app/Http/Controllers/Fulfillment/DocumentController.php` | Packing slip and pick list generation |
| | `app/Http/Controllers/Fulfillment/TrackingWebhookController.php` | EasyPost webhook endpoint |
| | `app/Http/Controllers/Fulfillment/ShipStationWebhookController.php` | ShipStation webhook endpoint |
| | `app/Http/Controllers/Fulfillment/ShippingEasyWebhookController.php` | ShippingEasy webhook endpoint |
| | `app/Http/Controllers/Api/MobilePickController.php` | Mobile picking API: list, start/complete, pick/short items, barcode verify (routes in `routes/api.php`) |
| | `app/Http/Controllers/Api/MobileOrderController.php` | Mobile orders API: list, detail, assign, hold/release |
| | `app/Http/Controllers/Api/MobilePackController.php` | Mobile packing API: queue, start, rates, label purchase, complete |
| | `app/Http/Controllers/Api/MobileShipController.php` | Mobile shipping API: queue, confirm shipped, void label |
| | `app/Http/Controllers/Api/MobileReturnController.php` | Mobile returns API: list, detail, approve, receive, inspect, restock |
| **Jobs** | `app/Jobs/Fulfillment/SyncEbayOrders.php` | Poll eBay orders (queue: `ebay`, 120s timeout, 2 tries) |
| | `app/Jobs/Fulfillment/SyncShopifyOrders.php` | Poll Shopify orders (queue: `shopify`, 120s timeout, 2 tries) |
| | `app/Jobs/Fulfillment/SyncAmazonOrders.php` | Poll Amazon orders (queue: `amazon`, 120s timeout, 2 tries) |
| | `app/Jobs/Fulfillment/SyncSingleEbayOrder.php` | Import one eBay order (queue: `ebay`, 60s, 3 tries) |
| | `app/Jobs/Fulfillment/UploadTrackingToEbay.php` | Push tracking to eBay (queue: `ebay`, 30s, 3 tries) |
| | `app/Jobs/Fulfillment/UploadTrackingToShopify.php` | Push tracking to Shopify (queue: `shopify`, 30s, 3 tries) |
| | `app/Jobs/Fulfillment/UploadTrackingToAmazon.php` | Push tracking to Amazon via feed (queue: `amazon`, 30s, 3 tries) |
| | `app/Jobs/Fulfillment/SyncRefundToMarketplace.php` | Sync refund to eBay/Shopify/Amazon (queue: `fulfillment`, 60s, 3 tries) |
| | `app/Jobs/Fulfillment/AutoAssignOrders.php` | Auto-assign pending orders to locations (queue: `fulfillment`) |
| | `app/Jobs/Fulfillment/ProcessTrackingWebhook.php` | Process EasyPost tracking updates (queue: `fulfillment`) |
| | `app/Jobs/Fulfillment/ProcessShipStationWebhook.php` | Process ShipStation webhooks (queue: `fulfillment`) |
| | `app/Jobs/Fulfillment/ProcessShippingEasyWebhook.php` | Process ShippingEasy webhooks (queue: `fulfillment`) |
| | `app/Jobs/Fulfillment/CheckDropshipSLA.php` | Alert on overdue supplier fulfillment requests |
| | `app/Jobs/Fulfillment/CheckStaleShipments.php` | Flag shipments with no delivery update in 7+ days |
| | `app/Jobs/Fulfillment/CreateSettlementItems.php` | Generate settlement records (queue: `settlements`) |
| **Routes** | `routes/fulfillment.php` | All fulfillment routes (auth + 3 unauthenticated webhooks) |

## Order Status State Machine

```
PENDING -----> ASSIGNED -----> PICKING -----> PICKED -----> PACKING -----> PACKED -----> SHIPPED
   |              |                                                                        |
   |              v                                                                        v
   |           ON_HOLD <--- (any non-terminal)                                        IN_TRANSIT
   |              |                                                                        |
   v              v                                                                        v
CANCELLED     ASSIGNED (releaseOrder)                                                 DELIVERED
                                                                                        |
                                                                              RETURN_INITIATED -> RETURNED
```

### Status Constants
| Status | Value | Guard Method | Trigger |
|--------|-------|--------------|---------|
| `STATUS_PENDING` | `pending` | -- | Initial state on import |
| `STATUS_ASSIGNED` | `assigned` | -- | `assignOrder()` or auto-assign; also from eBay `IN_PROGRESS` |
| `STATUS_PICKING` | `picking` | `canBePicked()` | `startPicking()` |
| `STATUS_PICKED` | `picked` | -- | `completePicking()` (all items picked/shorted) |
| `STATUS_PACKING` | `packing` | `canBePacked()` | `startPacking()` |
| `STATUS_PACKED` | `packed` | -- | `completePacking()` |
| `STATUS_SHIPPED` | `shipped` | `canBeShipped()` | `shipOrder()`, `markShipped()`, or `manualShip()` (manual tracking entry) |
| `STATUS_IN_TRANSIT` | `in_transit` | -- | Tracking webhook |
| `STATUS_DELIVERED` | `delivered` | -- | All shipments delivered |
| `STATUS_CANCELLED` | `cancelled` | -- | `cancelOrder()` (unreserves inventory) |
| `STATUS_ON_HOLD` | `on_hold` | -- | `holdOrder()` from any non-terminal |
| `STATUS_RETURN_INITIATED` | `return_initiated` | -- | `createReturn()` |
| `STATUS_RETURNED` | `returned` | -- | `processRefund()` |

### Guard Methods
- `canBePicked()`: status in `[assigned, picking]`
- `canBePacked()`: status in `[picked, packing]`
- `canBeShipped()`: status in `[packed]`

### Priority Levels
- `standard`, `high`, `urgent` -- pick list sorted by `array_position(['urgent','high','standard'], priority)` then `ship_by_date`

### Fulfillment Modes
- `org_fulfilled` -- internal warehouse pick/pack/ship
- `supplier_dropship` -- routed to supplier via `SupplierFulfillmentRequest`

## Shipment Status State Machine

```
PENDING -> LABEL_PURCHASED -> PACKED -> SHIPPED -> IN_TRANSIT -> OUT_FOR_DELIVERY -> DELIVERED
                                                                                      |
LABEL_PURCHASED -> CANCELLED (voidLabel)            SHIPPED/IN_TRANSIT -> FAILED / RETURNED_TO_SENDER
```

## Return Request State Machine

```
REQUESTED -> APPROVED -> RECEIVED -> INSPECTED -> REFUNDED (sets order to RETURNED)
                                        |
REQUESTED -> DENIED                  INSPECTED -> DENIED
```

Sources: `buyer`, `internal`, `marketplace`. RMA numbers: `RMA-{Ymd}-{0001}`.

## Order Import Flow (All Marketplaces -> Inventory Sync)

This is the critical bridge between "item sells on marketplace" and "sync quantity everywhere". All three marketplaces follow the same pattern:

### eBay
1. **`SyncEbayOrders` job** polls eBay Fulfillment API (`GET /sell/fulfillment/v1/order`, last 2 hours, pages of 50)
2. **`OrderSyncService::importOrder()`** upserts Order + OrderItems in DB transaction
3. **`matchOrderItemsToProducts()`** matches by EbayListing first, then by SKU
4. **`reserveInventoryForOrder()`** calls `Product::decrementAvailableQuantity($qty)` for each matched item
5. **`PushInventoryToMarketplaces` job** dispatched per affected product (see `marketplace-sync` skill). Respects `auto_sync_enabled` flag -- skips marketplaces where the flag is off.

### Shopify
1. **`SyncShopifyOrders` job** polls Shopify Orders API (paid orders, last 2 hours)
2. **`ProcessShopifyWebhook`** also imports on `orders/create` (real-time), handles `orders/cancelled` and `orders/updated`
3. **`ShopifyOrderSyncService::importOrder()`** upserts Order + OrderItems
4. **`matchOrderItemsToProducts()`** matches by `ShopifyListing.shopify_variant_id` first, then SKU
5. Same reservation + inventory push as eBay

### Amazon
1. **`SyncAmazonOrders` job** polls Amazon SP-API (`/orders/v0/orders`, Unshipped/PartiallyShipped)
2. **`AmazonOrderSyncService::importOrder()`** upserts Order + OrderItems, fetches items per order
3. **`matchOrderItemsToProducts()`** matches by `AmazonListing.asin`, then `amazon_sku`, then SKU
4. **`acknowledgeOrder()`** acknowledges receipt to Amazon
5. Same reservation + inventory push as eBay

**Key:** reservation never fails the order import -- errors are logged but the order still saves.

## Multi-Marketplace Tracking Upload

When `FulfillmentService::shipOrder()` runs, it automatically dispatches tracking upload jobs for each shipment:

```
TrackingService::dispatchTrackingUpload($shipment)
  -> match($order->marketplace)
     'ebay'    -> UploadTrackingToEbay (eBay Fulfillment API)
     'shopify' -> UploadTrackingToShopify (Shopify Fulfillment API)
     'amazon'  -> UploadTrackingToAmazon (Amazon Feeds API: POST_ORDER_FULFILLMENT_DATA)
```

`TrackingService::retryFailedTrackingUploads()` re-dispatches all pending uploads.

### Manual Tracking Entry
For orders shipped outside the system (walked to PO, used external label tool, dropship handoff with carrier-supplied tracking):

- **Endpoint:** `POST fulfillment/orders/{order}/manual-ship` (route: `fulfillment.orders.manual-ship`)
- **Service:** `ShipmentService::manualShip(Order, array, User)` -- reuses any non-terminal shipment on the order, else creates one from order items at the assigned location
- **Required:** `carrier`, `tracking_number`. Optional: `service`, `tracking_url`, `shipped_at`, `location_id` (only used when order is unassigned)
- **Side effects:** sets `shipping_provider = 'manual'`, calls `FulfillmentService::shipOrder()` (decrements inventory, fires shipped notifications, queues marketplace tracking upload), then synchronously runs `TrackingService::uploadTrackingToMarketplace()` for immediate push
- **Blocked when:** order status is `delivered` or `cancelled`
- **UI:** "Enter Tracking" button on `Pages/Fulfillment/Orders/Show.vue` and per-row link on `Pages/Fulfillment/Ship/Index.vue` for shipments without a tracking number. Manual rows hide the Void button (no carrier label to refund)

## Marketplace Return/Refund Sync

When `ReturnService::processRefund()` runs, it dispatches `SyncRefundToMarketplace`:
- **eBay**: `OrderSyncService::issueRefund()` (Post-Order API)
- **Shopify**: `ShopifyOrderService::createRefund()` (Refund API with parent transaction)
- **Amazon**: `AmazonOrderService::authorizeReturn()` (SP-API return authorization)

## Pick/Pack/Ship Workflow

### Pick Phase
1. `FulfillmentService::startPicking($order, $actor)` -- sets status to `picking`
2. `pickItem($item, $actor, $quantity)` -- marks item as `picked`
3. `shortItem($item, $actor, $reason)` -- marks item as `short` (insufficient stock)
4. `completePicking($order, $actor)` -- validates all items picked or shorted, sets `picked`

#### Where the bin label comes from
The pick UI (`Fulfillment/Pick/Index.vue`, `FulfillmentPrintService::resolvePickBinLabel`) reads
from `inventory_levels.bin_id` filtered by `Order.location_id`. Bin assignment originates at intake
(`ProductIntakeQueue.quick_bin_id`) and is seeded onto the InventoryLevel by
`IntakeQueueController::seedProductInventory()`. Do NOT use `Product.warehouse_location_id` -- that
is a legacy bigint FK to a deprecated `warehouse_locations` table and is never populated.
See `docs/PRODUCT_LIFECYCLE_OVERVIEW.md`.

### Pack Phase
1. `startPacking($order, $actor)` -- sets status to `packing`
2. `ShipmentService::createShipment($order, $items, $location)` -- generates `SHP-{Ymd}-{0001}` number
3. `getRates($shipment)` -- fetches rates from configured provider
4. `purchaseLabel($shipment, $rateId, $actor)` -- buys label, stores provider-specific IDs
5. `completePacking($order, $packageDimensions, $actor)` -- sets `packed`

### Ship Phase
1. `FulfillmentService::shipOrder($order, $actor)` -- sets `shipped`, decrements `quantity_on_hand` and `quantity_reserved` on InventoryLevel
2. `TrackingService::uploadTrackingToMarketplace($shipment)` -- calls `OrderSyncService::uploadTracking()` for eBay orders
3. Tracking webhooks update shipment status through delivery

### Auto-Assignment
`FulfillmentService::autoAssignOrders($orders)` finds the best location per order by counting how many order items have inventory at each location. Ties broken by location with most matching products.

### Dropship Routing
`FulfillmentService::routeToSupplier($order, $supplier, $actor)` creates a `SupplierFulfillmentRequest` with SLA deadline from `$supplier->dropship_sla_hours` (default 48h). `CheckDropshipSLA` job monitors overdue requests.

## Shipping Providers

The active provider is stored in `OrganizationSettings` key `fulfillment_shipping_provider`. `ShipmentService::getShippingService()` resolves the correct service class.

### EasyPost
- Auth: HTTP Basic (API key as username, empty password)
- Base URL: `https://api.easypost.com/v2`
- Webhook verification: HMAC-SHA256 using `credential.config.webhook_secret`
- Address verification: stores `easypost_address_id` on InventoryLocation
- Retry: 2 attempts, 1000ms delay

### ShipStation
- Auth: HTTP Basic (`apiKey:apiSecret`)
- Base URL: `https://ssapi.shipstation.com`
- Label flow: create order (`/orders/createorder`) then label (`/orders/createlabelfororder`)
- Webhook events: `SHIP_NOTIFY`, `ORDER_NOTIFY`

### ShippingEasy
- Auth: HMAC-SHA256 signed requests (`api_key`, `api_timestamp`, `api_signature` headers)
- Signature: ksort params -> `HMAC-SHA256(METHOD & path & http_build_query(params) & body, apiSecret)` -- auth sent as URL query params, not headers
- Rate limit: 120 calls/min per API key; retries on 429 with exponential backoff (1s, 2s, 4s), max 3 retries
- Label flow: create order (`/stores/{key}/orders`) then label (`/orders/{id}/labels`) -- note: label endpoint is undocumented
- Cancel order: `POST /stores/{key}/orders/{external_order_identifier}/cancellations` (uses our order number, not SE ID)
- Additional: `getStores()`, `findOrderByExternalId()`, `updateOrderStatus()`, `getAllOrders()` (paginated)
- Webhook: SE sends `workflow_state` (`label_ready`, `label_printed`, `cancelled`, `drop_shipped`, `label_pending`) mapped to internal events
- Webhook payload: `shipment.shipment_cost` in cents, `carrier_key`, `carrier_service_key`, nested `orders[]` array
- Shipment lookup: SE shipment ID -> nested order ID -> tracking number (priority order)

### Provider Detection
`Shipment.shipping_provider` field discriminates: `'easypost'` | `'shipstation'` | `'shippingeasy'`. Helper methods: `isEasyPost()`, `isShipStation()`, `isShippingEasy()`.

## Webhook Endpoints (Unauthenticated)

| Endpoint | Controller | Events | Job Dispatched |
|----------|-----------|--------|----------------|
| `POST /webhooks/easypost` | `TrackingWebhookController@handleEasyPost` | tracker events | `ProcessTrackingWebhook` |
| `POST /webhooks/shipstation` | `ShipStationWebhookController@handle` | `SHIP_NOTIFY`, `ORDER_NOTIFY` | `ProcessShipStationWebhook` |
| `POST /webhooks/shippingeasy` | `ShippingEasyWebhookController@handle` | SE `workflow_state`: `label_ready`, `label_printed`, `cancelled`, `drop_shipped` | `ProcessShippingEasyWebhook` |

## Tracking Status Mapping (EasyPost -> Shipment)

| EasyPost Status | Shipment Status |
|----------------|-----------------|
| `pre_transit` | `shipped` |
| `in_transit` | `in_transit` |
| `out_for_delivery` | `out_for_delivery` |
| `delivered` | `delivered` |
| `return_to_sender` | `returned_to_sender` |
| `failure` | `failed` |

## FulfillmentLog Actions

All actions are logged via `FulfillmentAuditService::log()` with polymorphic `loggable_type`/`loggable_id`:

**Order lifecycle:** `order_imported`, `order_assigned`, `order_priority_changed`, `order_held`, `order_released`, `order_cancelled`
**Pick:** `pick_started`, `item_picked`, `pick_completed`, `pick_shorted`
**Pack:** `pack_started`, `rate_shopped`, `label_purchased`, `label_voided`, `item_packed`, `pack_completed`
**Ship:** `shipment_created`, `tracking_uploaded`, `tracking_updated`, `delivery_confirmed`
**Returns:** `return_requested`, `return_received`, `return_inspected`, `refund_processed`, `item_restocked`
**Dropship:** `dropship_routed`, `dropship_accepted`, `dropship_rejected`, `dropship_escalated`
**Printing:** `label_printed`, `packing_slip_printed`, `pick_list_printed`

## Settings Keys (OrganizationSettings)

| Key | Purpose |
|-----|---------|
| `fulfillment_shipping_provider` | `'easypost'` / `'shipstation'` / `'shippingeasy'` |
| `fulfillment_shipping` | Shipping defaults (carrier preferences, etc.) |
| `fulfillment_labels` | Label format/size defaults |
| `fulfillment_workflow` | Workflow configuration |
| `fulfillment_returns` | Return policy defaults |
| `fulfillment_notifications` | Notification preferences |

## Fulfillment Routing

### Key Files
| File | Purpose |
|------|---------|
| `app/Models/Fulfillment/FulfillmentRoutingRule.php` | Rule definition with strategy, priority, and conditions |
| `app/Services/Fulfillment/FulfillmentRouter.php` | Evaluates rules and selects the optimal fulfillment location |

### Routing Strategies
| Strategy | Behavior |
|----------|----------|
| `proximity` | Select location closest to the shipping destination (geo-distance) |
| `lowest_cost` | Select location with the cheapest estimated shipping cost |
| `highest_stock` | Select location with the most stock for the order's items |
| `round_robin` | Rotate evenly across eligible locations |
| `specific_location` | Always route to a designated location |

### Integration
When orders are imported via `OrderSyncService` (eBay, Shopify, Amazon), the `FulfillmentRouter` is invoked to evaluate active routing rules in priority order. The first matching rule determines the fulfillment location. If no rule matches, the system falls back to the default auto-assignment logic in `FulfillmentService::autoAssignOrders()`.

---

## Shipping Rules

### Key Files
| File | Purpose |
|------|---------|
| `app/Models/Fulfillment/ShippingRule.php` | Rule definition with conditions and actions |
| `app/Services/Fulfillment/ShippingRuleEngine.php` | Evaluates shipping rules against order/shipment data |

### Rule Conditions
| Condition | Description |
|-----------|-------------|
| `weight` | Total shipment weight (oz/lbs) -- supports ranges |
| `value` | Order total value -- supports min/max thresholds |
| `destination` | Ship-to state, country, or zone |
| `marketplace` | Source marketplace (ebay, shopify, amazon) |
| `item_count` | Number of items in the order |

### Rule Actions
| Action | Description |
|--------|-------------|
| `carrier` | Select a specific carrier (USPS, UPS, FedEx, DHL) |
| `service` | Select a specific service level (Priority, Ground, Express) |
| `package_preset` | Select a `PackagePreset` for box/envelope dimensions |

### Integration
During the packing workflow, when a label is being generated, the `ShippingRuleEngine` evaluates active rules against the order and shipment data. Matching rules auto-populate carrier, service, and package preset selections, reducing manual decision-making in the pack phase. Rules are evaluated in priority order; the first match wins.

---

## Debugging Commands

```php
// Order status distribution
DB::table('orders')->where('organization_id', $orgId)
  ->selectRaw("status, count(*) as count")->groupBy('status')->get();

// Stuck orders (assigned/picking/packing > 4 hours)
DB::table('orders')->where('organization_id', $orgId)
  ->whereIn('status', ['assigned', 'picking', 'packing'])
  ->where('updated_at', '<', now()->subHours(4))->count();

// Failed fulfillment jobs (last 24h)
DB::table('failed_jobs')->where('failed_at', '>', now()->subDay())
  ->where('payload', 'like', '%Fulfillment%')->count();

// Pending tracking uploads
Order::where('organization_id', $orgId)->where('status', 'shipped')
  ->whereHas('shipments', fn($q) => $q->whereNull('marketplace_tracking_uploaded_at'))->count();

// Stale shipments (shipped > 7 days, no delivery)
Shipment::whereIn('status', ['shipped', 'in_transit'])
  ->where('shipped_at', '<', now()->subDays(7))
  ->whereNull('delivered_at')->count();

// Order timeline
FulfillmentLog::where('order_id', $orderId)->orderBy('created_at')->get(['action', 'created_at', 'metadata']);

// Shipment provider breakdown
DB::table('shipments')->where('organization_id', $orgId)
  ->selectRaw("shipping_provider, status, count(*) as count")
  ->groupBy('shipping_provider', 'status')->get();
```

## Notifications

Fulfillment events that trigger user notifications:

| Event | Notification Class | Wired In |
|-------|--------------------|----------|
| Order sync failed (eBay) | `Fulfillment\OrderSyncFailedNotification` | `SyncEbayOrders` catch block |
| Order sync failed (Shopify) | `Fulfillment\OrderSyncFailedNotification` | `SyncShopifyOrders` catch block |
| Tracking upload failed (eBay) | `Fulfillment\TrackingUploadFailedNotification` | `UploadTrackingToEbay::failed()` |
| Tracking upload failed (Shopify) | `Fulfillment\TrackingUploadFailedNotification` | `UploadTrackingToShopify::failed()` |
| Tracking upload failed (Amazon) | `Fulfillment\TrackingUploadFailedNotification` | `UploadTrackingToAmazon::failed()` |

All notifications go to org admins/owners. See `docs/NOTIFICATION_SYSTEM.md` for the full notification architecture.

## Audit Logging

| Action | Category | Severity | Metadata keys |
|--------|----------|----------|---------------|
| `order.imported` | fulfillment | info | marketplace, marketplace_order_id, source: webhook|cron |
| `order.assigned` | fulfillment | info | assigned_picker_id, location_id |
| `order.picked` | fulfillment | info | picker_id, item_count, pick_duration_seconds |
| `order.packed` | fulfillment | info | packer_id, package_preset_id |
| `order.shipped` | fulfillment | info | carrier, tracking_number, ship_to_country |
| `order.cancelled` | fulfillment | warning | cancel_reason, refund_issued |
| `shipment.label_purchased` | fulfillment | info | provider, rate_cents, service_level |
| `shipment.tracking_uploaded` | fulfillment | info | marketplace, upload_status |
| `return.requested` | fulfillment | info | reason_code |
| `return.approved` | fulfillment | info | refund_amount_cents |
| `return.processed` | fulfillment | info | restocked, refund_issued |

Trait: models in this feature use `LogsAudit` (`app/Traits/LogsAudit.php`) for auto CRUD diff. Domain lifecycle events emitted from `app/Services/Fulfillment/{OrderSyncService,ShipmentService,ReturnsService,FulfillmentService,TrackingService}`, `PickPackController`; `Order`, `Shipment`, `ReturnRequest` models (`LogsAudit`) via `UserActivityLog::log(action, desc, $user, $metadata, $severity, $category)`.

Pick/pack timing detail stays in `fulfillment_logs` (sub-second granularity). `user_activity_logs` complements it for the Admin Audit Hub timeline and audit-derived KPIs. Cron/webhook syncs MUST use `withAuditActor(null, ..., ['source' => 'cron|webhook'])` or order.imported rows will have null actors.

Spec: `docs/AUDIT_SYSTEM.md`. Enforced rule: `.claude/rules/audit-logging.md`. Tests: `use Tests\Support\AssertsAudits;` then `$this->assertAudited('action', $user, [...])`.
