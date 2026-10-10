# TC-API-004: Inventory Endpoints

| Field            | Value                                              |
|------------------|----------------------------------------------------|
| Document ID      | TC-API-004                                         |
| Domain           | API Endpoints                                      |
| Related Test Plan| TP-API (api-endpoints-test-plan.md)                |
| Last Updated     | 2026-04-15                                         |

---

## TC-API-004-01: GET /api/inventory/levels

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** User authenticated. Products with inventory levels exist.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | GET /api/inventory/levels           | 200 response with inventory level data               |
| 2    | Verify response structure           | Each entry has product_id, location, quantity         |
| 3    | Verify org scoping                  | Only levels for user's organization returned         |
| 4    | GET with location filter            | Only levels at specified location returned           |
| 5    | GET with low_stock=true             | Only products below reorder threshold returned       |

---

## TC-API-004-02: POST /api/inventory/adjust

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Product exists at a location with known quantity.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | POST with product_id, location_id, adjustment (+5) | 200 response                      |
| 2    | Verify quantity updated             | Inventory level increased by 5                       |
| 3    | Verify movement recorded            | InventoryMovement created with type "adjustment"     |
| 4    | POST negative adjustment            | Quantity decreased, movement recorded                |
| 5    | POST adjustment below zero          | 422 response (cannot go negative)                    |
| 6    | POST with reason field              | Reason stored in movement record                     |

---

## TC-API-004-03: POST /api/inventory/transfer

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Product exists at source location with sufficient quantity. Destination location exists.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | POST with product, from_location, to_location, qty | 200 response                      |
| 2    | Verify source decremented           | Source location quantity reduced                      |
| 3    | Verify destination incremented      | Destination location quantity increased              |
| 4    | Verify two movements created        | OUT movement at source, IN movement at destination   |
| 5    | Transfer more than available         | 422 response (insufficient stock)                   |
| 6    | Transfer to same location           | 422 response (same source and destination)           |

---

## TC-API-004-04: GET /api/inventory/movements

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Inventory movements exist from adjustments, transfers, and sales.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | GET /api/inventory/movements        | 200 response with paginated movements                |
| 2    | Verify movement fields              | type, product_id, quantity, location, timestamp      |
| 3    | GET with type=adjustment filter     | Only adjustment movements returned                   |
| 4    | GET with product_id filter          | Only movements for specified product                 |
| 5    | Verify chronological sort           | Newest movements first by default                    |
| 6    | Verify org scoping                  | Only movements from user's org returned              |

---

## TC-API-004-05: GET /api/inventory/low-stock Alerts

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Products exist with quantity below reorder threshold.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | GET /api/inventory/low-stock        | 200 response with low-stock products                 |
| 2    | Verify threshold comparison         | Only products below their reorder point included     |
| 3    | Verify fields                       | product_id, name, current_qty, reorder_point         |
| 4    | All products above threshold        | 200 response with empty array                        |
| 5    | Verify org scoping                  | Only current org products evaluated                  |

---

## TC-API-004-06: GET /api/categories Tree

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Categories with parent-child hierarchy exist.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | GET /api/categories                 | 200 response with category tree                      |
| 2    | Verify nested structure             | Children nested under parent categories              |
| 3    | Verify fields                       | id, name, parent_id, product_count per category      |
| 4    | Verify org scoping                  | Only categories from user's org returned             |
| 5    | Empty categories                    | Categories with zero products still included         |

---

## TC-API-004-07: GET /api/locations List

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Inventory locations configured for the organization.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | GET /api/locations                  | 200 response with location list                      |
| 2    | Verify fields                       | id, name, type, product_count per location           |
| 3    | Verify org scoping                  | Only locations from user's org returned              |
| 4    | No locations configured             | 200 response with empty array                        |
| 5    | Verify active status                | Only active locations returned by default            |

---

## TC-API-004-08: GET /api/inventory/valuation

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P2                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Products exist with cost and quantity data.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | GET /api/inventory/valuation        | 200 response with valuation summary                  |
| 2    | Verify total valuation              | Sum of (quantity x cost) across all products         |
| 3    | Verify per-product breakdown        | Each product shows quantity, unit cost, total value   |
| 4    | Verify org scoping                  | Only current org inventory valued                    |
| 5    | GET with location filter            | Valuation scoped to specific location                |
