# TC-MOB-003: Inventory

| Field            | Value                                              |
|------------------|----------------------------------------------------|
| Document ID      | TC-MOB-003                                         |
| Domain           | Mobile App                                         |
| Related Test Plan| TP-MOB (mobile-app-test-plan.md)                   |
| Last Updated     | 2026-04-15                                         |

---

## TC-MOB-003-01: Product List With Search

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** User authenticated with inventory permissions. Products exist in organization.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Navigate to Products screen         | GET /api/products called with pagination             |
| 2    | Product list renders                | Products shown with name, SKU, image thumbnail       |
| 3    | Type search query in search bar     | List filters as user types (debounced API call)      |
| 4    | Clear search                        | Full product list restored                           |
| 5    | Scroll to bottom                    | Next page loaded (infinite scroll pagination)        |

---

## TC-MOB-003-02: Product Detail Page

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** Product list loaded.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Tap a product from list             | GET /api/products/{id} called                        |
| 2    | Detail screen renders               | Name, SKU, description, price, quantity displayed    |
| 3    | Images carousel loads               | Product images shown in swipeable carousel           |
| 4    | Verify inventory section            | Available quantity and location shown                |
| 5    | Tap back button                     | Returns to product list at previous scroll position  |

---

## TC-MOB-003-03: Create Product Form

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** User has product creation permissions.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Tap Add Product button              | Create product form opens                            |
| 2    | Fill required fields (name, SKU)    | Fields accept input                                  |
| 3    | Select category from dropdown       | Category picker shows tree structure                 |
| 4    | Enter price and quantity            | Numeric fields accept valid input                    |
| 5    | Tap Save                           | POST /api/products called                            |
| 6    | Server responds 201                 | Success message, navigates to product detail         |
| 7    | Submit with missing required fields | Validation errors shown inline                       |

---

## TC-MOB-003-04: Barcode Lookup Finds Product

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | No                        |

**Preconditions:** Product exists with matching UPC/barcode. Camera permissions granted.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Tap barcode scan icon               | Camera scanner opens                                 |
| 2    | Scan product barcode                | GET /api/products/barcode-lookup?code={barcode}      |
| 3    | Product found                       | Product detail screen opens for matched product      |
| 4    | Verify product data                 | Correct product displayed with all fields            |

---

## TC-MOB-003-05: Barcode Lookup No-Match Creates Intake Item

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | No                        |

**Preconditions:** No product matches the barcode. Camera permissions granted.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Scan barcode with no matching product | API returns 404 or empty result                    |
| 2    | App shows "Product not found" prompt| Option to add to intake queue displayed              |
| 3    | Tap "Add to Intake Queue"           | POST to intake queue endpoint with barcode data      |
| 4    | Server responds 201                 | Success message, item queued for processing          |
| 5    | Verify queue                        | Item appears in intake queue list                    |

---

## TC-MOB-003-06: Categories Tree Display

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Widget                    |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** Categories with parent-child hierarchy exist.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Navigate to Categories screen       | GET /api/categories called                           |
| 2    | Tree renders                        | Top-level categories displayed                       |
| 3    | Tap a parent category               | Child categories expand below                        |
| 4    | Tap a child category                | Products filtered to that category                   |
| 5    | Collapse parent                     | Children hidden                                      |

---

## TC-MOB-003-07: Locations List

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** Inventory locations configured for the organization.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Navigate to Locations screen        | GET /api/locations called                            |
| 2    | List renders                        | Locations shown with name and product count          |
| 3    | Tap a location                      | Location detail with inventory levels displayed      |
| 4    | Verify inventory items              | Products at this location listed with quantities     |

---

## TC-MOB-003-08: Movement History

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P2                        |
| Type          | Integration               |
| Platform      | iOS, Android              |
| Automated     | Yes                       |

**Preconditions:** Inventory movements exist (adjustments, transfers, sales).

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | Navigate to Movement History        | GET /api/inventory/movements called                  |
| 2    | List renders                        | Movements shown with type, product, quantity, date   |
| 3    | Filter by movement type             | Only selected type (e.g., adjustment) shown          |
| 4    | Tap a movement entry                | Detail view with full movement information           |
| 5    | Pagination                          | Scrolling loads additional pages                     |
