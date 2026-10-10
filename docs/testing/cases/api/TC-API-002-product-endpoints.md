# TC-API-002: Product Endpoints

| Field            | Value                                              |
|------------------|----------------------------------------------------|
| Document ID      | TC-API-002                                         |
| Domain           | API Endpoints                                      |
| Related Test Plan| TP-API (api-endpoints-test-plan.md)                |
| Last Updated     | 2026-04-15                                         |

---

## TC-API-002-01: GET /api/products Paginated List

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** User authenticated. Multiple products exist in organization.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | GET /api/products                   | 200 response with paginated product list             |
| 2    | Verify pagination structure         | Response contains `data`, `meta` with page info      |
| 3    | GET /api/products?page=2            | Second page of results returned                      |
| 4    | GET /api/products?search=keyword    | Filtered results matching search term                |
| 5    | Verify product fields               | Each product has id, name, sku, price, quantity       |
| 6    | Only org products returned          | No products from other organizations in results      |

---

## TC-API-002-02: GET /api/products/{id} Detail

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Product exists with known UUID.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | GET /api/products/{valid-uuid}      | 200 response with full product data                  |
| 2    | Verify all fields present           | name, sku, description, price, images, inventory     |
| 3    | Verify relationships loaded         | Category, brand, identifiers included                |
| 4    | GET /api/products/{invalid-uuid}    | 404 response                                        |
| 5    | GET product from another org        | 404 response (not 403, to avoid ID enumeration)      |

---

## TC-API-002-03: POST /api/products Create

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** User authenticated with product creation permissions.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | POST /api/products with valid data  | 201 response with created product                    |
| 2    | Verify UUID assigned                | Product has UUID primary key                         |
| 3    | Verify organization scoped          | Product belongs to user's organization               |
| 4    | POST with missing required fields   | 422 response with validation errors                  |
| 5    | POST with duplicate SKU             | 422 response with SKU uniqueness error               |
| 6    | Verify product in database          | Product retrievable via GET                          |

---

## TC-API-002-04: PUT /api/products/{id} Update

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Product exists belonging to user's organization.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | PUT /api/products/{id} with updated name | 200 response with updated product              |
| 2    | Verify field updated                | Name reflects new value                              |
| 3    | Verify unchanged fields preserved   | Other fields remain unchanged                        |
| 4    | PUT with invalid data               | 422 response with validation errors                  |
| 5    | PUT product from another org        | 404 response                                        |

---

## TC-API-002-05: DELETE /api/products/{id} Archive

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Product exists with no active listings or pending orders.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | DELETE /api/products/{id}           | 200 response confirming archive                      |
| 2    | Verify soft delete                  | Product has deleted_at timestamp set                  |
| 3    | GET /api/products/{id}              | 404 response (excluded from normal queries)          |
| 4    | DELETE product with active listings | 409 or 422 response with conflict explanation        |
| 5    | DELETE product from another org     | 404 response                                        |

---

## TC-API-002-06: GET /api/products/barcode-lookup

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Product exists with UPC identifier.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | GET /api/products/barcode-lookup?code={upc} | 200 response with matching product            |
| 2    | Verify product data returned        | Full product object with inventory info              |
| 3    | Lookup with unknown barcode         | 404 response with no match indication                |
| 4    | Lookup scoped to organization       | Only matches products in user's org                  |
| 5    | Lookup with EAN format              | Matches product with EAN identifier                  |

---

## TC-API-002-07: GET /api/products/{id}/deletion-impact

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Product exists with various relationships (listings, orders, bundles).

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | GET /api/products/{id}/deletion-impact | 200 response with impact analysis                 |
| 2    | Verify active listings count        | Number of marketplace listings that would be affected|
| 3    | Verify pending orders count         | Number of unfulfilled orders containing this product |
| 4    | Verify bundle membership            | Bundles that include this product listed             |
| 5    | Product with no dependencies        | Impact report shows zero counts, safe to delete      |

---

## TC-API-002-08: Organization Scoping Verified

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** Two organizations exist with separate products.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Authenticate as Org A user          | Token scoped to Org A                                |
| 2    | GET /api/products                   | Only Org A products returned                         |
| 3    | GET /api/products/{org-b-product-id}| 404 response                                        |
| 4    | PUT /api/products/{org-b-product-id}| 404 response                                        |
| 5    | Authenticate as Org B user          | GET /api/products returns only Org B products        |
| 6    | Verify count isolation              | Product counts differ per organization               |
