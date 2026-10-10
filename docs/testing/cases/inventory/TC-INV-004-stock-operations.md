# Stock Operations Test Cases

## Document ID: TC-INV-004

**Domain:** Inventory
**Related Test Plan:** `docs/testing/plans/inventory-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-INV-004-01: Adjust stock UP creates InventoryMovement with type=adjustment, balance tracked

| Field | Value |
|-------|-------|
| **ID** | TC-INV-004-01 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/StockOperationsTest.php` (needed) |
| **Test Method** | `test_adjust_stock_up_creates_movement_and_updates_level()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `inventory.adjust` permission
3. A product exists in the organization
4. An InventoryLocation exists in the organization
5. An InventoryLevel exists for the product at the location with `quantity_on_hand = 20`

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` request to `route('inventory.operations.adjust.store')` with `product_id`, `location_id`, `quantity = 10`, `reason = 'found'`, `notes = 'Found in back room'` | Response redirects with success flash |
| 2 | Assert an InventoryMovement record was created | Movement exists in database |
| 3 | Assert movement `type` is `'adjustment'` | Movement type is adjustment |
| 4 | Assert movement `quantity` is `10` (positive for stock increase) | Positive quantity for increase |
| 5 | Assert movement `balance_before` is `20` | Balance before adjustment recorded |
| 6 | Assert movement `balance_after` is `30` | Balance after = before + adjustment |
| 7 | Assert movement `reason` is `'found'` | Reason code stored |
| 8 | Assert movement `notes` is `'Found in back room'` | Freeform notes stored |
| 9 | Assert movement `created_by` is the authenticated user's ID | Audit trail: who performed the adjustment |
| 10 | Refresh the InventoryLevel record | Fresh data loaded |
| 11 | Assert `quantity_on_hand` is now `30` (was 20, +10) | Inventory level updated |
| 12 | Assert `last_movement_at` is approximately now | Timestamp updated |

**Test Data:**

```php
$product = Product::factory()->create([
    'organization_id' => $this->organization->id,
    'sku' => 'TEST-ADJ-001',
    'stock_quantity' => 20,
]);

$location = InventoryLocation::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Main Warehouse',
    'type' => 'warehouse',
]);

InventoryLevel::create([
    'organization_id' => $this->organization->id,
    'product_id' => $product->id,
    'location_id' => $location->id,
    'quantity_on_hand' => 20,
    'quantity_reserved' => 0,
    'average_cost' => 10.00,
]);

$adjustmentData = [
    'product_id' => $product->id,
    'location_id' => $location->id,
    'quantity' => 10,
    'reason' => 'found',
    'notes' => 'Found in back room during recount',
];
```

**Postconditions:**

- One InventoryMovement record created with `type = 'adjustment'` and positive quantity
- InventoryLevel `quantity_on_hand` incremented by the adjustment amount
- `balance_before` and `balance_after` provide audit trail for the change
- Movement `organization_id` matches the product's organization
- `created_by` references the authenticated user

**Notes:**

- Routes: `GET inventory.operations.adjust.form` (display form) and `POST inventory.operations.adjust.store` (process adjustment)
- The InventoryMovement model uses `HasUuid` for UUID primary keys
- `UPDATED_AT` is set to null on InventoryMovement -- only `created_at` is tracked
- Reason codes: `recount`, `damaged`, `theft`, `found`, `received`, `returned`, `correction`, `expired`, `quality`, `other`
- `balance_before` and `balance_after` are critical for audit trail and reconciliation

---

### TC-INV-004-02: Adjust stock DOWN creates movement and updates InventoryLevel.quantity_on_hand

| Field | Value |
|-------|-------|
| **ID** | TC-INV-004-02 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/StockOperationsTest.php` (needed) |
| **Test Method** | `test_adjust_stock_down_creates_movement_and_decrements_level()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `inventory.adjust` permission
3. Product exists with an InventoryLevel at a location with `quantity_on_hand = 50`

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('inventory.operations.adjust.store')` with `quantity = -15`, `reason = 'damaged'` | Response redirects with success flash |
| 2 | Assert InventoryMovement created with `type = 'adjustment'` | Movement record exists |
| 3 | Assert movement `quantity` is `-15` (negative for stock decrease) | Negative quantity for decrease |
| 4 | Assert movement `balance_before` is `50` | Prior balance recorded |
| 5 | Assert movement `balance_after` is `35` | 50 - 15 = 35 |
| 6 | Assert movement `reason` is `'damaged'` | Reason code for the decrease |
| 7 | Refresh InventoryLevel | Fresh data |
| 8 | Assert `quantity_on_hand` is `35` | Level decremented |

**Test Data:**

```php
InventoryLevel::create([
    'organization_id' => $this->organization->id,
    'product_id' => $product->id,
    'location_id' => $location->id,
    'quantity_on_hand' => 50,
    'quantity_reserved' => 0,
]);

$adjustmentData = [
    'product_id' => $product->id,
    'location_id' => $location->id,
    'quantity' => -15,
    'reason' => 'damaged',
    'notes' => '15 units damaged during transit',
];
```

**Postconditions:**

- InventoryLevel `quantity_on_hand` decremented by 15
- Movement `isDecrease()` returns true (quantity < 0)
- `balance_after` correctly reflects the new balance

**Notes:**

- The `InventoryMovement::isDecrease()` method checks `$this->quantity < 0`
- The `scopeDecreases()` scope filters for movements with `quantity < 0`
- Negative adjustments should not reduce `quantity_on_hand` below 0 (business rule depends on implementation)
- Consider testing edge case: adjusting down by more than available quantity

---

### TC-INV-004-03: Adjust stock updates Product.stock_quantity (aggregate across locations)

| Field | Value |
|-------|-------|
| **ID** | TC-INV-004-03 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/StockOperationsTest.php` (needed) |
| **Test Method** | `test_adjust_stock_updates_product_aggregate_quantity()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `inventory.adjust` permission
3. A product exists with InventoryLevels at two locations: Location A (qty 30) and Location B (qty 20). Product `stock_quantity` = 50 (aggregate).

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to adjust stock at Location A: `quantity = +10` | Adjustment processed |
| 2 | Refresh product from database | Fresh data loaded |
| 3 | Assert `Product.stock_quantity` equals 60 (was 50, +10 at Location A) | Aggregate stock updated |
| 4 | Assert InventoryLevel at Location A has `quantity_on_hand = 40` (was 30, +10) | Location-specific level updated |
| 5 | Assert InventoryLevel at Location B is unchanged at `quantity_on_hand = 20` | Other locations unaffected |
| 6 | Send `POST` to adjust stock at Location B: `quantity = -5` | Second adjustment processed |
| 7 | Refresh product | Fresh data |
| 8 | Assert `Product.stock_quantity` equals 55 (was 60, -5 at Location B) | Aggregate decremented |

**Test Data:**

```php
$product = Product::factory()->create([
    'organization_id' => $this->organization->id,
    'stock_quantity' => 50,
]);

$locationA = InventoryLocation::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Warehouse A',
]);

$locationB = InventoryLocation::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Warehouse B',
]);

InventoryLevel::create([
    'organization_id' => $this->organization->id,
    'product_id' => $product->id,
    'location_id' => $locationA->id,
    'quantity_on_hand' => 30,
]);

InventoryLevel::create([
    'organization_id' => $this->organization->id,
    'product_id' => $product->id,
    'location_id' => $locationB->id,
    'quantity_on_hand' => 20,
]);
```

**Postconditions:**

- `Product.stock_quantity` reflects the sum of `quantity_on_hand` across all locations
- Individual InventoryLevel records at each location are independently updated
- Two InventoryMovement records exist (one per adjustment)

**Notes:**

- `Product.stock_quantity` is the aggregate total across all locations
- `Product.available_quantity` is the single source of truth for cross-marketplace sell quantity and may be updated separately
- The adjustment endpoint may also update `Product.available_quantity` if the adjustment affects sellable inventory
- The `ProductController@adjustStock` endpoint (`POST /inventory/products/{product}/adjust-stock`) provides an alternative product-centric adjustment interface

---

### TC-INV-004-04: Transfer stock between locations creates transfer_out and transfer_in movements

| Field | Value |
|-------|-------|
| **ID** | TC-INV-004-04 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/StockOperationsTest.php` (needed) |
| **Test Method** | `test_transfer_stock_creates_paired_movements()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `inventory.transfer` permission
3. Two locations exist: Source (qty 30) and Destination (qty 10)
4. InventoryLevels exist for the product at both locations

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('inventory.operations.transfer.store')` with `product_id`, `from_location_id`, `to_location_id`, `quantity = 15` | Response redirects with success flash |
| 2 | Assert two InventoryMovement records were created | Two movements exist for this operation |
| 3 | Assert first movement has `type = 'transfer_out'`, `location_id = source`, `quantity = -15` | Outbound movement at source location |
| 4 | Assert second movement has `type = 'transfer_in'`, `location_id = destination`, `quantity = 15` | Inbound movement at destination location |
| 5 | Assert source movement `balance_before = 30`, `balance_after = 15` | Source balance tracked |
| 6 | Assert destination movement `balance_before = 10`, `balance_after = 25` | Destination balance tracked |
| 7 | Refresh source InventoryLevel | Fresh data |
| 8 | Assert source `quantity_on_hand = 15` (was 30, -15) | Source decremented |
| 9 | Refresh destination InventoryLevel | Fresh data |
| 10 | Assert destination `quantity_on_hand = 25` (was 10, +15) | Destination incremented |
| 11 | Assert `Product.stock_quantity` is unchanged (total = 40) | Transfer is zero-sum across the product |

**Test Data:**

```php
$product = Product::factory()->create([
    'organization_id' => $this->organization->id,
    'stock_quantity' => 40,
]);

$sourceLocation = InventoryLocation::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Source Warehouse',
]);

$destLocation = InventoryLocation::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Destination Store',
]);

InventoryLevel::create([
    'organization_id' => $this->organization->id,
    'product_id' => $product->id,
    'location_id' => $sourceLocation->id,
    'quantity_on_hand' => 30,
]);

InventoryLevel::create([
    'organization_id' => $this->organization->id,
    'product_id' => $product->id,
    'location_id' => $destLocation->id,
    'quantity_on_hand' => 10,
]);

$transferData = [
    'product_id' => $product->id,
    'from_location_id' => $sourceLocation->id,
    'to_location_id' => $destLocation->id,
    'quantity' => 15,
    'notes' => 'Restock retail location',
];
```

**Postconditions:**

- Two paired InventoryMovement records created (transfer_out + transfer_in)
- Source InventoryLevel decremented, destination incremented
- `Product.stock_quantity` unchanged (transfer is a zero-sum redistribution)
- `Product.available_quantity` unchanged (transfer does not affect marketplace sell quantity)
- Both movements share the same `reference_type` and `reference_id` for traceability

**Notes:**

- Routes: `GET inventory.operations.transfer.form` (display form), `POST inventory.operations.transfer.store` (process transfer)
- The transfer operation should be atomic (wrapped in a DB transaction)
- `transfer_out` movement has negative quantity; `transfer_in` has positive quantity
- Movement constants: `TYPE_TRANSFER_IN = 'transfer_in'`, `TYPE_TRANSFER_OUT = 'transfer_out'`
- Transfer between the same location (from = to) should be rejected by validation

---

### TC-INV-004-05: Transfer stock fails if source location has insufficient quantity

| Field | Value |
|-------|-------|
| **ID** | TC-INV-004-05 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/StockOperationsTest.php` (needed) |
| **Test Method** | `test_transfer_fails_on_insufficient_source_quantity()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `inventory.transfer` permission
3. Source location has `quantity_on_hand = 5` for the product
4. Transfer request is for `quantity = 20` (more than available)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('inventory.operations.transfer.store')` with `quantity = 20` (source only has 5) | Response redirects back with error or returns 422 |
| 2 | Assert error message indicates insufficient quantity | Error mentions available quantity or insufficient stock |
| 3 | Assert no InventoryMovement records were created | No partial transfer occurred |
| 4 | Assert source InventoryLevel `quantity_on_hand` is still 5 | Source unchanged |
| 5 | Assert destination InventoryLevel is unchanged | Destination unchanged |
| 6 | Assert `Product.stock_quantity` is unchanged | No aggregate change |

**Test Data:**

```php
InventoryLevel::create([
    'organization_id' => $this->organization->id,
    'product_id' => $product->id,
    'location_id' => $sourceLocation->id,
    'quantity_on_hand' => 5,
    'quantity_reserved' => 2, // 3 available (5 on_hand - 2 reserved)
]);

$transferData = [
    'product_id' => $product->id,
    'from_location_id' => $sourceLocation->id,
    'to_location_id' => $destLocation->id,
    'quantity' => 20, // exceeds available
];
```

**Postconditions:**

- No database mutations (transaction rolled back or validation prevented execution)
- Both InventoryLevels remain at their original values
- No InventoryMovement records created

**Notes:**

- The check should consider `quantity_available` (on_hand - reserved), not just `quantity_on_hand`
- Reserved stock should not be transferable without first releasing the reservation
- The validation may occur in the form request, controller, or service layer
- The transfer operation should be atomic -- if the destination update fails, the source decrement should also be rolled back

---

### TC-INV-004-06: Reserve stock increments InventoryLevel.quantity_reserved

| Field | Value |
|-------|-------|
| **ID** | TC-INV-004-06 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/StockOperationsTest.php` (needed) |
| **Test Method** | `test_reserve_stock_increments_quantity_reserved()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `inventory.adjust` permission
3. An InventoryLevel exists with `quantity_on_hand = 20`, `quantity_reserved = 0`

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('inventory.operations.reserve.store')` with `product_id`, `location_id`, `quantity = 5` | Response redirects with success flash |
| 2 | Refresh InventoryLevel from database | Fresh data loaded |
| 3 | Assert `quantity_reserved` is `5` (was 0, +5) | Reserved quantity incremented |
| 4 | Assert `quantity_on_hand` is still `20` | On-hand quantity unchanged by reservation |
| 5 | Assert `quantity_available` accessor returns `15` (20 - 5) | Available = on_hand - reserved |
| 6 | Assert `canReserve(15)` returns true | 15 available, can reserve 15 more |
| 7 | Assert `canReserve(16)` returns false | Cannot reserve more than available |

**Test Data:**

```php
$level = InventoryLevel::create([
    'organization_id' => $this->organization->id,
    'product_id' => $product->id,
    'location_id' => $location->id,
    'quantity_on_hand' => 20,
    'quantity_reserved' => 0,
]);

$reserveData = [
    'product_id' => $product->id,
    'location_id' => $location->id,
    'quantity' => 5,
];
```

**Postconditions:**

- `quantity_reserved` incremented by the reservation amount
- `quantity_on_hand` unchanged (reservation does not consume stock, only earmarks it)
- `quantity_available` (computed: `on_hand - reserved`) reflects the reduced available quantity
- The `reserve()` method on InventoryLevel uses `$this->increment('quantity_reserved', $quantity)`

**Notes:**

- Routes: `GET inventory.operations.reserve.form`, `POST inventory.operations.reserve.store`
- The `InventoryLevel::reserve($quantity)` method checks `canReserve($quantity)` before incrementing
- `canReserve()` checks `$this->quantity_available >= $quantity` where `quantity_available = on_hand - reserved`
- This is separate from `Product.reserved_quantity` and `Product.available_quantity` which operate at the product level (cross-location)
- Order imports use `Product.decrementAvailableQuantity()` for marketplace-level reservation, not this endpoint

---

### TC-INV-004-07: Release reserved stock decrements quantity_reserved

| Field | Value |
|-------|-------|
| **ID** | TC-INV-004-07 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/StockOperationsTest.php` (needed) |
| **Test Method** | `test_release_reserved_stock_decrements_quantity_reserved()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `inventory.adjust` permission
3. An InventoryLevel exists with `quantity_on_hand = 20`, `quantity_reserved = 8`

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Call `InventoryLevel::unreserve(3)` or send appropriate request to release 3 units | Unreserve operation succeeds (returns true) |
| 2 | Refresh InventoryLevel | Fresh data loaded |
| 3 | Assert `quantity_reserved` is `5` (was 8, -3) | Reserved quantity decremented |
| 4 | Assert `quantity_on_hand` is still `20` | On-hand unchanged |
| 5 | Assert `quantity_available` accessor returns `15` (20 - 5) | Available increased |
| 6 | Attempt to unreserve more than currently reserved (e.g., `unreserve(10)` when only 5 reserved) | Returns false, no change |
| 7 | Assert `quantity_reserved` is still `5` after failed unreserve | No negative reserved quantity |

**Test Data:**

```php
$level = InventoryLevel::create([
    'organization_id' => $this->organization->id,
    'product_id' => $product->id,
    'location_id' => $location->id,
    'quantity_on_hand' => 20,
    'quantity_reserved' => 8,
]);

// Release 3: quantity_reserved goes from 8 to 5
// Release 10 (from 5): fails, returns false, quantity_reserved stays at 5
```

**Postconditions:**

- `quantity_reserved` decremented on successful unreserve
- `quantity_reserved` unchanged on failed unreserve (cannot go below 0)
- The `unreserve()` method checks `$this->quantity_reserved >= $quantity` before decrementing
- Uses `$this->decrement('quantity_reserved', $quantity)` for atomic operation

**Notes:**

- The `InventoryLevel::unreserve()` method returns a boolean: true on success, false on failure
- The failure check: `if ($this->quantity_reserved < $quantity) return false;`
- This prevents `quantity_reserved` from going negative
- Unreserving stock is the reverse of reserving and is typically triggered when an order is cancelled or fulfilled

---

### TC-INV-004-08: Movement records reason code and notes

| Field | Value |
|-------|-------|
| **ID** | TC-INV-004-08 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/StockOperationsTest.php` (needed) |
| **Test Method** | `test_movement_records_reason_code_and_notes()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `inventory.adjust` permission
3. A product with an InventoryLevel at a location

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create a stock adjustment with `reason = 'recount'` and `notes = 'Annual physical inventory count'` | Adjustment processed |
| 2 | Load the created InventoryMovement | Movement record retrieved |
| 3 | Assert `reason` equals `'recount'` | Reason code stored |
| 4 | Assert `notes` equals `'Annual physical inventory count'` | Freeform notes stored |
| 5 | Assert `getMovementTypeLabel()` returns `'Adjustment'` | Human-readable type label |
| 6 | Create another adjustment with `reason = 'theft'` | Second adjustment processed |
| 7 | Assert second movement `reason` equals `'theft'` | Different reason code stored |
| 8 | Create a movement with no reason or notes (both null) | Movement created with null reason and notes |
| 9 | Assert null values are stored correctly | Both fields accept null |

**Test Data:**

```php
// Valid reason codes:
$validReasons = [
    'recount',    // Physical inventory recount
    'damaged',    // Items damaged
    'theft',      // Theft/shrinkage
    'found',      // Items found/recovered
    'received',   // Received from supplier
    'returned',   // Customer return
    'correction', // Data entry correction
    'expired',    // Items expired
    'quality',    // Quality control rejection
    'other',      // Other/unspecified
];

$adjustmentData = [
    'product_id' => $product->id,
    'location_id' => $location->id,
    'quantity' => -3,
    'reason' => 'damaged',
    'notes' => 'Three units had water damage from roof leak',
];
```

**Postconditions:**

- InventoryMovement record has `reason` and `notes` fields populated
- Both fields are nullable (not required)
- `reason` is a string enum stored as a plain string column
- `notes` is a freeform text field

**Notes:**

- The `reason` field stores a machine-readable code (e.g., `recount`, `damaged`) rather than human-readable text
- The `notes` field provides additional context for auditors
- Both fields are included in the `$fillable` array on the InventoryMovement model
- The API endpoint at `GET api/v1/inventory/reason-codes` returns the list of valid reason codes
- The `InventoryMovementController::reasonCodes()` method provides the reason code reference

---

### TC-INV-004-09: Movement records reference_type and reference_id for traceability

| Field | Value |
|-------|-------|
| **ID** | TC-INV-004-09 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/StockOperationsTest.php` (needed) |
| **Test Method** | `test_movement_records_reference_for_traceability()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `inventory.adjust` permission
3. A product and location exist with an InventoryLevel
4. A PurchaseOrder exists for reference linking

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create an InventoryMovement with `reference_type = 'App\\Models\\Procurement\\PurchaseOrder'` and `reference_id = $purchaseOrder->id` | Movement created with reference |
| 2 | Assert `reference_type` equals `'App\\Models\\Procurement\\PurchaseOrder'` | Polymorphic type stored |
| 3 | Assert `reference_id` equals the purchase order's UUID | Reference ID stored |
| 4 | Assert `reference_number` stores a human-readable reference (e.g., PO number) | Reference number for display |
| 5 | Create a movement with `reference_type = 'App\\Models\\Fulfillment\\Order'` and an order reference | Movement created with order reference |
| 6 | Assert movement can trace back to the originating record | Reference fields enable full traceability |
| 7 | Create a movement with no reference (manual adjustment) | `reference_type` and `reference_id` are null |

**Test Data:**

```php
$purchaseOrder = PurchaseOrder::factory()->create([
    'organization_id' => $this->organization->id,
    'po_number' => 'PO-2026-0042',
]);

$movementData = [
    'organization_id' => $this->organization->id,
    'product_id' => $product->id,
    'location_id' => $location->id,
    'type' => InventoryMovement::TYPE_PURCHASE,
    'quantity' => 50,
    'unit_cost' => 10.00,
    'total_cost' => 500.00,
    'reference_type' => 'App\\Models\\Procurement\\PurchaseOrder',
    'reference_id' => $purchaseOrder->id,
    'reference_number' => 'PO-2026-0042',
    'reason' => 'received',
    'balance_before' => 0,
    'balance_after' => 50,
];
```

**Postconditions:**

- InventoryMovement has `reference_type`, `reference_id`, and `reference_number` populated
- The combination of `reference_type` + `reference_id` provides a polymorphic link to the source record
- `reference_number` provides a human-readable label (e.g., PO number, order number) for UI display
- All three fields are nullable (manual adjustments have no reference)

**Notes:**

- `reference_type` uses fully-qualified class names (Laravel polymorphic convention)
- Common reference types: `PurchaseOrder` (receiving), `Order` (sales/fulfillment), `PartOutWorkOrder` (part-out consume/produce)
- `reference_number` is a denormalized field for convenience -- avoids JOIN to display the reference in movement history
- The `reference_type` and `reference_id` columns together form a polymorphic relationship pattern
- This is not defined as a formal Eloquent `morphTo()` relationship on the model, but could be

---

### TC-INV-004-10: Unit cost and total cost calculated correctly on movement

| Field | Value |
|-------|-------|
| **ID** | TC-INV-004-10 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/StockOperationsTest.php` (needed) |
| **Test Method** | `test_movement_cost_calculated_correctly()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `inventory.adjust` permission
3. A product and location with an existing InventoryLevel

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create an InventoryMovement with `quantity = 25` and `unit_cost = 12.50` | Movement created |
| 2 | Assert `unit_cost` is `12.50` (decimal:2) | Unit cost stored as decimal with 2 places |
| 3 | Assert `total_cost` is `312.50` (25 * 12.50) | Total cost = quantity * unit_cost |
| 4 | Create a movement with `quantity = -10` and `unit_cost = 12.50` | Negative quantity movement |
| 5 | Assert `total_cost` is `-125.00` (-10 * 12.50) or `125.00` depending on convention | Cost tracks the value of the movement |
| 6 | Assert `unit_cost` and `total_cost` are cast to `decimal:2` | Proper decimal precision |

**Test Data:**

```php
$movement = InventoryMovement::create([
    'organization_id' => $this->organization->id,
    'product_id' => $product->id,
    'location_id' => $location->id,
    'type' => InventoryMovement::TYPE_PURCHASE,
    'quantity' => 25,
    'unit_cost' => 12.50,
    'total_cost' => 312.50, // 25 * 12.50
    'balance_before' => 0,
    'balance_after' => 25,
    'created_by' => $this->user->id,
]);
```

**Postconditions:**

- `unit_cost` and `total_cost` stored with decimal:2 precision
- Both fields are in the model's `$casts` array as `'decimal:2'`
- Cost fields provide the monetary value of each inventory movement for COGS and valuation reports

**Notes:**

- `unit_cost` is the per-unit cost at the time of the movement
- `total_cost` = `quantity * unit_cost` (this calculation may be done in the service layer or in the controller)
- For `TYPE_PURCHASE` movements, `unit_cost` comes from the supplier/PO
- For `TYPE_SALE` movements, `unit_cost` may reflect COGS (cost of goods sold)
- For `TYPE_ADJUSTMENT`, `unit_cost` may use the InventoryLevel's `average_cost`
- The `average_cost` on InventoryLevel may be updated using weighted average when purchase movements are recorded

---

### TC-INV-004-11: View movement history with type and date range filters

| Field | Value |
|-------|-------|
| **ID** | TC-INV-004-11 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/StockOperationsTest.php` (needed) |
| **Test Method** | `test_movement_history_with_type_and_date_filters()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `inventory.view` permission
3. Multiple InventoryMovement records exist with different types and dates

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `GET` to `route('inventory.movements.index')` | Response status 200, movement history page rendered |
| 2 | Assert response contains movements for the authenticated user's organization | Only org-scoped movements returned |
| 3 | Send `GET` with `?type=adjustment` filter | Only movements with `type = 'adjustment'` are returned |
| 4 | Send `GET` with `?type=purchase` filter | Only movements with `type = 'purchase'` are returned |
| 5 | Send `GET` with date range filter (start_date and end_date) | Only movements within the date range are returned |
| 6 | Assert each movement displays `getMovementTypeLabel()` correctly | Human-readable labels: 'Purchase', 'Sale', 'Adjustment', 'Transfer In', 'Transfer Out', etc. |
| 7 | Assert each movement displays `getDisplayQuantity()` correctly | Positive quantities prefixed with '+', negatives with '-' |

**Test Data:**

```php
// Create movements with different types and dates
InventoryMovement::create([
    'organization_id' => $this->organization->id,
    'product_id' => $product->id,
    'location_id' => $location->id,
    'type' => 'purchase',
    'quantity' => 100,
    'created_at' => now()->subDays(10),
]);

InventoryMovement::create([
    'organization_id' => $this->organization->id,
    'product_id' => $product->id,
    'location_id' => $location->id,
    'type' => 'adjustment',
    'quantity' => -5,
    'reason' => 'damaged',
    'created_at' => now()->subDays(5),
]);

InventoryMovement::create([
    'organization_id' => $this->organization->id,
    'product_id' => $product->id,
    'location_id' => $location->id,
    'type' => 'sale',
    'quantity' => -2,
    'created_at' => now()->subDays(1),
]);
```

**Postconditions:**

- No database mutations; movement history is read-only
- Movements are paginated and sorted by creation date (newest first)
- Filters narrow the result set without affecting the underlying data

**Notes:**

- Routes: `GET /inventory/movements` (defined in `routes/inventory-movements.php`)
- The `InventoryMovementController@index` handles the movement history page
- Available scopes for filtering: `scopeByType()`, `scopeByProduct()`, `scopeByLocation()`, `scopeByDateRange()`
- The `scopeIncreases()` and `scopeDecreases()` scopes filter by positive/negative quantity
- Movement type labels are provided by the `getMovementTypeLabel()` method using a `match` expression
- Part-out types are also supported: `TYPE_PARTOUT_CONSUME = 'partout_consume'`, `TYPE_PARTOUT_PRODUCE = 'partout_produce'`

---

### TC-INV-004-12: Low stock alerts generated when quantity_on_hand <= reorder_point

| Field | Value |
|-------|-------|
| **ID** | TC-INV-004-12 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/StockOperationsTest.php` (needed) |
| **Test Method** | `test_low_stock_alerts_when_below_reorder_point()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `inventory.view` permission
3. Products exist with varying stock levels relative to their reorder points
4. InventoryLevels exist with `reorder_point` set

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create InventoryLevel with `quantity_on_hand = 3`, `reorder_point = 10` | Level is below reorder point |
| 2 | Assert `$level->isLowStock()` returns true | Low stock detected |
| 3 | Create InventoryLevel with `quantity_on_hand = 15`, `reorder_point = 10` | Level is above reorder point |
| 4 | Assert `$level->isLowStock()` returns false | Not low stock |
| 5 | Create InventoryLevel with `quantity_on_hand = 10`, `reorder_point = 10` | Level equals reorder point |
| 6 | Assert `$level->isLowStock()` returns true | Equal to threshold is considered low |
| 7 | Send `GET` to `route('inventory.operations.low-stock')` | Low stock alerts page rendered |
| 8 | Assert only products where `quantity_on_hand <= reorder_point` are listed | Low stock products filtered |
| 9 | Assert `Product::isLowStock()` returns true for products where `stock_quantity <= low_stock_threshold` | Product-level low stock check |

**Test Data:**

```php
// Below reorder point (LOW STOCK)
$lowStockLevel = InventoryLevel::create([
    'organization_id' => $this->organization->id,
    'product_id' => $lowProduct->id,
    'location_id' => $location->id,
    'quantity_on_hand' => 3,
    'reorder_point' => 10,
    'reorder_quantity' => 50,
]);

// Above reorder point (OK)
$normalLevel = InventoryLevel::create([
    'organization_id' => $this->organization->id,
    'product_id' => $normalProduct->id,
    'location_id' => $location->id,
    'quantity_on_hand' => 50,
    'reorder_point' => 10,
]);

// Out of stock
$outOfStockLevel = InventoryLevel::create([
    'organization_id' => $this->organization->id,
    'product_id' => $outProduct->id,
    'location_id' => $location->id,
    'quantity_on_hand' => 0,
    'reorder_point' => 5,
]);
```

**Postconditions:**

- Low stock alerts page lists only products/levels below their reorder points
- The `scopeLowStock()` scope on InventoryLevel uses `whereColumn('quantity_on_hand', '<=', 'reorder_point')`
- The `scopeOutOfStock()` scope checks `where('quantity_on_hand', 0)`
- The `scopeOverStock()` scope checks `quantity_on_hand > max_stock_level` (when `max_stock_level` is set)

**Notes:**

- Two levels of low stock detection exist:
  1. `InventoryLevel::isLowStock()` -- per location, uses `reorder_point` column
  2. `Product::isLowStock()` -- aggregate, uses `stock_quantity` vs `low_stock_threshold`
- Route: `GET /inventory/operations/low-stock` (defined as `inventory.operations.low-stock`)
- The demand forecasting system (`DemandForecast`, `ReorderSuggestion` models) provides a more sophisticated alerting mechanism
- `reorder_quantity` on InventoryLevel suggests how much to order when reorder point is triggered
- `max_stock_level` can be used for overstock detection via `scopeOverStock()`
