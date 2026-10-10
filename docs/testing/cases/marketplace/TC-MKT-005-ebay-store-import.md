# TC-MKT-005: eBay Store Import

| Field              | Value                                                                 |
|--------------------|-----------------------------------------------------------------------|
| **Document ID**    | TC-MKT-005                                                            |
| **Domain**         | Marketplace Integration - eBay Store Import                           |
| **Related Test Plan** | [Feature Testing Plan - Phase 1](../../project_feature_testing_plan.md) |
| **Last Updated**   | 2026-04-15                                                            |

---

## TC-MKT-005-01: Start Import Creates BulkOperationJob and Dispatches Job

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-005-01                                                      |
| **Priority**   | Critical                                                           |
| **Type**       | Feature                                                            |
| **Platform**   | eBay                                                               |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Ebay/EbayStoreImportTest.php`                      |

### Preconditions
- Organization has an active eBay IntegrationCredential with valid OAuthToken.
- eBay seller account has at least 50 active listings.
- No existing BulkOperationJob of type `ebay_import` is currently running for this organization.
- eBay Sell Inventory API `getInventoryItems` endpoint is mocked to return paginated results.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to eBay > Import Store. | Import page displays with a "Start Import" button and options (e.g., import all or by category). |
| 2 | Click "Start Import" with default settings (import all). | POST request sent to the import initiation endpoint. |
| 3 | Verify a BulkOperationJob record is created. | Record exists with `type=ebay_import`, `status=processing`, `total_items=null` (unknown until first API page), `organization_id` set. |
| 4 | Verify `ImportEbayInventory` job is dispatched to the queue. | Job is on the queue with the BulkOperationJob ID and organization context. |
| 5 | User is redirected to the import progress page. | Progress page shows "Import started" with a progress indicator. |

### Test Data
- eBay store with 50 active listings across multiple categories.

### Postconditions
- BulkOperationJob record exists with `status=processing`.
- `ImportEbayInventory` job queued.

### Notes
- Only one import can run per organization at a time. Attempting to start a second should return an error: "An import is already in progress."

---

## TC-MKT-005-02: Import Status Endpoint Returns Progress

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-005-02                                                      |
| **Priority**   | High                                                               |
| **Type**       | Feature                                                            |
| **Platform**   | eBay                                                               |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Ebay/EbayStoreImportTest.php`                      |

### Preconditions
- A BulkOperationJob exists with `type=ebay_import`, `status=processing`.
- Import has processed 20 of 50 items so far.
- BulkOperationJob has `total_items=50`, `processed_items=20`, `failed_items=2`.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send GET request to the import status endpoint with the BulkOperationJob ID. | HTTP 200 response. |
| 2 | Verify the response contains progress data. | Response includes: `status: "processing"`, `total_items: 50`, `processed_items: 20`, `failed_items: 2`, `progress_percentage: 40`. |
| 3 | Verify failed item details are included. | Response includes a `failures` array with reasons for each failed item (e.g., "Missing required field: price"). |
| 4 | Poll the endpoint again after more items are processed. | `processed_items` and `progress_percentage` have increased. |
| 5 | Verify the endpoint returns `status: "completed"` when all items are processed. | Final response: `status: "completed"`, `processed_items: 50`, `failed_items: 2`, `progress_percentage: 100`. |

### Test Data
- BulkOperationJob: `{total_items: 50, processed_items: 20, failed_items: 2}`.

### Postconditions
- Progress endpoint accurately reflects the current state of the import.

### Notes
- The frontend polls this endpoint on an interval (e.g., every 5 seconds) to update the progress bar. Alternatively, Pusher broadcasting could push updates in real time.

---

## TC-MKT-005-03: Cancel Import Marks Job Cancelled

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-005-03                                                      |
| **Priority**   | Medium                                                             |
| **Type**       | Feature                                                            |
| **Platform**   | eBay                                                               |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Ebay/EbayStoreImportTest.php`                      |

### Preconditions
- A BulkOperationJob exists with `type=ebay_import`, `status=processing`.
- Import has processed 15 of 50 items.
- The `ImportEbayInventory` job checks for cancellation between pages.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click "Cancel Import" on the import progress page. | PATCH request sent to cancel the BulkOperationJob. |
| 2 | Verify BulkOperationJob status is updated. | `status=cancelled`, `cancelled_at` is set. |
| 3 | Verify the running `ImportEbayInventory` job detects the cancellation. | On the next pagination loop iteration, job checks `BulkOperationJob.status` and exits gracefully. |
| 4 | Verify already-imported products are preserved. | The 15 products imported before cancellation remain in the database. |
| 5 | Verify no further eBay API calls are made after cancellation. | No additional `getInventoryItems` calls after the cancel flag is detected. |

### Test Data
- BulkOperationJob in processing state with 15 of 50 items done.

### Postconditions
- BulkOperationJob `status=cancelled`.
- 15 imported products retained.
- No orphaned or partial product records.

### Notes
- Cancellation is cooperative: the job checks a flag between API page requests. There may be a slight delay before the job actually stops.

---

## TC-MKT-005-04: Resume Import Restarts Processing

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-005-04                                                      |
| **Priority**   | Medium                                                             |
| **Type**       | Feature                                                            |
| **Platform**   | eBay                                                               |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Ebay/EbayStoreImportTest.php`                      |

### Preconditions
- A BulkOperationJob exists with `type=ebay_import`, `status=cancelled` or `status=failed`.
- 15 of 50 items were previously imported.
- The BulkOperationJob stores the last processed page/offset for resumption.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click "Resume Import" on the import page. | PATCH request sent to resume the BulkOperationJob. |
| 2 | Verify BulkOperationJob status is updated. | `status=processing`, `cancelled_at` cleared (if applicable). |
| 3 | Verify a new `ImportEbayInventory` job is dispatched. | Job starts from the last processed offset, not from the beginning. |
| 4 | Job continues fetching eBay inventory items from page 4 (assuming pages 1-3 were done). | API calls start from the correct offset. |
| 5 | Verify no duplicate products are created for the 15 already-imported items. | Duplicate detection by `ebay_listing_id` prevents re-creation. |

### Test Data
- BulkOperationJob with `last_processed_offset` or page cursor stored in metadata.
- 15 existing products from the initial import run.

### Postconditions
- Import continues from where it left off.
- No duplicate products.
- BulkOperationJob progresses toward completion.

### Notes
- Resume functionality depends on eBay's pagination cursor or offset being stored in the BulkOperationJob metadata.

---

## TC-MKT-005-05: Imported Items Create Products with Correct Fields

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-005-05                                                      |
| **Priority**   | Critical                                                           |
| **Type**       | Integration                                                        |
| **Platform**   | eBay                                                               |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Ebay/EbayStoreImportTest.php`                      |

### Preconditions
- `ImportEbayInventory` job is processing a page of eBay inventory items.
- eBay API returns a well-formed inventory item with all fields populated.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Job receives an eBay inventory item from the API response. | Item contains: SKU, title, description, price, quantity, condition, images, category ID, item specifics. |
| 2 | Job creates a Product record from the eBay data. | Product exists with: `title` from eBay title, `description` from eBay description (HTML stripped/sanitized), `price` from eBay price, `sku` from eBay SKU, `condition` mapped from eBay condition enum. |
| 3 | Verify product images are imported. | ProductImage records created for each eBay image URL. Images are downloaded and stored locally or URLs are stored directly. |
| 4 | Verify an EbayListing record is created and linked. | EbayListing exists with `status=published`, `ebay_listing_id` set, linked to the new Product via `product_id`. |
| 5 | Verify the product is scoped to the correct organization. | `Product.organization_id` matches the authenticated organization. |
| 6 | Verify `available_quantity` is set from the eBay quantity. | `Product.available_quantity` matches the eBay `shipToLocationAvailability.quantity`. |

### Test Data
- eBay inventory item:
  ```json
  {
    "sku": "EBAY-IMP-001",
    "product": {
      "title": "Vintage Camera Lens 50mm f/1.4",
      "description": "<p>Excellent condition vintage lens</p>",
      "imageUrls": ["https://i.ebayimg.com/images/g/abc/s-l1600.jpg"],
      "aspects": {"Brand": ["Canon"], "Focal Length": ["50mm"]}
    },
    "condition": "USED_EXCELLENT",
    "availability": {
      "shipToLocationAvailability": {"quantity": 1}
    },
    "offers": [{
      "offerId": "5000000099",
      "listingId": "110000000099",
      "pricingSummary": {"price": {"value": "149.99", "currency": "USD"}},
      "status": "PUBLISHED"
    }]
  }
  ```

### Postconditions
- Product created with all fields mapped correctly.
- EbayListing linked to the product.
- ProductImage records created for images.

### Notes
- eBay condition enums (e.g., `USED_EXCELLENT`, `NEW`, `USED_GOOD`) must be mapped to the application's condition values. Unmapped conditions should default to a sensible value and log a warning.

---

## TC-MKT-005-06: Duplicate Prevention (Same ebay_listing_id)

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-005-06                                                      |
| **Priority**   | Critical                                                           |
| **Type**       | Integration                                                        |
| **Platform**   | eBay                                                               |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Ebay/EbayStoreImportTest.php`                      |

### Preconditions
- An eBay listing with `ebay_listing_id = "110000000099"` was previously imported.
- A Product and EbayListing record exist for this listing.
- The import job encounters the same listing again (e.g., during a re-import or resume).

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Import job processes an eBay item with `listingId = "110000000099"`. | Job checks for an existing EbayListing with this `ebay_listing_id` in the organization. |
| 2 | Existing EbayListing is found. | Job skips product creation. |
| 3 | Verify no duplicate Product is created. | Product count for SKU "EBAY-IMP-001" remains 1. |
| 4 | Verify the existing EbayListing is optionally updated. | If the eBay data has changed (price, quantity), the existing EbayListing and Product are updated. |
| 5 | Verify the BulkOperationJob `processed_items` counter still increments. | The item counts as processed (not failed), since it was handled (skipped or updated). |
| 6 | Run a full re-import of all 50 items where all already exist. | All 50 items are skipped or updated. Zero new Product records created. Product count remains 50. |

### Test Data
- Existing Product with SKU "EBAY-IMP-001" and EbayListing with `ebay_listing_id = "110000000099"`.
- Same eBay item data as TC-MKT-005-05.

### Postconditions
- No duplicate products or listings.
- Existing records optionally updated with latest eBay data.

### Notes
- Duplicate detection is based on `ebay_listing_id` within the organization scope, not on SKU or title. Two organizations can import the same eBay listing independently without conflict.

---

## TC-MKT-005-07: SKU Parsing & Backend Wiring (owner-prefix pattern)

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-005-07                                                      |
| **Priority**   | Critical                                                           |
| **Type**       | Integration                                                        |
| **Platform**   | eBay                                                               |
| **Automated**  | Partial (some scenarios marked as Gap, see below)                  |
| **Test File**  | `tests/Unit/EbaySkuLocationParserTest.php`, `tests/Feature/EbayStoreImportServiceTest.php` |

### Background

eBay store import is the onboarding pipeline. A single custom-label SKU hydrates three backend domains in one pass: Catalog (Product), Suppliers (warehouse owner + 3rd-party consignor), and Warehouse (Zone + Bin + InventoryLevel). Canonical token order:

```
[bracket-prefix?] OWNER [(LISTER_INITIALS | CONSIGNOR [item_id]) ...] [price] LOCATION_TOKENS
```

Validated against a 66,296-row production export. See `docs/EBAY_SYNC_AND_UPDATE_SYSTEM.md` § "Structured Location Parser" for full wiring map and known gaps.

### Preconditions

- Organization has an active eBay IntegrationCredential with no `known_owners` override (defaults seeded).
- `housePrefixes` config is empty for the org.
- No existing Suppliers or WarehouseZones (clean slate).

### Test Scenarios

Scenarios assume `IntegrationCredential.config.known_listers=['ABC','DEF','GHI']` is configured.

| Scenario | Raw SKU | Expected Backend Writes | Status |
|----------|---------|-------------------------|--------|
| Owner + plain location | `HAWK RM 134 SHELF B3 TUB GOLDENGOOSE` | Supplier(HAWK), Zone(`RM-134`), Bin(`SHLF-B3-TUB-GOLDENGOOSE`), InventoryLocation(WAREHOUSE), InventoryLevel(product, location, bin) | Happy path |
| Owner + consignor + item_id + location | `HAWK COOP 58585 SHELF 29 TUB ELTONJOHN` | Supplier(HAWK), Supplier(COOP, `meta.is_consignor=true`), `Product.attributes.consignor_code=COOP`, `consignor_item_id=58585`, Bin(`SHLF-29-TUB-ELTONJOHN`) | Happy path |
| ARC pattern with lister + product SKU | `ARC ABC PO-1043-1020-101 AE-3/BOOST` | Supplier(ARC), `Product.sku=PO-1043-1020-101`, `Product.attributes.lister_initials=ABC`. No Supplier(ABC). | Happy path |
| Owner + lister initials + location | `HAWK ABC SHELF 28 LVL 3` | Supplier(HAWK), `Product.attributes.lister_initials=ABC`, Bin(`SHLF-28-LVL-3`). No Supplier(ABC). | Happy path |
| Bracket prefix | `(C) PINE Tub Morrigan Shelf B3 21655` | Supplier(PINE), Bin(`SHLF-B3-TUB-MORRIGAN`), `Product.attributes.bracket_prefix=C`. | Happy path |
| Lowercase owner | `arc abc PO-1043-1020-101 FOH` | Supplier(ARC), `Product.attributes.lister_initials=ABC`, Zone(`FOH`), `Product.sku=PO-1043-1020-101`. | Happy path |
| Inverted order | `COOP HAWK SHELF 28 TUB FREE MASONS` | First-token-wins: Supplier(COOP) becomes the owner; HAWK (in `known_owners`) is suppressed as a consignor and dropped. Edge case, still documented as Gap #4. | Edge case |
| Special area only (no room) | `HAWK FOH CART` | Supplier(HAWK), Zone(`FOH`, name=Front of House), no Bin (no shelf/container parsed), InventoryLevel(product, FOH location). | Happy path |
| House prefix configured | `HAWK RM 132 SHELF 1` with `house_prefixes=['HAWK']` | No Supplier created; `Product.attributes.is_house_stock=true`; Zone/Bin still created normally. | Configurable path |
| Idempotency | Re-import the same row twice | No duplicate Supplier / Zone / Bin / InventoryLevel rows. `firstOrCreate` guarantees uniqueness. | Critical |

### Test Steps (idempotency check)

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Run import with SKU `HAWK COOP 58585 SHELF 29 TUB ELTONJOHN`. | One Supplier(HAWK), one Supplier(COOP, is_consignor), one Zone, one Bin, one InventoryLevel created. |
| 2 | Re-run the import on the same eBay listing (duplicate detection still applies). | Row count for Supplier / Zone / Bin / InventoryLevel is unchanged. |
| 3 | Modify the listing's qty on eBay and re-import. | `InventoryLevel.quantity_on_hand` reflects new qty; no duplicate Zone/Bin/Supplier created. |

### Postconditions

- Backend tables populated according to the wiring map above.
- Re-running the import is safe (idempotent).
- All writes scoped to `organization_id` (directly on Supplier/Zone/Location/InventoryLevel; via parent FK on Bin).

### Notes

- Gaps #1, #2, #3, and #6 were resolved in the 2026-05-13 code pass — the scenarios that used to be flagged "Known bug" are now happy paths in this matrix. Remaining open gaps (#4 inverted-order COOP, #5 GHI ownership, #7 owner-less SKUs, #8 bin code typos) are still documented limitations.
- Validation dataset: `eBay-all-active-listings-report-2026-01-29-13279007187.csv` (66,296 rows).
