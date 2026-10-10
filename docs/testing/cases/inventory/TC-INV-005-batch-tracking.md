# Batch Tracking Test Cases

## Document ID: TC-INV-005

**Domain:** Inventory
**Related Test Plan:** `docs/testing/plans/inventory-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-INV-005-01: View batches index with search and status filter

| Field | Value |
|-------|-------|
| **ID** | TC-INV-005-01 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | Partial |
| **Test File** | `tests/Feature/Inventory/BatchTrackingTest.php` (exists, covers basic index rendering) |
| **Test Method** | `test_index_displays_batches()` (exists), `test_index_filters_by_status()` (needed), `test_index_searches_by_batch_number()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait (or manual setup as in existing `BatchTrackingTest`)
2. User is authenticated with `inventory.view` permission
3. Multiple InventoryBatch records exist in the organization with different statuses (`open`, `closed`) and associated suppliers
4. Team and OnboardingProgress records exist for the user

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `GET` request to `route('inventory.batches.index')` | Response status 200, Inertia renders `Inventory/Batches/Index` |
| 2 | Assert response contains `batches` prop with paginated collection (20 per page) | Paginator present with batch data |
| 3 | Assert each batch includes `supplier` relationship (loaded via `supplier:id,name,company_name`) | Supplier data available for display |
| 4 | Assert each batch includes `items_count` (via `withCount('items')`) | Item count appended |
| 5 | Assert response contains `stats` prop with `total`, `open`, `closed`, `total_acquisition_cost`, `total_items` | Statistics from `BatchTrackingService::getStats()` |
| 6 | Send `GET` with `?status=open` filter | Only batches with `status = 'open'` returned |
| 7 | Send `GET` with `?status=closed` filter | Only batches with `status = 'closed'` returned |
| 8 | Send `GET` with `?search=BATCH-2026` query parameter | Batches matching the search term in `batch_number` or `name` (ILIKE) returned |
| 9 | Assert batches are ordered by `created_at DESC` (newest first) | Default sort order is newest first |
| 10 | Assert batches from other organizations are NOT returned | Organization isolation enforced |

**Test Data:**

```php
// Via manual setup (matching existing BatchTrackingTest pattern):
$this->organization = Organization::factory()->create();
$this->team = Team::factory()->create(['organization_id' => $this->organization->id]);
$this->user = User::factory()->create(['organization_id' => $this->organization->id]);
$this->user->current_team_id = $this->team->id;

$supplier = Supplier::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Test Supplier',
    'company_name' => 'Supplier Co.',
]);

$openBatch = InventoryBatch::factory()->create([
    'organization_id' => $this->organization->id,
    'batch_number' => 'BATCH-2026-001',
    'name' => 'Q1 Supplier Lot',
    'supplier_id' => $supplier->id,
    'status' => 'open',
    'acquisition_cost' => 500.00,
    'item_count' => 25,
]);

$closedBatch = InventoryBatch::factory()->create([
    'organization_id' => $this->organization->id,
    'batch_number' => 'BATCH-2026-002',
    'name' => 'Closeout Sale Lot',
    'supplier_id' => $supplier->id,
    'status' => 'closed',
    'acquisition_cost' => 1200.00,
    'item_count' => 60,
]);
```

**Postconditions:**

- No database mutations; read-only endpoint
- Batches are scoped to the organization via `withoutGlobalScopes()->where('organization_id', $organizationId)`
- Search uses PostgreSQL `ILIKE` for case-insensitive matching
- Pagination preserves query string via `withQueryString()`

**Notes:**

- The existing `BatchTrackingTest` sets up fixtures manually rather than using the `CreatesOrganizationFixtures` trait
- The controller uses `withoutGlobalScopes()` on the query, then manually filters by `organization_id`
- `InventoryBatch` uses the `BelongsToOrganization` trait which provides a global scope
- `InventoryBatch` uses `HasUuid` for UUID primary keys and `SoftDeletes` for soft deletion
- Stats are computed by `BatchTrackingService::getStats($organizationId)`

---

### TC-INV-005-02: Create batch with supplier, auto-generated batch_number, and acquisition details

| Field | Value |
|-------|-------|
| **ID** | TC-INV-005-02 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | Partial |
| **Test File** | `tests/Feature/Inventory/BatchTrackingTest.php` (exists, may cover basic creation) |
| **Test Method** | `test_store_batch_with_supplier_and_acquisition_details()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `inventory.batches` permission
3. A Supplier exists in the organization
4. Optionally, a PurchaseOrder exists for linking

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` request to `route('inventory.batches.store')` with batch data including `name`, `supplier_id`, `description`, `acquisition_date` | Response redirects to the batch show page with success flash |
| 2 | Assert batch exists in database with correct `organization_id` | Batch created in the correct organization |
| 3 | Assert `batch_number` is auto-generated and unique within the organization | Batch number follows the auto-generation pattern |
| 4 | Assert `supplier_id` references the correct Supplier | Supplier relationship linked |
| 5 | Assert `status` is `'open'` | New batches always start as open |
| 6 | Assert `acquisition_date` is stored correctly | Date persisted as date type |
| 7 | Assert `acquisition_cost` is stored as decimal:2 | Cost persisted with proper precision |
| 8 | Assert `source_description` and `notes` are stored | Optional text fields persisted |
| 9 | Assert batch UUID primary key is generated | `HasUuid` trait generates UUID |

**Test Data:**

```php
$supplier = Supplier::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Electronics Supplier',
]);

$batchData = [
    'name' => 'Spring Electronics Lot',
    'supplier_id' => $supplier->id,
    'description' => 'Mixed electronics from spring clearance',
    'acquisition_date' => '2026-04-10',
    'source_description' => 'Clearance sale at supplier warehouse',
    'notes' => 'Approximately 30% of items are refurbished',
];
```

**Postconditions:**

- One new InventoryBatch record exists with `status = 'open'`
- `batch_number` is auto-generated by the `BatchTrackingService::create()` method
- `organization_id` is set from the authenticated user's organization
- Redirect target is `inventory.batches.show` with the new batch
- If `items` array is included in the request, batch items are also created (see TC-INV-005-04)

**Notes:**

- The `BatchTrackingService::create()` method handles batch creation and auto-generates the batch number
- Validation rules from the controller `store()` method:
  - `name`: required, string, max 255
  - `supplier_id`: nullable, uuid, exists in suppliers table
  - `purchase_order_id`: nullable, uuid, exists in purchase_orders table
  - `description`: nullable, string, max 1000
  - `acquisition_date`: nullable, date
  - `source_description`: nullable, string, max 500
  - `notes`: nullable, string, max 2000
  - `items`: nullable, array (items can be added during creation or later)
- The service sets `status = 'open'` hardcoded, not from the request

---

### TC-INV-005-03: Batch number format is auto-generated and unique within org

| Field | Value |
|-------|-------|
| **ID** | TC-INV-005-03 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/BatchTrackingTest.php` (needed addition) |
| **Test Method** | `test_batch_number_auto_generated_unique_within_org()` (needed) |

**Preconditions:**

1. Organization fixtures set up (Org A and Org B)
2. User is authenticated
3. No batches exist initially (clean state)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create a batch in Org A | Batch created with auto-generated `batch_number` |
| 2 | Assert `batch_number` is non-null and non-empty | Number generated automatically |
| 3 | Assert `batch_number` follows the expected format (e.g., `BATCH-0001` or date-based) | Format is consistent |
| 4 | Create a second batch in Org A | Second batch created |
| 5 | Assert second batch has a different `batch_number` than the first | Numbers are unique within org |
| 6 | Assert batch numbers are sequential or otherwise distinguishable | Incrementing or timestamp-based |
| 7 | Create a batch in Org B | Batch created in different org |
| 8 | Assert Org B's batch number can overlap with Org A's numbering | Uniqueness is scoped to organization, not global |

**Test Data:**

```php
// Org A batch 1: batch_number auto-generated (e.g., 'BATCH-0001')
// Org A batch 2: batch_number auto-generated (e.g., 'BATCH-0002')
// Org B batch 1: batch_number could also be 'BATCH-0001' (different org)

$batchDataA1 = ['name' => 'Batch A1'];
$batchDataA2 = ['name' => 'Batch A2'];
$batchDataB1 = ['name' => 'Batch B1'];
```

**Postconditions:**

- Each batch within an organization has a unique `batch_number`
- Batch numbers from different organizations can be identical (org-scoped uniqueness)
- The `BatchTrackingService::create()` method handles number generation

**Notes:**

- The exact batch number format depends on the `BatchTrackingService` implementation
- Common formats: sequential (`BATCH-0001`), date-based (`BATCH-20260415-001`), or org-prefix-based
- The `batch_number` field is in the InventoryBatch `$fillable` array but is set by the service, not user input
- Search on the index page uses `ILIKE` on `batch_number`, so the format should be search-friendly

---

### TC-INV-005-04: Add items to open batch creates InventoryBatchItem records

| Field | Value |
|-------|-------|
| **ID** | TC-INV-005-04 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/BatchTrackingTest.php` (needed addition) |
| **Test Method** | `test_add_item_to_open_batch_creates_batch_item()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `inventory.batches` permission
3. An open batch exists (`status = 'open'`)
4. A product exists in the organization with a known `cost_price`

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` request to `route('inventory.batches.add-item', $batch)` with `product_id`, `quantity = 10`, `unit_cost = 15.00` | Response redirects back with success flash "Item added to batch." |
| 2 | Assert an InventoryBatchItem record was created | Record exists in `inventory_batch_items` table |
| 3 | Assert batch item `batch_id` references the correct batch | Foreign key set correctly |
| 4 | Assert batch item `product_id` references the correct product (UUID) | Product linked correctly |
| 5 | Assert batch item `quantity` is `10` | Quantity stored |
| 6 | Assert batch item `unit_cost` is `15.00` | Unit cost stored as decimal |
| 7 | Refresh the batch from database | Fresh data with recalculated values |
| 8 | Assert batch `item_count` is updated (via `recalculate()`) | Aggregate count reflects new item |
| 9 | Assert batch `acquisition_cost` is updated (via `recalculate()`) | Aggregate cost reflects new item value |

**Test Data:**

```php
$batch = InventoryBatch::factory()->create([
    'organization_id' => $this->organization->id,
    'status' => 'open',
    'item_count' => 0,
    'acquisition_cost' => 0.00,
]);

$product = Product::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Widget A',
    'sku' => 'TEST-WA-001',
    'cost_price' => 15.00,
]);

$itemData = [
    'product_id' => $product->id,   // UUID string
    'quantity' => 10,
    'unit_cost' => 15.00,
];
```

**Postconditions:**

- One InventoryBatchItem record created
- Batch `item_count` and `acquisition_cost` recalculated via `$batch->recalculate()`
- The `BatchTrackingService::addItem()` method handles the creation
- Validation: `product_id` must be a valid UUID that exists in the products table

**Notes:**

- Route: `POST /inventory/batches/{batch}/items`
- The controller checks `$batch->isOpen()` before adding items (see TC-INV-005-07 for the closed batch case)
- Validation rules for items: `product_id` required uuid exists, `quantity` required integer min 1, `unit_cost` required numeric min 0
- The `recalculate()` method on the batch updates aggregate fields from the items relationship
- The `items()` relationship uses `hasMany(InventoryBatchItem::class, 'batch_id')`
- Adding items during batch creation (via the `items` array in the store request) also calls `addItem()` for each item

---

### TC-INV-005-05: Adding items to batch creates InventoryBatchItem records

| Field | Value |
|-------|-------|
| **ID** | TC-INV-005-05 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/BatchTrackingTest.php` (needed addition) |
| **Test Method** | `test_multiple_items_added_to_batch()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `inventory.batches` permission
3. An open batch exists
4. Multiple products exist in the organization

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Add first item to batch: Product A, quantity 5, unit_cost 10.00 | First InventoryBatchItem created |
| 2 | Add second item to batch: Product B, quantity 3, unit_cost 25.00 | Second InventoryBatchItem created |
| 3 | Add third item to batch: Product A again, quantity 2, unit_cost 10.00 | Third InventoryBatchItem created (same product, separate record) |
| 4 | Assert batch has 3 items (via `items()->count()`) | Three distinct batch item records |
| 5 | Assert batch `items_count` attribute reflects 3 | Count updated after recalculate |
| 6 | Load batch with items relationship | `$batch->load('items.product:id,name,sku,cost_price')` succeeds |
| 7 | Assert each item has correct `product_id`, `quantity`, and `unit_cost` | All fields correct per item |
| 8 | Assert batch `acquisition_cost` reflects the total: (5*10) + (3*25) + (2*10) = 145.00 | Aggregate cost correct |

**Test Data:**

```php
$productA = Product::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Widget A',
    'sku' => 'TEST-WA-001',
]);

$productB = Product::factory()->create([
    'organization_id' => $this->organization->id,
    'name' => 'Widget B',
    'sku' => 'TEST-WB-002',
]);

// Three items:
// Item 1: Product A, qty 5, cost 10.00 -> 50.00
// Item 2: Product B, qty 3, cost 25.00 -> 75.00
// Item 3: Product A, qty 2, cost 10.00 -> 20.00
// Total acquisition_cost: 145.00
// Total item_count: 10 (or 3 depending on what item_count means)
```

**Postconditions:**

- Three InventoryBatchItem records exist in the database
- Each item is independently tracked (same product can appear multiple times)
- Batch aggregate fields (`item_count`, `acquisition_cost`, `cost_per_item`) reflect all items
- The show page (`GET /inventory/batches/{batch}`) loads items with product details

**Notes:**

- The `items` relationship on InventoryBatch: `hasMany(InventoryBatchItem::class, 'batch_id')`
- The batch show page loads items via `$batch->load(['items.product:id,name,sku,cost_price', ...])`
- Whether `item_count` means "number of item records" or "total quantity across items" depends on the `recalculate()` implementation
- `cost_per_item` is likely `acquisition_cost / item_count` and is stored as `decimal:2`
- Duplicate products in the same batch are allowed (e.g., different lots at different costs)

---

### TC-INV-005-06: Close batch sets status=closed, prevents further item additions

| Field | Value |
|-------|-------|
| **ID** | TC-INV-005-06 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/BatchTrackingTest.php` (needed addition) |
| **Test Method** | `test_close_batch_sets_status_closed()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `inventory.batches` permission
3. An open batch exists with at least one item

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Assert batch `status` is `'open'` | Initial state confirmed |
| 2 | Send `POST` request to `route('inventory.batches.close', $batch)` | Response redirects back with success flash "Batch closed." |
| 3 | Refresh batch from database | Fresh data loaded |
| 4 | Assert `status` equals `'closed'` | Status changed to closed |
| 5 | Assert batch record still exists (not deleted) | Closing preserves the record |
| 6 | Assert batch items are still accessible via `items()` relationship | Items remain linked |
| 7 | Assert batch appears in the index when filtered by `?status=closed` | Closed batches are visible |

**Test Data:**

```php
$batch = InventoryBatch::factory()->create([
    'organization_id' => $this->organization->id,
    'status' => 'open',
    'batch_number' => 'BATCH-CLOSE-001',
    'name' => 'Batch to Close',
    'item_count' => 5,
    'acquisition_cost' => 100.00,
]);

// Add at least one item before closing
InventoryBatchItem::create([
    'batch_id' => $batch->id,
    'product_id' => $product->id,
    'quantity' => 5,
    'unit_cost' => 20.00,
]);
```

**Postconditions:**

- Batch `status` changed from `'open'` to `'closed'`
- Batch is now immutable (no further item additions allowed)
- The `scopeOpen()` scope no longer includes this batch
- The `scopeClosed()` scope now includes this batch
- Batch items and aggregate fields remain intact after closing

**Notes:**

- Route: `POST /inventory/batches/{batch}/close`
- The `BatchTrackingService::close()` method handles the status change
- Closing a batch may also trigger inventory-related side effects (e.g., finalizing COGS calculations)
- The `InventoryBatch::STATUS_OPEN = 'open'` and `STATUS_CLOSED = 'closed'` constants define the valid statuses
- Reopening a closed batch is not currently supported (no reopen endpoint)

---

### TC-INV-005-07: Cannot add items to closed batch

| Field | Value |
|-------|-------|
| **ID** | TC-INV-005-07 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/BatchTrackingTest.php` (needed addition) |
| **Test Method** | `test_cannot_add_items_to_closed_batch()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `inventory.batches` permission
3. A closed batch exists (`status = 'closed'`)
4. A product exists in the organization

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Assert batch `status` is `'closed'` | Precondition verified |
| 2 | Send `POST` request to `route('inventory.batches.add-item', $batch)` with valid item data | Response redirects back with error flash "Cannot add items to a closed batch." |
| 3 | Assert no new InventoryBatchItem records were created | Database unchanged |
| 4 | Assert batch `item_count` is unchanged | Aggregate not modified |
| 5 | Assert batch `acquisition_cost` is unchanged | Aggregate not modified |

**Test Data:**

```php
$closedBatch = InventoryBatch::factory()->create([
    'organization_id' => $this->organization->id,
    'status' => 'closed',
    'batch_number' => 'BATCH-CLOSED-001',
    'name' => 'Already Closed Batch',
    'item_count' => 10,
    'acquisition_cost' => 250.00,
]);

$product = Product::factory()->create([
    'organization_id' => $this->organization->id,
]);

$itemData = [
    'product_id' => $product->id,
    'quantity' => 5,
    'unit_cost' => 10.00,
];
```

**Postconditions:**

- No InventoryBatchItem records added
- Batch record unchanged
- Error message displayed to user via session flash: "Cannot add items to a closed batch."

**Notes:**

- The controller checks `$batch->isOpen()` at the top of the `addItem()` method before proceeding
- The `isOpen()` method presumably checks `$this->status === self::STATUS_OPEN`
- This check is done in the controller, not via middleware or form request validation
- If `isOpen()` returns false, the controller immediately redirects back with the error session message
- The request body is never processed, and `BatchTrackingService::addItem()` is never called

---

### TC-INV-005-08: Batch with purchase_order_id links to procurement

| Field | Value |
|-------|-------|
| **ID** | TC-INV-005-08 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/Inventory/BatchTrackingTest.php` (needed addition) |
| **Test Method** | `test_batch_links_to_purchase_order()` (needed) |

**Preconditions:**

1. Organization fixtures set up via `CreatesOrganizationFixtures` trait
2. User is authenticated with `inventory.batches` permission
3. A PurchaseOrder exists in the organization (not cancelled)
4. A Supplier exists in the organization

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('inventory.batches.store')` with `purchase_order_id` set to the PO's UUID | Response redirects to batch show with success flash |
| 2 | Assert batch `purchase_order_id` references the correct PurchaseOrder | Foreign key set correctly |
| 3 | Load batch with `purchaseOrder` relationship | `$batch->purchaseOrder` returns the PurchaseOrder model |
| 4 | Assert the PO's `po_number` is accessible through the relationship | `$batch->purchaseOrder->po_number` returns the expected value |
| 5 | Assert the batch show page displays the PO reference | Show page loads `purchaseOrder:id,po_number` |
| 6 | Create a batch WITHOUT a `purchase_order_id` | Batch created with `purchase_order_id = null` |
| 7 | Assert `purchase_order_id` is null | Optional field accepts null |
| 8 | Assert `purchaseOrder` relationship returns null | No related PO |

**Test Data:**

```php
$supplier = Supplier::factory()->create([
    'organization_id' => $this->organization->id,
]);

$purchaseOrder = PurchaseOrder::factory()->create([
    'organization_id' => $this->organization->id,
    'supplier_id' => $supplier->id,
    'po_number' => 'PO-2026-0042',
    'status' => 'approved',
]);

$batchData = [
    'name' => 'PO Receiving Batch',
    'supplier_id' => $supplier->id,
    'purchase_order_id' => $purchaseOrder->id, // UUID
    'acquisition_date' => '2026-04-15',
    'notes' => 'Received from PO-2026-0042',
];
```

**Postconditions:**

- InventoryBatch record has `purchase_order_id` referencing the PurchaseOrder
- The `purchaseOrder()` BelongsTo relationship on InventoryBatch returns the linked PO
- The batch show page loads the PO via `$batch->load(['purchaseOrder:id,po_number'])`
- The create form provides available POs via `PurchaseOrder::withoutGlobalScopes()->where('organization_id', ...)->whereNotIn('status', ['cancelled'])->limit(50)->get()`

**Notes:**

- The `purchase_order_id` field is validated as `nullable|uuid|exists:purchase_orders,id`
- Only non-cancelled PurchaseOrders are shown in the create form dropdown (last 50)
- This links the batch tracking system to the procurement module, enabling end-to-end receiving traceability
- The supplier on the batch may differ from the supplier on the PO (e.g., drop-ship scenarios), but typically they match
- `PurchaseOrder` uses `withoutGlobalScopes()` in the create form query to bypass organization scoping, then filters by `organization_id`
- The procurement receiving workflow may automatically create batches when PO items are received
