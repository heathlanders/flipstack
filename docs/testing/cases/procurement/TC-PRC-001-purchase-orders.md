# Purchase Orders Test Cases

## Document ID: TC-PRC-001

**Domain:** Procurement
**Related Test Plan:** `docs/testing/plans/procurement-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-PRC-001-01: View PO list with search, status filter, supplier filter

| Field | Value |
|-------|-------|
| **ID** | TC-PRC-001-01 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Procurement/PurchaseOrderTest.php` (may partially exist) |
| **Test Method** | `test_index_displays_purchase_orders_with_filters()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated
3. Multiple `PurchaseOrder` records exist in the organization across statuses (`draft`, `submitted`, `partially_received`, `received`, `cancelled`)
4. At least two active `Supplier` records exist, each with associated POs

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `GET` to `route('procurement.purchase-orders.index')` | Response status 200, Inertia renders `Procurement/PurchaseOrders/Index` |
| 2 | Assert response `purchaseOrders` prop contains paginated collection (20 per page) | Paginator with `data`, `current_page`, `per_page` |
| 3 | Assert each PO includes eager-loaded `supplier` (name, company_name), `location` (name), `creator` (name) | Relationships loaded via `with()` |
| 4 | Assert response `stats` prop contains PO statistics from `PurchaseOrderService::getStats()` | Summary statistics present |
| 5 | Assert response `suppliers` prop contains active suppliers for filter dropdown | Supplier list for UI filter |
| 6 | Send `GET` with `?search=PO-2026-001` | Only POs matching `po_number` ILIKE or supplier name ILIKE returned |
| 7 | Send `GET` with `?status=draft` | Only POs with `status = 'draft'` returned |
| 8 | Send `GET` with `?supplier_id={uuid}` | Only POs for specified supplier returned |
| 9 | Send `GET` with `?sort=total_amount&direction=desc` | POs sorted by `total_amount` descending |
| 10 | Assert default sort is `created_at` descending | Most recent POs first |

**Test Data:**

```php
$this->setUpOrganizationFixtures();

$supplierA = Supplier::factory()->create([
    'organization_id' => $this->organization->id,
    'is_active' => true,
    'name' => 'Alpha Supplies',
]);

$supplierB = Supplier::factory()->create([
    'organization_id' => $this->organization->id,
    'is_active' => true,
    'name' => 'Beta Distributors',
]);

$draftPO = PurchaseOrder::factory()->create([
    'organization_id' => $this->organization->id,
    'supplier_id' => $supplierA->id,
    'status' => 'draft',
    'po_number' => 'PO-2026-001',
]);

$submittedPO = PurchaseOrder::factory()->create([
    'organization_id' => $this->organization->id,
    'supplier_id' => $supplierB->id,
    'status' => 'submitted',
    'po_number' => 'PO-2026-002',
]);
```

**Postconditions:**

- No database mutations; read-only endpoint
- Filters echoed back in `filters` prop
- Allowed sort columns: `po_number`, `status`, `order_date`, `expected_delivery_date`, `total_amount`, `created_at`
- Invalid sort columns fall back to `created_at desc`

**Notes:**

- The controller uses `ILIKE` (PostgreSQL case-insensitive) for search on `po_number` and supplier `name`
- Supplier filter uses `whereHas` for name matching in search
- Sort direction sanitized to `asc` or `desc` only
- POs scoped to organization via `where('organization_id', $organizationId)`

---

### TC-PRC-001-02: Create draft PO with items

| Field | Value |
|-------|-------|
| **ID** | TC-PRC-001-02 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Procurement/PurchaseOrderTest.php` (needed) |
| **Test Method** | `test_store_creates_draft_po_with_items()` (needed) |

**Preconditions:**

1. Organization fixtures set up
2. User authenticated
3. Active supplier and inventory location exist in the organization
4. At least one Product exists for line item references

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('procurement.purchase-orders.store')` with `supplier_id`, `location_id`, `order_date`, `expected_delivery_date`, `notes`, `items[]` array | Response redirects to PO show page with success flash |
| 2 | Assert `PurchaseOrder` record created with `status = 'draft'` | PO persisted in draft state |
| 3 | Assert `po_number` auto-generated and unique within organization | PO number set by `PurchaseOrderService::create()` |
| 4 | Assert `organization_id` matches authenticated user's org | Org scoping applied |
| 5 | Assert `created_by` set to authenticated user's ID | Creator tracked |
| 6 | Assert `PurchaseOrderItem` records created for each item in `items[]` | Line items persisted |
| 7 | Assert each item has `quantity_ordered`, `unit_cost` from input, `quantity_received = 0` | Initial received quantity is zero |
| 8 | Assert `total_amount` calculated from items + tax + shipping | Total computed correctly |

**Test Data:**

```php
$supplier = Supplier::factory()->create([
    'organization_id' => $this->organization->id,
    'is_active' => true,
]);

$location = InventoryLocation::factory()->create([
    'organization_id' => $this->organization->id,
    'is_active' => true,
]);

$product = Product::factory()->create([
    'organization_id' => $this->organization->id,
]);

$storeData = [
    'supplier_id' => $supplier->id,
    'location_id' => $location->id,
    'order_date' => '2026-04-15',
    'expected_delivery_date' => '2026-04-22',
    'notes' => 'Urgent restock order',
    'tax_amount' => 25.00,
    'shipping_cost' => 15.00,
    'items' => [
        [
            'product_id' => $product->id,
            'description' => 'Network Switch',
            'sku' => $product->sku,
            'quantity_ordered' => 10,
            'unit_cost' => 45.00,
            'notes' => 'Confirm compatibility',
        ],
    ],
];
```

**Postconditions:**

- `PurchaseOrder` with UUID PK (`HasUuid` trait) and `status = 'draft'`
- `PurchaseOrderItem` records linked via `purchase_order_id`
- Redirect to `route('procurement.purchase-orders.show', $po->id)`
- Flash message includes auto-generated PO number

**Notes:**

- `supplier_id` and `location_id` validated as UUIDs with `exists` rule
- `items.*.product_id` is nullable (PO items can exist without linked products)
- `items.*.quantity_ordered` required, min 1; `items.*.unit_cost` required, min 0
- The `PurchaseOrderService::create()` handles PO number generation and total calculation

---

### TC-PRC-001-03: PO number auto-generated unique within org

| Field | Value |
|-------|-------|
| **ID** | TC-PRC-001-03 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Backend |
| **Automated** | No |
| **Test File** | `tests/Feature/Procurement/PurchaseOrderTest.php` (needed) |
| **Test Method** | `test_po_number_auto_generated_unique()` (needed) |

**Preconditions:**

1. Organization exists
2. Multiple POs will be created in sequence

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create first PO via `PurchaseOrderService::create()` | PO number generated (e.g., `PO-2026-0001`) |
| 2 | Create second PO in same organization | Different PO number generated (e.g., `PO-2026-0002`) |
| 3 | Assert both PO numbers are unique | `$po1->po_number !== $po2->po_number` |
| 4 | Create PO in a different organization | PO number may reuse sequence (org-scoped uniqueness) |
| 5 | Assert PO number format follows expected pattern | Consistent format across the organization |

**Test Data:**

```php
$po1 = $this->purchaseOrderService->create($data1, $this->organization->id, $this->user->id);
$po2 = $this->purchaseOrderService->create($data2, $this->organization->id, $this->user->id);

// Assert unique
$this->assertNotEquals($po1->po_number, $po2->po_number);
```

**Postconditions:**

- Each PO has a unique `po_number` within the organization
- PO numbers follow a consistent format
- No collisions under concurrent creation

**Notes:**

- The `PurchaseOrderService::generatePoNumber()` method handles number generation
- PO numbers are typically sequential within an organization (e.g., `PO-YYYY-NNNN`)
- Database should enforce uniqueness via a unique index on `(organization_id, po_number)`

---

### TC-PRC-001-04: Update draft PO items (add/remove/change quantities)

| Field | Value |
|-------|-------|
| **ID** | TC-PRC-001-04 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Procurement/PurchaseOrderTest.php` (needed) |
| **Test Method** | `test_update_draft_po_modifies_items()` (needed) |

**Preconditions:**

1. Organization fixtures set up
2. User authenticated
3. A `PurchaseOrder` in `draft` status with 2 existing line items

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `PUT` to `route('procurement.purchase-orders.update', $po)` with modified items array | Response redirects to show page with success flash |
| 2 | Assert existing item quantity changed from 10 to 20 | `PurchaseOrderItem` updated |
| 3 | Assert new item added to the PO | New `PurchaseOrderItem` record created |
| 4 | Assert removed item deleted from PO | Old `PurchaseOrderItem` record removed |
| 5 | Assert `total_amount` recalculated | Total reflects updated items + tax + shipping |
| 6 | Assert `supplier_id`, `location_id`, `notes` updated when provided | PO header fields updated |

**Test Data:**

```php
$po = PurchaseOrder::factory()->create([
    'organization_id' => $this->organization->id,
    'status' => 'draft',
]);

$existingItem = PurchaseOrderItem::factory()->create([
    'purchase_order_id' => $po->id,
    'quantity_ordered' => 10,
    'unit_cost' => 25.00,
]);

$updateData = [
    'supplier_id' => $newSupplier->id,
    'notes' => 'Updated notes',
    'items' => [
        [
            'id' => $existingItem->id,
            'product_id' => $existingItem->product_id,
            'quantity_ordered' => 20,  // changed from 10
            'unit_cost' => 25.00,
        ],
        [
            // No 'id' = new item
            'product_id' => $newProduct->id,
            'quantity_ordered' => 5,
            'unit_cost' => 30.00,
        ],
    ],
];
```

**Postconditions:**

- PO items reflect the submitted state (updated, added, removed)
- `total_amount` recalculated
- PO remains in `draft` status

**Notes:**

- Items with an `id` field are updates; items without `id` are new additions
- Items present in the database but absent from the update request may be deleted (depends on `PurchaseOrderService::update()` implementation)
- `items.*.id` validated as nullable UUID

---

### TC-PRC-001-05: Submit PO transitions draft -> submitted, sets submitted_at

| Field | Value |
|-------|-------|
| **ID** | TC-PRC-001-05 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Procurement/PurchaseOrderTest.php` (needed) |
| **Test Method** | `test_submit_transitions_draft_to_submitted()` (needed) |

**Preconditions:**

1. Organization fixtures set up
2. User authenticated
3. A `PurchaseOrder` in `draft` status with at least one line item

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('procurement.purchase-orders.submit', $po)` | Response redirects back with success flash |
| 2 | Assert `status` changed from `'draft'` to `'submitted'` | Status transitioned |
| 3 | Assert `submitted_at` set to approximately now | Submission timestamp recorded |
| 4 | Assert `approved_by` set to authenticated user's ID | Submitter tracked |
| 5 | Assert `canBeEdited()` returns false | `isDraft()` returns false |
| 6 | Assert `canReceiveItems()` returns true | PO now eligible for receiving |

**Test Data:**

```php
$po = PurchaseOrder::factory()->create([
    'organization_id' => $this->organization->id,
    'status' => PurchaseOrder::STATUS_DRAFT,
    'submitted_at' => null,
]);

PurchaseOrderItem::factory()->create([
    'purchase_order_id' => $po->id,
    'quantity_ordered' => 10,
    'unit_cost' => 25.00,
]);
```

**Postconditions:**

- `status = 'submitted'`
- `submitted_at` timestamp set
- PO no longer editable via `update()` (controller checks `canBeEdited()`)
- PO eligible for receiving workflow

**Notes:**

- The `PurchaseOrderService::submit()` method handles the transition
- If PO has no items or is not in draft status, a `RuntimeException` is thrown
- The controller catches `RuntimeException` and returns an error flash message

---

### TC-PRC-001-06: Cancel PO with reason, sets cancelled_at

| Field | Value |
|-------|-------|
| **ID** | TC-PRC-001-06 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Procurement/PurchaseOrderTest.php` (needed) |
| **Test Method** | `test_cancel_po_sets_cancelled_status_and_reason()` (needed) |

**Preconditions:**

1. Organization fixtures set up
2. User authenticated
3. A `PurchaseOrder` in `draft` or `submitted` status

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('procurement.purchase-orders.cancel', $po)` with `reason = 'Supplier unable to fulfill'` | Response redirects back with success flash |
| 2 | Assert `status` changed to `'cancelled'` | Status transitioned |
| 3 | Assert `cancelled_at` set to approximately now | Cancellation timestamp recorded |
| 4 | Assert `cancel_reason` set to provided reason string | Reason persisted |
| 5 | Assert `canReceiveItems()` returns false | Cancelled POs cannot receive |
| 6 | Assert `canBeEdited()` returns false | Cancelled POs cannot be edited |

**Test Data:**

```php
$po = PurchaseOrder::factory()->create([
    'organization_id' => $this->organization->id,
    'status' => PurchaseOrder::STATUS_SUBMITTED,
]);

$cancelData = [
    'reason' => 'Supplier unable to fulfill order within required timeline',
];
```

**Postconditions:**

- `status = 'cancelled'`, `cancelled_at` timestamp set, `cancel_reason` stored
- PO is in a terminal state
- No inventory impact (no items were received)

**Notes:**

- The `reason` field is optional (`nullable|string|max:500`)
- The `PurchaseOrderService::cancel()` handles the transition
- Cancellation of a `partially_received` PO may need special handling (items already received and in inventory)

---

### TC-PRC-001-07: Cannot edit submitted PO (only draft editable)

| Field | Value |
|-------|-------|
| **ID** | TC-PRC-001-07 |
| **Priority** | P1 |
| **Type** | Negative |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Procurement/PurchaseOrderTest.php` (needed) |
| **Test Method** | `test_cannot_edit_submitted_po()` (needed) |

**Preconditions:**

1. A `PurchaseOrder` with `status = 'submitted'`
2. User authenticated

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `GET` to `route('procurement.purchase-orders.edit', $submittedPo)` | Redirect to show page with error flash: "This purchase order cannot be edited." |
| 2 | Assert `canBeEdited()` returns false for submitted PO | `isDraft()` returns false |
| 3 | Send `PUT` to `route('procurement.purchase-orders.update', $submittedPo)` with modified data | Request rejected or changes ignored |
| 4 | Assert PO data unchanged in database | No mutations |
| 5 | Repeat for `received` and `cancelled` statuses | Same rejection behavior |

**Test Data:**

```php
$submittedPo = PurchaseOrder::factory()->create([
    'organization_id' => $this->organization->id,
    'status' => PurchaseOrder::STATUS_SUBMITTED,
]);
```

**Postconditions:**

- PO remains unchanged in all non-draft statuses
- User receives clear error message

**Notes:**

- The `edit()` controller method checks `$purchaseOrder->canBeEdited()` and redirects with error if false
- `canBeEdited()` returns `$this->status === self::STATUS_DRAFT`
- The `update()` method may also need to check `canBeEdited()` to prevent API-level edits

---

### TC-PRC-001-08: Cannot submit already-submitted PO

| Field | Value |
|-------|-------|
| **ID** | TC-PRC-001-08 |
| **Priority** | P1 |
| **Type** | Negative |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Procurement/PurchaseOrderTest.php` (needed) |
| **Test Method** | `test_cannot_submit_already_submitted_po()` (needed) |

**Preconditions:**

1. A `PurchaseOrder` with `status = 'submitted'`
2. User authenticated

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('procurement.purchase-orders.submit', $submittedPo)` | Redirect back with error flash |
| 2 | Assert `RuntimeException` caught by controller | Error message returned to user |
| 3 | Assert `status` remains `'submitted'` | No state change |
| 4 | Assert `submitted_at` unchanged | Original timestamp preserved |
| 5 | Repeat for `received` and `cancelled` statuses | Same rejection behavior |

**Test Data:**

```php
$submittedPo = PurchaseOrder::factory()->create([
    'organization_id' => $this->organization->id,
    'status' => PurchaseOrder::STATUS_SUBMITTED,
    'submitted_at' => now()->subDay(),
]);
```

**Postconditions:**

- PO status unchanged
- Error message indicates PO cannot be submitted
- `canBeSubmitted()` returns false for non-draft POs

**Notes:**

- The `PurchaseOrderService::submit()` method should throw `RuntimeException` for non-draft POs
- The controller catches `RuntimeException` and returns `back()->with('error', $e->getMessage())`

---

### TC-PRC-001-09: Delete PO (soft delete)

| Field | Value |
|-------|-------|
| **ID** | TC-PRC-001-09 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Procurement/PurchaseOrderTest.php` (needed) |
| **Test Method** | `test_destroy_draft_po_soft_deletes()` (needed) |

**Preconditions:**

1. A `PurchaseOrder` with `status = 'draft'` and `SoftDeletes` trait
2. User authenticated

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `DELETE` to `route('procurement.purchase-orders.destroy', $draftPo)` | Redirect to index with success flash including PO number |
| 2 | Assert PO soft-deleted: `PurchaseOrder::find($po->id)` returns null | Standard query excludes soft-deleted |
| 3 | Assert PO exists with trashed: `PurchaseOrder::withTrashed()->find($po->id)` returns record | `deleted_at` set |
| 4 | Assert flash message includes PO number: "Purchase order PO-2026-001 deleted." | User feedback |
| 5 | Attempt to delete a non-draft PO | Response redirects back with error: "Only draft purchase orders can be deleted." |
| 6 | Assert non-draft PO remains in database unchanged | Deletion guard enforced |

**Test Data:**

```php
$draftPo = PurchaseOrder::factory()->create([
    'organization_id' => $this->organization->id,
    'status' => PurchaseOrder::STATUS_DRAFT,
    'po_number' => 'PO-2026-001',
]);

$submittedPo = PurchaseOrder::factory()->create([
    'organization_id' => $this->organization->id,
    'status' => PurchaseOrder::STATUS_SUBMITTED,
]);
```

**Postconditions:**

- Draft PO soft-deleted (`deleted_at` set)
- Non-draft PO deletion rejected
- Related `PurchaseOrderItem` records are NOT cascade-deleted (preserved by soft delete)

**Notes:**

- The `PurchaseOrder` model uses `SoftDeletes` trait
- The controller checks `$purchaseOrder->isDraft()` before allowing deletion
- Only draft POs can be deleted; submitted/received/cancelled POs are preserved for audit trail
- Flash message includes the PO number for user reference

---

### TC-PRC-001-10: Org isolation -- cannot see other org's POs

| Field | Value |
|-------|-------|
| **ID** | TC-PRC-001-10 |
| **Priority** | P0 |
| **Type** | Security |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Procurement/PurchaseOrderIsolationTest.php` (needed) |
| **Test Method** | `test_user_cannot_see_other_org_purchase_orders()` (needed) |

**Preconditions:**

1. Two organizations exist (Org A and Org B)
2. Each org has its own user, team, and POs
3. Org A user is authenticated

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Authenticate as Org A user | Session established |
| 2 | Send `GET` to `route('procurement.purchase-orders.index')` | Only Org A's POs returned |
| 3 | Assert Org B's POs are absent from the response | No data leakage |
| 4 | Attempt to view Org B's PO: `GET route('procurement.purchase-orders.show', $orgBPo)` | 403 Forbidden or 404 Not Found |
| 5 | Attempt to update Org B's PO: `PUT route('procurement.purchase-orders.update', $orgBPo)` | 403 Forbidden or 404 Not Found |
| 6 | Attempt to submit Org B's PO: `POST route('procurement.purchase-orders.submit', $orgBPo)` | 403 Forbidden or 404 Not Found |
| 7 | Attempt to delete Org B's PO: `DELETE route('procurement.purchase-orders.destroy', $orgBPo)` | 403 Forbidden or 404 Not Found |

**Test Data:**

```php
// Org A
$orgA = Organization::factory()->create();
$userA = User::factory()->create(['organization_id' => $orgA->id]);
$poA = PurchaseOrder::factory()->create(['organization_id' => $orgA->id]);

// Org B
$orgB = Organization::factory()->create();
$userB = User::factory()->create(['organization_id' => $orgB->id]);
$poB = PurchaseOrder::factory()->create(['organization_id' => $orgB->id]);
```

**Postconditions:**

- All cross-org access attempts rejected
- No data leakage in index, show, update, submit, or delete operations
- Organization scoping enforced via `where('organization_id', $organizationId)` in index and `BelongsToOrganization` trait on model

**Notes:**

- The `PurchaseOrder` model uses the `BelongsToOrganization` trait for scoping
- The controller derives `organizationId` from `$user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id`
- Route model binding may resolve POs across organizations; the controller or a policy must enforce org matching
- This is a critical multi-tenant security test
