# Receiving Test Cases

## Document ID: TC-PRC-002

**Domain:** Procurement
**Related Test Plan:** `docs/testing/plans/procurement-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-PRC-002-01: Receive all items on submitted PO, status -> received

| Field | Value |
|-------|-------|
| **ID** | TC-PRC-002-01 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Procurement/ReceivingTest.php` (needed) |
| **Test Method** | `test_full_receive_transitions_to_received()` (needed) |

**Preconditions:**

1. Organization fixtures set up
2. User authenticated
3. A `PurchaseOrder` with `status = 'submitted'` and 2 line items (quantity_ordered: 10 and 5)
4. `canReceiveItems()` returns true (status is `submitted` or `partially_received`)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('procurement.purchase-orders.process-receive', $po)` with items receiving full quantities | Response redirects to show page with success flash |
| 2 | Assert `PurchaseOrder` status changed to `'received'` | `isFullyReceived()` returns true, status updated |
| 3 | Assert `received_at` set to approximately now | Receive completion timestamp recorded |
| 4 | Assert each `PurchaseOrderItem.quantity_received` equals `quantity_ordered` | All items fully received |
| 5 | Assert `PurchaseOrderReceipt` record created | Receipt with `received_by`, `received_at`, `location_id` |
| 6 | Assert `PurchaseOrderReceiptItem` records created for each received item | Receipt line items persisted |

**Test Data:**

```php
$po = PurchaseOrder::factory()->create([
    'organization_id' => $this->organization->id,
    'supplier_id' => $supplier->id,
    'location_id' => $location->id,
    'status' => PurchaseOrder::STATUS_SUBMITTED,
]);

$item1 = PurchaseOrderItem::factory()->create([
    'purchase_order_id' => $po->id,
    'product_id' => $productA->id,
    'quantity_ordered' => 10,
    'quantity_received' => 0,
    'unit_cost' => 25.00,
]);

$item2 = PurchaseOrderItem::factory()->create([
    'purchase_order_id' => $po->id,
    'product_id' => $productB->id,
    'quantity_ordered' => 5,
    'quantity_received' => 0,
    'unit_cost' => 50.00,
]);

$receiveData = [
    'location_id' => $location->id,
    'notes' => 'All items in good condition',
    'items' => [
        [
            'purchase_order_item_id' => $item1->id,
            'quantity_received' => 10,
            'condition' => 'good',
        ],
        [
            'purchase_order_item_id' => $item2->id,
            'quantity_received' => 5,
            'condition' => 'good',
        ],
    ],
];
```

**Postconditions:**

- PO `status = 'received'`, `received_at` set
- All items have `quantity_received = quantity_ordered`
- `PurchaseOrderReceipt` and `PurchaseOrderReceiptItem` records created
- `ReceivingService::updatePoStatus()` called, which checks `isFullyReceived()`
- `LabelingWorkflowService::createFromPoReceipt()` triggered (non-blocking)

**Notes:**

- The `ReceivingService::receive()` wraps all operations in a database transaction
- `isFullyReceived()` checks that ALL items have `quantity_received >= quantity_ordered`
- The `updatePoStatus()` method sets `STATUS_RECEIVED` and `received_at` when fully received
- The labeling workflow trigger runs outside the transaction and failure is non-blocking

---

### TC-PRC-002-02: Partial receive updates quantities, status -> partially_received

| Field | Value |
|-------|-------|
| **ID** | TC-PRC-002-02 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Procurement/ReceivingTest.php` (needed) |
| **Test Method** | `test_partial_receive_transitions_to_partially_received()` (needed) |

**Preconditions:**

1. Organization fixtures set up
2. User authenticated
3. A `PurchaseOrder` with `status = 'submitted'` and items with `quantity_ordered = 10`

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('procurement.purchase-orders.process-receive', $po)` with only 6 of 10 ordered quantity for one item | Response redirects with success flash |
| 2 | Assert PO `status` changed to `'partially_received'` | Partial status set |
| 3 | Assert `received_at` is NOT set (not fully received) | Completion timestamp only on full receive |
| 4 | Assert `PurchaseOrderItem.quantity_received` equals 6 | Partial quantity recorded via `increment()` |
| 5 | Assert `canReceiveItems()` still returns true | PO still accepts more receiving |
| 6 | Assert `PurchaseOrderReceipt` record created for this partial receipt | Receipt tracks the partial delivery |

**Test Data:**

```php
$po = PurchaseOrder::factory()->create([
    'organization_id' => $this->organization->id,
    'status' => PurchaseOrder::STATUS_SUBMITTED,
    'location_id' => $location->id,
]);

$item = PurchaseOrderItem::factory()->create([
    'purchase_order_id' => $po->id,
    'product_id' => $product->id,
    'quantity_ordered' => 10,
    'quantity_received' => 0,
    'unit_cost' => 25.00,
]);

$receiveData = [
    'items' => [
        [
            'purchase_order_item_id' => $item->id,
            'quantity_received' => 6,
            'condition' => 'good',
        ],
    ],
];
```

**Postconditions:**

- PO `status = 'partially_received'`
- Item `quantity_received = 6` (4 remaining)
- One `PurchaseOrderReceipt` with one `PurchaseOrderReceiptItem`
- Inventory updated for the 6 received units

**Notes:**

- The `updatePoStatus()` method checks `isFullyReceived()` first; if false, checks if any items have been received and sets `STATUS_PARTIALLY_RECEIVED`
- `canReceiveItems()` returns true for both `submitted` and `partially_received` statuses

---

### TC-PRC-002-03: Second partial receive completes PO, status -> received

| Field | Value |
|-------|-------|
| **ID** | TC-PRC-002-03 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Procurement/ReceivingTest.php` (needed) |
| **Test Method** | `test_second_partial_receive_completes_po()` (needed) |

**Preconditions:**

1. A `PurchaseOrder` with `status = 'partially_received'`
2. An item with `quantity_ordered = 10` and `quantity_received = 6` (from previous partial receive)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('procurement.purchase-orders.process-receive', $po)` with remaining 4 units | Response redirects with success flash |
| 2 | Assert `PurchaseOrderItem.quantity_received` now equals 10 | `increment('quantity_received', 4)` applied to existing 6 |
| 3 | Assert `isFullyReceived()` returns true | All items fully received |
| 4 | Assert PO `status` changed to `'received'` | Full receive status set |
| 5 | Assert `received_at` set | Completion timestamp now recorded |
| 6 | Assert two `PurchaseOrderReceipt` records exist for this PO | One per receiving session |

**Test Data:**

```php
$po = PurchaseOrder::factory()->create([
    'organization_id' => $this->organization->id,
    'status' => PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
    'location_id' => $location->id,
]);

$item = PurchaseOrderItem::factory()->create([
    'purchase_order_id' => $po->id,
    'product_id' => $product->id,
    'quantity_ordered' => 10,
    'quantity_received' => 6,  // already partially received
    'unit_cost' => 25.00,
]);

$receiveData = [
    'items' => [
        [
            'purchase_order_item_id' => $item->id,
            'quantity_received' => 4,  // remaining
            'condition' => 'good',
        ],
    ],
];
```

**Postconditions:**

- PO `status = 'received'`, `received_at` set
- Item `quantity_received = 10` (6 + 4)
- Two receipt records exist for full audit trail
- Total inventory movement: 10 units across two receiving sessions

**Notes:**

- The `receiveItem()` method uses `$poItem->increment('quantity_received', $quantityReceived)` which adds to existing value
- `isFullyReceived()` uses `$po->items->every(fn ($item) => $item->quantity_received >= $item->quantity_ordered)`
- Multiple receipts per PO is expected and supported via the `HasMany` relationship

---

### TC-PRC-002-04: Receipt records condition per item (good/damaged/missing_parts) with notes

| Field | Value |
|-------|-------|
| **ID** | TC-PRC-002-04 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Procurement/ReceivingTest.php` (needed) |
| **Test Method** | `test_receipt_records_condition_and_notes()` (needed) |

**Preconditions:**

1. A `PurchaseOrder` with `status = 'submitted'` and multiple items
2. Some items arrive in damaged condition

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('procurement.purchase-orders.process-receive', $po)` with mixed conditions | Response redirects with success |
| 2 | Assert first receipt item has `condition = 'good'` | Good condition recorded |
| 3 | Assert second receipt item has `condition = 'damaged'` and `condition_notes = 'Box crushed, unit tested OK'` | Damaged condition with notes |
| 4 | Assert third receipt item has `condition = 'defective'` and `condition_notes = 'DOA - power supply failure'` | Defective condition documented |
| 5 | Assert all `PurchaseOrderReceiptItem` records have correct `quantity_received` | Quantities persisted regardless of condition |

**Test Data:**

```php
$receiveData = [
    'notes' => 'Mixed condition shipment from supplier',
    'items' => [
        [
            'purchase_order_item_id' => $item1->id,
            'quantity_received' => 8,
            'condition' => 'good',
            'condition_notes' => null,
        ],
        [
            'purchase_order_item_id' => $item2->id,
            'quantity_received' => 2,
            'condition' => 'damaged',
            'condition_notes' => 'Box crushed, unit tested OK',
        ],
        [
            'purchase_order_item_id' => $item3->id,
            'quantity_received' => 1,
            'condition' => 'defective',
            'condition_notes' => 'DOA - power supply failure',
        ],
    ],
];
```

**Postconditions:**

- Each `PurchaseOrderReceiptItem` has `condition` and `condition_notes` persisted
- All items count toward `quantity_received` regardless of condition
- Inventory updated for all received quantities (condition does not affect stock movement)

**Notes:**

- Valid conditions in the controller validation: `good`, `damaged`, `defective`
- The `ReceivingService` stores condition as `$data['condition'] ?? 'good'` (defaults to good)
- `condition_notes` is optional, max 500 characters
- The receipt-level `notes` field captures general shipment observations

---

### TC-PRC-002-05: Receiving creates PurchaseOrderReceipt and PurchaseOrderReceiptItem records

| Field | Value |
|-------|-------|
| **ID** | TC-PRC-002-05 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Backend |
| **Automated** | No |
| **Test File** | `tests/Feature/Procurement/ReceivingTest.php` (needed) |
| **Test Method** | `test_receiving_creates_receipt_and_receipt_items()` (needed) |

**Preconditions:**

1. A `PurchaseOrder` with `status = 'submitted'` and 3 line items
2. User authenticated

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Call `ReceivingService::receive($po, $data, $userId)` | Receipt created within transaction |
| 2 | Assert `PurchaseOrderReceipt` created with correct `purchase_order_id`, `received_by`, `location_id`, `received_at` | Receipt header correct |
| 3 | Assert `received_by` set to authenticated user's ID | Receiver tracked |
| 4 | Assert `location_id` defaults to PO's `location_id` when not provided in request | Fallback to PO location |
| 5 | Assert `location_id` uses request value when explicitly provided | Override location works |
| 6 | Assert 3 `PurchaseOrderReceiptItem` records created, each linked to `receipt_id` and `purchase_order_item_id` | One receipt item per PO item received |
| 7 | Assert each receipt item has `product_id` copied from the PO item | Product reference propagated |
| 8 | Assert receipt returned with `items` and `purchaseOrder` relationships loaded | `$receipt->fresh(['items', 'purchaseOrder'])` |

**Test Data:**

```php
$receipt = $this->receivingService->receive($po, [
    'location_id' => $alternateLocation->id,
    'notes' => 'Received at warehouse B',
    'items' => [
        ['purchase_order_item_id' => $item1->id, 'quantity_received' => 10, 'condition' => 'good'],
        ['purchase_order_item_id' => $item2->id, 'quantity_received' => 5, 'condition' => 'good'],
        ['purchase_order_item_id' => $item3->id, 'quantity_received' => 3, 'condition' => 'damaged'],
    ],
], $this->user->id);
```

**Postconditions:**

- `PurchaseOrderReceipt` with `purchase_order_id`, `received_by`, `received_at`, `location_id`, `notes`
- 3 `PurchaseOrderReceiptItem` records with `receipt_id`, `purchase_order_item_id`, `product_id`, `quantity_received`, `condition`, `condition_notes`
- All created within a database transaction (atomicity guaranteed)

**Notes:**

- `ReceivingService::receive()` uses `DB::transaction()` to wrap all operations
- Location falls back to `$data['location_id'] ?? $po->location_id`
- The `receiveItem()` method handles individual item processing
- Quantity of zero is rejected: `if ($quantityReceived <= 0)` throws `RuntimeException`

---

### TC-PRC-002-06: Receiving creates InventoryMovement (type=purchase) at PO location

| Field | Value |
|-------|-------|
| **ID** | TC-PRC-002-06 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Backend |
| **Automated** | No |
| **Test File** | `tests/Feature/Procurement/ReceivingTest.php` (needed) |
| **Test Method** | `test_receiving_creates_inventory_movement()` (needed) |

**Preconditions:**

1. A submitted PO with items linked to products (`product_id` is not null)
2. A receiving location specified

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Receive items on PO via `ReceivingService::receive()` | Receiving completed |
| 2 | Assert `InventoryMovement` record created for each received item | One movement per item received |
| 3 | Assert movement `type = 'purchase'` | Movement type correct |
| 4 | Assert movement `quantity` matches received quantity | Quantity recorded |
| 5 | Assert movement `unit_cost` matches PO item `unit_cost` | Cost tracked |
| 6 | Assert movement `reference_type = 'purchase_order_receipt'` | Reference type correct |
| 7 | Assert movement `reference_id` equals the receipt UUID | Receipt linked for traceability |
| 8 | Assert movement `location_id` matches receiving location | Location tracked |
| 9 | Assert movement `organization_id` correct | Org scoping maintained |
| 10 | Assert movement `performed_by` set to authenticated user | Actor tracked |
| 11 | Assert movement `notes` is `'Received from purchase order'` | Descriptive note |

**Test Data:**

```php
// After receiving:
$movement = InventoryMovement::where('reference_type', 'purchase_order_receipt')
    ->where('reference_id', $receipt->id)
    ->where('product_id', $product->id)
    ->first();

$this->assertNotNull($movement);
$this->assertEquals('purchase', $movement->type);
$this->assertEquals(10, $movement->quantity);
$this->assertEquals(25.00, $movement->unit_cost);
$this->assertEquals($location->id, $movement->location_id);
```

**Postconditions:**

- `InventoryMovement` records created with full audit trail
- Movements reference the `PurchaseOrderReceipt` for traceability
- No movement created if `$poItem->product_id` is null (unlinked PO items)
- No movement created if `$locationId` is null

**Notes:**

- The `updateInventory()` method in `ReceivingService` creates the movement
- Movements are only created when both `product_id` and `locationId` are present (line 87: `if ($poItem->product_id && $locationId)`)
- `performed_by` uses `auth()->id()` (may be null in some contexts; consider passing explicitly)

---

### TC-PRC-002-07: Receiving updates InventoryLevel.quantity_on_hand at location

| Field | Value |
|-------|-------|
| **ID** | TC-PRC-002-07 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Backend |
| **Automated** | No |
| **Test File** | `tests/Feature/Procurement/ReceivingTest.php` (needed) |
| **Test Method** | `test_receiving_updates_inventory_level()` (needed) |

**Preconditions:**

1. A submitted PO with items linked to products
2. An `InventoryLevel` may or may not already exist for the product/location combination

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Receive items on PO | Receiving completed |
| 2 | Assert `InventoryLevel` exists for the product/location combination | `firstOrCreate()` ensures record exists |
| 3 | Assert `quantity_on_hand` incremented by received quantity | `increment('quantity_on_hand', $quantity)` applied |
| 4 | If new `InventoryLevel`: assert initial `quantity_on_hand` equals received quantity, `quantity_reserved = 0` | New record created with correct defaults |
| 5 | If existing `InventoryLevel` with `quantity_on_hand = 5`: assert new value is `5 + received_quantity` | Addition to existing stock |
| 6 | Assert `average_cost` updated using weighted average formula | `(old_qty * old_avg + new_qty * unit_cost) / (old_qty + new_qty)` |
| 7 | Assert `last_movement_at` set to approximately now | Timestamp updated |
| 8 | Assert `Product.stock_quantity` incremented by received quantity | Product-level stock updated |
| 9 | Assert `Product.available_quantity` incremented by received quantity | Available quantity also updated |

**Test Data:**

```php
// Existing inventory level
$existingLevel = InventoryLevel::create([
    'organization_id' => $this->organization->id,
    'product_id' => $product->id,
    'location_id' => $location->id,
    'quantity_on_hand' => 5,
    'quantity_reserved' => 2,
    'average_cost' => 20.00,
]);

// Receive 10 units at $25.00 each
// Expected new average_cost: (5 * 20.00 + 10 * 25.00) / (5 + 10) = 350 / 15 = 23.33
// Expected new quantity_on_hand: 15
```

**Postconditions:**

- `InventoryLevel.quantity_on_hand` increased by received quantity
- `average_cost` recalculated via weighted average (rounded to 2 decimal places)
- `last_movement_at` updated
- `Product.stock_quantity` and `Product.available_quantity` both incremented
- `quantity_reserved` unchanged (receiving does not affect reservations)

**Notes:**

- `InventoryLevel::firstOrCreate()` uses `organization_id`, `product_id`, `location_id` as the lookup keys
- Weighted average cost formula: `round(($totalOldValue + $totalNewValue) / $newTotalQty, 2)`
- If `unitCost <= 0`, average cost is not recalculated
- If `previousQty` is 0, average cost is simply set to `$unitCost`
- Both `Product.stock_quantity` and `Product.available_quantity` are incremented (product is the source of truth for marketplace sync)

---

### TC-PRC-002-08: Cannot receive on cancelled PO

| Field | Value |
|-------|-------|
| **ID** | TC-PRC-002-08 |
| **Priority** | P1 |
| **Type** | Negative |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Procurement/ReceivingTest.php` (needed) |
| **Test Method** | `test_cannot_receive_on_cancelled_po()` (needed) |

**Preconditions:**

1. A `PurchaseOrder` with `status = 'cancelled'`
2. User authenticated

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Assert `canReceiveItems()` returns false for cancelled PO | Guard check fails |
| 2 | Attempt to view receive page: `GET route('procurement.purchase-orders.receive', $cancelledPo)` | Redirect to show page with error: "This purchase order cannot receive items." |
| 3 | Send `POST` to `route('procurement.purchase-orders.process-receive', $cancelledPo)` | `RuntimeException` thrown by `ReceivingService`, controller returns error |
| 4 | Assert no `PurchaseOrderReceipt` records created | No side effects |
| 5 | Assert no `InventoryMovement` records created | No inventory impact |
| 6 | Assert no `InventoryLevel` changes | Stock unchanged |
| 7 | Repeat for `status = 'received'` (already fully received) | Same rejection; `canReceiveItems()` only accepts `submitted` and `partially_received` |

**Test Data:**

```php
$cancelledPo = PurchaseOrder::factory()->create([
    'organization_id' => $this->organization->id,
    'status' => PurchaseOrder::STATUS_CANCELLED,
    'cancelled_at' => now()->subDay(),
    'cancel_reason' => 'Order no longer needed',
]);

$receivedPo = PurchaseOrder::factory()->create([
    'organization_id' => $this->organization->id,
    'status' => PurchaseOrder::STATUS_RECEIVED,
    'received_at' => now()->subDay(),
]);
```

**Postconditions:**

- No database mutations from receiving attempt
- Error message displayed to user
- Cancelled and fully-received POs are terminal states for receiving

**Notes:**

- `canReceiveItems()` returns true only for `STATUS_SUBMITTED` and `STATUS_PARTIALLY_RECEIVED`
- The controller `receive()` method checks `canReceiveItems()` before rendering the receive form
- The `ReceivingService::receive()` also checks `canReceiveItems()` as a guard: `if (!$po->canReceiveItems()) throw new RuntimeException`
- Draft POs also cannot receive items (must be submitted first)
