# TC-INV-007: Product Deletion

| Field             | Value                                      |
|-------------------|--------------------------------------------|
| Document ID       | TC-INV-007                                 |
| Domain            | Inventory                                  |
| Related Test Plan | TP-INV-001 Product Management              |
| Last Updated      | 2026-04-15                                 |
| Controller        | ProductController                          |
| Service           | ProductDeletionService                     |

---

## TC-INV-007-01: Deletion Impact Analysis

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-INV-007-01                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with inventory management permissions.
- A product exists with associated eBay listings, Shopify listings, orders, and inventory batches.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | GET inventory.products.deletion-impact with product ID  | Impact analysis response is returned.                        |
| 2    | Verify eBay listings count                              | Response shows the number of eBay listings linked to this product. |
| 3    | Verify Shopify listings count                           | Response shows the number of Shopify listings linked to this product. |
| 4    | Verify orders count                                     | Response shows the number of orders containing this product. |
| 5    | Verify batches count                                    | Response shows the number of inventory batches referencing this product. |

**Test Data:**
- Product with 2 eBay listings, 1 Shopify listing, 3 orders, 1 inventory batch.

**Postconditions:**
- No data is modified. This is a read-only impact analysis.

**Notes:**
- The impact analysis helps users understand the consequences of deletion before proceeding.

---

## TC-INV-007-02: Soft Delete (Archive) Product

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-INV-007-02                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | Yes                                                          |
| Test File      | tests/Feature/ProductDeletionTest.php                        |

**Preconditions:**
- User is authenticated with inventory management permissions.
- A product exists with status=active.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | DELETE inventory.products.destroy with product ID       | Product is soft deleted (archived).                          |
| 2    | Verify response                                         | Redirect to product index with success message.              |
| 3    | Verify database: deleted_at                             | Product's deleted_at timestamp is set.                       |
| 4    | Verify database: status                                 | Product's status is changed to "archived".                   |
| 5    | Verify product hidden from index                        | GET inventory.products.index does not include the archived product. |

**Test Data:**
- Active product with UUID primary key.

**Postconditions:**
- Product is soft deleted with deleted_at set and status=archived.
- Product is excluded from standard index queries.

**Notes:**
- Soft delete preserves the record for potential restoration. The product is not permanently removed.

---

## TC-INV-007-03: Restore Archived Product

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-INV-007-03                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with inventory management permissions.
- A soft-deleted (archived) product exists with deleted_at set and status=archived.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | POST inventory.products.restore with product ID         | Product is restored from archived state.                     |
| 2    | Verify response                                         | Redirect to product detail with success message.             |
| 3    | Verify database: deleted_at                             | Product's deleted_at is cleared (set to null).               |
| 4    | Verify database: status                                 | Product's status is changed to "draft".                      |
| 5    | Verify product visible in index                         | GET inventory.products.index now includes the restored product. |

**Test Data:**
- Archived product (deleted_at is not null, status=archived).

**Postconditions:**
- Product is restored with deleted_at=null and status=draft.

**Notes:**
- Restored products return to "draft" status rather than their original status, requiring manual reactivation.

---

## TC-INV-007-04: Force Delete Product Permanently

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-INV-007-04                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with inventory management permissions.
- A product exists (may be soft-deleted or active) with associated inventory levels, movements, and marketplace listings.
- Product has no active orders.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | DELETE inventory.products.force-destroy with product ID | Product is permanently deleted.                              |
| 2    | Verify response                                         | Redirect to product index with success message.              |
| 3    | Verify product record removed                           | Product record no longer exists in the database (not even soft-deleted). |
| 4    | Verify inventory levels removed                         | InventoryLevel records for this product are deleted.         |
| 5    | Verify inventory movements removed                      | InventoryMovement records for this product are deleted.      |
| 6    | Verify marketplace listings removed                     | EbayListing and ShopifyListing records are deleted.          |

**Test Data:**
- Product with 2 inventory levels, 5 inventory movements, 1 eBay listing.

**Postconditions:**
- Product and all related records are permanently removed from the database.

**Notes:**
- Force delete is irreversible. This action should require elevated permissions and explicit confirmation.

---

## TC-INV-007-05: Cannot Force Delete Product with Active Orders

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-INV-007-05                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with inventory management permissions.
- A product exists with at least 1 active (non-cancelled, non-completed) order.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | DELETE inventory.products.force-destroy with product ID | Force delete is rejected.                                    |
| 2    | Verify response                                         | Error response indicating product has active orders.         |
| 3    | Verify product still exists                             | Product record remains in the database unchanged.            |
| 4    | Verify order still valid                                | Associated order records are unaffected.                     |

**Test Data:**
- Product linked to 1 order with status="pending" or "processing".

**Postconditions:**
- No data is modified. Product and orders remain intact.

**Notes:**
- Active orders include any order not in a terminal state (completed, cancelled). The deletion impact endpoint (TC-INV-007-01) should warn about this.

---

## TC-INV-007-06: Bulk Archive Multiple Products

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-INV-007-06                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with inventory management permissions.
- At least 3 active products exist.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | POST inventory.products.bulk-action with action=archive and product_ids array | Bulk archive operation executes.                             |
| 2    | Verify response                                         | Success response with count of archived products.            |
| 3    | Verify all selected products archived                   | Each selected product has deleted_at set and status=archived. |
| 4    | Verify non-selected products unaffected                 | Products not in the selection remain unchanged.              |

**Test Data:**
- product_ids: [product_1_uuid, product_2_uuid, product_3_uuid]
- action: "archive"

**Postconditions:**
- All selected products are soft deleted with status=archived.

**Notes:**
- Bulk archive follows the same soft delete logic as individual archive (TC-INV-007-02) applied to multiple products.
