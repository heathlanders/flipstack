---
name: procurement
description: Purchase order lifecycle, receiving workflow, batch/lot tracking, COGS calculation, and inventory movement integration
user-invocable: false
---

# Procurement System

## Key Files

| Area | File | Purpose |
|------|------|---------|
| **Models** | `app/Models/Procurement/PurchaseOrder.php` | PO with 5 statuses, UUID PK, soft deletes, auto-totals |
| | `app/Models/Procurement/PurchaseOrderItem.php` | Line items with ordered/received quantities, auto total_cost on save |
| | `app/Models/Procurement/PurchaseOrderReceipt.php` | Receipt record per receiving session |
| | `app/Models/Procurement/PurchaseOrderReceiptItem.php` | Per-item receipt with condition tracking (good/damaged/defective) |
| | `app/Models/Inventory/InventoryBatch.php` | Batch/lot with supplier, PO link, acquisition cost, open/closed status |
| | `app/Models/Inventory/InventoryBatchItem.php` | Product-level batch item with unit_cost and total_cost |
| **Services** | `app/Services/Procurement/PurchaseOrderService.php` | PO CRUD, item sync, submit, cancel, stats, supplier product lookup |
| | `app/Services/Procurement/ReceivingService.php` | Receive items, update inventory levels, weighted average cost, create movements |
| **Controllers** | `app/Http/Controllers/Procurement/PurchaseOrderController.php` | Full PO web UI: CRUD, submit, cancel, receive, supplier products AJAX |
| **Routes** | `routes/procurement.php` | All procurement routes under `procurement.` prefix |

## Purchase Order Status State Machine

```
DRAFT -----> SUBMITTED -----> PARTIALLY_RECEIVED -----> RECEIVED
  |              |
  v              v
CANCELLED    CANCELLED
```

### Status Constants
| Status | Value | Guard Method | Trigger |
|--------|-------|--------------|---------|
| `STATUS_DRAFT` | `draft` | -- | Initial state on creation |
| `STATUS_SUBMITTED` | `submitted` | `canBeSubmitted()` | `PurchaseOrderService::submit()` |
| `STATUS_PARTIALLY_RECEIVED` | `partially_received` | `canReceiveItems()` | `ReceivingService::updatePoStatus()` when some items received |
| `STATUS_RECEIVED` | `received` | -- | `ReceivingService::updatePoStatus()` when `isFullyReceived()` is true |
| `STATUS_CANCELLED` | `cancelled` | `canBeCancelled()` | `PurchaseOrderService::cancel()` |

### Guard Methods
- `canBeEdited()`: status is `draft`
- `canBeSubmitted()`: status is `draft` AND has at least one item
- `canBeCancelled()`: status in `[draft, submitted]`
- `canReceiveItems()`: status in `[submitted, partially_received]`
- `isFullyReceived()`: all items have `quantity_received >= quantity_ordered`

## PO Number Format

`PO-{000001}` -- sequential per organization, zero-padded to 6 digits.

## PO Totals Calculation

`PurchaseOrderItem` auto-calculates on save:
```
item.total_cost = quantity_ordered * unit_cost
```

After any item save/delete, `PurchaseOrder::recalculateTotals()` runs:
```
subtotal = SUM(items.total_cost)
total_amount = subtotal + tax_amount + shipping_cost
```

## Receiving Workflow

1. User navigates to receive page (`GET /procurement/purchase-orders/{id}/receive`)
2. Selects items and quantities to receive, with condition per item
3. `ReceivingService::receive()` runs in a DB transaction:
   a. Creates `PurchaseOrderReceipt` record
   b. For each item:
      - Creates `PurchaseOrderReceiptItem`
      - Increments `PurchaseOrderItem.quantity_received`
      - If product is linked and location set, calls `updateInventory()`
   c. Updates PO status (`partially_received` or `received`)

### Inventory Update on Receive

`ReceivingService::updateInventory()` performs four operations per received item:

1. **InventoryLevel**: `firstOrCreate` for product+location, then increment `quantity_on_hand`
2. **Weighted Average Cost**: `new_avg = (old_qty * old_avg + new_qty * unit_cost) / total_qty`
3. **Product stock**: increments `stock_quantity` and `available_quantity` on Product
4. **InventoryMovement**: creates movement with `type: 'purchase'`, `reference_type: 'purchase_order_receipt'`

## Batch/Lot Tracking

`InventoryBatch` links to a supplier and optionally a PurchaseOrder. Each batch has:
- `batch_number` -- unique identifier
- `acquisition_cost` / `item_count` / `cost_per_item` -- COGS data
- `status` -- `open` (receiving in progress) or `closed` (complete)

`InventoryBatchItem` links a product to a batch with quantity and unit/total cost, providing per-product COGS within the batch.

## Auto-Population from SupplierProduct

When adding items to a PO via `PurchaseOrderService::addItem()`, if `supplier_product_id` is provided, the service auto-populates:
- `unit_cost` from `SupplierProduct.cost_price` (if not already set)
- `sku` from `SupplierProduct.supplier_sku`
- `description` from `SupplierProduct.supplier_name`

## Routes

All routes require authentication and use prefix `procurement/purchase-orders`:

| Method | URI | Action |
|--------|-----|--------|
| GET | `/procurement/purchase-orders` | Index with search, status filter, supplier filter |
| GET | `/procurement/purchase-orders/create` | Create form |
| POST | `/procurement/purchase-orders` | Store new PO |
| GET | `/procurement/purchase-orders/{id}` | Show with items, receipts, supplier |
| GET | `/procurement/purchase-orders/{id}/edit` | Edit form (draft only) |
| PUT | `/procurement/purchase-orders/{id}` | Update PO |
| DELETE | `/procurement/purchase-orders/{id}` | Delete (draft only) |
| POST | `/procurement/purchase-orders/{id}/submit` | Submit PO |
| POST | `/procurement/purchase-orders/{id}/cancel` | Cancel PO |
| GET | `/procurement/purchase-orders/{id}/receive` | Receiving form |
| POST | `/procurement/purchase-orders/{id}/receive` | Process receive |
| GET | `/procurement/purchase-orders/ajax/supplier-products` | Supplier products JSON |

## Debugging Commands

```php
// PO status distribution
DB::table('purchase_orders')->where('organization_id', $orgId)
  ->selectRaw("status, count(*) as count")->groupBy('status')->get();

// Overdue POs
PurchaseOrder::where('organization_id', $orgId)->overdue()->count();

// Open PO value
PurchaseOrder::where('organization_id', $orgId)->open()->sum('total_amount');

// Items pending receipt on a PO
PurchaseOrderItem::where('purchase_order_id', $poId)
  ->whereRaw('quantity_received < quantity_ordered')->get(['id', 'sku', 'quantity_ordered', 'quantity_received']);

// Recent inventory movements from PO receipts
InventoryMovement::where('organization_id', $orgId)
  ->where('type', 'purchase')
  ->orderByDesc('created_at')
  ->limit(20)->get();
```

## Audit Logging

| Action | Category | Severity | Metadata keys |
|--------|----------|----------|---------------|
| `po.created` | procurement | info | supplier_id, total_amount, item_count |
| `po.submitted` | procurement | info | supplier_id |
| `po.approved` | procurement | info | approver_id |
| `po.received` | procurement | info | receipt_id, items_received |
| `po.partially_received` | procurement | info | receipt_id, items_received, items_remaining |
| `po.closed` | procurement | info | reason |
| `po.cancelled` | procurement | warning | reason |
| `po.receipt.recorded` | procurement | info | po_id, items, batch_id |

Trait: models in this feature use `LogsAudit` (`app/Traits/LogsAudit.php`) for auto CRUD diff. Domain lifecycle events emitted from `app/Services/Procurement/{PurchaseOrderService,ReceivingService}`; `PurchaseOrder`, `PurchaseOrderItem`, `PurchaseOrderReceipt`, `PurchaseOrderReceiptItem` models (`LogsAudit`) via `UserActivityLog::log(action, desc, $user, $metadata, $severity, $category)`.

KPI consumer: `UserProductivityDaily.pos_created_count`, `pos_approved_count`, `pos_received_count` (from `po.created`, `po.approved`, `po.received`).

Spec: `docs/AUDIT_SYSTEM.md`. Enforced rule: `.claude/rules/audit-logging.md`. Tests: `use Tests\Support\AssertsAudits;` then `$this->assertAudited('action', $user, [...])`.
