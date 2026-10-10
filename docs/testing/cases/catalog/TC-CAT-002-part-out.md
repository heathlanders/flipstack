# Part-Out System Test Cases

## Document ID: TC-CAT-002

**Domain:** Part-Out
**Related Test Plan:** `docs/testing/plans/catalog-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-CAT-002-01: List work orders with filters

| Field | Value |
|-------|-------|
| **ID** | TC-CAT-002-01 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user (controller applies `auth` middleware)
2. Organization has PartOutWorkOrder records in various statuses (draft, completed, cancelled)
3. PartOutService injected into PartOutWorkOrderController

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `GET` to `route('inventory.part-out.work-orders.index')` | Response status 200, Inertia renders `Inventory/PartOut/WorkOrders/Index` |
| 2 | Assert `workOrders` prop is paginated at 20 per page with `sourceProduct` and `template` relationships loaded | Paginator with related data |
| 3 | Assert each work order has `items_count` attribute via `withCount('items')` | Component count per work order |
| 4 | Assert `stats` prop from `PartOutService::getStats($organizationId)` | Summary statistics for dashboard |
| 5 | Send `GET` with `?status=draft` | Only draft work orders returned |
| 6 | Send `GET` with `?search=WO-` | Work orders matching `work_order_number` or source product name/SKU via ILIKE returned |
| 7 | Assert work orders ordered by `created_at desc` | Most recent first |
| 8 | Assert `filters` prop echoes back `search` and `status` values | Filter state preserved |

**Notes:**
- Route: `GET /inventory/part-out/work-orders` named `inventory.part-out.work-orders.index`
- Uses `PartOutWorkOrder::withoutGlobalScopes()->where('organization_id', ...)` for explicit org filtering
- Search applies ILIKE to `work_order_number` and related `sourceProduct.name` / `sourceProduct.sku`
- Source product loaded with select: `id, name, sku, cost_price`

---

### TC-CAT-002-02: Create template from product

| Field | Value |
|-------|-------|
| **ID** | TC-CAT-002-02 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. Product exists with populated `component_breakdown` JSON field (from AI analysis in intake pipeline)
3. PartOutService injected

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('inventory.part-out.templates.from-product', $product)` | Redirect to template show page with flash `'Template created from product component data.'` |
| 2 | Assert PartOutTemplate created with `source_product_id = $product->id` | Template linked to source product |
| 3 | Assert template name derived from product name | e.g., "Part-Out: {product.name}" |
| 4 | Assert PartOutTemplateItem records created matching the `component_breakdown` data | Items auto-populated from AI analysis |
| 5 | Assert `created_by` set to authenticated user ID | Creator tracked |
| 6 | Attempt with product that has `component_breakdown = null` | Redirect back with error: `'Product has no component breakdown data from AI analysis.'` |

**Notes:**
- Route: `POST /inventory/part-out/templates/from-product/{product}` named `inventory.part-out.templates.from-product`
- `PartOutTemplateController::createFromProduct()` checks `empty($product->component_breakdown)` first
- `PartOutService::createTemplateFromProduct()` transforms the AI component data into template items
- The `component_breakdown` field is populated during the product intake pipeline's AI research step

---

### TC-CAT-002-03: Create work order from template

| Field | Value |
|-------|-------|
| **ID** | TC-CAT-002-03 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. PartOutTemplate exists (is_active = true) with template items
3. Source product exists with `available_quantity > 0`
4. InventoryLocation exists (is_active = true)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('inventory.part-out.work-orders.store')` with `source_product_id`, `template_id`, `source_quantity = 1`, `cost_allocation_method = 'market_value'`, `location_id`, and `items` array | Redirect to work order show page with flash `'Work order created.'` |
| 2 | Assert PartOutWorkOrder created with `status = 'draft'` and `organization_id` | Work order persisted |
| 3 | Assert `work_order_number` auto-generated | Format: e.g., `WO-NNNNNN` |
| 4 | Assert `template_id` links to the source template | Template reference stored |
| 5 | Assert PartOutWorkOrderItem records created matching submitted items | Component items with `name`, `quantity`, `cost_allocation_pct`, `suggested_selling_price` |
| 6 | Assert `created_by` set to authenticated user ID | Creator tracked |
| 7 | Validate: `cost_allocation_method` must be `market_value`, `percentage`, or `even` | Validation error on invalid method |
| 8 | Validate: `items` required array min:1 with required `name` and `quantity` per item | Validation on item structure |

**Notes:**
- Validation: `source_product_id` required UUID exists:products, `template_id` nullable UUID, `source_quantity` required int min:1
- `cost_allocation_method` determines how source product cost is distributed to component products
- Items array supports: `name`, `part_number`, `manufacturer`, `category`, `quantity`, `cost_allocation_pct`, `suggested_selling_price`, `condition`, `notes`
- `PartOutService::createWorkOrder()` handles creation with the validated data and organization ID

---

### TC-CAT-002-04: Preview cost allocation across components

| Field | Value |
|-------|-------|
| **ID** | TC-CAT-002-04 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. Known source product cost and component selling prices for market_value allocation

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('inventory.part-out.work-orders.preview-cost')` with `source_total_cost = 100.00`, `cost_allocation_method = 'even'`, and 4 items each with `quantity = 1` | JSON response with cost preview |
| 2 | Assert each item receives `100 / 4 = 25.00` cost allocation | Even distribution |
| 3 | Repeat with `cost_allocation_method = 'percentage'` and items having `cost_allocation_pct` values (40%, 30%, 20%, 10%) | Item costs: 40.00, 30.00, 20.00, 10.00 |
| 4 | Repeat with `cost_allocation_method = 'market_value'` and items having `suggested_selling_price` values | Cost distributed proportionally to market values |
| 5 | Include `remaining_parent_cost = 20.00` in request | Only `80.00` distributed to components (source_total_cost - remaining_parent_cost) |
| 6 | Assert preview is read-only (no database mutations) | No work order or product changes |

**Notes:**
- Route: `POST /inventory/part-out/work-orders/preview-cost` named `inventory.part-out.work-orders.preview-cost`
- This is a JSON endpoint for live cost preview in the UI before committing the work order
- `PartOutService::previewCostAllocation()` computes the distribution without persisting
- `remaining_parent_cost` allows the source product to retain some cost value if `parent_continues = true`

---

### TC-CAT-002-05: Execute work order creates component products

| Field | Value |
|-------|-------|
| **ID** | TC-CAT-002-05 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. PartOutWorkOrder in `draft` status with `canExecute() = true`
3. Source product with `available_quantity >= source_quantity`
4. Work order items defined with names and quantities

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('inventory.part-out.work-orders.execute', $workOrder)` | Redirect to work order show page with flash `'Work order executed. Component products have been created.'` |
| 2 | Assert work order status changed to `completed` | `$workOrder->status` equals `'completed'` |
| 3 | Assert `completed_by` set to authenticated user ID and `completed_at` set | Execution tracked |
| 4 | Assert new Product records created for each work order item | `$workOrder->createdProducts` relationship populated |
| 5 | Assert each created product has `parted_from_product_id` linking to the source product | Product lineage tracked |
| 6 | Assert each created product has `cost_price` set based on cost allocation method | Allocated cost from source product |
| 7 | Assert source product's `is_parted_out` flag set to `true` (if fully consumed) | Source product marked as disassembled |
| 8 | Assert `canExecute()` returns `false` after execution (status no longer draft) | Cannot re-execute |

**Notes:**
- `canExecute()` guard checks work order status is appropriate for execution
- `PartOutService::executeWorkOrder()` runs in a database transaction
- Created products inherit organization_id from the source product
- Each component product's `cost_price` is derived from the cost allocation calculation
- After execution, `LabelingWorkflowService::createFromPartOut()` is called (outside the main transaction, wrapped in try/catch)

---

### TC-CAT-002-06: Execute creates inventory movements (partout_consume for source, partout_produce for components)

| Field | Value |
|-------|-------|
| **ID** | TC-CAT-002-06 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. PartOutWorkOrder in draft status ready for execution
2. Source product with `stock_quantity = 5`, `available_quantity = 5`
3. Work order `source_quantity = 1`
4. Location specified on work order

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Execute the work order | Execution succeeds |
| 2 | Assert InventoryMovement created with `type = 'partout_consume'` for source product | Source consumption recorded |
| 3 | Assert source product `stock_quantity` decremented by `source_quantity` (5 -> 4) | Source stock reduced |
| 4 | Assert source product `available_quantity` decremented accordingly | Available quantity reduced |
| 5 | Assert InventoryMovement(s) created with `type = 'partout_produce'` for each component product | Component production recorded |
| 6 | Assert each component product's `stock_quantity` and `available_quantity` set to the item's quantity | Component stock initialized |
| 7 | Assert InventoryLevel records created/updated at the work order's location | Location-level inventory tracked |
| 8 | Assert movement `reference_type` and `reference_id` link to the work order | Traceability to the work order |

**Notes:**
- Two movement types distinguish the direction: `partout_consume` (source deducted) and `partout_produce` (components added)
- All inventory operations within `executeWorkOrder()` are transactional
- The work order's `location_id` determines where inventory changes are recorded
- If `parent_continues = true`, the source product may retain reduced stock rather than being fully consumed

---

### TC-CAT-002-07: Cancel draft work order

| Field | Value |
|-------|-------|
| **ID** | TC-CAT-002-07 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. PartOutWorkOrder in `draft` status with `canCancel() = true`

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('inventory.part-out.work-orders.cancel', $workOrder)` | Redirect to work order show page with flash `'Work order cancelled.'` |
| 2 | Assert work order status changed to `cancelled` | `$workOrder->status` equals `'cancelled'` |
| 3 | Assert no products were created | `$workOrder->createdProducts` is empty |
| 4 | Assert no inventory movements generated | No side effects from cancellation |
| 5 | Assert source product stock quantities unchanged | No inventory impact |

**Notes:**
- Route: `POST /inventory/part-out/work-orders/{workOrder}/cancel` named `inventory.part-out.work-orders.cancel`
- `canCancel()` checks work order status allows cancellation
- `PartOutService::cancelWorkOrder()` handles the status transition
- Cancellation is a simple status change with no inventory or product side effects

---

### TC-CAT-002-08: Cannot cancel completed work order

| Field | Value |
|-------|-------|
| **ID** | TC-CAT-002-08 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. PartOutWorkOrder in `completed` status (already executed)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Assert `$workOrder->canCancel()` returns `false` | Guard method prevents cancellation |
| 2 | Send `POST` to `route('inventory.part-out.work-orders.cancel', $workOrder)` | Redirect back with error flash: `'Work order cannot be cancelled in its current status.'` |
| 3 | Assert work order status remains `completed` | Status unchanged |
| 4 | Assert created component products still exist | No cleanup or rollback |
| 5 | Assert inventory movements still intact | Historical records preserved |

**Notes:**
- The `cancel` controller method checks `$workOrder->canCancel()` before proceeding
- Completed work orders have already created products and inventory movements that cannot be easily reversed
- Similarly, `canExecute()` returns `false` for completed work orders (cannot double-execute)
- To undo a completed work order, manual intervention would be required (adjust stock, archive created products)

---

### TC-CAT-002-09: Labeling work order auto-created on execute

| Field | Value |
|-------|-------|
| **ID** | TC-CAT-002-09 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. PartOutWorkOrder in draft status ready for execution
2. LabelingWorkflowService available and operational
3. Work order has 3 component items

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Execute the work order | Execution succeeds, component products created |
| 2 | Assert `LabelingWorkflowService::createFromPartOut()` was called | Labeling workflow triggered |
| 3 | Assert a LabelingWorkOrder was created for the component products | Labeling work order linked to created product IDs |
| 4 | Assert labeling items correspond to each created component product | One labeling item per component |
| 5 | If `createFromPartOut()` throws an exception, assert work order execution still succeeds | Labeling failure is non-fatal (wrapped in try/catch) |
| 6 | Assert warning logged if labeling creation fails | `Log::warning()` with work order ID and error message |

**Notes:**
- Labeling work order creation happens AFTER the main execution transaction
- Wrapped in `try/catch` with `\Throwable` -- labeling failure does not roll back the part-out execution
- Component product IDs are extracted via `$result->items->pluck('component_product_id')->filter()->values()->toArray()`
- The `LabelingWorkflowService` is resolved via `app()` helper, not constructor injection

---

### TC-CAT-002-10: Work order number auto-generated

| Field | Value |
|-------|-------|
| **ID** | TC-CAT-002-10 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |

**Preconditions:**
1. Authenticated user
2. Organization with known count of existing work orders (or clean state)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create a new work order via `route('inventory.part-out.work-orders.store')` | Work order created with auto-generated `work_order_number` |
| 2 | Assert `work_order_number` follows expected format (e.g., `WO-NNNNNN` or similar) | Number format consistent |
| 3 | Create a second work order | Second work order has sequential number (e.g., `WO-000002` if first was `WO-000001`) |
| 4 | Assert `work_order_number` is unique within the organization | No duplicate numbers |
| 5 | Assert cancelled work orders still consume a number (no number reuse) | Sequential numbering preserved regardless of status |
| 6 | Assert search by `work_order_number` in index page returns the correct work order | Searchable via ILIKE filter |

**Notes:**
- Work order number generation is handled by `PartOutService::createWorkOrder()`
- Similar pattern to PO number generation (`PO-NNNNNN`) on PurchaseOrder
- Number is assigned at creation time, not at execution time
- The number should be unique within the organization scope, not globally
