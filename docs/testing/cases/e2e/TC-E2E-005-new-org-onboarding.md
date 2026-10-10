# New Organization Onboarding End-to-End Test

## Document ID: TC-E2E-005

**Systems Involved:** Web, Queue (Redis/Horizon), PostgreSQL, External APIs (eBay OAuth, Shopify OAuth)
**Business Process:** Complete new user registration through organization setup, marketplace connection, product import, fulfillment configuration, and first order receipt.
**Last Updated:** 2026-04-15

---

## 1. Workflow Overview

This test validates the full onboarding lifecycle for a new organization, from user registration through to receiving the first marketplace order. It exercises account creation, organization provisioning, onboarding wizard progression, OAuth marketplace connections, product import, settings configuration, and the order import pipeline.

```
  User registers            Organization created        Onboarding wizard starts
  (email + password)  ----> (auto-provisioned)  ------> (multi-step setup flow)
       |                         |                           |
       v                         v                           v
  Email verified            Team created                Step 1: Org details
  (if required)             (default team)              (name, subdomain, SKU prefix)
       |                         |                           |
       v                         v                           v
  First login               User assigned              Step 2: Connect marketplace
  (redirected to            owner role                  (eBay OAuth or Shopify OAuth)
   onboarding)                   |                           |
                                 v                           v
                            Onboarding progress         OAuth callback
                            tracked on org              (IntegrationCredential created)
                                                             |
                                                             v
                                                        Step 3: Import products
                                                        (store import job dispatched)
                                                             |
                                                             v
                                                        Step 4: Configure settings
                                                        (fulfillment, currency, etc.)
                                                             |
                                                             v
                                                        Step 5: First order test
                                                        (order sync from marketplace)
                                                             |
                                                             v
                                                        Onboarding complete
                                                        (redirect to main dashboard)
```

**Trigger:** User navigates to registration page and creates a new account
**End State:** Organization fully configured with marketplace connected, products imported, settings configured, and first order received. User sees the main dashboard with populated KPIs.

---

## 2. Preconditions

| # | Requirement | Setup Method |
|---|-------------|--------------|
| 1 | Clean test environment with no pre-existing user for the test email | Database reset or unique email |
| 2 | eBay sandbox app credentials configured in `.env` (`EBAY_CLIENT_ID`, `EBAY_CLIENT_SECRET`, `EBAY_REDIRECT_URI`) | Environment configuration |
| 3 | Shopify partner app credentials configured in `.env` (or skip Shopify steps) | Environment configuration |
| 4 | Redis and Horizon running for queue job processing | Docker environment |
| 5 | Mail driver configured (log or mailtrap) for email verification if enabled | `.env` MAIL_MAILER setting |
| 6 | No rate limiting active that would block rapid test actions | Test environment configuration |

---

## 3. Test Scenario

| Step | Actor | System | Action | Expected Result | Verification Method |
|------|-------|--------|--------|-----------------|---------------------|
| 1 | User | Web UI | Navigate to registration page. Fill in name, email, password, password_confirmation. Submit registration form | User record created in `users` table. Organization auto-created via `OrganizationService`. Default Team created and linked to organization. User assigned `owner` role in the team. Onboarding progress initialized on organization. Session established (user logged in). Redirect to onboarding wizard or dashboard | DB: Assert `users` record exists with email. Assert `organizations` record created with `user.organization_id` matching. Assert `teams` record with `organization_id`. Assert `memberships` record linking user to team with owner role. Assert `organization.onboarding_progress` initialized |
| 2 | User | Web UI | Onboarding wizard Step 1: Organization setup. Enter organization name, subdomain, SKU prefix. Submit | Organization record updated with name, subdomain. `OrganizationSettings` or `organization.sku_prefix` set. Subdomain validated for uniqueness and format. Onboarding progress updated to reflect step 1 completion | DB: Assert `organizations.name` and `organizations.subdomain` updated. Assert SKU prefix stored. UI: Onboarding progress indicator advances to step 2 |
| 3 | User | Web UI | Onboarding wizard Step 2: Connect marketplace. Click "Connect eBay" button. Redirect to eBay OAuth consent page | User redirected to eBay sandbox OAuth URL with correct `client_id`, `redirect_uri`, `scope`, and `state` parameter. State parameter stored in session for CSRF validation | Browser: Verify redirect URL contains eBay OAuth domain with correct query parameters. Session: State token stored |
| 4 | User | eBay OAuth | Authorize the application on eBay consent page. eBay redirects back to application callback URL with authorization code | Application receives callback at configured `redirect_uri`. Authorization code exchanged for access_token and refresh_token via eBay OAuth token endpoint. `IntegrationCredential` created with `platform = 'ebay'`, `is_active = true`. `OAuthToken` created with encrypted access_token, refresh_token, expires_at. Onboarding progress updated | DB: Assert `integration_credentials` record with platform='ebay', organization_id matching. Assert `oauth_tokens` record with non-null access_token and refresh_token. Assert token expires_at is in the future |
| 5 | User | Web UI | Onboarding wizard Step 3: Import products. Click "Import eBay Store" button. Store import job dispatched to queue | eBay store import job dispatched (e.g., `ImportEbayStoreListings`). Job fetches active listings from eBay via the FindingService or Sell API. Products created in the organization with data from eBay listings. EbayListing records created linking products to eBay item IDs. Flash message: import queued or progress shown | Queue: Assert import job dispatched. After job completes: Assert `products` records created with `organization_id`. Assert `ebay_listings` records created with `product_id` references. Assert product `sku`, `name`, `selling_price` populated from eBay data |
| 6 | User | Web UI | Onboarding wizard Step 4: Configure settings. Set default inventory location (name, code). Set fulfillment preferences (default carrier, auto-pick settings). Configure currency (base currency = USD) | InventoryLocation created for the organization (is_active = true, is_default = true). Fulfillment settings stored in organization settings. Currency preferences set. Onboarding progress updated | DB: Assert `inventory_locations` record with organization_id, is_active=true. Assert organization settings reflect fulfillment preferences. UI: Settings confirmation shown |
| 7 | User | Web UI | Onboarding wizard Step 5: First order test. Trigger an eBay order sync (either manually or wait for a test order placed in sandbox). OrderSyncService processes the order | `OrderSyncService` fetches orders from eBay API. Order record created with `organization_id`, `marketplace = 'ebay'`, `status = 'pending'`. OrderItem records created. `reserveInventoryForOrder()` decrements `Product.available_quantity`. `PushInventoryToMarketplaces` job dispatched to sync new quantities | DB: Assert `orders` record with marketplace='ebay', status='pending'. Assert `order_items` records linked. Assert product `available_quantity` decremented by ordered quantity. Queue: Assert `PushInventoryToMarketplaces` dispatched |
| 8 | User | Web UI | Onboarding complete. User redirected to main dashboard. Dashboard displays populated KPIs from imported data | Main dashboard loads with `dashboardType = 'main'`. `metrics.inventory.total_products` reflects imported product count. `financialSummary` shows inventory value. `marketplaceConnections` shows eBay as connected. Onboarding wizard no longer shown (progress marked complete) | UI: Dashboard renders with non-zero product count. eBay connection shows status='connected'. No onboarding overlay or redirect |

---

## 4. Data Flow Verification

| Checkpoint | After Step | Expected State | Verification Query/Check |
|------------|-----------|----------------|--------------------------|
| User and Org Created | Step 1 | User exists with organization_id set. Organization exists with onboarding_progress initialized. Team and membership created. User has owner role | `SELECT u.id, u.organization_id, o.name, o.onboarding_progress FROM users u JOIN organizations o ON u.organization_id = o.id WHERE u.email = '{test_email}'` |
| Org Configured | Step 2 | Organization has subdomain, name updated. SKU prefix set for auto-numbering | `SELECT name, subdomain, sku_prefix FROM organizations WHERE id = '{org_id}'` -- Assert all non-null |
| eBay Connected | Step 4 | IntegrationCredential exists (platform=ebay, is_active=true). OAuthToken with valid tokens and expiry | `SELECT ic.platform, ic.is_active, ot.expires_at FROM integration_credentials ic LEFT JOIN oauth_tokens ot ON ot.credential_id = ic.id WHERE ic.organization_id = '{org_id}' AND ic.platform = 'ebay'` |
| Products Imported | Step 5 | Products created from eBay store. EbayListings linked. Stock quantities initialized | `SELECT COUNT(*) as product_count FROM products WHERE organization_id = '{org_id}'` -- Assert > 0. `SELECT COUNT(*) FROM ebay_listings WHERE organization_id = '{org_id}'` -- Assert > 0 |
| Settings Configured | Step 6 | InventoryLocation created. Fulfillment settings stored | `SELECT COUNT(*) FROM inventory_locations WHERE organization_id = '{org_id}' AND is_active = true` -- Assert >= 1 |
| First Order Received | Step 7 | Order with items exists. Product available_quantity decremented | `SELECT COUNT(*) FROM orders WHERE organization_id = '{org_id}'` -- Assert >= 1 |
| Onboarding Complete | Step 8 | Dashboard loads with real data. Onboarding marked complete on organization | `SELECT onboarding_progress FROM organizations WHERE id = '{org_id}'` -- Assert complete/finished state |

---

## 5. Error Scenarios

| ID | Error Point | Error Condition | Expected Recovery | Verified |
|----|-------------|-----------------|-------------------|----------|
| E2E-5-ERR-01 | Step 3 | eBay OAuth consent fails (user denies access or eBay sandbox down) | Callback receives error parameter instead of authorization code. Controller detects error, redirects back to onboarding step 2 with error flash message: "Failed to connect eBay account." User can retry connection. Onboarding does not advance past step 2 | [ ] |
| E2E-5-ERR-02 | Step 4 | eBay token exchange fails (invalid authorization code, expired code, network timeout) | OAuth service catches exception during token exchange. No IntegrationCredential created. Error logged. User redirected to onboarding with error: "Could not complete eBay authorization." Retry possible | [ ] |
| E2E-5-ERR-03 | Step 5 | Product import job times out (large eBay store with thousands of listings) | Import job has `$timeout` configured. On timeout, Horizon kills the worker. Job retried up to `$tries` times. If all retries exhausted: `failed()` method logs error, notification sent to user. Partial imports may exist (products created before timeout). User can retry import or wait for scheduled sync | [ ] |
| E2E-5-ERR-04 | Step 5 | eBay API rate limit hit during product import | eBay API returns 429 Too Many Requests. Import job should implement backoff/retry. `EbaySyncLog` records the rate limit event. Job retried after backoff period. User sees partial import progress | [ ] |
| E2E-5-ERR-05 | Step 2 | Subdomain already taken by another organization | Validation catches duplicate subdomain. Form returns with error: "This subdomain is already in use." User must choose a different subdomain. Organization record not updated | [ ] |
| E2E-5-ERR-06 | Step 1 | Registration with duplicate email | Laravel validation catches existing email. Registration form returns with error: "The email has already been taken." No user or organization created | [ ] |
| E2E-5-ERR-07 | Step 6 | Onboarding abandoned midway (user closes browser after step 3) | Onboarding progress saved at each step. On next login, user redirected to the last incomplete step. eBay connection from step 3/4 preserved. No data loss. User resumes from where they left off | [ ] |
| E2E-5-ERR-08 | Step 7 | Order sync fails (eBay token expired during onboarding) | `OrderSyncService` detects expired token. `RefreshOAuthTokens` job should have refreshed it automatically. If refresh also fails: sync error logged, user notified. User may need to re-authorize eBay (return to connection step). Manual token refresh available in settings | [ ] |

---

## 6. Performance Expectations

| Metric | Acceptable | Measured |
|--------|-----------|----------|
| User registration + organization provisioning (Step 1) | < 2 seconds (user create, org create, team create, membership create, role assign) | |
| Organization settings update (Step 2) | < 500ms | |
| eBay OAuth redirect generation (Step 3) | < 1 second (URL construction + session state storage) | |
| eBay token exchange (Step 4) | < 5 seconds (external API call to eBay OAuth endpoint) | |
| Product import job completion for 100 listings (Step 5) | < 60 seconds (eBay API pagination + product creation) | |
| Product import job completion for 1000 listings (Step 5) | < 10 minutes (batched API calls + bulk inserts) | |
| Settings configuration save (Step 6) | < 500ms | |
| Order sync for first order (Step 7) | < 10 seconds (API fetch + order + items + inventory reservation) | |
| Dashboard initial load with imported data (Step 8) | < 3 seconds (aggregation queries on fresh data) | |

---

## 7. Cleanup / Rollback

| Step | Action |
|------|--------|
| 1 | Delete test Orders and OrderItems created during step 7 |
| 2 | Delete EbayListing records created during product import |
| 3 | Delete Products created during import (force delete to bypass soft deletes) |
| 4 | Delete OAuthToken records for the test organization |
| 5 | Delete IntegrationCredential records for the test organization |
| 6 | Delete InventoryLocation records for the test organization |
| 7 | Delete Membership, Team records for the test organization |
| 8 | Delete Organization record |
| 9 | Delete User record |
| 10 | Revoke eBay sandbox app authorization if needed (via eBay sandbox developer portal) |
| 11 | Clear failed_jobs table entries related to test organization |
| 12 | Clear EbaySyncLog entries for the test organization |

---

## 8. Notes

- **Registration flow:** The registration process is handled by Laravel Jetstream/Fortify. The `CreatesNewUser` action creates the user, and custom logic in `OrganizationService` or an observer provisions the organization, team, and role assignment.
- **Onboarding progress tracking:** The organization record tracks onboarding progress (likely a JSON column or dedicated fields). Each onboarding step updates this state so the wizard can resume from the correct step.
- **OAuth state parameter:** The state parameter in the OAuth flow prevents CSRF attacks. It must be generated before redirect and validated on callback. Missing or mismatched state should abort the OAuth flow.
- **eBay sandbox vs production:** This test should run against the eBay sandbox environment. Sandbox credentials are configured separately from production. Ensure `.env` points to sandbox endpoints for testing.
- **Shopify alternative path:** If testing Shopify instead of eBay, replace steps 3-5 with Shopify OAuth flow and shop import. The onboarding wizard supports both marketplaces. The Shopify flow uses `ShopifyAuthController` for OAuth and store import.
- **Idempotent import:** The product import should handle re-import gracefully (skip already-imported listings based on eBay item ID matching). Running import twice should not create duplicate products.
- **Multi-tenant isolation from step 1:** From the moment the organization is created, all data must be scoped to it. Even during onboarding, queries must filter by organization_id to prevent cross-org data leakage.
- **Queue dependency:** Steps 5 and 7 depend on queue job execution. In a test environment, jobs may be executed synchronously (`QUEUE_CONNECTION=sync`) or require Horizon to be running. Verify the queue connection setting for the test environment.
