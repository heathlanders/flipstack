# Pack Workflow Test Cases

## Document ID: TC-FUL-003

**Domain:** Fulfillment
**Related Test Plan:** `docs/testing/plans/fulfillment-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-FUL-003-01: View pack queue shows PICKED/PACKING/PACKED orders

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-003-01 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Fulfillment/PackWorkflowTest.php` (needed) |
| **Test Method** | `test_pack_queue_shows_correct_order_statuses()` (needed) |

**Preconditions:**

1. Authenticated user with `orders.view` permission (route middleware: `auth:sanctum`, `can:orders.view`)
2. Organization fixtures created via `CreatesOrganizationFixtures` trait
3. `RefreshDatabase` trait resets state
4. Orders exist in multiple statuses: pending, assigned, picking, picked, packing, packed, shipped
5. At least one InventoryLocation exists

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create orders in each status: pending, assigned, picking, picked, packing, packed, shipped | Seven orders exist |
| 2 | GET `fulfillment.pack.index` as authenticated user | Response status 200, renders `Fulfillment/Pack/Index` |
| 3 | Assert only PICKED, PACKING, and PACKED orders appear in the response `orders` prop | Other statuses excluded |
| 4 | Assert orders are sorted by priority (urgent > high > standard) then by `ship_by_date` ascending | Correct sort order |
| 5 | Assert `stats.awaiting_pack` equals count of PICKED orders | Stat reflects queue depth |
| 6 | Assert `stats.packing` equals count of PACKING orders | In-progress count correct |
| 7 | Assert `stats.awaiting_label` equals count of PACKED orders without a shipment that has a `label_url` | Label purchase needed count |
| 8 | Assert `stats.ready_to_ship` equals count of Shipments with `status = label_purchased` | Ready-to-ship count from Shipment table |
| 9 | Assert response is paginated with 20 items per page | Pagination applied |

**Test Data:**

```php
$pickedOrder = Order::factory()->create([
    'status' => Order::STATUS_PICKED,
    'priority' => Order::PRIORITY_URGENT,
    'ship_by_date' => now()->addDay(),
]);

$packingOrder = Order::factory()->create([
    'status' => Order::STATUS_PACKING,
    'priority' => Order::PRIORITY_HIGH,
    'ship_by_date' => now()->addDays(2),
]);

$packedOrder = Order::factory()->create([
    'status' => Order::STATUS_PACKED,
    'priority' => Order::PRIORITY_STANDARD,
    'ship_by_date' => now()->addDays(3),
]);

// Orders that should NOT appear:
Order::factory()->create(['status' => Order::STATUS_PENDING]);
Order::factory()->create(['status' => Order::STATUS_ASSIGNED]);
Order::factory()->create(['status' => Order::STATUS_SHIPPED]);
```

**Postconditions:**

- No database state changes (read-only operation)
- Inertia response includes `orders` (paginated) and `stats` props
- Orders eager-load: `items.product:id,name,sku`, `location:id,name,code`, `shipments`, `assignedPacker:id,name`

**Notes:**

- The pack queue uses the same priority sort as the pick list: `array_position(ARRAY['urgent', 'high', 'standard'], priority)`
- The `awaiting_label` stat uses `whereDoesntHave('shipments', ...)` with a subquery for shipments without `label_url`
- The `ready_to_ship` stat queries the Shipment model directly, not through orders
- Pagination is 20 per page (via `->paginate(20)`)

---

### TC-FUL-003-02: Start pack transitions order to PACKING, creates Shipment with picked items as ShipmentItems

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-003-02 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Fulfillment/PackWorkflowTest.php` (needed) |
| **Test Method** | `test_start_pack_transitions_to_packing_and_creates_shipment()` (needed) |

**Preconditions:**

1. Authenticated user with `orders.view` permission
2. Order exists with `status = Order::STATUS_PICKED`
3. Order has 2 OrderItems with `pick_status = 'picked'` and 1 with `pick_status = 'short'`
4. Order has no existing shipments (`shipments()->count() === 0`)
5. Order has a `location_id` set

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST `fulfillment.pack.start` with the order's UUID | Response redirects back with success message |
| 2 | Refresh the order from database | Order record updated |
| 3 | Assert `order.status` equals `Order::STATUS_PACKING` | Status transitioned to packing |
| 4 | Assert `order.assigned_packer_id` is set (current user if none pre-assigned) | Packer assignment recorded |
| 5 | Assert a FulfillmentLog with action `PACK_STARTED` exists | Audit trail entry created |
| 6 | Assert order has exactly 1 Shipment | Shipment auto-created |
| 7 | Assert the Shipment has `status = Shipment::STATUS_PENDING` | New shipment starts as pending |
| 8 | Assert the Shipment has ShipmentItems only for the 2 picked items (not the shorted item) | Only picked items included in shipment |
| 9 | Assert each ShipmentItem has correct `order_item_id` and `quantity` | Item mapping correct |

**Test Data:**

```php
$order = Order::factory()->create([
    'status' => Order::STATUS_PICKED,
    'location_id' => $location->id,
    'assigned_packer_id' => null,
]);

$pickedItem1 = OrderItem::factory()->create([
    'order_id' => $order->id,
    'pick_status' => OrderItem::PICK_PICKED,
    'quantity' => 1,
    'title' => 'Widget A',
]);

$pickedItem2 = OrderItem::factory()->create([
    'order_id' => $order->id,
    'pick_status' => OrderItem::PICK_PICKED,
    'quantity' => 3,
    'title' => 'Widget B',
]);

$shortedItem = OrderItem::factory()->create([
    'order_id' => $order->id,
    'pick_status' => OrderItem::PICK_SHORT,
    'quantity' => 1,
    'title' => 'Widget C',
]);
```

**Postconditions:**

- `order.status` = `'packing'`
- `order.assigned_packer_id` = authenticated user's ID
- 1 Shipment created via `ShipmentService::createShipment()`
- 2 ShipmentItems created (only for picked items, not shorted)
- FulfillmentLog with `action = 'pack_started'`

**Notes:**

- The controller filters items to only those with `pick_status = 'picked'` before creating the shipment
- Shipment creation is skipped if the order already has shipments (`$order->shipments()->count() === 0` check)
- `FulfillmentService::startPacking()` checks `$order->canBePacked()` which requires status in [picked, packing]
- `ShipmentService::createShipment()` receives the order, items array, and location
- Re-entering `startPack` on an already-PACKING order will NOT create a duplicate shipment (count check)

---

### TC-FUL-003-03: Get shipping rates returns carrier/service/price options

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-003-03 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Fulfillment/PackWorkflowTest.php` (needed) |
| **Test Method** | `test_get_rates_returns_shipping_options()` (needed) |

**Preconditions:**

1. Authenticated user with `orders.view` permission
2. Order exists with `status = Order::STATUS_PACKING`
3. Order has a pending Shipment (`status = Shipment::STATUS_PENDING`)
4. Shipment has ship-from address (from location) and ship-to address (from order)
5. `ShipmentService::getRates()` may need to be mocked (calls external shipping API)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `fulfillment.pack.rates` with the order's UUID | Response redirects back with `rates` session data |
| 2 | Assert `rates` is an array of rate options | Rates returned as array |
| 3 | Assert each rate contains at minimum: carrier name, service level, price, rate_id | Rate structure is complete |
| 4 | Assert rates are sorted by price ascending (cheapest first) or by carrier grouping | Rates are ordered logically |

**Test Data:**

```php
// Mock ShipmentService::getRates() to return:
$mockRates = collect([
    [
        'rate_id' => 'rate_abc123',
        'carrier' => 'USPS',
        'service' => 'Priority Mail',
        'rate' => 8.50,
        'estimated_days' => 3,
    ],
    [
        'rate_id' => 'rate_def456',
        'carrier' => 'UPS',
        'service' => 'Ground',
        'rate' => 12.99,
        'estimated_days' => 5,
    ],
    [
        'rate_id' => 'rate_ghi789',
        'carrier' => 'FedEx',
        'service' => 'Express',
        'rate' => 24.50,
        'estimated_days' => 1,
    ],
]);
```

**Postconditions:**

- No database changes to the order
- Rates returned via session flash (`back()->with('rates', $rates->toArray())`)
- If dimensions were provided as query params, the shipment dimensions are updated before rate fetch

**Notes:**

- The route is a GET request (`fulfillment.pack.rates`) despite potentially updating shipment dimensions
- If `weight_oz`, `length`, `width`, or `height` params are provided, `ShipmentService::updateDimensions()` is called before fetching rates
- If no pending shipment exists for the order, returns redirect with error: `'No pending shipment found for this order.'`
- The rates response format depends on the shipping provider (EasyPost, ShipStation, ShippingEasy)

---

### TC-FUL-003-04: Purchase label stores tracking_number, label_url, label_format, label_cost

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-003-04 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Fulfillment/PackWorkflowTest.php` (needed) |
| **Test Method** | `test_purchase_label_stores_label_details()` (needed) |

**Preconditions:**

1. Authenticated user with `orders.view` permission
2. Order exists with `status = Order::STATUS_PACKING` or `Order::STATUS_PACKED`
3. Order has a Shipment with `status` in [pending, packed]
4. `ShipmentService::purchaseLabel()` may need to be mocked (calls external API)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST `fulfillment.pack.purchase-label` with `rate_id = 'rate_abc123'` | Response redirects back with success message including tracking number |
| 2 | Refresh the shipment from database | Shipment record updated |
| 3 | Assert `shipment.tracking_number` is set (e.g., `'9400111899223456789012'`) | Tracking number stored |
| 4 | Assert `shipment.label_url` is set (URL to downloadable label) | Label URL stored |
| 5 | Assert `shipment.label_cost` is set (e.g., `8.50`) | Label cost recorded as decimal:2 |
| 6 | Assert `shipment.status` equals `Shipment::STATUS_LABEL_PURCHASED` | Status updated |
| 7 | Assert `shipment.label_purchased_at` is set | Timestamp recorded |

**Test Data:**

```php
// POST data:
$postData = [
    'rate_id' => 'rate_abc123',
    'weight_oz' => 12.5,
    'length' => 10.0,
    'width' => 8.0,
    'height' => 4.0,
];

// Validation rules:
// 'rate_id' => 'required|string'
// 'weight_oz' => 'nullable|numeric|min:0.1'
// 'length' => 'nullable|numeric|min:0.1'
// 'width' => 'nullable|numeric|min:0.1'
// 'height' => 'nullable|numeric|min:0.1'

// Mock ShipmentService::purchaseLabel() to return shipment with:
$shipment->tracking_number = '9400111899223456789012';
$shipment->label_url = 'https://easypost.com/labels/abc123.png';
$shipment->label_format = 'PNG';
$shipment->label_cost = 8.50;
$shipment->status = Shipment::STATUS_LABEL_PURCHASED;
$shipment->label_purchased_at = now();
```

**Postconditions:**

- Shipment updated with tracking_number, label_url, label_format, label_cost
- `shipment.status` = `'label_purchased'`
- `shipment.label_purchased_at` set to current timestamp
- If dimensions provided, shipment dimensions updated via `updateDimensions()` before purchase
- `FulfillmentPrintService::autoPrintShippingLabel()` called (auto-print if configured)

**Notes:**

- Validation requires `rate_id` (string), dimensions are optional
- If `weight_oz` is provided, `ShipmentService::updateDimensions()` is called before label purchase
- The controller finds the shipment via `$order->shipments()->whereIn('status', [pending, packed])->first()`
- If no eligible shipment found, returns error: `'No shipment available for label purchase.'`
- After label purchase, `autoPrintShippingLabel()` is called -- this may print to a configured printer or be a no-op
- Success message includes the tracking number: `'Label purchased. Tracking: {tracking_number}'`

---

### TC-FUL-003-05: Complete pack transitions order status, generates packing slip

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-003-05 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Fulfillment/PackWorkflowTest.php` (needed) |
| **Test Method** | `test_complete_pack_transitions_to_packed()` (needed) |

**Preconditions:**

1. Authenticated user with `orders.view` permission
2. Order exists with `status = Order::STATUS_PACKING`
3. Order has items and a shipment

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST `fulfillment.pack.complete` with the order's UUID and optional dimensions | Response redirects back with success message |
| 2 | Refresh the order from database | Order record updated |
| 3 | Assert `order.status` equals `Order::STATUS_PACKED` | Status transitioned to packed |
| 4 | Assert FulfillmentLog with action `PACK_COMPLETED` exists | Audit trail entry |
| 5 | Assert FulfillmentLog details include package dimensions if provided | Dimensions recorded in audit |
| 6 | Assert `FulfillmentPrintService::generatePackingSlip()` was called | Packing slip generated |

**Test Data:**

```php
// POST data (all optional):
$postData = [
    'weight_oz' => 16.0,
    'length' => 12.0,
    'width' => 10.0,
    'height' => 6.0,
];

// Validation rules:
// 'weight_oz' => 'nullable|numeric|min:0.1'
// 'length' => 'nullable|numeric|min:0.1'
// 'width' => 'nullable|numeric|min:0.1'
// 'height' => 'nullable|numeric|min:0.1'
```

**Postconditions:**

- `order.status` = `'packed'`
- FulfillmentLog with `action = 'pack_completed'` and details: `{package: {weight_oz, length, width, height}}`
- Packing slip generated via `FulfillmentPrintService::generatePackingSlip()`
- Order is now eligible for the ship workflow or for label purchase

**Notes:**

- `FulfillmentService::completePacking()` receives the dimensions array and the actor
- Unlike `completePicking()`, there is no item-level validation gate -- pack completion does not check shipment state
- The `generatePackingSlip()` call auto-prints if the user/org has auto-print configured
- Dimensions are passed to `completePacking()` but stored in the audit log, not directly on the order

---

### TC-FUL-003-06: Cannot start pack on non-PICKED order

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-003-06 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Fulfillment/PackWorkflowTest.php` (needed) |
| **Test Method** | `test_start_pack_fails_for_non_picked_order()` (needed) |

**Preconditions:**

1. Authenticated user with `orders.view` permission
2. Orders exist in non-packable statuses: pending, assigned, picking, packed, shipped

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create order with `status = Order::STATUS_PENDING` | Order exists |
| 2 | POST `fulfillment.pack.start` with the order's UUID | Exception thrown |
| 3 | Assert order status remains `pending` | No state change |
| 4 | Repeat for `status = Order::STATUS_ASSIGNED` | Same failure |
| 5 | Repeat for `status = Order::STATUS_PICKING` | Same failure |
| 6 | Repeat for `status = Order::STATUS_PACKED` | Same failure |
| 7 | Repeat for `status = Order::STATUS_SHIPPED` | Same failure |

**Test Data:**

```php
// Non-packable statuses (canBePacked() returns false):
$invalidStatuses = [
    Order::STATUS_PENDING,
    Order::STATUS_ASSIGNED,
    Order::STATUS_PICKING,
    Order::STATUS_PACKED,
    Order::STATUS_SHIPPED,
    Order::STATUS_IN_TRANSIT,
    Order::STATUS_DELIVERED,
    Order::STATUS_CANCELLED,
    Order::STATUS_ON_HOLD,
    Order::STATUS_RETURN_INITIATED,
    Order::STATUS_RETURNED,
];

// Packable statuses (canBePacked() returns true):
$validStatuses = [
    Order::STATUS_PICKED,
    Order::STATUS_PACKING,
];
```

**Postconditions:**

- Order status unchanged
- Exception message: `'Order cannot be packed in its current state.'`
- No Shipment records created
- No FulfillmentLog records created

**Notes:**

- `canBePacked()` returns true only for statuses: `picked`, `packing`
- Starting pack on an already-PACKING order is allowed (re-entry case)
- Re-entering `startPack` on a PACKING order with existing shipments will NOT create a duplicate shipment

---

### TC-FUL-003-07: Get rates with dimensions updates shipment dimensions

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-003-07 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Fulfillment/PackWorkflowTest.php` (needed) |
| **Test Method** | `test_get_rates_updates_shipment_dimensions()` (needed) |

**Preconditions:**

1. Authenticated user with `orders.view` permission
2. Order in PACKING status with a pending Shipment
3. Shipment has no dimensions set initially
4. `ShipmentService` mocked for both `updateDimensions()` and `getRates()`

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | GET `fulfillment.pack.rates?weight_oz=16&length=12&width=8&height=6` with order UUID | Response includes rates |
| 2 | Refresh shipment from database | Shipment dimensions updated |
| 3 | Assert `shipment.package_weight_oz` equals 16.00 | Weight stored |
| 4 | Assert `shipment.package_length` equals 12.00 | Length stored |
| 5 | Assert `shipment.package_width` equals 8.00 | Width stored |
| 6 | Assert `shipment.package_height` equals 6.00 | Height stored |
| 7 | Verify `updateDimensions()` was called before `getRates()` | Order of operations correct |

**Test Data:**

```php
// GET query params:
// ?weight_oz=16&length=12&width=8&height=6

// Shipment before:
$shipment->package_weight_oz = null;
$shipment->package_length = null;
$shipment->package_width = null;
$shipment->package_height = null;

// Shipment after:
$shipment->package_weight_oz = 16.00;
$shipment->package_length = 12.00;
$shipment->package_width = 8.00;
$shipment->package_height = 6.00;
```

**Postconditions:**

- Shipment dimensions updated in database
- Rates fetched using updated dimensions
- Dimension fields are `decimal:2` casts on the Shipment model

**Notes:**

- Dimensions update only happens if at least one dimension param is `filled()` (non-null, non-empty)
- The controller passes `$request->only(['weight_oz', 'length', 'width', 'height'])` to `updateDimensions()`
- If no dimension params are provided, rates are fetched with existing shipment dimensions
- This is a GET route that has a side effect (dimension update) -- potentially an architectural concern

---

### TC-FUL-003-08: Print packing slip generates downloadable PDF

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-003-08 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Fulfillment/PackWorkflowTest.php` (needed) |
| **Test Method** | `test_print_packing_slip_generates_pdf()` (needed) |

**Preconditions:**

1. Authenticated user with `orders.view` permission
2. Order exists (any packable/packed status)
3. `FulfillmentPrintService` available (may need mock)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST `fulfillment.pack.packing-slip` with the order's UUID | Response redirects back with success and `download_url` |
| 2 | Assert the response session contains `download_url` key | URL to PDF provided |
| 3 | Assert `FulfillmentPrintService::generatePackingSlip()` was called with `$order`, `$user`, and `true` (force download flag) | Correct service call with download flag |

**Test Data:**

```php
// No POST body data required -- only the order UUID in the route

// Expected response session:
// 'success' => 'Packing slip generated.'
// 'download_url' => '/fulfillment/documents/packing-slip/order-{id}.pdf'
```

**Postconditions:**

- PDF file generated or mock verifies generation
- Response includes `download_url` and `success` flash data
- No order or shipment status changes

**Notes:**

- The third parameter `true` to `generatePackingSlip()` forces a download URL to be returned (vs. auto-print)
- The packing slip includes order details, items, shipping address, and any special instructions
- The document can be downloaded via `fulfillment.documents.download` route
- No validation is needed on the POST body (only the route-bound order)
