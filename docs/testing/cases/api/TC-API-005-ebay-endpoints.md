# TC-API-005: eBay Endpoints

| Field            | Value                                              |
|------------------|----------------------------------------------------|
| Document ID      | TC-API-005                                         |
| Domain           | API Endpoints                                      |
| Related Test Plan| TP-API (api-endpoints-test-plan.md)                |
| Last Updated     | 2026-04-15                                         |

---

## TC-API-005-01: GET /api/ebay/status Returns Config

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** User authenticated. eBay integration configured for organization.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | GET /api/ebay/status                | 200 response with eBay configuration                 |
| 2    | Verify connection status            | connected flag, token expiry, marketplace ID         |
| 3    | Verify sync settings                | Auto-sync enabled/disabled, sync interval            |
| 4    | No eBay integration configured      | 200 response with connected=false                    |
| 5    | Token expired                       | Response indicates token needs refresh               |

---

## TC-API-005-02: GET /api/ebay/stats Returns Counts

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** eBay integration active with listings.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | GET /api/ebay/stats                 | 200 response with listing statistics                 |
| 2    | Verify counts                       | active_listings, ended_listings, error_listings      |
| 3    | Verify revenue data                 | Total revenue from eBay sales                        |
| 4    | Verify org scoping                  | Stats only for current organization                  |
| 5    | No listings exist                   | 200 response with zero counts                        |

---

## TC-API-005-03: GET /api/ebay/listings Paginated

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** eBay listings exist for the organization.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | GET /api/ebay/listings              | 200 response with paginated listing data             |
| 2    | Verify listing fields               | ebay_item_id, title, price, status, quantity         |
| 3    | GET with status=active filter       | Only active listings returned                        |
| 4    | GET with page=2                     | Second page of results                               |
| 5    | Verify org scoping                  | Only current org listings returned                   |
| 6    | Verify product relationship         | Each listing includes linked product_id              |

---

## TC-API-005-04: POST /api/ebay/sync/product Dispatches Job

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P0                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** eBay integration active. Product exists with eBay listing.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | POST /api/ebay/sync/product with product_id | 200 response with job dispatched message      |
| 2    | Verify job queued                   | Sync job dispatched to queue                         |
| 3    | POST with invalid product_id        | 404 response                                        |
| 4    | POST with product not on eBay       | 422 response indicating no eBay listing              |
| 5    | Verify org scoping                  | Cannot sync products from another org                |

---

## TC-API-005-05: POST /api/ebay/sync/bulk

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** eBay integration active. Multiple products with eBay listings.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | POST /api/ebay/sync/bulk            | 200 response with batch job info                     |
| 2    | Verify batch dispatched             | Multiple sync jobs queued                            |
| 3    | Verify response contains count      | Number of products queued for sync returned          |
| 4    | POST with no syncable products      | 200 response with zero count                         |
| 5    | Verify rate limiting                | Cannot dispatch bulk sync too frequently             |

---

## TC-API-005-06: POST /api/ebay/retry/all

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** eBay listings exist with error status.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | POST /api/ebay/retry/all            | 200 response with retry info                         |
| 2    | Verify error listings queued        | Retry jobs dispatched for error listings              |
| 3    | Verify count returned               | Number of retried listings in response               |
| 4    | No error listings exist             | 200 response with zero count                         |
| 5    | Verify org scoping                  | Only retries current org error listings              |

---

## TC-API-005-07: GET /api/ebay/logs Filtered

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** eBay sync logs exist from prior sync operations.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | GET /api/ebay/logs                  | 200 response with paginated sync logs                |
| 2    | Verify log fields                   | action, status, product_id, message, timestamp       |
| 3    | GET with status=error filter        | Only error logs returned                             |
| 4    | GET with date range filter          | Logs within specified date range only                |
| 5    | Verify org scoping                  | Only current org logs returned                       |
| 6    | Verify chronological order          | Newest logs first                                    |

---

## TC-API-005-08: PATCH /api/ebay/config Updates Settings

| Field         | Value                     |
|---------------|---------------------------|
| Priority      | P1                        |
| Type          | Feature                   |
| Platform      | API                       |
| Automated     | Yes                       |

**Preconditions:** eBay integration configured. User has admin permissions.

| Step | Action                              | Expected Result                                      |
|------|-------------------------------------|------------------------------------------------------|
| 1    | PATCH /api/ebay/config with auto_sync=true | 200 response with updated config             |
| 2    | Verify setting persisted            | Auto-sync flag updated in integration credentials    |
| 3    | PATCH with invalid setting          | 422 response with validation error                   |
| 4    | PATCH without admin permissions     | 403 response                                        |
| 5    | Verify org scoping                  | Cannot update another org's eBay config              |
