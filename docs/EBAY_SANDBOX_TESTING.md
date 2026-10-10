# eBay Sandbox Testing

How `develop.flipstack.app` runs against the eBay sandbox safely. Production deploys at `flipstack.app` continue to use the live eBay API.

## How environment selection works

`config/services.php` `ebay.use_sandbox` evaluates to `true` whenever `APP_ENV !== 'production'`. The staging compose file sets `APP_ENV=staging`, so develop auto-selects sandbox. `EBAY_USE_SANDBOX=true|false` overrides if needed.

`EbayConfigService::getApiBaseUrl()` returns `api.sandbox.ebay.com` or `api.ebay.com`. Every code path that hits eBay must route through this service (no hardcoded `api.ebay.com` URLs).

## Sandbox seller account setup

1. Sign in at https://developer.ebay.com → **Test Users** tab.
2. Create a sandbox seller (and buyer for E2E order flows). eBay generates a test username + password.
3. In the sandbox app's **Application Keys**: copy `App ID`, `Cert ID`, `Dev ID`.
4. Set on develop server `.env`:
   ```
   EBAY_SANDBOX_CLIENT_ID=<App ID>
   EBAY_SANDBOX_CLIENT_SECRET=<Cert ID>
   EBAY_SANDBOX_RU_NAME=<RuName from sandbox app>
   ```
5. The sandbox app's RuName redirect URL must match: `https://develop.flipstack.app/ebay/oauth/callback`.

## Sandbox seller policies + merchant location

Sandbox sellers start empty. Before publishing offers, the sandbox account needs:

- **Fulfillment policy** — create via `/sell/account/v1/fulfillment_policy`
- **Payment policy** — create via `/sell/account/v1/payment_policy`
- **Return policy** — create via `/sell/account/v1/return_policy`
- **Merchant location key** — create via `/sell/inventory/v1/location/{locationKey}`

Easiest path: connect the sandbox seller through the app's normal OAuth flow on develop, then use the policy management UI (or `EbayPolicyService` via tinker) to seed policies. Test data must be created from scratch — production policies cannot be copied across.

## ECN (event notification) setup for sandbox

eBay requires HTTPS endpoints for ECN. Sandbox uses a separate destination registration from production.

1. In **eBay Developer Portal → Sandbox → Alerts and Notifications**, register destination URL:
   `https://develop.flipstack.app/api/webhooks/ebay/{credentialId}` (per-credential, recommended)
   or
   `https://develop.flipstack.app/api/webhooks/ebay` (legacy app-level)
2. For the legacy endpoint, set `EBAY_SANDBOX_NOTIFICATION_TOKEN` on the develop server — the controller reads the env-appropriate token automatically.
3. Per-credential endpoint uses `IntegrationCredential.config.ebay_notification_token`, generated on OAuth callback. No env var needed.
4. Subscribe to `MARKETPLACE_ACCOUNT_DELETION` topic.
5. Use the sandbox portal's **Send Test Notification** button to verify the challenge response and HMAC signature both succeed.

## What works on sandbox

- **Sell Inventory API** — create inventory items, offers, publish listings to sandbox
- **Sell Account API** — fulfillment / payment / return policies
- **Sell Fulfillment API** — orders, shipments, refunds (after a sandbox buyer purchases)
- **Sell Metadata API** — categories, item conditions, aspects
- **Trading API** (eBay messaging) — `GetMyMessages` works against `api.sandbox.ebay.com/ws/api.dll`
- **ECN webhooks** — challenge + HMAC

## What does NOT work on sandbox

- **Finding API** (`svcs.ebay.com`) — no sandbox endpoint exists at all. `EbayFindingService` short-circuits and returns empty when `isSandbox()` is true. Research / market-pricing flows that depend on Finding will produce no comps on develop.
- **Browse API** — sandbox returns stub/seeded data only. UPC lookup (`UpcLookupService`) and `EbayBrowseService` will run but results are unreliable for product research. Logged as warning each call.
- **Marketing API / Promoted Listings** — not available in sandbox. `EbayPromotedListingsService::syncStats()` is currently a stub anyway, so no behavior change.
- **Marketplace Insights API** — sandbox not supported.

## Cutover checklist for develop

- [ ] `APP_ENV=staging` (already set in `docker-compose.coolify-staging.yaml`)
- [ ] `EBAY_SANDBOX_CLIENT_ID/SECRET/RU_NAME` populated in `/data/flipstack-staging/shared/.env`
- [ ] `EBAY_SANDBOX_NOTIFICATION_TOKEN` populated (if using legacy webhook endpoint)
- [ ] Sandbox seller test user created in eBay Developer Portal
- [ ] OAuth flow executed on develop → `IntegrationCredential.environment='sandbox'`
- [ ] Sandbox policies + merchant location seeded
- [ ] ECN destination registered in sandbox portal
- [ ] Existing `IntegrationCredential` rows on develop with `environment='production'` deleted (re-OAuth required)

```sql
-- One-time cleanup of develop DB before sandbox cutover
DELETE FROM integration_credentials WHERE platform = 'ebay' AND environment = 'production';
DELETE FROM oauth_tokens WHERE platform = 'ebay' AND environment = 'production';
```
