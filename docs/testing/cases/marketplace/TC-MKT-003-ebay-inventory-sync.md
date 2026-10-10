# TC-MKT-003: eBay Inventory Push

| Field              | Value                                                                 |
|--------------------|-----------------------------------------------------------------------|
| **Document ID**    | TC-MKT-003                                                            |
| **Domain**         | Marketplace Integration - eBay Inventory Sync                         |
| **Related Test Plan** | [Feature Testing Plan - Phase 1](../../project_feature_testing_plan.md) |
| **Last Updated**   | 2026-04-15                                                            |

---

## TC-MKT-003-01: PushInventoryToMarketplaces Dispatches for Product

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-003-01                                                      |
| **Priority**   | Critical                                                           |
| **Type**       | Unit                                                               |
| **Platform**   | eBay                                                               |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Inventory/PushInventoryTest.php`                    |

### Preconditions
- Product exists with `available_quantity = 8`.
- Organization has an active eBay IntegrationCredential with valid OAuthToken.
- Product has a published EbayListing (`status=published`, valid `ebay_listing_id`).
- `MarketplaceInventorySync` service is injectable.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Dispatch `PushInventoryToMarketplaces` job with the product ID (string UUID). | Job is queued and begins processing. |
| 2 | Job loads the product by UUID. | Product is found with `available_quantity = 8`. |
| 3 | Job calls `MarketplaceInventorySync->pushQuantity()` for the product. | Service iterates over all active marketplace credentials for the organization. |
| 4 | Verify the eBay adapter is invoked. | `EbayMarketplaceAdapter->updateQuantity()` is called with the product and quantity 8. |
| 5 | Verify the job completes without error. | Job finishes successfully; no entry in `failed_jobs`. |

### Test Data
- Product UUID: valid UUID string.
- `available_quantity`: 8.

### Postconditions
- eBay inventory item reflects quantity 8.
- Job completed successfully.

### Notes
- `PushInventoryToMarketplaces` accepts a `string $productId` (not a Product model) to avoid serialization issues with UUID primary keys.

---

## TC-MKT-003-02: EbayMarketplaceAdapter.updateQuantity Calls eBay API

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-003-02                                                      |
| **Priority**   | Critical                                                           |
| **Type**       | Integration                                                        |
| **Platform**   | eBay                                                               |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Inventory/PushInventoryTest.php`                    |

### Preconditions
- Product has a published EbayListing with a valid SKU.
- eBay IntegrationCredential is active with a non-expired OAuthToken.
- eBay Inventory API endpoint is mocked.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Call `EbayMarketplaceAdapter->updateQuantity($product, 12)`. | Adapter prepares an eBay Inventory API request. |
| 2 | Adapter calls eBay `createOrReplaceInventoryItem` with the product SKU and quantity 12. | HTTP PUT request sent to `https://api.ebay.com/sell/inventory/v1/inventory_item/{sku}` with `availability.shipToLocationAvailability.quantity = 12`. |
| 3 | eBay returns HTTP 204 (No Content). | Adapter returns success. |
| 4 | Verify the Authorization header contains the current access_token. | Header value is `Bearer {access_token}` from the OAuthToken. |
| 5 | Verify the EbayListing `last_synced_at` is updated. | Timestamp is set to the current time. |

### Test Data
- Product SKU: "TW-001".
- Target quantity: 12.
- Mock eBay response: HTTP 204.

### Postconditions
- eBay inventory item quantity is 12.
- EbayListing `last_synced_at` updated.

### Notes
- The adapter must use the product's SKU (not the EbayListing ID) as the inventory item identifier, per eBay's Inventory API design.

---

## TC-MKT-003-03: Quantity Pushed Matches Product.available_quantity

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-003-03                                                      |
| **Priority**   | Critical                                                           |
| **Type**       | Integration                                                        |
| **Platform**   | eBay                                                               |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Inventory/PushInventoryTest.php`                    |

### Preconditions
- Product has `available_quantity = 5` (after accounting for reserved orders).
- Product has `quantity = 10` (total stock, including reserved).
- EbayListing exists with `status=published`.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Dispatch `PushInventoryToMarketplaces` for the product. | Job processes the product. |
| 2 | Verify the quantity sent to eBay is `available_quantity`, not `quantity`. | eBay API receives quantity = 5, not 10. |
| 3 | Reserve 3 more units (simulate an order from another channel). | `available_quantity` drops to 2. |
| 4 | Dispatch `PushInventoryToMarketplaces` again. | Job processes the updated product. |
| 5 | Verify eBay now receives quantity = 2. | API call payload contains `quantity = 2`. |

### Test Data
- Initial: `quantity=10`, `available_quantity=5`.
- After reservation: `quantity=10`, `available_quantity=2`.

### Postconditions
- eBay always reflects `available_quantity`, ensuring oversell prevention.

### Notes
- `Product.available_quantity` is the single source of truth for cross-marketplace sell quantity. This is a core architectural decision documented in `docs/INVENTORY_SYNC_SYSTEM.md`.

---

## TC-MKT-003-04: auto_sync_enabled=false Skips Push

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-003-04                                                      |
| **Priority**   | High                                                               |
| **Type**       | Feature                                                            |
| **Platform**   | eBay                                                               |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Inventory/PushInventoryTest.php`                    |

### Preconditions
- Product has a published EbayListing.
- eBay IntegrationCredential has `config.auto_sync_enabled = false`.
- Product `available_quantity` has changed.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Dispatch `PushInventoryToMarketplaces` for the product. | Job begins processing. |
| 2 | Job checks the eBay IntegrationCredential `config.auto_sync_enabled` flag. | Flag is `false`. |
| 3 | Job skips the eBay inventory push. | No eBay API calls are made. |
| 4 | Verify the Shopify adapter is still called (if Shopify credential has auto_sync_enabled=true). | Shopify push proceeds independently. |
| 5 | Verify no error is logged for the skipped push. | Job completes successfully; skip is a normal flow, not an error. |

### Test Data
- IntegrationCredential config: `{"auto_sync_enabled": false}`.

### Postconditions
- eBay inventory is NOT updated.
- Other marketplace adapters with auto_sync_enabled=true are unaffected.

### Notes
- The `auto_sync_enabled` flag is per-credential (per-marketplace), allowing users to pause sync for one marketplace without affecting others.

---

## TC-MKT-003-05: MarketplaceSyncRuleEngine Filtering

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-003-05                                                      |
| **Priority**   | High                                                               |
| **Type**       | Feature                                                            |
| **Platform**   | eBay                                                               |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Inventory/PushInventoryTest.php`                    |

### Preconditions
- Organization has marketplace sync rules configured (e.g., "Only sync products in category Electronics to eBay").
- Product A is in the "Electronics" category (should sync).
- Product B is in the "Clothing" category (should not sync).
- Both products have published EbayListings.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Dispatch `PushInventoryToMarketplaces` for Product A. | Job processes Product A. |
| 2 | `MarketplaceSyncRuleEngine` evaluates Product A against the sync rules. | Product A passes the filter (Electronics category matches the rule). |
| 3 | Verify eBay inventory push proceeds for Product A. | eBay API called with Product A's quantity. |
| 4 | Dispatch `PushInventoryToMarketplaces` for Product B. | Job processes Product B. |
| 5 | `MarketplaceSyncRuleEngine` evaluates Product B against the sync rules. | Product B does NOT pass the filter (Clothing category does not match). |
| 6 | Verify eBay inventory push is skipped for Product B. | No eBay API call for Product B. |

### Test Data
- Sync rule: `{platform: "ebay", filter_type: "category", filter_value: "Electronics", action: "include"}`.
- Product A: category "Electronics".
- Product B: category "Clothing".

### Postconditions
- Only Product A's inventory is pushed to eBay.
- Product B's eBay listing quantity remains unchanged.

### Notes
- Sync rules can be inclusive or exclusive. The rule engine should support multiple rule types (category, brand, price range, tag).

---

## TC-MKT-003-06: Sync Failure Logged But Not Thrown

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-003-06                                                      |
| **Priority**   | High                                                               |
| **Type**       | Negative                                                           |
| **Platform**   | eBay                                                               |
| **Automated**  | Yes                                                                |
| **Test File**  | `tests/Feature/Inventory/PushInventoryTest.php`                    |

### Preconditions
- Product has a published EbayListing.
- eBay API is mocked to return HTTP 500 (Internal Server Error).
- Product also has an active Shopify listing.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Dispatch `PushInventoryToMarketplaces` for the product. | Job processes the product across all marketplaces. |
| 2 | eBay adapter attempts the API call. | eBay returns HTTP 500. |
| 3 | Verify the eBay failure is caught and logged. | EbaySyncLog record created with `status=error` and the error details. Also logged to Laravel log. |
| 4 | Verify the Shopify adapter is still called. | Shopify inventory push proceeds despite the eBay failure. |
| 5 | Verify the job does NOT fail (does not go to failed_jobs). | Job completes successfully. Individual marketplace failures are logged, not thrown. |
| 6 | Verify the EbayListing status is NOT changed to `error`. | Listing status remains `published`; only the sync log records the transient failure. |

### Test Data
- Mock eBay response: HTTP 500 `{"errors": [{"message": "Internal Server Error"}]}`.
- Mock Shopify response: HTTP 200 (success).

### Postconditions
- eBay sync failure logged in EbaySyncLog.
- Shopify sync completed successfully.
- Job did not fail.
- EbayListing status unchanged.

### Notes
- Transient API failures should not block other marketplace syncs. The system should retry on the next inventory change or scheduled sync cycle. Persistent failures should trigger an alert after a configurable threshold.
