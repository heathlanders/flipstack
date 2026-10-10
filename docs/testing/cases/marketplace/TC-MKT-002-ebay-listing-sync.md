# TC-MKT-002: eBay Listing Creation and Sync

| Field              | Value                                                                 |
|--------------------|-----------------------------------------------------------------------|
| **Document ID**    | TC-MKT-002                                                            |
| **Domain**         | Marketplace Integration - eBay Listing Sync                           |
| **Related Test Plan** | [Feature Testing Plan - Phase 1](../../project_feature_testing_plan.md) |
| **Last Updated**   | 2026-04-15                                                            |

---

## TC-MKT-002-01: Sync Single Product - Full Lifecycle (pending -> published)

| Field          | Value                                                      |
|----------------|------------------------------------------------------------|
| **ID**         | TC-MKT-002-01                                              |
| **Priority**   | Critical                                                   |
| **Type**       | Integration                                                |
| **Platform**   | eBay                                                       |
| **Automated**  | Yes                                                        |
| **Test File**  | `tests/Feature/Ebay/EbayListingSyncTest.php`               |

### Preconditions
- Organization has an active eBay IntegrationCredential with valid OAuthToken.
- Product exists with title, description, price, quantity, SKU, condition, and at least one image.
- Product has a mapped eBay category (EbayCategory association).
- No existing EbayListing for this product.
- eBay API endpoints are mocked for inventory item, offer, and publish.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Dispatch `SyncProductToEbay` job for the product. | Job is queued and begins processing. |
| 2 | Job creates an EbayListing record with status `pending`. | EbayListing record exists in the database with `status=pending`, linked to the product and organization. |
| 3 | Job calls eBay Inventory API `createOrReplaceInventoryItem`. | eBay returns HTTP 204 (success). EbayListing status transitions to `inventory_created`. |
| 4 | Job calls eBay Sell API `createOffer` with the inventory item SKU. | eBay returns HTTP 201 with an `offerId`. EbayListing status transitions to `offer_created`, `ebay_offer_id` is stored. |
| 5 | Job calls eBay Sell API `publishOffer` with the offerId. | eBay returns HTTP 200 with a `listingId`. EbayListing status transitions to `published`, `ebay_listing_id` is stored. |
| 6 | Verify EbayListing final state. | `status=published`, `ebay_listing_id` is set, `ebay_offer_id` is set, `last_synced_at` is current timestamp. |

### Test Data
- Product: `{title: "Test Widget", price: 29.99, quantity: 5, sku: "TW-001", condition: "new"}`.
- Mock eBay responses: inventory item 204, offer 201 with `offerId: "5000000001"`, publish 200 with `listingId: "110000000001"`.

### Postconditions
- EbayListing record with `status=published` and valid eBay IDs.
- Product data hash is stored on the EbayListing for change detection.

### Notes
- The eBay Offer API requires an active eBay payment/shipping policy. These policy IDs must be stored in IntegrationCredential config.

---

## TC-MKT-002-02: Bulk Sync Dispatches Batch of Jobs

| Field          | Value                                                      |
|----------------|------------------------------------------------------------|
| **ID**         | TC-MKT-002-02                                              |
| **Priority**   | High                                                       |
| **Type**       | Feature                                                    |
| **Platform**   | eBay                                                       |
| **Automated**  | Yes                                                        |
| **Test File**  | `tests/Feature/Ebay/EbayListingSyncTest.php`               |

### Preconditions
- Organization has an active eBay IntegrationCredential.
- 10 products exist that are eligible for eBay listing (have required fields and category mapping).
- None of the products have existing EbayListing records.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Select all 10 products in the product list UI. | Products are selected with checkboxes. |
| 2 | Click "Sync to eBay" from the bulk actions menu. | Application dispatches individual `SyncProductToEbay` jobs for each product. |
| 3 | Verify the correct number of jobs were dispatched. | Exactly 10 `SyncProductToEbay` jobs are on the queue. |
| 4 | Verify a BulkOperationJob record is created. | BulkOperationJob exists with `type=ebay_sync`, `total_items=10`, `status=processing`. |
| 5 | Verify each job has rate limiting applied. | Jobs are dispatched with appropriate delay to respect eBay API rate limits. |

### Test Data
- 10 products with valid titles, SKUs, prices, and eBay category mappings.

### Postconditions
- 10 queued jobs pending execution.
- BulkOperationJob tracking record exists.

### Notes
- eBay API rate limits vary by endpoint (typically 5000 calls/day for inventory). Bulk sync must throttle accordingly.

---

## TC-MKT-002-03: Sync with Missing Category Fails Gracefully

| Field          | Value                                                      |
|----------------|------------------------------------------------------------|
| **ID**         | TC-MKT-002-03                                              |
| **Priority**   | High                                                       |
| **Type**       | Negative                                                   |
| **Platform**   | eBay                                                       |
| **Automated**  | Yes                                                        |
| **Test File**  | `tests/Feature/Ebay/EbayListingSyncTest.php`               |

### Preconditions
- Organization has an active eBay IntegrationCredential.
- Product exists with all required fields except no eBay category mapping.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Dispatch `SyncProductToEbay` job for the product. | Job begins processing. |
| 2 | Job detects the product has no eBay category mapping. | Job skips the eBay API call entirely. |
| 3 | Verify EbayListing record is created with error status. | EbayListing exists with `status=error` and `error_message` containing "Missing eBay category mapping". |
| 4 | Verify no eBay API calls were made. | Mocked eBay endpoints received zero requests. |
| 5 | Verify the job does not throw an unhandled exception. | Job completes without failure (does not go to failed_jobs table). |

### Test Data
- Product with no associated EbayCategory.

### Postconditions
- EbayListing record with `status=error`.
- No eBay API calls made.
- Job marked as completed (not failed).

### Notes
- Other missing fields (title, price, SKU) should produce similarly descriptive error messages.

---

## TC-MKT-002-04: Retry Failed Listing Resets Status to Pending

| Field          | Value                                                      |
|----------------|------------------------------------------------------------|
| **ID**         | TC-MKT-002-04                                              |
| **Priority**   | High                                                       |
| **Type**       | Feature                                                    |
| **Platform**   | eBay                                                       |
| **Automated**  | Yes                                                        |
| **Test File**  | `tests/Feature/Ebay/EbayListingSyncTest.php`               |

### Preconditions
- Organization has an active eBay IntegrationCredential.
- Product has an existing EbayListing with `status=error`.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to the product detail page, eBay listing section. | EbayListing shows status "Error" with the error message and a "Retry" button. |
| 2 | Click the "Retry" button. | Application resets the EbayListing status and dispatches a new sync job. |
| 3 | Verify EbayListing status is reset. | `status=pending`, `error_message` is cleared. |
| 4 | Verify a new `SyncProductToEbay` job is dispatched. | Job is on the queue for the correct product. |

### Test Data
- Existing EbayListing: `{status: "error", error_message: "eBay API returned 400: Invalid SKU format"}`.

### Postconditions
- EbayListing `status=pending`, `error_message=null`.
- New sync job queued.

### Notes
- The retry should use the current product data, not the data from the original failed attempt.

---

## TC-MKT-002-05: Listing Update - Price Change

| Field          | Value                                                      |
|----------------|------------------------------------------------------------|
| **ID**         | TC-MKT-002-05                                              |
| **Priority**   | Critical                                                   |
| **Type**       | Integration                                                |
| **Platform**   | eBay                                                       |
| **Automated**  | Yes                                                        |
| **Test File**  | `tests/Feature/Ebay/EbayListingSyncTest.php`               |

### Preconditions
- Organization has an active eBay IntegrationCredential.
- Product has a published EbayListing (`status=published`, valid `ebay_offer_id`).
- Product price is currently $29.99.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Update the product price from $29.99 to $34.99. | Product record is updated in the database. |
| 2 | Dispatch `SyncProductToEbay` job (triggered by product update or manual sync). | Job begins processing. |
| 3 | Job detects price change via product data hash comparison. | Hash mismatch triggers an update flow. |
| 4 | Job calls eBay Inventory API `createOrReplaceInventoryItem` with updated price. | eBay returns HTTP 204. |
| 5 | Job calls eBay Sell API `updateOffer` with the updated price. | eBay returns HTTP 204. Offer reflects the new price. |
| 6 | Verify EbayListing is updated. | `last_synced_at` updated, product data hash refreshed, `status` remains `published`. |

### Test Data
- Original price: 29.99, Updated price: 34.99.
- Mock eBay update responses: inventory item 204, update offer 204.

### Postconditions
- EbayListing `last_synced_at` is current, data hash reflects the new price.

### Notes
- Price updates go through the Offer API, not just the Inventory Item API.

---

## TC-MKT-002-06: Listing Update - Quantity Change

| Field          | Value                                                      |
|----------------|------------------------------------------------------------|
| **ID**         | TC-MKT-002-06                                              |
| **Priority**   | Critical                                                   |
| **Type**       | Integration                                                |
| **Platform**   | eBay                                                       |
| **Automated**  | Yes                                                        |
| **Test File**  | `tests/Feature/Ebay/EbayListingSyncTest.php`               |

### Preconditions
- Organization has an active eBay IntegrationCredential.
- Product has a published EbayListing (`status=published`).
- Product `available_quantity` is currently 10.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Product `available_quantity` changes from 10 to 3 (e.g., due to an order). | Product record is updated. |
| 2 | `PushInventoryToMarketplaces` job is dispatched for this product. | Job runs and calls `MarketplaceInventorySync`. |
| 3 | eBay adapter calls `createOrReplaceInventoryItem` with quantity 3. | eBay returns HTTP 204. Inventory item quantity updated. |
| 4 | Verify EbayListing reflects the update. | `last_synced_at` updated. |
| 5 | Verify that if quantity reaches 0, listing status changes to `out_of_stock`. | EbayListing `status=out_of_stock` when `available_quantity=0` and out-of-stock control is enabled. |

### Test Data
- Original quantity: 10, Updated quantity: 3.
- Zero quantity scenario: quantity 0.

### Postconditions
- eBay inventory reflects the correct quantity.
- Out-of-stock handling works as expected.

### Notes
- eBay out-of-stock control must be enabled in the seller's account for zero-quantity listings to remain active (hidden from search but not ended).

---

## TC-MKT-002-07: Listing Update - Price and Quantity Combined

| Field          | Value                                                      |
|----------------|------------------------------------------------------------|
| **ID**         | TC-MKT-002-07                                              |
| **Priority**   | High                                                       |
| **Type**       | Integration                                                |
| **Platform**   | eBay                                                       |
| **Automated**  | Yes                                                        |
| **Test File**  | `tests/Feature/Ebay/EbayListingSyncTest.php`               |

### Preconditions
- Organization has an active eBay IntegrationCredential.
- Product has a published EbayListing.
- Product price is $29.99 and `available_quantity` is 10.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Update product price to $24.99 and quantity to 15 simultaneously. | Product record updated with both changes. |
| 2 | Dispatch `SyncProductToEbay` job. | Job detects changes via data hash comparison. |
| 3 | Job sends both updates in a single sync cycle. | Inventory item updated with new quantity, offer updated with new price. |
| 4 | Verify only the necessary API calls are made. | Exactly one `createOrReplaceInventoryItem` and one `updateOffer` call. No redundant publish call. |
| 5 | Verify EbayListing data hash is updated to reflect both changes. | New hash stored; re-running sync with no changes would be a no-op. |

### Test Data
- Price: 29.99 -> 24.99, Quantity: 10 -> 15.

### Postconditions
- EbayListing synced with both updates, data hash current.

### Notes
- Combined updates should not result in duplicate API calls or partial update states.

---

## TC-MKT-002-08: Product Data Hash Prevents Unnecessary Syncs

| Field          | Value                                                      |
|----------------|------------------------------------------------------------|
| **ID**         | TC-MKT-002-08                                              |
| **Priority**   | High                                                       |
| **Type**       | Feature                                                    |
| **Platform**   | eBay                                                       |
| **Automated**  | Yes                                                        |
| **Test File**  | `tests/Feature/Ebay/EbayListingSyncTest.php`               |

### Preconditions
- Organization has an active eBay IntegrationCredential.
- Product has a published EbayListing with a current data hash matching the product state.
- No changes have been made to the product since last sync.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Dispatch `SyncProductToEbay` job for the product. | Job begins processing. |
| 2 | Job computes the current product data hash. | Hash matches the stored hash on the EbayListing. |
| 3 | Job skips all eBay API calls. | No HTTP requests sent to eBay. |
| 4 | Verify EbayListing is unchanged. | `last_synced_at` is NOT updated (no sync occurred). `status` remains `published`. |
| 5 | Now update the product title and dispatch the job again. | Hash mismatch detected; sync proceeds normally. |

### Test Data
- Product with no changes since last sync.
- Hash algorithm: MD5 or SHA-256 of serialized product fields (title, description, price, quantity, images, category).

### Postconditions
- No unnecessary eBay API calls when product data is unchanged.

### Notes
- The data hash should include all fields that are sent to eBay: title, description, price, quantity, images, condition, category, and item specifics. Fields not synced to eBay (e.g., internal notes) should be excluded from the hash.
