# TC-MKT-013: Multi-Tenant Sync Isolation

| Field              | Value                                                                 |
|--------------------|-----------------------------------------------------------------------|
| **Document ID**    | TC-MKT-013                                                            |
| **Domain**         | Marketplace Integration — Multi-Tenant Boundary                       |
| **Related Test Plan** | `marketplace-ebay-test-plan.md`, `marketplace-shopify-test-plan.md` |
| **Last Updated**   | 2026-04-27                                                            |

---

## Overview

Verifies that all marketplace sync paths respect organization boundaries. Two organizations (Org A and Org B) each have independent eBay + Shopify credentials and listings; no operation initiated by one org may read or write the other org's data. `COVERAGE_MATRIX.md` flags multi-tenant isolation as a **critical gap** with no dedicated tests today — this case closes that gap for the marketplace surface.

**Scope:** marketplace credentials, listings, webhooks, sync jobs, sync rules. Auth/RBAC and ProductPolicy isolation are covered separately under `auth-org-test-plan.md`.

---

## TC-MKT-013-01: PushInventoryToMarketplaces Loads Only Org's Credentials

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-013-01                                                      |
| **Priority**   | Critical (P0)                                                      |
| **Type**       | Feature                                                            |
| **Platform**   | Backend                                                            |
| **Automated**  | Yes (target)                                                       |
| **Test File**  | `tests/Feature/Inventory/MultiTenantSyncIsolationTest.php` (to create) |

### Preconditions

- Org A: 1 active eBay credential + 1 active Shopify credential.
- Org B: 1 active eBay credential + 1 active Shopify credential (different `ebay_user_id`, different Shopify shop domain).
- Org A has Product P_A with `EbayListing` and `ShopifyListing`.
- Org B has Product P_B with `EbayListing` and `ShopifyListing`.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Dispatch `PushInventoryToMarketplaces` for P_A's UUID. | Job loads Product P_A by UUID. |
| 2 | Inspect `MarketplaceInventorySync::pushQuantityToAll()` credential lookup. | Only Org A's eBay + Shopify credentials are loaded (filtered by `organization_id`). |
| 3 | Verify the eBay API call carries Org A's access token. | Authorization header = `Bearer {orgA_token}`, never Org B's. |
| 4 | Verify the Shopify API call uses Org A's shop domain. | Request URL host = `{orgA_shop}.myshopify.com`. |
| 5 | Repeat with P_B; confirm Org B credentials used. | Org A credentials never appear in Org B's flow. |
| 6 | Verify no cross-org `EbaySyncLog` rows exist. | All logs scoped to the originating org. |

### Postconditions

- No credential leakage across orgs.

---

## TC-MKT-013-02: eBay ECN Webhook Routes to Correct Organization

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-013-02                                                      |
| **Priority**   | Critical (P0)                                                      |
| **Type**       | Integration                                                        |
| **Platform**   | API webhook                                                        |
| **Automated**  | Yes (target)                                                       |

### Preconditions

- Org A and Org B both have eBay credentials with distinct `ebay_user_id` values stored on the credential.
- ECN verification token configured for the application (`IntegrationCredential.config.ebay_notification_token`).

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST a valid ECN payload (e.g., `MARKETPLACE_ACCOUNT_DELETION` or order-event) with HMAC computed for Org A's user. | `EbayNotificationController` dispatches `ProcessEbayNotification` job tagged with Org A's `organization_id`. |
| 2 | Verify the job processes only Org A's data. | Org A `IntegrationCredential` queried; Org B credentials never touched. |
| 3 | POST a payload for an `ebay_user_id` that matches NO org. | Controller returns 200 (per eBay spec) but logs an "unmatched ebay_user_id" warning; no job dispatched. |
| 4 | POST a payload with HMAC computed using a wrong secret. | Controller rejects with 401/403; no job dispatched; `Log::warning` records the rejection. |
| 5 | Send the verification challenge to `GET /api/webhooks/ebay`. | Controller returns the `challengeResponse` hash matching the configured token. |

### Postconditions

- ECN webhook is routed only to the matching org.
- Bad signatures and unmatched users do not contaminate any org's data.

### Notes

- This case validates the `IntegrationCredential.config.ebay_notification_token` configuration described in the Inventory Sync System docs.

---

## TC-MKT-013-03: Shopify Webhook HMAC Verified Per-Org

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-013-03                                                      |
| **Priority**   | Critical (P0)                                                      |
| **Type**       | Integration                                                        |
| **Platform**   | API webhook                                                        |
| **Automated**  | Yes (extend `tests/Feature/Shopify/ShopifyWebhookTest.php`)        |

### Preconditions

- Org A and Org B each have a Shopify credential with distinct shared secrets.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | POST a webhook with `X-Shopify-Shop-Domain: orgA.myshopify.com` and HMAC computed using Org A's secret. | Webhook accepted; `ProcessShopifyWebhook` job tagged with Org A's id. |
| 2 | POST a webhook with `X-Shopify-Shop-Domain: orgA.myshopify.com` but HMAC computed using Org B's secret. | Webhook rejected (401); no job dispatched; warning logged. |
| 3 | POST a webhook with shop domain not matching any org. | 404 / 401; no job dispatched. |
| 4 | POST a valid Org B webhook. | Routed to Org B; Org A unaffected. |

### Postconditions

- Webhook HMAC validation is per-org; no cross-org acceptance.

---

## TC-MKT-013-04: Cross-Listing UI Cannot See Another Org's Products

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-013-04                                                      |
| **Priority**   | High (P1)                                                          |
| **Type**       | Feature                                                            |
| **Platform**   | Web                                                                |

### Preconditions

- User U_A logged in to Org A. User U_B exists in Org B.
- Org B has products and listings.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | U_A opens Cross-Listing preview (`GET marketplaces.cross-list.preview?product_id={P_B.id}`). | 403 Forbidden or 404 (org scope filter blocks the lookup). |
| 2 | U_A POSTs `marketplaces.cross-list.execute` with P_B's UUID. | Request rejected; no listing created in Org B's data. |
| 3 | U_A views their own product list. | Only Org A's products returned (verify SQL filter `WHERE organization_id = U_A.organization_id`). |
| 4 | U_A attempts to load a sync rule belonging to Org B by ID. | Rule not found / 403. |

### Postconditions

- Cross-org data access via the marketplace UI is impossible.

---

## TC-MKT-013-05: MarketplaceSyncRuleEngine Respects Org Scope

| Field          | Value                                                              |
|----------------|--------------------------------------------------------------------|
| **ID**         | TC-MKT-013-05                                                      |
| **Priority**   | High (P1)                                                          |
| **Type**       | Unit                                                               |
| **Platform**   | Backend                                                            |
| **Automated**  | Yes (extend `tests/Unit/Services/MarketplaceSyncRuleEngineTest.php`) |

### Preconditions

- Org A has rule R_A: `marketplace=ebay, scope=category, value=Electronics, action=do_not_sync`.
- Org B has rule R_B: `marketplace=ebay, scope=all, action=sync`.
- Product P_A_elec belongs to Org A in Electronics category.
- Product P_B_elec belongs to Org B in Electronics category.

### Test Steps

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Call `shouldSync(P_A_elec, "ebay")`. | Returns false (Org A rule R_A blocks Electronics). Org B's rules NOT loaded. |
| 2 | Call `shouldSync(P_B_elec, "ebay")`. | Returns true (Org B rule R_B permits all). Org A's rules NOT loaded. |
| 3 | Inspect engine's rule-loading query. | Filters by `organization_id`; never returns rows from the other org. |

### Postconditions

- Rule evaluation strictly org-scoped.

---

## Cross-Cutting Verification

For each sub-case above, also verify:

- No raw SQL `WHERE` clause omits `organization_id` for tables that have it.
- All factories used in tests must take an `organization_id` parameter explicitly to avoid accidental cross-pollination.
- `tests/Traits/CreatesOrganizationFixtures` is the canonical setup.

## Notes

- This case complements (but does not replace) auth-level isolation in `auth-org-test-plan.md`.
- New marketplaces added later (per `marketplace-sync` skill) **must** include their own TC-MKT-013-style isolation sub-case.
