# Procurement to Stock End-to-End Test

## Document ID: TC-E2E-003

**Systems Involved:** Web, Queue (Redis/Horizon), PostgreSQL
**Business Process:** Complete procurement cycle from purchase order creation through goods receiving to inventory availability and cross-marketplace quantity synchronization.
**Last Updated:** 2026-04-15

---

## 1. Workflow Overview

This test validates the full procurement lifecycle from drafting a purchase order through receiving goods into warehouse inventory. It exercises the PurchaseOrderService creation and submission pipeline, the ReceivingService goods receipt flow (including partial and full receipts), InventoryLevel and InventoryMovement record creation, Product stock/available quantity updates, and the downstream PushInventoryToMarketplaces job that synchronizes new stock levels to eBay and Shopify.

```
  User creates PO          Items added             Totals calculated
  (status=draft)  --------> (products A,B,C) -----> (subtotal + tax + shipping = total_amount)
       |                         |                          |
       |                         v                          v
       |                  PO has 3 items              PurchaseOrderItem.total_cost
       |                  (qty: 10, 5, 20)            = quantity_ordered * unit_cost
       |                         |
       v                         |
  Submit PO                      |
  (status=submitted)             |
  (submitted_at set)             |
       |                         |
       v                         |
  Goods arrive                   |
  (supplier ships)               |
       |                         |
       v                         v
  Navigate to Receive page
  (shows items + remaining qty)
       |
       v
  Partial Receipt #1
  (Product A: 10 good, Product B: 3 good + 2 damaged)
       |
       +-----> PurchaseOrderReceipt created
       |       (received_by, location_id, received_at)
       |
       +-----> PurchaseOrderReceiptItem records created
       |       (quantity_received, condition)
       |
       +-----> PurchaseOrderItem.quantity_received incremented
       |
       +-----> PO status -> partially_received
       |
       +-----> InventoryLevel.quantity_on_hand incremented at PO location
       |       (firstOrCreate pattern)
       |
       +-----> InventoryLevel.average_cost recalculated (weighted average)
       |
       +-----> InventoryMovement created
       |       (type=purchase, reference_type=purchase_order_receipt)
       |
       +-----> Product.stock_quantity incremented
       |
       +-----> Product.available_quantity incremented
       |
       +-----> PushInventoryToMarketplaces dispatched (per product)
       |       (pushes new available_quantity to eBay + Shopify)
       |
       +-----> LabelingWorkflowService.createFromPoReceipt() (if auto-labeling enabled)
       |
       v
  Full Receipt #2
  (Product C: 20 good)
       |
       +-----> Same receipt/inventory/movement flow as above
       |
       +-----> PO status -> received (all items fully received)
       |
       +-----> PO.received_at set
       |
       v
  All stock available on marketplaces
```

**Trigger:** User navigates to Procurement > Create Purchase Order (`/procurement/purchase-orders/create`)
**End State:** PurchaseOrder.status = `received`, all items fully received, InventoryLevel.quantity_on_hand reflects received quantities, Product.stock_quantity and available_quantity updated, marketplace listings reflect new stock levels

---

## 2. Preconditions

| # | Requirement | Setup Method |
|---|-------------|--------------|
| 1 | Organization with active subscription and configured SKU prefix | Factory / Seeder |
| 2 | User with `inventory.view` permission (required by procurement route middleware) | Factory with role assignment |
| 3 | Supplier record exists (is_active=true) belonging to Organization | Factory: `Supplier::factory()->create()` |
| 4 | InventoryLocation exists (is_active=true) with name and code, belonging to Organization | Factory: `InventoryLocation::factory()->create()` |
| 5 | Product A exists with known stock_quantity and available_quantity (e.g., both = 5) | Factory: `Product::factory()->create(['stock_quantity' => 5, 'available_quantity' => 5])` |
| 6 | Product B exists with known stock_quantity and available_quantity (e.g., both = 3) | Factory: `Product::factory()->create(['stock_quantity' => 3, 'available_quantity' => 3])` |
| 7 | Product C exists with known stock_quantity and available_quantity (e.g., both = 0) | Factory: `Product::factory()->create(['stock_quantity' => 0, 'available_quantity' => 0])` |
| 8 | InventoryLevel records exist for Products A, B, C at the target InventoryLocation (quantity_on_hand matching product stock) | Factory or manual creation |
| 9 | eBay IntegrationCredential (platform=ebay, is_active=true, auto_sync_enabled=true) with valid OAuthToken | Factory with sandbox credentials |
| 10 | Shopify IntegrationCredential (platform=shopify, is_active=true, auto_sync_enabled=true) with shop_domain in config | Factory with sandbox credentials |
| 11 | EbayListing (status=published) and/or ShopifyListing (status=active) linked to Products A, B, C (needed for marketplace push) | Factory |
| 12 | Redis and Horizon running (queue jobs must execute for marketplace sync) | Docker environment |
| 13 | No pre-existing PurchaseOrders in the organization (to verify PO number auto-generation starts at PO-000001) | Clean state or isolated test org |

---

## 3. Test Scenario

| Step | Actor | System | Action | Expected Result | Verification Method |
|------|-------|--------|--------|-----------------|---------------------|
| 1 | User | Web UI | Navigates to `/procurement/purchase-orders/create`. Selects Supplier and InventoryLocation. Submits via POST to `procurement.purchase-orders.store` with `supplier_id`, `location_id`, `order_date`, `expected_delivery_date`, `tax_amount=5.00`, `shipping_cost=15.00`, and empty items array | PurchaseOrder created with status=`draft`. `po_number` auto-generated as `PO-000001` (format: `PO-NNNNNN` based on org PO count + 1). `created_by` = current user ID. `order_date` defaults to today if not provided. `tax_amount=5.00`, `shipping_cost=15.00`. Redirects to PO show page | DB: `SELECT id, po_number, status, supplier_id, location_id, created_by, tax_amount, shipping_cost FROM purchase_orders WHERE po_number = 'PO-000001'` -- Assert status='draft', supplier_id matches, location_id matches, created_by = user ID |
| 2 | User | Web UI | Edits draft PO via PUT to `procurement.purchase-orders.update`. Adds 3 items: Product A (quantity_ordered=10, unit_cost=25.00), Product B (quantity_ordered=5, unit_cost=50.00), Product C (quantity_ordered=20, unit_cost=10.00). Items sent in `items` array with `product_id`, `quantity_ordered`, `unit_cost` | Three PurchaseOrderItem records created. Each item's `total_cost` auto-calculated by model `saving` hook: A=250.00, B=250.00, C=200.00. PO `subtotal` recalculated to 700.00 via `recalculateTotals()`. PO `total_amount` = subtotal(700) + tax(5) + shipping(15) = 720.00 | DB: `SELECT poi.product_id, poi.quantity_ordered, poi.unit_cost, poi.total_cost FROM purchase_order_items poi WHERE poi.purchase_order_id = '{po_id}' ORDER BY poi.unit_cost DESC` -- Assert 3 rows with correct totals. `SELECT subtotal, total_amount FROM purchase_orders WHERE id = '{po_id}'` -- Assert subtotal=700.00, total_amount=720.00 |
| 3 | User | Web UI | Views PO show page at `/procurement/purchase-orders/{id}`. Verifies item count, totals, and supplier info displayed correctly | Inertia renders `Procurement/PurchaseOrders/Show` with purchaseOrder loaded including `items.product`, `supplier`, `location`, `creator`. `items_count` accessor returns 3. `total_quantity_ordered` accessor returns 35. `receiving_progress` accessor returns 0.0 | UI: Verify 3 items listed with correct product names, quantities, costs. Verify PO total = 720.00. Verify status badge shows "Draft" |
| 4 | User | Web UI | Submits PO via POST to `procurement.purchase-orders.submit`. `PurchaseOrderService::submit()` called | PO status changes from `draft` to `submitted`. `submitted_at` set to current timestamp. `approved_by` set to current user ID. `approved_at` set to current timestamp. `canBeSubmitted()` guard passes (status=draft AND items count > 0). `canBeEdited()` now returns false. Flash message: "Purchase order PO-000001 submitted." | DB: `SELECT status, submitted_at, approved_by, approved_at FROM purchase_orders WHERE id = '{po_id}'` -- Assert status='submitted', submitted_at NOT NULL, approved_by = user ID |
| 5 | User | Web UI | Navigates to receive page via GET `procurement.purchase-orders.receive` (`/procurement/purchase-orders/{id}/receive`). `canReceiveItems()` guard passes (status=submitted) | Inertia renders `Procurement/PurchaseOrders/Receive` with PO items loaded (including `items.product` with name, SKU, featured_image). Each item shows `quantity_ordered`, `quantity_received` (0), and `remaining_quantity` accessor (= quantity_ordered - quantity_received). Locations dropdown populated | UI: Verify Product A shows ordered=10, received=0, remaining=10. Product B shows ordered=5, received=0, remaining=5. Product C shows ordered=20, received=0, remaining=20 |
| 6 | User | Web UI | Processes partial receipt via POST to `procurement.purchase-orders.process-receive`. Payload: `location_id` (same as PO location), `notes="First shipment"`, `items` array with two entries: Item 1: `purchase_order_item_id` (Product A item ID), `quantity_received=10`, `condition="good"`. Item 2: `purchase_order_item_id` (Product B item ID), `quantity_received=3`, `condition="good"`. Item 3: `purchase_order_item_id` (Product B item ID -- same item, separate entry for damaged), `quantity_received=2`, `condition="damaged"`, `condition_notes="Crushed packaging"` | `ReceivingService::receive()` executes in DB transaction. PurchaseOrderReceipt created with `received_by`=user ID, `location_id`=PO location, `received_at`=now, `notes`="First shipment". Three PurchaseOrderReceiptItem records created (qty 10/good, qty 3/good, qty 2/damaged). PO Item A: `quantity_received` incremented to 10. PO Item B: `quantity_received` incremented to 5 (3+2). Product C not received yet. Redirect with flash "Items received successfully." | DB: `SELECT id, received_by, location_id, received_at, notes FROM purchase_order_receipts WHERE purchase_order_id = '{po_id}'` -- Assert 1 receipt. `SELECT quantity_received, condition, condition_notes FROM purchase_order_receipt_items WHERE receipt_id = '{receipt_id}'` -- Assert 3 rows |
| 7 | System | ReceivingService | `updatePoStatus()` evaluates whether all items are fully received. Product A: 10/10 (full). Product B: 5/5 (full). Product C: 0/20 (none). Not all items fully received | PO status updated to `partially_received` (not all items pass `quantity_received >= quantity_ordered`). `received_at` NOT set (only set when fully received). `receiving_progress` accessor = round((15/35)*100, 1) = 42.9 | DB: `SELECT status, received_at FROM purchase_orders WHERE id = '{po_id}'` -- Assert status='partially_received', received_at IS NULL. `SELECT quantity_received FROM purchase_order_items WHERE purchase_order_id = '{po_id}' ORDER BY quantity_ordered` -- Assert [5, 10, 0] |
| 8 | System | ReceivingService | `updateInventory()` called for Product A (qty=10) and Product B (qty=3, then qty=2). For each: InventoryLevel at PO location is found or created. `quantity_on_hand` incremented. `average_cost` recalculated using weighted average formula: `(old_qty * old_avg_cost + new_qty * unit_cost) / (old_qty + new_qty)`. `last_movement_at` updated. InventoryMovement created with `type='purchase'`, `reference_type='purchase_order_receipt'`, `reference_id`=receipt UUID | Product A InventoryLevel: quantity_on_hand increased by 10 (from precondition value). Product B InventoryLevel: quantity_on_hand increased by 5 (3+2 across two receiveItem calls). InventoryMovement records: 3 created (one per receiveItem call), all with type='purchase'. Each movement has `unit_cost` from PO item, `reference_id` = receipt ID | DB: `SELECT quantity_on_hand, average_cost, last_movement_at FROM inventory_levels WHERE product_id = '{product_a_id}' AND location_id = '{location_id}'` -- Assert quantity_on_hand = precondition + 10. `SELECT COUNT(*), SUM(quantity) FROM inventory_movements WHERE reference_id = '{receipt_id}' AND type = 'purchase'` -- Assert count=3, sum=15 |
| 9 | System | ReceivingService | `Product.increment('stock_quantity', quantity)` and `Product.increment('available_quantity', quantity)` called for Products A and B. After inventory update, PushInventoryToMarketplaces should be dispatched to sync new available_quantity to eBay/Shopify (note: in current implementation, the ReceivingService updates product quantities but does not directly dispatch PushInventoryToMarketplaces -- this is a known gap; verify whether an observer or event triggers it, or whether it must be dispatched manually/via scheduled reconciliation) | Product A: stock_quantity = precondition(5) + 10 = 15, available_quantity = precondition(5) + 10 = 15. Product B: stock_quantity = precondition(3) + 5 = 8, available_quantity = precondition(3) + 5 = 8. If marketplace sync job dispatched: eBay/Shopify listing quantities updated to match new available_quantity. If not dispatched: quantities update on next scheduled ReconcileMarketplaceInventory run | DB: `SELECT stock_quantity, available_quantity FROM products WHERE id = '{product_a_id}'` -- Assert stock_quantity=15, available_quantity=15. `SELECT stock_quantity, available_quantity FROM products WHERE id = '{product_b_id}'` -- Assert stock_quantity=8, available_quantity=8. Queue: Check if PushInventoryToMarketplaces job dispatched for product_a_id and product_b_id |
| 10 | User | Web UI | Navigates to receive page again for remaining items. Processes second receipt: Product C: `quantity_received=20`, `condition="good"`. `ReceivingService::receive()` executes. `updatePoStatus()` checks all items: A=10/10, B=5/5, C=20/20. All fully received | Second PurchaseOrderReceipt created. PurchaseOrderReceiptItem for Product C (qty=20, condition=good). PO Item C: quantity_received=20. PO status -> `received`. PO `received_at` set to current timestamp. InventoryLevel for Product C: quantity_on_hand increased by 20. InventoryMovement created (type=purchase). Product C: stock_quantity = 0+20 = 20, available_quantity = 0+20 = 20. `isFullyReceived()` returns true. `receiving_progress` = 100.0 | DB: `SELECT status, received_at FROM purchase_orders WHERE id = '{po_id}'` -- Assert status='received', received_at NOT NULL. `SELECT COUNT(*) FROM purchase_order_receipts WHERE purchase_order_id = '{po_id}'` -- Assert 2. `SELECT stock_quantity, available_quantity FROM products WHERE id = '{product_c_id}'` -- Assert both = 20. `SELECT quantity_on_hand FROM inventory_levels WHERE product_id = '{product_c_id}' AND location_id = '{location_id}'` -- Assert = precondition + 20 |

---

## 4. Data Flow Verification

| Checkpoint | After Step | Expected State | Verification Query/Check |
|------------|-----------|----------------|--------------------------|
| PO Created with Items | Step 2 | PurchaseOrder exists with status=draft, 3 PurchaseOrderItems linked. Subtotal=700.00 (sum of item total_costs). Total=720.00 (subtotal + tax + shipping). All items have quantity_received=0 | `SELECT po.status, po.subtotal, po.total_amount, COUNT(poi.id) as item_count FROM purchase_orders po JOIN purchase_order_items poi ON poi.purchase_order_id = po.id WHERE po.id = '{po_id}' GROUP BY po.id` -- Assert status='draft', subtotal=700.00, total_amount=720.00, item_count=3 |
| PO Submitted | Step 4 | PO status=submitted. submitted_at populated with timestamp. canBeEdited()=false. canReceiveItems()=true. canBeCancelled()=true | `SELECT status, submitted_at, approved_by FROM purchase_orders WHERE id = '{po_id}'` -- Assert status='submitted', submitted_at NOT NULL, approved_by NOT NULL |
| Partial Receipt Recorded | Step 7 | 1 PurchaseOrderReceipt exists with 3 PurchaseOrderReceiptItems (10 good + 3 good + 2 damaged). PO status=partially_received. PO Item A quantity_received=10. PO Item B quantity_received=5. PO Item C quantity_received=0 | `SELECT poi.product_id, poi.quantity_ordered, poi.quantity_received FROM purchase_order_items poi WHERE poi.purchase_order_id = '{po_id}' ORDER BY poi.quantity_ordered` -- Assert rows: [5,5], [10,10], [20,0] |
| Inventory Updated (Partial) | Step 8 | InventoryLevel.quantity_on_hand increased for Products A (+10) and B (+5) at PO location. 3 InventoryMovement records exist with type=purchase, reference_type=purchase_order_receipt. Each movement's quantity matches the receiptItem quantity. average_cost recalculated using weighted average | `SELECT im.product_id, im.quantity, im.unit_cost, im.type, im.reference_type FROM inventory_movements im WHERE im.reference_id = '{receipt_1_id}' ORDER BY im.created_at` -- Assert 3 rows, type='purchase', reference_type='purchase_order_receipt'. Verify unit_cost matches PO item unit_cost (25.00 for A, 50.00 for B) |
| Product Quantities Updated (Partial) | Step 9 | Product A: stock_quantity=15, available_quantity=15. Product B: stock_quantity=8, available_quantity=8. Product C unchanged. If marketplace sync dispatched: eBay/Shopify quantities reflect new available_quantity for A and B | `SELECT id, stock_quantity, available_quantity FROM products WHERE id IN ('{product_a_id}', '{product_b_id}', '{product_c_id}') ORDER BY stock_quantity` -- Assert A=15/15, B=8/8, C=0/0 |
| Fully Received | Step 10 | PO status=received. received_at set. All PurchaseOrderItems: quantity_received = quantity_ordered. 2 PurchaseOrderReceipts exist total. Product C: stock_quantity=20, available_quantity=20. receiving_progress=100.0. Total InventoryMovements for this PO = 4 (3 from receipt 1 + 1 from receipt 2) | `SELECT status, received_at FROM purchase_orders WHERE id = '{po_id}'` -- Assert status='received', received_at NOT NULL. `SELECT COUNT(*) FROM inventory_movements im JOIN purchase_order_receipts por ON im.reference_id = por.id::text WHERE por.purchase_order_id = '{po_id}' AND im.type = 'purchase'` -- Assert count=4 |

---

## 5. Error Scenarios

| ID | Error Point | Error Condition | Expected Recovery | Verified |
|----|-------------|-----------------|-------------------|----------|
| E2E-3-ERR-01 | Step 6 | User attempts to receive more than ordered quantity (e.g., Product A quantity_received=15 when quantity_ordered=10) | Current implementation: `ReceivingService::receiveItem()` does NOT validate against remaining quantity. The PO item's `quantity_received` would exceed `quantity_ordered`. `isFullyReceived()` still returns true since `quantity_received >= quantity_ordered`. This is a permissive design -- no hard cap enforced at the service layer. The UI should warn but the backend allows over-receiving. Verify: `PurchaseOrderItem.remaining_quantity` accessor returns `max(0, ordered - received)` = 0, not negative | [ ] |
| E2E-3-ERR-02 | Step 6 | User attempts to receive items on a cancelled PO (status=cancelled) | `ReceivingService::receive()` calls `$po->canReceiveItems()` which checks `in_array(status, ['submitted', 'partially_received'])`. Cancelled POs fail this guard. RuntimeException thrown: "Purchase order is not in a receivable status." Controller catches it, redirects back with error flash | [ ] |
| E2E-3-ERR-03 | Step 6 | User attempts to receive items on a draft PO (status=draft, not yet submitted) | Same guard as ERR-02. Draft status is not in the allowed array for `canReceiveItems()`. Blocked with RuntimeException. User must submit PO first | [ ] |
| E2E-3-ERR-04 | Step 6 | Damaged goods: User marks items with condition="damaged" or condition="defective" | `PurchaseOrderReceiptItem.condition` field accepts 'good', 'damaged', 'defective' (validated in controller). `condition_notes` stores free-text explanation. Inventory is still incremented regardless of condition (current implementation does not quarantine damaged goods). Damaged items are tracked for reporting but still increase on-hand stock | [ ] |
| E2E-3-ERR-05 | Step 6 | Duplicate receipt: User accidentally submits the same receipt twice (double-click, page refresh) | Each POST to `processReceive` creates a new PurchaseOrderReceipt. No idempotency guard exists in `ReceivingService`. Double submission would double-count: `PurchaseOrderItem.quantity_received` incremented twice, `InventoryLevel.quantity_on_hand` increased twice, `Product.stock_quantity` increased twice. Prevention must be handled at the UI layer (disable button after click, debounce) or via CSRF token consumption | [ ] |
| E2E-3-ERR-06 | Step 1 | PO created with invalid `supplier_id` (non-existent UUID) | Controller validation rule `'supplier_id' => ['nullable', 'uuid', 'exists:suppliers,id']` rejects the request with 422 Unprocessable Entity. Inertia returns validation errors to the form. PO not created | [ ] |
| E2E-3-ERR-07 | Step 6 | User submits receipt item with `quantity_received=0` or negative | `ReceivingService::receiveItem()` explicitly checks `if ($quantityReceived <= 0)` and throws RuntimeException: "Quantity received must be greater than zero." Controller validation also enforces `'items.*.quantity_received' => ['required', 'integer', 'min:1']` -- request rejected before reaching service | [ ] |
| E2E-3-ERR-08 | Step 9 | Network failure during PushInventoryToMarketplaces job execution (eBay/Shopify API unreachable) | PushInventoryToMarketplaces has `$tries=3` and `$backoff=30` seconds. Job retried up to 3 times. On final failure: `failed()` method logs error, creates EbaySyncLog/ShopifySyncLog entries with status='error', notifies org admins via MarketplaceInventorySyncFailedNotification. Local inventory (DB) remains correct; only marketplace sync is delayed | [ ] |
| E2E-3-ERR-09 | Step 4 | User attempts to submit PO with zero items | `PurchaseOrder::canBeSubmitted()` checks `$this->items()->count() > 0`. Returns false when no items exist. `PurchaseOrderService::submit()` throws RuntimeException: "Purchase order cannot be submitted. Ensure it is in draft status and has items." | [ ] |
| E2E-3-ERR-10 | Step 6 | Database transaction fails mid-receipt (e.g., product_id FK constraint violation on ReceiptItem, or connection timeout) | `ReceivingService::receive()` wraps all operations in `DB::transaction()`. If any step fails, entire receipt rolls back: no PurchaseOrderReceipt created, no ReceiptItems, no PurchaseOrderItem.quantity_received changes, no InventoryLevel changes, no InventoryMovement records, no Product stock changes. Atomicity preserved. LabelingWorkflowService call (outside transaction) is skipped since receipt creation failed | [ ] |

---

## 6. Performance Expectations

| Metric | Acceptable | Measured |
|--------|-----------|----------|
| PO creation with 3 items (Step 1-2, single DB transaction) | < 500ms (3 item inserts + 3 recalculateTotals calls via saved hook) | |
| PO item total_cost auto-calculation (model saving hook, per item) | < 10ms per item | |
| PO recalculateTotals (SUM query + update, triggered per item save) | < 50ms per call | |
| PO submission (single update, Step 4) | < 100ms | |
| Receipt processing with 3 receipt items (Step 6, full DB transaction) | < 2 seconds (3 receiveItem calls, each with InventoryLevel firstOrCreate + increment + InventoryMovement create + Product increment) | |
| InventoryLevel firstOrCreate + increment (per product per location) | < 100ms per call | |
| InventoryMovement creation (single insert) | < 50ms per record | |
| Product stock_quantity/available_quantity increment (per product) | < 50ms per call | |
| PushInventoryToMarketplaces job execution (marketplace API calls) | < 30 seconds per product across all marketplace adapters | |
| Full end-to-end (Steps 1-10, excluding marketplace API latency) | < 10 seconds for all user-driven and system steps | |
| Queue job pickup latency (Horizon) | < 5 seconds from dispatch to processing start | |

---

## 7. Cleanup / Rollback

| Step | Action |
|------|--------|
| 1 | Delete test PurchaseOrder (soft delete via SoftDeletes trait, or force delete). Cascade: PurchaseOrderItems deleted via DB cascade or manual cleanup |
| 2 | Delete PurchaseOrderReceipt and PurchaseOrderReceiptItem records for the test PO |
| 3 | Delete InventoryMovement records where `reference_type = 'purchase_order_receipt'` and `reference_id` matches test receipt IDs |
| 4 | Restore InventoryLevel.quantity_on_hand to precondition values for Products A, B, C at the test location. Reset average_cost and last_movement_at |
| 5 | Restore Product.stock_quantity and Product.available_quantity to precondition values for Products A, B, C |
| 6 | Delete any LabelingWorkOrder records created by LabelingWorkflowService.createFromPoReceipt() for the test receipts |
| 7 | If eBay sandbox listing was affected by marketplace push: restore inventory quantity via eBay Inventory API |
| 8 | If Shopify dev store listing was affected by marketplace push: reset inventory level via Shopify Admin API |
| 9 | Clear EbaySyncLog and ShopifySyncLog entries created during test (filter by product_id and time window) |
| 10 | Clear failed_jobs table entries related to test products: `DELETE FROM failed_jobs WHERE payload LIKE '%{product_a_id}%' OR payload LIKE '%{product_b_id}%' OR payload LIKE '%{product_c_id}%'` |
| 11 | Delete test Supplier, InventoryLocation, and Products if created specifically for this test (not shared fixtures) |

---

## 8. Notes

- **PO number generation:** `PurchaseOrderService::generatePoNumber()` counts ALL POs in the organization (including soft-deleted via `withoutGlobalScopes()`) and formats as `PO-NNNNNN`. In a test environment with pre-existing POs, the number will not start at PO-000001. Query the count before test to predict the expected PO number.
- **Item total_cost auto-calculation:** The `PurchaseOrderItem` model has a `saving` boot hook that sets `total_cost = quantity_ordered * unit_cost`. Additionally, the `saved` and `deleted` hooks call `purchaseOrder->recalculateTotals()`. This means every item add/update/remove triggers a PO subtotal recalculation. In the 3-item scenario, `recalculateTotals()` fires 3 times during creation.
- **Weighted average cost:** The ReceivingService computes weighted average cost on receive: `new_avg = (old_qty * old_avg + new_qty * new_cost) / (old_qty + new_qty)`. If the InventoryLevel is newly created (old_qty=0), average_cost defaults to the unit_cost from the PO item.
- **Marketplace sync gap:** The ReceivingService updates `Product.stock_quantity` and `Product.available_quantity` via `increment()` but does NOT directly dispatch `PushInventoryToMarketplaces`. This differs from the order import flow (OrderSyncService) which explicitly dispatches the job after reservation. Marketplace quantities are reconciled either through: (a) a Product model observer if one exists, (b) the scheduled `ReconcileMarketplaceInventory` job, or (c) manual dispatch. Test should verify whether the sync happens automatically or requires scheduled reconciliation.
- **Partial receipt condition tracking:** The `condition` field on PurchaseOrderReceiptItem accepts 'good', 'damaged', or 'defective'. However, inventory is incremented regardless of condition. There is no quarantine or hold-back mechanism for damaged goods in the current implementation. All received quantities flow directly into quantity_on_hand and available_quantity.
- **No over-receive guard:** The ReceivingService does not enforce that `quantity_received` stays at or below `quantity_ordered`. The `remaining_quantity` accessor on PurchaseOrderItem uses `max(0, ordered - received)` to avoid negative display values, but the underlying data can have received > ordered. The UI should implement client-side warnings.
- **Transaction boundary:** The DB transaction in `ReceivingService::receive()` covers receipt creation, receipt item creation, PO item quantity_received increment, inventory level update, inventory movement creation, product stock increment, and PO status update. The `LabelingWorkflowService::createFromPoReceipt()` call is intentionally OUTSIDE the transaction (wrapped in try/catch) so labeling failures do not roll back the receipt.
- **UUID primary keys:** PurchaseOrder, PurchaseOrderItem, PurchaseOrderReceipt, PurchaseOrderReceiptItem, Product, InventoryLevel, InventoryMovement, and InventoryLocation all use UUID primary keys via the `HasUuid` trait. All foreign key references and route model binding use UUID strings, not integer IDs.
- **Multi-tenant isolation:** PurchaseOrder uses the `BelongsToOrganization` trait which applies a global scope. The PO index query and PO number generation both filter by organization_id. Receipts inherit organization context from the parent PO. Verify no cross-organization data leakage during the workflow.
- **Soft deletes:** PurchaseOrder uses the `SoftDeletes` trait. Deleted draft POs are soft-deleted (not permanently removed). The `generatePoNumber()` method uses `withoutGlobalScopes()` to count soft-deleted POs, ensuring PO numbers never collide even after deletion.
- **Cancellation constraints:** POs can only be cancelled when in `draft` or `submitted` status (`canBeCancelled()`). Once a PO reaches `partially_received` or `received`, it cannot be cancelled. This prevents cancelling POs that have already affected inventory.
