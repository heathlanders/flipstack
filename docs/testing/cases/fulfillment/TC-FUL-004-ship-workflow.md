# Ship Workflow Test Cases

## Document ID: TC-FUL-004

**Domain:** Fulfillment
**Related Test Plan:** `docs/testing/plans/fulfillment-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-FUL-004-01: View ship queue shows LABEL_PURCHASED/PACKED shipments with stats

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-004-01 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Fulfillment/ShipWorkflowTest.php` (needed) |
| **Test Method** | `test_ship_queue_shows_correct_shipment_statuses_with_stats()` (needed) |

**Preconditions:**

1. Authenticated user with `orders.view` permission (route middleware: `auth:sanctum`, `can:orders.view`)
2. Organization fixtures created via `CreatesOrganizationFixtures` trait
3. `RefreshDatabase` trait resets state
4. Shipments exist in multiple statuses: pending, label_purchased, packed, shipped, in_transit, delivered
5. Associated Orders and InventoryLocations exist

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create shipments in each status: pending, label_purchased, packed, shipped, in_transit, delivered | Six shipments exist |
| 2 | Mark one shipment as `shipped_at = today()` and one as `actual_delivery_date = today()` | Today's activity shipments exist |
| 3 | GET `fulfillment.ship.index` as authenticated user | Response status 200, renders `Fulfillment/Ship/Index` |
| 4 | Assert only LABEL_PURCHASED and PACKED shipments appear in the response `shipments` prop | Pending, shipped, in_transit, delivered excluded |
| 5 | Assert shipments are sorted by `created_at` ascending | Oldest ready-to-ship first |
| 6 | Assert `stats.ready_to_ship` equals count of shipments with status in [label_purchased, packed] | Ready count correct |
| 7 | Assert `stats.shipped_today` equals count of shipments with `shipped_at` = today | Today's shipped count |
| 8 | Assert `stats.in_transit` equals count of shipments with status = in_transit | In-transit count |
| 9 | Assert `stats.delivered_today` equals count of shipments with `actual_delivery_date` = today | Today's delivery count |
| 10 | Assert response is paginated with 25 items per page | Pagination applied |

**Test Data:**

```php
$location = InventoryLocation::factory()->create([
    'name' => 'Main Warehouse',
    'code' => 'MW',
]);

$order = Order::factory()->create([
    'status' => Order::STATUS_PACKED,
    'marketplace_order_id' => 'ORD-001',
    'buyer_name' => 'John Doe',
    'shipping_city' => 'Portland',
    'shipping_state' => 'OR',
    'priority' => Order::PRIORITY_STANDARD,
]);

$labelPurchased = Shipment::factory()->create([
    'order_id' => $order->id,
    'location_id' => $location->id,
    'status' => Shipment::STATUS_LABEL_PURCHASED,
    'tracking_number' => '9400111899223456789012',
    'label_url' => 'https://easypost.com/labels/abc.png',
]);

$packed = Shipment::factory()->create([
    'order_id' => $order->id,
    'location_id' => $location->id,
    'status' => Shipment::STATUS_PACKED,
]);

// Shipments that should NOT appear in the queue:
$pending = Shipment::factory()->create([
    'status' => Shipment::STATUS_PENDING,
]);

$shipped = Shipment::factory()->create([
    'status' => Shipment::STATUS_SHIPPED,
    'shipped_at' => today(),
]);

$inTransit = Shipment::factory()->create([
    'status' => Shipment::STATUS_IN_TRANSIT,
]);

$delivered = Shipment::factory()->create([
    'status' => Shipment::STATUS_DELIVERED,
    'actual_delivery_date' => today(),
]);
```

**Postconditions:**

- No database state changes (read-only operation)
- Inertia response includes `shipments` (paginated) and `stats` props
- Shipments eager-load: `order:id,marketplace_order_id,buyer_name,shipping_city,shipping_state,priority,status`, `order.items:id,order_id,title,quantity`, `location:id,name,code`

**Notes:**

- The ship queue operates on Shipment models, not Order models (unlike pick/pack queues)
- Stats use separate queries, not the filtered collection
- `shipped_today` uses `whereDate('shipped_at', today())`
- `delivered_today` uses `whereDate('actual_delivery_date', today())`
- Pagination is 25 per page (vs. 20 for pack queue)
- Sort is by `created_at` (not priority) -- oldest ready shipments first

---

### TC-FUL-004-02: Confirm ship marks shipment as shipped, sets shipped_at and shipped_by, uploads tracking to marketplace

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-004-02 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Fulfillment/ShipWorkflowTest.php` (needed) |
| **Test Method** | `test_confirm_ship_marks_shipped_and_uploads_tracking()` (needed) |

**Preconditions:**

1. Authenticated user with `orders.view` permission
2. Shipment exists with `status = Shipment::STATUS_LABEL_PURCHASED`
3. Shipment has `tracking_number` set
4. Associated Order exists with items that have `pick_status = 'picked'`
5. Order items have `product_id` and `location_id` set for inventory deduction
6. InventoryLevel records exist for the products at the location
7. `TrackingService` available (may need mock for marketplace upload)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST `fulfillment.ship.confirm` with the shipment's UUID | Response redirects back with success message including tracking number |
| 2 | Refresh shipment from database | Shipment record updated |
| 3 | Assert `shipment.status` equals `Shipment::STATUS_SHIPPED` | Status updated to shipped |
| 4 | Assert `shipment.shipped_at` is set to approximately now | Ship timestamp recorded |
| 5 | Refresh the parent order from database | Order record updated |
| 6 | Assert `order.status` equals `Order::STATUS_SHIPPED` | Parent order status updated |
| 7 | Assert `order.shipped_at` is set | Order ship timestamp recorded |
| 8 | Assert InventoryLevel `quantity_on_hand` decremented by item quantities | Physical inventory deducted |
| 9 | Assert InventoryLevel `quantity_reserved` decremented (if >= item quantity) | Reservation released |
| 10 | Assert `TrackingService::uploadTrackingToMarketplace()` was called | Tracking upload triggered |

**Test Data:**

```php
$product = Product::factory()->create([
    'available_quantity' => 10,
]);

$inventoryLevel = InventoryLevel::factory()->create([
    'product_id' => $product->id,
    'location_id' => $location->id,
    'quantity_on_hand' => 5,
    'quantity_reserved' => 1,
]);

$order = Order::factory()->create([
    'status' => Order::STATUS_PACKED,
    'marketplace' => 'ebay',
    'marketplace_order_id' => '04-12345-67890',
]);

$orderItem = OrderItem::factory()->create([
    'order_id' => $order->id,
    'product_id' => $product->id,
    'location_id' => $location->id,
    'pick_status' => OrderItem::PICK_PICKED,
    'quantity' => 1,
]);

$shipment = Shipment::factory()->create([
    'order_id' => $order->id,
    'status' => Shipment::STATUS_LABEL_PURCHASED,
    'tracking_number' => '9400111899223456789012',
    'label_url' => 'https://easypost.com/labels/abc.png',
]);
```

**Postconditions:**

- `shipment.status` = `'shipped'`
- `shipment.shipped_at` = current timestamp
- `order.status` = `'shipped'`
- `order.shipped_at` = current timestamp
- `inventory_levels.quantity_on_hand` decremented by item quantity (5 - 1 = 4)
- `inventory_levels.quantity_reserved` decremented by item quantity (1 - 1 = 0)
- Tracking upload dispatched to the correct marketplace (eBay/Shopify/Amazon based on `order.marketplace`)
- FulfillmentLog with `action = 'tracking_uploaded'`

**Notes:**

- The controller calls three services sequentially: `shipmentService.markShipped()`, `fulfillmentService.shipOrder()`, `trackingService.uploadTrackingToMarketplace()`
- `shipOrder()` handles inventory deduction from InventoryLevel (quantity_on_hand, quantity_reserved)
- `shipOrder()` only decrements `quantity_reserved` if it is >= the item quantity (guard against negative)
- `shipOrder()` dispatches tracking upload for each shipment with a tracking_number
- The tracking upload uses marketplace-specific jobs (see TC-FUL-004-07)
- Success message: `'Shipment marked as shipped. Tracking: {tracking_number}'`

---

### TC-FUL-004-03: Void label cancels label, reverts shipment status

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-004-03 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Fulfillment/ShipWorkflowTest.php` (needed) |
| **Test Method** | `test_void_label_cancels_and_reverts_status()` (needed) |

**Preconditions:**

1. Authenticated user with `orders.view` permission
2. Shipment exists with `status = Shipment::STATUS_LABEL_PURCHASED`
3. Shipment has `tracking_number`, `label_url`, and `label_cost` set
4. `ShipmentService::voidLabel()` may need to be mocked (calls external shipping API to void)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST `fulfillment.ship.void` with the shipment's UUID | Response redirects back with success message |
| 2 | Refresh shipment from database | Shipment record updated |
| 3 | Assert shipment status reverted (e.g., to `pending` or `packed`) | Status rolled back |
| 4 | Assert `shipment.tracking_number` is cleared or voided | Tracking number invalidated |
| 5 | Assert `shipment.label_url` is cleared | Label no longer available |

**Test Data:**

```php
$shipment = Shipment::factory()->create([
    'order_id' => $order->id,
    'status' => Shipment::STATUS_LABEL_PURCHASED,
    'tracking_number' => '9400111899223456789012',
    'label_url' => 'https://easypost.com/labels/abc.png',
    'label_cost' => 8.50,
    'label_purchased_at' => now()->subHour(),
]);
```

**Postconditions:**

- Shipment status reverted from `label_purchased`
- Label-related fields cleared (tracking_number, label_url, label_cost)
- External shipping provider notified of void (if applicable)
- Success message: `'Label voided for shipment {shipment_number}'`

**Notes:**

- `ShipmentService::voidLabel()` handles the external API call to void the label with the shipping provider
- Not all shipping providers support label voiding (provider-specific behavior)
- Voiding a label typically has a time window (e.g., EasyPost allows void within 24 hours)
- The void does NOT change the parent order status -- the order remains in its current state
- A new label can be purchased after voiding

---

### TC-FUL-004-04: Bulk ship marks multiple shipments as shipped

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-004-04 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Fulfillment/ShipWorkflowTest.php` (needed) |
| **Test Method** | `test_bulk_ship_marks_multiple_shipments()` (needed) |

**Preconditions:**

1. Authenticated user with `orders.view` permission
2. Multiple shipments exist with status in [label_purchased, packed]
3. Each shipment has a tracking_number
4. Each shipment's parent order has items with inventory levels set
5. `TrackingService` available (mock marketplace uploads)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create 3 shipments with `status = Shipment::STATUS_LABEL_PURCHASED` | 3 eligible shipments exist |
| 2 | Create 1 shipment with `status = Shipment::STATUS_SHIPPED` (should be skipped) | Ineligible shipment exists |
| 3 | POST `fulfillment.ship.bulk` with `shipment_ids` = [id1, id2, id3, id4] | Response redirects back with success |
| 4 | Assert success message reports 3 shipments shipped (the already-shipped one filtered out) | Correct count in message |
| 5 | Refresh all shipments from database | Records updated |
| 6 | Assert shipments 1-3 have `status = 'shipped'` and `shipped_at` set | Eligible shipments processed |
| 7 | Assert shipment 4 status unchanged (already shipped) | Ineligible shipment skipped |
| 8 | Assert each parent order status updated to `shipped` | Order statuses cascade |
| 9 | Assert `TrackingService::uploadTrackingToMarketplace()` called 3 times | Tracking uploaded for each |

**Test Data:**

```php
// POST data:
$postData = [
    'shipment_ids' => [
        $shipment1->id, // label_purchased
        $shipment2->id, // label_purchased
        $shipment3->id, // packed
        $shipment4->id, // already shipped (will be filtered out)
    ],
];

// Validation rules:
// 'shipment_ids' => 'required|array|min:1'
// 'shipment_ids.*' => 'exists:shipments,id'
```

**Postconditions:**

- 3 shipments marked as shipped (status = 'shipped', shipped_at set)
- 3 parent orders updated to shipped status
- Inventory levels decremented for each order's picked items
- Tracking uploaded to marketplace for each shipment
- Success message: `'{N} shipments marked as shipped.'`

**Notes:**

- The controller queries for shipments matching the provided IDs AND having status in [label_purchased, packed]
- Already-shipped shipments in the `shipment_ids` array are silently filtered out (no error)
- Non-existent shipment IDs fail the `exists:shipments,id` validation (422 response)
- Processing is sequential (foreach loop), not parallel
- Each shipment triggers: `markShipped()` + `shipOrder()` + `uploadTrackingToMarketplace()`
- If one shipment fails mid-batch, previous ones are already committed (no batch transaction)

---

### TC-FUL-004-05: Batch print labels generates labels for multiple shipments

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-004-05 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Fulfillment/ShipWorkflowTest.php` (needed) |
| **Test Method** | `test_batch_print_labels_returns_counts()` (needed) |

**Preconditions:**

1. Authenticated user with `orders.view` permission
2. Multiple shipments exist, some with `label_url` set and some without
3. `FulfillmentPrintService::batchPrintShippingLabels()` available (may need mock)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create 3 shipments with `label_url` set | 3 printable shipments |
| 2 | Create 1 shipment without `label_url` (null) | 1 non-printable shipment |
| 3 | POST `fulfillment.ship.batch-print` with `shipment_ids` = [all 4 IDs] | Response redirects back with success |
| 4 | Assert success message contains printed, failed, and skipped counts | Message: `'Printed {N} labels. Failed: {N}. Skipped: {N}.'` |
| 5 | Assert only 3 shipments were included in the print batch (those with label_url) | Shipments without label_url filtered by query |

**Test Data:**

```php
// POST data:
$postData = [
    'shipment_ids' => [$s1->id, $s2->id, $s3->id, $s4->id],
];

// Validation rules:
// 'shipment_ids' => 'required|array|min:1'
// 'shipment_ids.*' => 'exists:shipments,id'

// Mock FulfillmentPrintService::batchPrintShippingLabels() return:
$results = [
    'printed' => 3,
    'failed' => 0,
    'skipped' => 0,
];
```

**Postconditions:**

- No shipment status changes (printing does not alter status)
- Print job sent to configured printer or PDFs generated for download
- Response includes counts: printed, failed, skipped

**Notes:**

- The controller filters shipments by `whereNotNull('label_url')` before passing to the print service
- Shipments without a `label_url` are excluded from the query entirely (not counted as skipped)
- The `skipped` count comes from the print service itself (e.g., printer offline, format mismatch)
- The `failed` count indicates print attempts that threw an error
- This is a POST route despite being a read-like print operation (creates print jobs)
- The success message format: `"Printed {printed} labels. Failed: {failed}. Skipped: {skipped}."`

---

### TC-FUL-004-06: Download label returns label_url JSON, 404 if no label_url

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-004-06 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web / API |
| **Automated** | No |
| **Test File** | `tests/Feature/Fulfillment/ShipWorkflowTest.php` (needed) |
| **Test Method** | `test_download_label_returns_url()` and `test_download_label_404_without_label()` (needed) |

**Preconditions:**

1. Authenticated user with `orders.view` permission
2. Shipment exists (test both with and without `label_url`)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create shipment with `label_url = 'https://easypost.com/labels/abc.png'` | Shipment with label exists |
| 2 | GET `fulfillment.ship.label` with shipment UUID | Response status 200 with JSON body |
| 3 | Assert response JSON contains `label_url` key with correct URL value | `{"label_url": "https://easypost.com/labels/abc.png"}` |
| 4 | Create shipment with `label_url = null` | Shipment without label exists |
| 5 | GET `fulfillment.ship.label` with that shipment's UUID | Response status 404 |
| 6 | Assert 404 response message: `'No label available for this shipment.'` | Error message correct |

**Test Data:**

```php
// Shipment with label:
$withLabel = Shipment::factory()->create([
    'label_url' => 'https://easypost.com/labels/abc123.png',
    'tracking_number' => '9400111899223456789012',
    'status' => Shipment::STATUS_LABEL_PURCHASED,
]);

// Shipment without label:
$withoutLabel = Shipment::factory()->create([
    'label_url' => null,
    'status' => Shipment::STATUS_PENDING,
]);
```

**Postconditions:**

- No database changes (read-only operation)
- Successful response: JSON `{"label_url": "..."}`
- Failed response: 404 abort with message

**Notes:**

- This is a GET route that returns JSON (not an Inertia response) -- it differs from other fulfillment routes
- The controller uses `abort(404, ...)` when `label_url` is null
- The `label_url` typically points to an external shipping provider's label storage (EasyPost, ShipStation)
- No redirect behavior -- this is a direct JSON response via `response()->json()`
- The route is `fulfillment.ship.label` with `{shipment}` model binding

---

### TC-FUL-004-07: Ship confirm dispatches tracking upload job based on order marketplace

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-004-07 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | API |
| **Automated** | No |
| **Test File** | `tests/Feature/Fulfillment/ShipWorkflowTest.php` (needed) |
| **Test Method** | `test_ship_confirm_dispatches_correct_tracking_job_per_marketplace()` (needed) |

**Preconditions:**

1. Authenticated user with `orders.view` permission
2. Shipments exist with tracking numbers, linked to orders from different marketplaces (eBay, Shopify, Amazon)
3. Queue is faked to capture dispatched jobs
4. `TrackingService::uploadTrackingToMarketplace()` dispatches marketplace-specific jobs

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create order with `marketplace = 'ebay'` and a label_purchased shipment | eBay order ready to ship |
| 2 | POST `fulfillment.ship.confirm` with the eBay shipment's UUID | Ship confirmed |
| 3 | Assert `UploadTrackingToEbay` job was dispatched | eBay-specific tracking job queued |
| 4 | Create order with `marketplace = 'shopify'` and a label_purchased shipment | Shopify order ready to ship |
| 5 | POST `fulfillment.ship.confirm` with the Shopify shipment's UUID | Ship confirmed |
| 6 | Assert `UploadTrackingToShopify` job was dispatched | Shopify-specific tracking job queued |
| 7 | Create order with `marketplace = 'amazon'` and a label_purchased shipment | Amazon order ready to ship |
| 8 | POST `fulfillment.ship.confirm` with the Amazon shipment's UUID | Ship confirmed |
| 9 | Assert `UploadTrackingToAmazon` job was dispatched | Amazon-specific tracking job queued |

**Test Data:**

```php
// eBay order + shipment
$ebayOrder = Order::factory()->create([
    'marketplace' => 'ebay',
    'marketplace_order_id' => '04-12345-67890',
    'status' => Order::STATUS_PACKED,
]);
$ebayShipment = Shipment::factory()->create([
    'order_id' => $ebayOrder->id,
    'status' => Shipment::STATUS_LABEL_PURCHASED,
    'tracking_number' => '9400111899223456789012',
]);

// Shopify order + shipment
$shopifyOrder = Order::factory()->create([
    'marketplace' => 'shopify',
    'marketplace_order_id' => '5551234567890',
    'status' => Order::STATUS_PACKED,
]);
$shopifyShipment = Shipment::factory()->create([
    'order_id' => $shopifyOrder->id,
    'status' => Shipment::STATUS_LABEL_PURCHASED,
    'tracking_number' => '1Z999AA10123456784',
]);

// Amazon order + shipment
$amazonOrder = Order::factory()->create([
    'marketplace' => 'amazon',
    'marketplace_order_id' => '113-1234567-1234567',
    'status' => Order::STATUS_PACKED,
]);
$amazonShipment = Shipment::factory()->create([
    'order_id' => $amazonOrder->id,
    'status' => Shipment::STATUS_LABEL_PURCHASED,
    'tracking_number' => '1Z999BB10123456785',
]);

// Job classes:
// App\Jobs\Fulfillment\UploadTrackingToEbay
// App\Jobs\Fulfillment\UploadTrackingToShopify
// App\Jobs\Fulfillment\UploadTrackingToAmazon
```

**Postconditions:**

- Correct marketplace-specific job dispatched for each shipment
- Each job receives the Shipment model (or shipment ID) as its payload
- Jobs are queued (not executed synchronously) when Queue is not faked
- `shipment.tracking_uploaded_to_marketplace` will be set to true when the job completes (not immediately)

**Notes:**

- `TrackingService::uploadTrackingToMarketplace()` is called from the ShipController (not from FulfillmentService)
- The TrackingService determines which job to dispatch based on `$shipment->order->marketplace`
- The specific job classes live in `App\Jobs\Fulfillment\`: `UploadTrackingToEbay`, `UploadTrackingToShopify`, `UploadTrackingToAmazon`
- `FulfillmentService::shipOrder()` ALSO dispatches tracking upload via `TrackingService` internally -- this may result in double dispatch; verify behavior
- The `dispatchTrackingUpload()` method on TrackingService wraps the per-marketplace logic
- If the order has no `marketplace` field or an unsupported marketplace, the tracking upload should be skipped gracefully

---

### TC-FUL-004-08: Cannot confirm ship on already-shipped shipment

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-004-08 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Fulfillment/ShipWorkflowTest.php` (needed) |
| **Test Method** | `test_confirm_ship_fails_for_already_shipped_shipment()` (needed) |

**Preconditions:**

1. Authenticated user with `orders.view` permission
2. Shipment exists with `status = Shipment::STATUS_SHIPPED`
3. Shipment has `shipped_at` already set

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create shipment with `status = 'shipped'` and `shipped_at` set | Already-shipped shipment exists |
| 2 | POST `fulfillment.ship.confirm` with the shipment's UUID | Request should fail or be rejected |
| 3 | Assert shipment status remains `shipped` | No status change |
| 4 | Assert `shipped_at` is unchanged | Timestamp not overwritten |
| 5 | Repeat for `status = Shipment::STATUS_IN_TRANSIT` | Same rejection |
| 6 | Repeat for `status = Shipment::STATUS_DELIVERED` | Same rejection |
| 7 | Repeat for `status = Shipment::STATUS_CANCELLED` | Same rejection |

**Test Data:**

```php
// Already-shipped shipment:
$shipment = Shipment::factory()->create([
    'order_id' => $order->id,
    'status' => Shipment::STATUS_SHIPPED,
    'shipped_at' => now()->subHour(),
    'tracking_number' => '9400111899223456789012',
]);

// All non-confirmable statuses:
$invalidStatuses = [
    Shipment::STATUS_SHIPPED,
    Shipment::STATUS_IN_TRANSIT,
    Shipment::STATUS_OUT_FOR_DELIVERY,
    Shipment::STATUS_DELIVERED,
    Shipment::STATUS_FAILED,
    Shipment::STATUS_RETURNED_TO_SENDER,
    Shipment::STATUS_CANCELLED,
];

// Confirmable statuses (should succeed):
$validStatuses = [
    Shipment::STATUS_LABEL_PURCHASED,
    Shipment::STATUS_PACKED,
];
```

**Postconditions:**

- Shipment status unchanged for all invalid statuses
- No duplicate tracking uploads dispatched
- No duplicate inventory deductions
- Parent order status unchanged

**Notes:**

- The `ShipController::markShipped()` does not currently include an explicit status guard -- the `ShipmentService::markShipped()` method should validate
- If no guard exists, this test documents a potential bug: re-confirming ship could double-deduct inventory and double-upload tracking
- The bulk ship endpoint (`fulfillment.ship.bulk`) DOES filter by status `whereIn([label_purchased, packed])`, but the single confirm endpoint may not
- Consider adding `canBeShipped()` check on Shipment model similar to `canBePicked()` / `canBePacked()` on Order
- This test should verify whether the current implementation properly guards against re-shipment or if a fix is needed
