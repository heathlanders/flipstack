# Purchase Order System

How inventory procurement flows from purchase order creation through receiving and into warehouse stock.

## Overview

```
DRAFT -----------> Create PO, add line items, set supplier/location
   |
   v
SUBMITTED -------> PO sent to supplier, awaiting delivery
   |
   v
PARTIALLY -------> Some items received, others still outstanding
RECEIVED           Inventory incremented for received items
   |
   v
RECEIVED --------> All items received, PO closed
                    Full inventory and cost updates applied

(at any point before partial receipt)
CANCELLED -------> PO voided with reason, no inventory impact
```

---

## Architecture

### Key Files

| Layer | File |
|-------|------|
| Model | `app/Models/Procurement/PurchaseOrder.php` |
| Model | `app/Models/Procurement/PurchaseOrderItem.php` |
| Model | `app/Models/Procurement/PurchaseOrderReceipt.php` |
| Model | `app/Models/Procurement/PurchaseOrderReceiptItem.php` |
| Model | `app/Models/Inventory/InventoryBatch.php` |
| Service | `app/Services/Procurement/PurchaseOrderService.php` |
| Service | `app/Services/Procurement/ReceivingService.php` |
| Controller | `app/Http/Controllers/Procurement/` |
| Routes | `routes/procurement.php` |
| Pages | `resources/js/Pages/Procurement/` |

### Service Responsibilities

**PurchaseOrderService** handles the PO lifecycle:
- PO number generation (format: `PO-000001`, sequential per organization)
- CRUD operations on draft POs
- Line item management (add, update, sync)
- Auto-populating item details from `SupplierProduct` records (cost, SKU, description)
- Status transitions (submit, cancel)
- Organization-level stats (counts by status, open value, overdue count)

**ReceivingService** handles the 4-step receiving workflow:
- Creates receipt records with per-item condition tracking
- Increments `quantity_received` on PO items
- Updates inventory levels (on-hand quantity and weighted average cost)
- Creates inventory movement records for audit trail
- Auto-transitions PO status based on fulfillment completeness

---

## Data Model

### PurchaseOrder

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID | Primary key |
| `organization_id` | UUID | Owning organization |
| `supplier_id` | UUID | Supplier being ordered from |
| `location_id` | UUID | Destination warehouse location |
| `po_number` | string | Auto-generated (PO-NNNNNN) |
| `status` | string | draft, submitted, partially_received, received, cancelled |
| `order_date` | date | When the PO was placed |
| `expected_delivery_date` | date | When delivery is expected (used for overdue detection) |
| `subtotal` | decimal | Sum of all line item costs |
| `tax_amount` | decimal | Tax on the order |
| `shipping_cost` | decimal | Shipping charged by supplier |
| `total_amount` | decimal | subtotal + tax + shipping |
| `created_by` | int | User who created the PO |
| `approved_by` | int | User who approved/submitted |

### PurchaseOrderItem

| Field | Type | Description |
|-------|------|-------------|
| `purchase_order_id` | UUID | Parent PO |
| `product_id` | UUID | Linked catalog product (optional) |
| `supplier_product_id` | UUID | Supplier's product record (optional) |
| `description` | string | Item description |
| `sku` | string | SKU reference |
| `quantity_ordered` | int | How many units ordered |
| `quantity_received` | int | How many units received so far |
| `unit_cost` | decimal | Cost per unit |
| `total_cost` | decimal | Computed: quantity_ordered * unit_cost |

### InventoryBatch

Batches group products acquired together for cost tracking purposes.

| Field | Type | Description |
|-------|------|-------------|
| `batch_number` | string | Unique batch identifier |
| `supplier_id` | UUID | Source supplier |
| `purchase_order_id` | UUID | Linked PO (optional) |
| `acquisition_date` | date | When goods were acquired |
| `acquisition_cost` | decimal | Total cost of the batch |
| `item_count` | int | Number of items in batch |
| `cost_per_item` | decimal | Average cost per item |
| `status` | string | open or closed |

---

## Key Flows

### 1. PO Creation

1. User creates a PO via `PurchaseOrderService::create()` inside a DB transaction
2. A PO number is auto-generated (`PO-NNNNNN`, sequential per organization)
3. If `supplier_product_id` is provided on line items, cost/SKU/description are auto-populated from the `SupplierProduct` record
4. PO starts in `draft` status
5. Totals are calculated: `subtotal = SUM(item.total_cost)`, `total = subtotal + tax + shipping`

### 2. Submission

1. PO must be in `draft` status and have at least one line item
2. `PurchaseOrderService::submit()` transitions status to `submitted`
3. Timestamps `submitted_at` and optionally `approved_by`/`approved_at` are recorded

### 3. Receiving (4-Step Workflow)

The receiving process is handled by `ReceivingService::receive()` in a single DB transaction:

**Step 1: Create Receipt**
- A `PurchaseOrderReceipt` record is created with the receiver's user ID, location, and timestamp

**Step 2: Receive Each Item**
- For each item in the receipt, a `PurchaseOrderReceiptItem` is created
- Condition is recorded (good, damaged, etc.) with optional notes
- The PO item's `quantity_received` is incremented

**Step 3: Update Inventory**
- If the PO item is linked to a product and has a location:
  - `InventoryLevel` is found or created for the product/location pair
  - `quantity_on_hand` is incremented
  - Weighted average cost is recalculated: `new_avg = (old_qty * old_avg + new_qty * unit_cost) / total_qty`
  - Product's `stock_quantity` and `available_quantity` are incremented
  - An `InventoryMovement` record is created (type: `purchase`, reference: receipt ID)

**Step 4: Update PO Status**
- If all items are fully received (`quantity_received >= quantity_ordered` for every item), status transitions to `received`
- If some items are received but not all, status transitions to `partially_received`

### 4. Cancellation

- Only POs in `draft` or `submitted` status can be cancelled
- Cancel reason is recorded along with `cancelled_at` timestamp
- No inventory impact (nothing was received)

### 5. Overdue Detection

POs are considered overdue when:
- Status is `submitted` or `partially_received`
- `expected_delivery_date` is set and is in the past

The `scopeOverdue` query scope provides this filter.

---

## COGS Calculation

Cost of goods is tracked through a **weighted average cost** method:

```
new_average_cost = (previous_qty * previous_avg_cost + received_qty * unit_cost) / (previous_qty + received_qty)
```

This calculation happens in `ReceivingService::updateInventory()` each time items are received. The weighted average is stored on `InventoryLevel.average_cost` and used by the profit/loss system to determine COGS per unit sold.

---

## Integration Points

| System | Integration |
|--------|-------------|
| **Inventory** | Receiving increments `InventoryLevel.quantity_on_hand` and `Product.stock_quantity` / `Product.available_quantity` |
| **Inventory Movements** | Every receipt creates an `InventoryMovement` with type `purchase` for full audit trail |
| **Supplier Products** | PO line items can auto-populate from `SupplierProduct` records (cost, SKU, description) |
| **Batch Tracking** | `InventoryBatch` records link to POs via `purchase_order_id` for acquisition cost grouping |
| **Profit/Loss** | Weighted average cost feeds into `OrderFinancialCalculator` for COGS calculation on sales |
