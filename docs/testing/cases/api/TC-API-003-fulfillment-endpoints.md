# TC-API-003: Fulfillment Endpoints

| Field            | Value                                              |
|------------------|----------------------------------------------------|
| Document ID      | TC-API-003                                         |
| Domain           | API Endpoints                                      |
| Related Test Plan| TP-API (api-endpoints-test-plan.md)                |
| Last Updated     | 2026-04-15                                         |

---

## TC-API-003-01: GET /api/fulfillment/pick List

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** User authenticated with fulfillment permissions. Orders in READY_TO_PICK status.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | GET /api/fulfillment/pick           | 200 response with pickable orders                    |
| 2    | Verify response structure           | Array of orders with items, priority, status         |
| 3    | Verify sort order                   | Orders sorted by priority (urgent first)             |
| 4    | Verify org scoping                  | Only orders from user's organization returned        |
| 5    | No pickable orders                  | 200 response with empty array                        |

---

## TC-API-003-02: POST /api/fulfillment/pick/{order}/start

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Order exists in READY_TO_PICK status.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | POST /api/fulfillment/pick/{order}/start | 200 response                                   |
| 2    | Verify status transition            | Order status changed to PICKING                      |
| 3    | Verify picker assigned              | Current user recorded as picker                      |
| 4    | POST on already-picking order       | 409 or 422 response (already in progress)            |
| 5    | POST on order from another org      | 404 response                                        |

---

## TC-API-003-03: POST /api/fulfillment/pick/items/{item}/scan-verify

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Order in PICKING status. Item belongs to the order.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | POST with matching barcode          | 200 response with verification success               |
| 2    | Verify item marked as picked        | Item status updated in response                      |
| 3    | POST with non-matching barcode      | 422 response with mismatch error                     |
| 4    | POST for already-picked item        | 422 response (already verified)                      |
| 5    | All items verified                  | Response indicates pick complete, order can advance   |

---

## TC-API-003-04: GET /api/fulfillment/pack List

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Orders exist in PICKED status.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | GET /api/fulfillment/pack           | 200 response with packable orders                    |
| 2    | Verify only PICKED orders           | No PICKING or READY_TO_PICK orders in response       |
| 3    | Verify item details                 | Each order includes items with SKU, name, quantity   |
| 4    | Verify org scoping                  | Only current org orders returned                     |

---

## TC-API-003-05: POST /api/fulfillment/pack/{order}/purchase-label

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Order in PICKED status. Shipping carrier configured.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | POST with package dimensions and weight | 200 response with label data                    |
| 2    | Verify shipment created             | Shipment record created with tracking number         |
| 3    | Verify label URL                    | Response contains downloadable label URL             |
| 4    | POST with invalid dimensions        | 422 response with validation errors                  |
| 5    | POST for order from another org     | 404 response                                        |

---

## TC-API-003-06: POST /api/fulfillment/ship/{shipment}/confirm

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Shipment exists in PACKED status with tracking number.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | POST /api/fulfillment/ship/{shipment}/confirm | 200 response                            |
| 2    | Verify status transition            | Shipment status changed to SHIPPED                   |
| 3    | Verify tracking upload triggered    | Tracking uploaded to marketplace (job dispatched)    |
| 4    | POST on already-shipped shipment    | 422 response (already confirmed)                     |
| 5    | POST for shipment from another org  | 404 response                                        |

---

## TC-API-003-07: GET /api/fulfillment/returns List

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Return requests exist in various statuses.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | GET /api/fulfillment/returns        | 200 response with return requests                    |
| 2    | Verify response fields              | Each return has id, order, reason, status, created_at|
| 3    | GET with status filter              | Only returns with specified status returned          |
| 4    | Verify pagination                   | Response supports page-based pagination              |
| 5    | Verify org scoping                  | Only current org returns listed                      |

---

## TC-API-003-08: POST /api/fulfillment/returns/{id}/inspect

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Return request in PENDING status.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | POST with condition and notes       | 200 response                                        |
| 2    | Verify status transition            | Return status changed to INSPECTED                   |
| 3    | Verify condition recorded           | Condition value persisted (e.g., "like_new")         |
| 4    | Verify notes saved                  | Inspection notes stored with return                  |
| 5    | POST on non-pending return          | 422 response (invalid status transition)             |
| 6    | POST for return from another org    | 404 response                                        |
