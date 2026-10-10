# Procurement Test Plan

## Document ID: FLIPSTACK-TP-PRC-001

**Version:** 1.0
**Last Updated:** 2026-04-15
**Status:** Approved

---

## 1. Overview

### 1.1 Scope

Purchase order lifecycle: create draft, add items, submit, receive goods, track receipts. Links to inventory (stock movements on receive) and batch tracking.

### 1.2 Related Documentation

| Resource | Path |
|----------|------|
| Existing tests | `tests/Feature/Procurement/PurchaseOrderTest.php` |
| Test cases | `docs/testing/cases/procurement/` |

---

## 2. Features Under Test

| # | Feature | Controller | Route Prefix | Priority |
|---|---------|------------|-------------|----------|
| 1 | PO listing & filtering | PurchaseOrderController@index | `procurement.purchase-orders` | P1 |
| 2 | Create draft PO with items | PurchaseOrderController@store | `procurement.purchase-orders` | P0 |
| 3 | Edit/update PO | PurchaseOrderController@update | `procurement.purchase-orders` | P1 |
| 4 | Submit PO (draft -> submitted) | PurchaseOrderController@submit | `procurement.purchase-orders` | P0 |
| 5 | Cancel PO | PurchaseOrderController@cancel | `procurement.purchase-orders` | P1 |
| 6 | Receive goods | PurchaseOrderController@processReceive | `procurement.purchase-orders` | P0 |
| 7 | Partial receiving | Multiple processReceive calls | `procurement.purchase-orders` | P1 |
| 8 | PO auto-numbering | PurchaseOrderService@generatePoNumber | - | P1 |

---

## 3. Test Strategy

### 3.1 Feature Tests

| Workflow | Test File | Status |
|----------|-----------|--------|
| PO CRUD | `tests/Feature/Procurement/PurchaseOrderTest.php` | Exists |
| **Receiving workflow** | - | **Needed** |
| **Partial receiving** | - | **Needed** |
| **Inventory movement on receive** | - | **Needed** |

### 3.2 Models

| Model | Key Fields | Status Constants |
|-------|-----------|------------------|
| PurchaseOrder | po_number, status, supplier_id, location_id, total_amount | draft, submitted, partially_received, received, cancelled |
| PurchaseOrderItem | quantity_ordered, quantity_received, unit_cost | - |
| PurchaseOrderReceipt | received_by, received_at, location_id | - |
| PurchaseOrderReceiptItem | quantity_received, condition (good/damaged/missing_parts) | - |

---

## 4. Test Cases

| Test Case ID | Title | File |
|--------------|-------|------|
| TC-PRC-001 | Purchase Orders | `cases/procurement/TC-PRC-001-purchase-orders.md` |
| TC-PRC-002 | Receiving | `cases/procurement/TC-PRC-002-receiving.md` |

---

## 5. Coverage Goals

| Metric | Current | Target |
|--------|---------|--------|
| PO CRUD | Exists | Verify all status transitions |
| Receiving | Not tested | Full + partial receiving |
| Inventory impact | Not tested | Stock movement created on receive |
| PO number generation | Not tested | Uniqueness and format |
