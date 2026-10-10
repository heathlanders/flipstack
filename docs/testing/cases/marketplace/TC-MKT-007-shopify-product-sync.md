# TC-MKT-007: Shopify Product Sync

| Field              | Value                                                                 |
|--------------------|-----------------------------------------------------------------------|
| **Document ID**    | TC-MKT-007                                                            |
| **Domain**         | Marketplace Integration - Shopify Product Sync                        |
| **Related Test Plan** | [Feature Testing Plan - Phase 1](../../project_feature_testing_plan.md) |
| **Last Updated**   | 2026-04-15                                                            |

---

## TC-MKT-007-01: Sync Single Product Creates ShopifyListing and Shopify Product

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-007-01                                                      |
| **Priority**   | Critical                                                           |
| **Type**       | Integration                                                        |
| **Platform**   | Shopify                                                            |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Shopify/ShopifyProductSyncTest.php`                 |

### Preconditions
- Organization has an active Shopify IntegrationCredential with valid OAuthToken.
- Product exists with title, description, price, quantity, SKU, condition, and at least one image.
- No existing ShopifyListing for this product.
- Shopify Admin API endpoints are mocked for product creation and inventory level set.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Dispatch `SyncProductToShopify` job for the product. | Job is queued and begins processing. |
| 2 | Job creates a ShopifyListing record with status `pending`. | ShopifyListing exists with `status=pending`, linked to the product and organization. |
| 3 | Job calls Shopify Admin API `POST /admin/api/2024-01/products.json` with product data. | Shopify returns HTTP 201 with the created product including `id`, `variants[0].id`, and `variants[0].inventory_item_id`. |
| 4 | Verify ShopifyListing is updated with Shopify IDs. | `shopify_product_id`, `shopify_variant_id`, `shopify_inventory_item_id` all populated. Status transitions to `active`. |
| 5 | Job calls Shopify Inventory API to set the inventory level at the primary location. | `POST /admin/api/2024-01/inventory_levels/set.json` called with `inventory_item_id`, `location_id`, and `available` quantity. |
| 6 | Verify final ShopifyListing state. | `status=active`, all Shopify IDs set, `last_synced_at` is current, product data hash stored. |

### Test Data
- Product: `{title: "Wireless Headphones", price: 59.99, quantity: 15, sku: "WH-100", condition: "new"}`.
- Mock Shopify response: `{product: {id: 7890123456, title: "Wireless Headphones", variants: [{id: 44556677, inventory_item_id: 99887766, sku: "WH-100"}]}}`.
- Primary location ID from `credential.config.shopify_location_id`.

### Postconditions
- ShopifyListing with `status=active` and valid Shopify IDs.
- Inventory level set at the primary Shopify location.

### Notes
- The primary location ID is resolved from `credential.config.shopify_location_id` or fetched and cached from the Shopify Locations API if not configured.

---

## TC-MKT-007-02: Bulk Sync Dispatches BulkSyncToShopify

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-007-02                                                      |
| **Priority**   | High                                                               |
| **Type**       | Feature                                                            |
| **Platform**   | Shopify                                                            |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Shopify/ShopifyProductSyncTest.php`                 |

### Preconditions
- Organization has an active Shopify IntegrationCredential.
- 8 products are selected for bulk sync to Shopify.
- None have existing ShopifyListing records.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Select 8 products and choose "Sync to Shopify" from bulk actions. | Application initiates bulk sync. |
| 2 | Verify `BulkSyncToShopify` job or individual `SyncProductToShopify` jobs are dispatched. | 8 sync jobs queued. |
| 3 | Verify a BulkOperationJob record is created for tracking. | Record exists with `type=shopify_sync`, `total_items=8`, `status=processing`. |
| 4 | Verify Shopify API rate limiting is respected. | Jobs are dispatched with throttling to stay within Shopify's rate limit (2 requests/second for standard plans, bucket-based). |
| 5 | Verify the UI shows a progress indicator. | Bulk operation progress displayed with completed/total counts. |

### Test Data
- 8 products with valid data for Shopify listing.

### Postconditions
- 8 jobs queued for processing.
- BulkOperationJob tracking record exists.

### Notes
- Shopify uses a leaky bucket rate limiting algorithm. The application should track the `X-Shopify-Shop-Api-Call-Limit` header and pause when approaching the limit.

---

## TC-MKT-007-03: Product Data Hash Prevents Unnecessary Update

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-007-03                                                      |
| **Priority**   | High                                                               |
| **Type**       | Feature                                                            |
| **Platform**   | Shopify                                                            |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Shopify/ShopifyProductSyncTest.php`                 |

### Preconditions
- Product has an active ShopifyListing with a current data hash.
- No changes have been made to the product since last sync.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Dispatch `SyncProductToShopify` job for the product. | Job begins processing. |
| 2 | Job computes the current product data hash. | Hash matches the stored hash on the ShopifyListing. |
| 3 | Job skips all Shopify API calls. | No HTTP requests sent to Shopify. |
| 4 | Verify ShopifyListing is unchanged. | `last_synced_at` is NOT updated. `status` remains `active`. |
| 5 | Update the product description and dispatch the job again. | Hash mismatch detected; sync proceeds with a PUT to Shopify's product endpoint. |
| 6 | Verify the hash is updated after the sync. | New hash stored on the ShopifyListing. |

### Test Data
- Product with unchanged data since last sync.
- Hash includes: title, description (body_html), price, SKU, images, product_type, tags.

### Postconditions
- No unnecessary API calls when data is unchanged.
- Hash updated after successful sync with changed data.

### Notes
- The hash must include the same fields sent to Shopify. Fields like `available_quantity` are handled separately via inventory sync and should NOT be part of the product data hash.

---

## TC-MKT-007-04: Force Update Bypasses Hash Check

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-007-04                                                      |
| **Priority**   | Medium                                                             |
| **Type**       | Feature                                                            |
| **Platform**   | Shopify                                                            |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Shopify/ShopifyProductSyncTest.php`                 |

### Preconditions
- Product has an active ShopifyListing with a current data hash matching the product state.
- No changes made to the product.
- User wants to force a resync (e.g., to fix a suspected Shopify-side data issue).

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click "Force Sync" on the product detail page Shopify section. | Sync job dispatched with a `force=true` flag. |
| 2 | Job receives the force flag. | Hash comparison is skipped entirely. |
| 3 | Job sends the product data to Shopify via `PUT /admin/api/2024-01/products/{id}.json`. | Shopify returns HTTP 200 with the updated product. |
| 4 | Verify the Shopify product data matches the local product data. | Title, description, price, and images match the application's product record. |
| 5 | Verify the data hash is recalculated and stored. | ShopifyListing data hash updated, `last_synced_at` updated. |

### Test Data
- Product with no local changes but `force=true` parameter.

### Postconditions
- Shopify product updated regardless of hash match.
- Hash and timestamp refreshed.

### Notes
- Force sync is useful for resolving discrepancies caused by manual edits on Shopify, webhook failures, or data corruption.

---

## TC-MKT-007-05: Sync Sets Collection Based on Category Mapping

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-007-05                                                      |
| **Priority**   | High                                                               |
| **Type**       | Integration                                                        |
| **Platform**   | Shopify                                                            |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Shopify/ShopifyProductSyncTest.php`                 |

### Preconditions
- Organization has Shopify collection mappings configured (e.g., local category "Electronics" maps to Shopify collection "Electronics Store").
- Product belongs to the "Electronics" category.
- Shopify Admin API collection and collect endpoints are mocked.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Dispatch `SyncProductToShopify` for the product. | Job processes the product. |
| 2 | Job creates the Shopify product with `product_type` set from the category mapping. | `product_type` field in the Shopify product payload is "Electronics". |
| 3 | Job adds the product to the mapped Shopify custom collection via `POST /admin/api/2024-01/collects.json`. | Collect record created linking the Shopify product to the collection. |
| 4 | Verify the ShopifyListing stores the collection association. | ShopifyListing metadata includes the collection ID. |
| 5 | Change the product category to "Accessories" and resync. | Product is removed from "Electronics" collection and added to "Accessories" collection (if mapping exists). |

### Test Data
- Category mapping: `{local_category: "Electronics", shopify_product_type: "Electronics", shopify_collection_id: 123456789}`.
- Product category: "Electronics".

### Postconditions
- Product is in the correct Shopify collection based on category mapping.
- `product_type` field reflects the mapped value.

### Notes
- Collection mapping is sourced from the product's category `product_type` field, as decided in the Shopify Collections design document.

---

## TC-MKT-007-06: Product with Images Syncs Image URLs

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-007-06                                                      |
| **Priority**   | High                                                               |
| **Type**       | Integration                                                        |
| **Platform**   | Shopify                                                            |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Shopify/ShopifyProductSyncTest.php`                 |

### Preconditions
- Product has 4 ProductImage records with publicly accessible URLs.
- Shopify Admin API product creation endpoint is mocked.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Dispatch `SyncProductToShopify` for the product. | Job includes images in the product creation payload. |
| 2 | Verify the Shopify product payload contains an `images` array. | Array has 4 entries, each with a `src` field containing the image URL. |
| 3 | Verify image order is preserved. | Images are ordered by their `position` or `sort_order` in the ProductImage records. |
| 4 | Verify the first image is set as the primary product image. | Shopify product response has the first image as `image.src` on the product root. |
| 5 | Update product to remove 1 image and add 2 new ones (5 total). | Resync sends the updated images array with 5 entries. |

### Test Data
- 4 ProductImage URLs: `["https://cdn.example.com/img1.jpg", "https://cdn.example.com/img2.jpg", "https://cdn.example.com/img3.jpg", "https://cdn.example.com/img4.jpg"]`.

### Postconditions
- Shopify product has the correct images in the correct order.

### Notes
- Shopify accepts image URLs and downloads them server-side. The URLs must be publicly accessible. If images are stored behind authentication, they must be served via a public URL or uploaded as base64.

---

## TC-MKT-007-07: Retry Failed Sync Resets Status

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-007-07                                                      |
| **Priority**   | High                                                               |
| **Type**       | Feature                                                            |
| **Platform**   | Shopify                                                            |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Shopify/ShopifyProductSyncTest.php`                 |

### Preconditions
- Product has a ShopifyListing with `status=error` and `error_message="Shopify API rate limit exceeded"`.
- Shopify API is now available (rate limit recovered).

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to the product detail page, Shopify listing section. | ShopifyListing shows "Error" status with the error message and a "Retry" button. |
| 2 | Click "Retry". | Application resets the ShopifyListing and dispatches a new sync job. |
| 3 | Verify ShopifyListing status is reset. | `status=pending`, `error_message=null`. |
| 4 | Verify `SyncProductToShopify` job is dispatched. | Job queued for processing. |
| 5 | Job runs successfully this time. | ShopifyListing transitions to `active` with valid Shopify IDs. |

### Test Data
- ShopifyListing: `{status: "error", error_message: "Shopify API rate limit exceeded"}`.

### Postconditions
- ShopifyListing `status=active` after successful retry.
- Error message cleared.

### Notes
- Rate limit errors are transient and should always be retryable. Other errors (e.g., invalid product data) may require user intervention before retry.

---

## TC-MKT-007-08: Inventory Set at Primary Location and Zeros Other Locations

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-007-08                                                      |
| **Priority**   | Critical                                                           |
| **Type**       | Integration                                                        |
| **Platform**   | Shopify                                                            |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Shopify/ShopifyProductSyncTest.php`                 |

### Preconditions
- Shopify store has 3 locations: Location A (primary, ID: 1001), Location B (ID: 1002), Location C (ID: 1003).
- IntegrationCredential `config.shopify_location_id` is set to 1001 (or resolved from Shopify Locations API).
- Product has `available_quantity = 20`.
- ShopifyListing exists with a valid `shopify_inventory_item_id`.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | `MarketplaceInventorySync` pushes quantity for the product to Shopify. | Shopify adapter processes the inventory update. |
| 2 | Adapter fetches all locations for the inventory item via `GET /admin/api/2024-01/inventory_levels.json?inventory_item_ids={id}`. | Returns inventory levels for all 3 locations. |
| 3 | Adapter sets inventory at the primary location (1001) to 20. | `POST /admin/api/2024-01/inventory_levels/set.json` called with `location_id=1001`, `available=20`. |
| 4 | Adapter sets inventory at Location B (1002) to 0. | `POST /admin/api/2024-01/inventory_levels/set.json` called with `location_id=1002`, `available=0`. |
| 5 | Adapter sets inventory at Location C (1003) to 0. | `POST /admin/api/2024-01/inventory_levels/set.json` called with `location_id=1003`, `available=0`. |
| 6 | Verify total Shopify inventory for the item equals `available_quantity`. | Sum across all locations = 20 (20 + 0 + 0). |

### Test Data
- Primary location: 1001, quantity: 20.
- Other locations: 1002 and 1003, quantity: 0.
- `available_quantity`: 20.

### Postconditions
- Shopify inventory is correct at all locations.
- Only the primary location holds stock; all others are zeroed.

### Notes
- Zeroing non-primary locations prevents Shopify from showing incorrect "available at other locations" information and avoids split-fulfillment issues. This is a known pattern documented in the Shopify integration notes: multi-location zeroing ensures the application remains the single source of truth for inventory.
