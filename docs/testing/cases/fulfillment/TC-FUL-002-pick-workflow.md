# Pick Workflow Test Cases

## Document ID: TC-FUL-002

**Domain:** Fulfillment
**Related Test Plan:** `docs/testing/plans/fulfillment-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-FUL-002-01: View pick list shows ASSIGNED/PICKING orders sorted by priority desc, ship_by_date

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-002-01 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Fulfillment/PickWorkflowTest.php` (needed) |
| **Test Method** | `test_pick_list_shows_assigned_and_picking_orders_sorted_correctly()` (needed) |

**Preconditions:**

1. Authenticated user with `orders.view` permission (route middleware: `auth:sanctum`, `can:orders.view`)
2. Organization fixtures created via `CreatesOrganizationFixtures` trait
3. `RefreshDatabase` trait resets state
4. Multiple orders exist in various statuses: pending, assigned, picking, picked, shipped
5. At least one InventoryLocation with `is_ship_from = true` and `is_active = true`

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create orders with different statuses: 1 pending, 2 assigned, 1 picking, 1 picked, 1 shipped | Six orders exist in the database |
| 2 | Create orders with different priorities: urgent (assigned), high (assigned), standard (picking) | Orders have varying priorities |
| 3 | Set `ship_by_date` on each order: urgent = tomorrow, high = today, standard = yesterday | Orders have varying ship-by dates |
| 4 | GET `fulfillment.pick.index` as authenticated user | Response status 200, renders `Fulfillment/Pick/Index` |
| 5 | Assert only ASSIGNED and PICKING orders appear in the response `orders` prop | Pending, picked, and shipped orders excluded |
| 6 | Assert order sort: urgent first, then high, then standard (by `array_position` on priority) | Priority ordering: urgent > high > standard |
| 7 | Assert within same priority, orders are sorted by `ship_by_date` ascending | Earlier ship dates come first |
| 8 | Assert `stats.total_orders` equals 3 (2 assigned + 1 picking) | Stats reflect filtered orders |
| 9 | Assert `stats.total_items` equals sum of pending-pick items across those orders | Item count reflects only pending pick items |
| 10 | Assert `stats.urgent` equals 1 | Urgent count correct |
| 11 | Assert `stats.overdue` counts orders where `ship_by_date` is past and status not shipped/delivered/cancelled | Overdue stat accurate |
| 12 | Assert `locations` prop contains active ship-from locations | Location filter options provided |

**Test Data:**

```php
$location = InventoryLocation::factory()->create([
    'is_ship_from' => true,
    'is_active' => true,
    'name' => 'Main Warehouse',
    'code' => 'MW',
]);

$urgentOrder = Order::factory()->create([
    'status' => Order::STATUS_ASSIGNED,
    'priority' => Order::PRIORITY_URGENT,
    'ship_by_date' => now()->addDay(),
    'location_id' => $location->id,
]);

$highOrder = Order::factory()->create([
    'status' => Order::STATUS_ASSIGNED,
    'priority' => Order::PRIORITY_HIGH,
    'ship_by_date' => now(),
    'location_id' => $location->id,
]);

$standardOrder = Order::factory()->create([
    'status' => Order::STATUS_PICKING,
    'priority' => Order::PRIORITY_STANDARD,
    'ship_by_date' => now()->subDay(),
    'location_id' => $location->id,
]);
```

**Postconditions:**

- No database state changes (read-only operation)
- Inertia response includes `orders`, `locations`, `stats`, and `filters` props

**Notes:**

- The sort uses PostgreSQL `array_position(ARRAY['urgent', 'high', 'standard'], priority)` -- this is DB-level sorting, not application-level
- The `orders` query eager-loads `items.product:id,name,sku,internal_sku` and `location:id,name,code`
- Stats computation happens in PHP on the already-loaded collection, not via separate queries
- `isOverdue()` checks: `ship_by_date` is past AND status not in [shipped, in_transit, delivered, cancelled]

---

### TC-FUL-002-02: Start pick transitions order from ASSIGNED to PICKING

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-002-02 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Fulfillment/PickWorkflowTest.php` (needed) |
| **Test Method** | `test_start_pick_transitions_order_to_picking()` (needed) |

**Preconditions:**

1. Authenticated user with `orders.view` permission
2. Order exists with `status = Order::STATUS_ASSIGNED`
3. Order has at least one OrderItem with `pick_status = 'pending'`

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST `fulfillment.pick.start` with the order's UUID | Response redirects back with success flash message |
| 2 | Refresh the order from database | Order record updated |
| 3 | Assert `order.status` equals `Order::STATUS_PICKING` | Status transitioned to picking |
| 4 | Assert `order.assigned_picker_id` is set | Picker assigned (current user if none was previously assigned) |
| 5 | Assert a FulfillmentLog record exists with action `PICK_STARTED` | Audit trail entry created |

**Test Data:**

```php
$order = Order::factory()->create([
    'organization_id' => $org->id,
    'status' => Order::STATUS_ASSIGNED,
    'location_id' => $location->id,
    'assigned_picker_id' => null,
]);

OrderItem::factory()->create([
    'order_id' => $order->id,
    'pick_status' => OrderItem::PICK_PENDING,
    'quantity' => 2,
]);
```

**Postconditions:**

- `order.status` = `picking`
- `order.assigned_picker_id` = authenticated user's ID (when no picker was pre-assigned)
- FulfillmentLog record with `action = 'pick_started'`, referencing the order and actor

**Notes:**

- `FulfillmentService::startPicking()` checks `$order->canBePicked()` which requires status in [assigned, picking]
- If `assigned_picker_id` was already set (pre-assigned picker), it is preserved
- If `assigned_picker_id` is null, the current actor becomes the picker
- Route uses model binding: `{order}` resolves to Order model by UUID

---

### TC-FUL-002-03: Pick item updates pick_status to 'picked', sets picked_at and picked_by

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-002-03 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Fulfillment/PickWorkflowTest.php` (needed) |
| **Test Method** | `test_pick_item_updates_status_and_timestamps()` (needed) |

**Preconditions:**

1. Authenticated user with `orders.view` permission
2. Order exists with `status = Order::STATUS_PICKING`
3. OrderItem exists with `pick_status = 'pending'` and `quantity = 2`

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST `fulfillment.pick.item` with the order item's UUID and optional `quantity` param | Response redirects back with success message |
| 2 | Refresh the order item from database | Item record updated |
| 3 | Assert `orderItem.pick_status` equals `OrderItem::PICK_PICKED` (`'picked'`) | Pick status updated |
| 4 | Assert `orderItem.picked_at` is set to approximately now | Timestamp recorded |
| 5 | Assert `orderItem.picked_by` equals the authenticated user's ID | Picker identity recorded |
| 6 | Assert a FulfillmentLog record exists with action `ITEM_PICKED` | Audit trail entry for item pick |

**Test Data:**

```php
$orderItem = OrderItem::factory()->create([
    'order_id' => $order->id,
    'pick_status' => OrderItem::PICK_PENDING,
    'quantity' => 2,
    'title' => 'Blue Widget',
    'sku' => 'BW-001',
]);

// POST data (quantity is optional, defaults to item's full quantity):
$postData = ['quantity' => 2];
```

**Postconditions:**

- `order_items.pick_status` = `'picked'`
- `order_items.picked_at` = current timestamp
- `order_items.picked_by` = user ID
- FulfillmentLog with action `ITEM_PICKED`, including `quantity`, `sku`, and `title` in details

**Notes:**

- The `quantity` parameter is validated: `nullable|integer|min:1|max:{item.quantity}`
- If `quantity` is omitted, it defaults to the item's full quantity
- Route binds `{orderItem}` to the OrderItem model (not the order)
- The pick action does NOT change the parent order's status -- that requires `completePick`
- The `picked_by` field stores the user ID (integer), not a UUID

---

### TC-FUL-002-04: Short item updates pick_status to 'short' with reason

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-002-04 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Fulfillment/PickWorkflowTest.php` (needed) |
| **Test Method** | `test_short_item_updates_status_with_reason()` (needed) |

**Preconditions:**

1. Authenticated user with `orders.view` permission
2. Order exists with `status = Order::STATUS_PICKING`
3. OrderItem exists with `pick_status = 'pending'`

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST `fulfillment.pick.short` with the order item's UUID and `reason = 'Item not found in bin B-12'` | Response redirects back with warning flash message |
| 2 | Refresh the order item from database | Item record updated |
| 3 | Assert `orderItem.pick_status` equals `OrderItem::PICK_SHORT` (`'short'`) | Pick status set to short |
| 4 | Assert `orderItem.picked_at` is set | Timestamp recorded (same field used for shorts) |
| 5 | Assert `orderItem.picked_by` equals the authenticated user's ID | Actor recorded |
| 6 | Assert FulfillmentLog with action `PICK_SHORTED` exists, and details include the reason | Audit trail includes short reason |

**Test Data:**

```php
// POST data:
$postData = ['reason' => 'Item not found in bin B-12'];

// Validation rules:
// 'reason' => 'required|string|max:500'
```

**Postconditions:**

- `order_items.pick_status` = `'short'`
- `order_items.picked_at` = current timestamp
- `order_items.picked_by` = user ID
- FulfillmentLog with action `PICK_SHORTED`, details: `{reason, sku, title}`

**Notes:**

- Validation: `reason` is required, string, max 500 characters
- Missing `reason` returns 422 validation error
- Empty string `reason` returns 422 validation error
- Shorted items still count as "resolved" for the `completePick` check
- Edge case: POST with `reason` exceeding 500 chars should return 422

---

### TC-FUL-002-05: Complete pick transitions order from PICKING to PICKED

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-002-05 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Fulfillment/PickWorkflowTest.php` (needed) |
| **Test Method** | `test_complete_pick_transitions_to_picked()` (needed) |

**Preconditions:**

1. Authenticated user with `orders.view` permission
2. Order exists with `status = Order::STATUS_PICKING`
3. All OrderItems for this order have `pick_status` of either `'picked'` or `'short'` (none pending)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Ensure all order items are in a terminal pick state (picked or short) | All items resolved |
| 2 | POST `fulfillment.pick.complete` with the order's UUID | Response redirects back with success message |
| 3 | Refresh the order from database | Order record updated |
| 4 | Assert `order.status` equals `Order::STATUS_PICKED` | Status transitioned to picked |
| 5 | Assert FulfillmentLog with action `PICK_COMPLETED` exists | Audit trail entry created |
| 6 | Assert FulfillmentLog details include `items_picked` and `items_shorted` counts | Pick summary recorded |

**Test Data:**

```php
$order = Order::factory()->create([
    'status' => Order::STATUS_PICKING,
]);

// 2 items picked, 1 item shorted
OrderItem::factory()->create([
    'order_id' => $order->id,
    'pick_status' => OrderItem::PICK_PICKED,
]);
OrderItem::factory()->create([
    'order_id' => $order->id,
    'pick_status' => OrderItem::PICK_PICKED,
]);
OrderItem::factory()->create([
    'order_id' => $order->id,
    'pick_status' => OrderItem::PICK_SHORT,
]);

// Expected log details:
// { items_picked: 2, items_shorted: 1 }
```

**Postconditions:**

- `order.status` = `'picked'`
- FulfillmentLog with `action = 'pick_completed'` and details `{items_picked: 2, items_shorted: 1}`
- Order is now eligible for the pack workflow (`canBePacked()` returns true for status = picked)

**Notes:**

- `completePicking()` checks `$order->items->every()` -- each item must have pick_status in [picked, short]
- If ANY item is still `pending`, the method throws an Exception (see TC-FUL-002-06)
- Items with `pick_status = 'short'` are acceptable for completion -- the order proceeds with partial fulfillment
- The order items collection must be loaded (eager or lazy) before the every() check

---

### TC-FUL-002-06: Cannot complete pick if items still pending

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-002-06 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Fulfillment/PickWorkflowTest.php` (needed) |
| **Test Method** | `test_complete_pick_fails_when_items_still_pending()` (needed) |

**Preconditions:**

1. Authenticated user with `orders.view` permission
2. Order exists with `status = Order::STATUS_PICKING`
3. Order has 3 items: 1 picked, 1 short, 1 still pending

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST `fulfillment.pick.complete` with the order's UUID | Response returns error (500 or handled exception) |
| 2 | Assert `order.status` remains `Order::STATUS_PICKING` | Status NOT changed |
| 3 | Assert no FulfillmentLog with action `PICK_COMPLETED` exists | No audit entry for incomplete pick |

**Test Data:**

```php
$order = Order::factory()->create([
    'status' => Order::STATUS_PICKING,
]);

OrderItem::factory()->create([
    'order_id' => $order->id,
    'pick_status' => OrderItem::PICK_PICKED,
]);
OrderItem::factory()->create([
    'order_id' => $order->id,
    'pick_status' => OrderItem::PICK_SHORT,
]);
OrderItem::factory()->create([
    'order_id' => $order->id,
    'pick_status' => OrderItem::PICK_PENDING, // Still pending
]);
```

**Postconditions:**

- Order status unchanged (still `picking`)
- Exception message: `'Not all items have been picked or marked short.'`
- No FulfillmentLog records created for this attempt

**Notes:**

- `FulfillmentService::completePicking()` throws `Exception` when validation fails
- The controller does not catch this exception -- it will bubble up as a 500 error
- Consider adding proper error handling in the controller to return a user-friendly message
- This is a guard against workflow violations in the pick process

---

### TC-FUL-002-07: Cannot start pick on non-ASSIGNED order

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-002-07 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Fulfillment/PickWorkflowTest.php` (needed) |
| **Test Method** | `test_start_pick_fails_for_non_assigned_order()` (needed) |

**Preconditions:**

1. Authenticated user with `orders.view` permission
2. Orders exist in various non-pickable statuses: pending, picked, packing, packed, shipped

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create order with `status = Order::STATUS_PENDING` | Order exists in pending state |
| 2 | POST `fulfillment.pick.start` with the order's UUID | Exception thrown, request fails |
| 3 | Assert order status remains `pending` | No state change |
| 4 | Repeat for `status = Order::STATUS_PICKED` | Same failure behavior |
| 5 | Repeat for `status = Order::STATUS_SHIPPED` | Same failure behavior |
| 6 | Repeat for `status = Order::STATUS_CANCELLED` | Same failure behavior |

**Test Data:**

```php
// Non-pickable statuses (canBePicked() returns false):
$invalidStatuses = [
    Order::STATUS_PENDING,
    Order::STATUS_PICKED,
    Order::STATUS_PACKING,
    Order::STATUS_PACKED,
    Order::STATUS_SHIPPED,
    Order::STATUS_IN_TRANSIT,
    Order::STATUS_DELIVERED,
    Order::STATUS_CANCELLED,
    Order::STATUS_ON_HOLD,
    Order::STATUS_RETURN_INITIATED,
    Order::STATUS_RETURNED,
];

// Pickable statuses (canBePicked() returns true):
$validStatuses = [
    Order::STATUS_ASSIGNED,
    Order::STATUS_PICKING,
];
```

**Postconditions:**

- Order status unchanged for all invalid statuses
- Exception message: `'Order cannot be picked in its current state.'`
- No FulfillmentLog records created

**Notes:**

- `canBePicked()` returns true only for statuses: `assigned`, `picking`
- Starting a pick on an already-PICKING order is allowed (re-entry, e.g., picker resumes after break)
- The exception is thrown by `FulfillmentService::startPicking()`, not the controller

---

### TC-FUL-002-08: Pick list filtered by location_id

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-002-08 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Fulfillment/PickWorkflowTest.php` (needed) |
| **Test Method** | `test_pick_list_filters_by_location_id()` (needed) |

**Preconditions:**

1. Authenticated user with `orders.view` permission
2. Two InventoryLocations exist (Location A and Location B), both `is_ship_from = true`, `is_active = true`
3. Orders assigned to Location A and Location B

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create 2 assigned orders at Location A and 1 assigned order at Location B | 3 orders total |
| 2 | GET `fulfillment.pick.index?location_id={locationA.id}` | Response status 200 |
| 3 | Assert only 2 orders appear (both from Location A) | Location B order filtered out |
| 4 | Assert `filters.location_id` equals Location A's ID in the response | Filter state returned to frontend |
| 5 | GET `fulfillment.pick.index` (no location_id param) | All 3 orders appear |

**Test Data:**

```php
$locationA = InventoryLocation::factory()->create([
    'name' => 'Warehouse East',
    'code' => 'WE',
    'is_ship_from' => true,
    'is_active' => true,
]);

$locationB = InventoryLocation::factory()->create([
    'name' => 'Warehouse West',
    'code' => 'WW',
    'is_ship_from' => true,
    'is_active' => true,
]);
```

**Postconditions:**

- No database changes (read-only operation)
- Response `locations` prop always includes both locations regardless of filter
- Response `stats` reflect only the filtered orders when location_id is provided

**Notes:**

- The `location_id` filter is applied via `$query->where('location_id', $locationId)` only when the param is present
- The `locations` dropdown always shows all active ship-from locations (not filtered)
- Stats are computed on the filtered `$orders` collection, so they reflect the location filter

---

### TC-FUL-002-09: Print pick list generates PDF for location

| Field | Value |
|-------|-------|
| **ID** | TC-FUL-002-09 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Fulfillment/PickWorkflowTest.php` (needed) |
| **Test Method** | `test_print_pick_list_generates_pdf()` (needed) |

**Preconditions:**

1. Authenticated user with `orders.view` permission
2. InventoryLocation exists with `is_ship_from = true`
3. At least one order assigned to this location with status ASSIGNED or PICKING
4. `FulfillmentPrintService` is available (may need to mock PDF generation)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST `fulfillment.pick.print` with `location_id = {location.id}` | Response redirects back with success message and `download_url` |
| 2 | Assert the response session contains `download_url` | URL to generated PDF provided |
| 3 | Assert `FulfillmentPrintService::generatePickList()` was called with the location and orders | Print service invoked correctly |

**Test Data:**

```php
// POST data:
$postData = ['location_id' => $location->id];

// Validation rules:
// 'location_id' => 'required|exists:inventory_locations,id'
```

**Postconditions:**

- PDF file generated (or mock verifies generation call)
- Response includes `download_url` in session flash data
- No order status changes

**Notes:**

- Validation: `location_id` is required and must exist in `inventory_locations` table
- Missing `location_id` returns 422 validation error
- Invalid `location_id` (non-existent) returns 422 validation error
- If no orders exist at the location, returns redirect with info message: `'No items to pick at this location.'`
- The `getPickList()` method on FulfillmentService returns orders for the location
- PDF generation may require mocking `FulfillmentPrintService` in tests to avoid actual file I/O
