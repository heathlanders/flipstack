# TC-MOB-002: Fulfillment

| Field            | Value                                              |
|------------------|----------------------------------------------------|
| Document ID      | TC-MOB-002                                         |
| Domain           | Mobile App                                         |
| Related Test Plan| TP-MOB (mobile-app-test-plan.md)                   |
| Last Updated     | 2026-04-15                                         |

---

## TC-MOB-002-01: Pick List Loads With Priority Sort

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** User has fulfillment permissions. Orders exist in READY_TO_PICK status.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Navigate to Pick tab                | GET /api/fulfillment/pick called                     |
| 2    | List renders                        | Orders displayed sorted by priority (urgent first)   |
| 3    | Verify order card content           | Order number, item count, priority badge visible     |
| 4    | Pull to refresh                     | List reloads with fresh data from API                |
| 5    | Empty state                         | "No orders to pick" message when list is empty       |

---

## TC-MOB-002-02: Start Pick Transitions Order

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** Pick list loaded with at least one order.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Tap an order from pick list         | Order detail screen opens with item list             |
| 2    | Tap Start Pick button               | POST /api/fulfillment/pick/{order}/start             |
| 3    | Server responds 200                 | Order status transitions to PICKING                  |
| 4    | UI updates                          | Items displayed with checkboxes, scan button visible |
| 5    | Navigate back                       | Order shows PICKING status in list                   |

---

## TC-MOB-002-03: Scan Barcode Verifies Item

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | No                        |

**Preconditions:** Pick started for an order. Camera permissions granted.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Tap Scan button on pick screen      | Camera scanner opens                                 |
| 2    | Scan barcode matching an order item | POST /api/fulfillment/pick/items/{item}/scan-verify  |
| 3    | Item matches                        | Success indicator, item marked as picked             |
| 4    | Scan barcode not in order           | Error indicator with "Item not in this order"        |
| 5    | All items scanned                   | Pick complete prompt shown                           |

---

## TC-MOB-002-04: Pack Queue Shows PICKED Orders

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** Orders exist in PICKED status.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Navigate to Pack tab                | GET /api/fulfillment/pack called                     |
| 2    | List renders                        | Only PICKED orders displayed                         |
| 3    | Tap an order                        | Pack detail screen with items and package options     |
| 4    | Verify item details                 | SKU, name, quantity, location shown per item         |

---

## TC-MOB-002-05: Ship Queue Shows Shipments

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** Shipments exist in PACKED status awaiting ship confirmation.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Navigate to Ship tab                | GET /api/fulfillment/ship called                     |
| 2    | List renders                        | Shipments with tracking info displayed               |
| 3    | Verify shipment card                | Order number, carrier, tracking number visible       |
| 4    | Filter by carrier                   | List filters to selected carrier only                |

---

## TC-MOB-002-06: Confirm Ship With Tracking

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** Shipment in PACKED status. Tracking number available.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Tap shipment from ship queue        | Shipment detail screen opens                         |
| 2    | Enter or scan tracking number       | Tracking field populated                             |
| 3    | Tap Confirm Shipment                | POST /api/fulfillment/ship/{shipment}/confirm        |
| 4    | Server responds 200                 | Shipment status changes to SHIPPED                   |
| 5    | Verify list update                  | Shipment removed from ship queue                     |

---

## TC-MOB-002-07: Return List With Filters

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** Return requests exist in various statuses.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Navigate to Returns tab             | GET /api/fulfillment/returns called                  |
| 2    | List renders                        | Returns displayed with status badges                 |
| 3    | Filter by status (Pending)          | Only pending returns shown                           |
| 4    | Filter by status (Inspected)        | Only inspected returns shown                         |
| 5    | Clear filters                       | All returns displayed                                |

---

## TC-MOB-002-08: Return Inspect With Condition

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** Return request in PENDING status.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Tap a pending return                | Return detail screen opens                           |
| 2    | Select condition (e.g., Like New)   | Condition dropdown updated                           |
| 3    | Add inspection notes                | Notes field accepts text                             |
| 4    | Tap Submit Inspection               | POST /api/fulfillment/returns/{id}/inspect           |
| 5    | Server responds 200                 | Return status changes to INSPECTED                   |
| 6    | Verify list update                  | Return shows updated status in list                  |
