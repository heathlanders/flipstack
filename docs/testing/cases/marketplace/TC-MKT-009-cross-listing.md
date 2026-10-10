# TC-MKT-009: Cross-Listing

| Field             | Value                                      |
|-------------------|--------------------------------------------|
| Document ID       | TC-MKT-009                                 |
| Domain            | Marketplace                                |
| Related Test Plan | TP-MKT-003 Cross-Marketplace Operations    |
| Last Updated      | 2026-04-15                                 |
| Controller        | CrossListingController                     |
| Routes            | marketplaces.cross-list.*                  |

---

## TC-MKT-009-01: Preview Cross-Listing Data Transformation

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-MKT-009-01                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | Yes                                                          |
| Test File      | tests/Feature/CrossListingTest.php                           |

**Preconditions:**
- User is authenticated with marketplace management permissions.
- A product exists with an active eBay listing (source marketplace).
- An active Shopify integration credential exists (target marketplace).

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | GET marketplaces.cross-list.preview with product_id, source=ebay, target=shopify | Preview response is returned with transformed listing data.  |
| 2    | Verify source data loaded                               | Preview shows the original eBay listing fields (title, description, price, images). |
| 3    | Verify data transformation applied                      | Fields are transformed to match Shopify's format and requirements. |
| 4    | Verify mapped fields displayed                          | Preview shows the target marketplace fields with mapped values. |
| 5    | Verify no listings created                              | No ShopifyListing or EbayListing records are created during preview. |

**Test Data:**
- Product with eBay listing: title, description, price=$49.99, condition="Used - Good".
- Target marketplace: Shopify.

**Postconditions:**
- No data is modified. Preview is read-only.

**Notes:**
- Data transformation includes field mapping, value conversion, and format adjustments specific to each marketplace.

---

## TC-MKT-009-02: Execute Cross-List from eBay to Shopify

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-MKT-009-02                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | Yes                                                          |
| Test File      | tests/Feature/CrossListingTest.php                           |

**Preconditions:**
- User is authenticated with marketplace management permissions.
- A product exists with an active eBay listing.
- An active Shopify integration credential exists.
- No Shopify listing exists for this product.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | POST marketplaces.cross-list.execute with product_id, source=ebay, target=shopify, listing_data | Cross-listing operation executes.                            |
| 2    | Verify ShopifyListing created                           | A new ShopifyListing record exists for the product.          |
| 3    | Verify listing data transformed                         | ShopifyListing fields contain correctly transformed data from eBay source. |
| 4    | Verify product linked to both marketplaces              | Product is now associated with both an EbayListing and a ShopifyListing. |
| 5    | Verify response                                         | Success response with the new listing details.               |

**Test Data:**
- product_id: existing product UUID.
- source: "ebay"
- target: "shopify"
- listing_data: transformed fields from preview.

**Postconditions:**
- A new ShopifyListing record exists linked to the product.

**Notes:**
- The actual Shopify API call to create the product on Shopify may be dispatched as a queued job.

---

## TC-MKT-009-03: Execute Cross-List from Shopify to eBay

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-MKT-009-03                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with marketplace management permissions.
- A product exists with an active Shopify listing.
- An active eBay integration credential exists.
- No eBay listing exists for this product.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | POST marketplaces.cross-list.execute with product_id, source=shopify, target=ebay, listing_data | Cross-listing operation executes.                            |
| 2    | Verify SyncProductToEbay job dispatched                 | A SyncProductToEbay job is dispatched to the queue.          |
| 3    | Verify listing data prepared                            | The job receives correctly transformed data from Shopify source. |
| 4    | Verify response                                         | Success response indicating the listing creation job has been queued. |

**Test Data:**
- product_id: existing product UUID with Shopify listing.
- source: "shopify"
- target: "ebay"
- listing_data: transformed fields from preview.

**Postconditions:**
- A SyncProductToEbay job is queued for processing.

**Notes:**
- eBay listing creation requires the Offer API flow (create inventory item, then create offer, then publish). This is handled asynchronously.

---

## TC-MKT-009-04: Data Transformer Maps eBay Condition to Shopify Tags

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-MKT-009-04                                                |
| Priority       | P2                                                           |
| Type           | Functional                                                   |
| Platform       | Backend                                                      |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- DataTransformer service is available.
- Source listing has an eBay condition value.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | Transform eBay condition "New" to Shopify format        | Shopify tags include "Condition:New".                        |
| 2    | Transform eBay condition "Used - Good" to Shopify format | Shopify tags include "Condition:Used - Good".                |
| 3    | Transform eBay condition "For parts or not working"     | Shopify tags include "Condition:For Parts".                  |
| 4    | Verify unmapped conditions handled gracefully           | Unknown conditions are passed through as-is in tags.         |

**Test Data:**
- eBay conditions: "New", "Used - Good", "Used - Acceptable", "For parts or not working".

**Postconditions:**
- No data is persisted. This tests the transformation logic.

**Notes:**
- Shopify does not have a native condition field. Conditions are typically stored as product tags or metafields.

---

## TC-MKT-009-05: Products Search for Cross-Listing

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-MKT-009-05                                                |
| Priority       | P2                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with marketplace management permissions.
- Organization has products, some with eBay-only listings, some with Shopify-only listings, and some with both.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | GET marketplaces.cross-list.products with target=shopify | Returns products that have an eBay listing but no Shopify listing. |
| 2    | Verify products with both listings excluded             | Products already listed on both marketplaces are not shown.  |
| 3    | GET marketplaces.cross-list.products with target=ebay   | Returns products that have a Shopify listing but no eBay listing. |
| 4    | Verify search functionality                             | Search by product title or SKU narrows results.              |

**Test Data:**
- Product A: eBay listing only (should appear when target=shopify).
- Product B: Shopify listing only (should appear when target=ebay).
- Product C: both listings (should not appear in either query).

**Postconditions:**
- No data is modified.

**Notes:**
- This endpoint helps users find products that are candidates for cross-listing.

---

## TC-MKT-009-06: Cannot Cross-List Without Active Credential

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-MKT-009-06                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with marketplace management permissions.
- A product exists with an eBay listing.
- No active Shopify integration credential exists for the organization (either missing or expired).

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | POST marketplaces.cross-list.execute with target=shopify | Cross-listing is rejected.                                   |
| 2    | Verify error response                                   | Error message indicates no active credential for the target marketplace. |
| 3    | Verify no listing created                               | No ShopifyListing record is created.                         |
| 4    | Test with expired credential                            | Same error if the credential exists but has expired OAuth token. |

**Test Data:**
- product_id: existing product UUID with eBay listing.
- target: "shopify"
- No active Shopify IntegrationCredential.

**Postconditions:**
- No data is modified. Cross-listing is blocked.

**Notes:**
- The UI should check credential status and disable the cross-list action before the user reaches this point, but the backend must also enforce this guard.
