# Amazon SP-API Production Cutover Checklist

How to take the Amazon integration live for the first paying seller. Code shipped across 11 phases on `develop` (commits `927931c` … `943c736`); production go-live is gated on Amazon's app review (2-6 weeks) and on the production env config below.

For day-to-day sandbox testing, see `AMAZON_SANDBOX_TESTING.md`.

## Status snapshot

| Item | Status |
|---|---|
| Code: adapter, OAuth, listing, inventory/price sync, order sync, messaging, repricing, notifications | DONE |
| Tests: 36 Amazon-related, 89 assertions | DONE |
| Sandbox endpoint + `sandbox:status` command | DONE |
| `amazon:health` operator command | DONE |
| Amazon Developer App registration + LWA security profile | **PENDING** |
| Amazon SP-API role approval + app published | **PENDING (2-6 wk)** |
| Production AWS SNS topic + IAM policy | **PENDING** |
| Production server `.env` populated | **PENDING** |
| First production seller OAuth → list → order → ship | **PENDING** |

## Step 1 — Amazon Developer Account + LWA app

Once per organization. Skip if already done.

1. Sign in at https://developer.amazon.com → **Apps & Services** → **Login With Amazon**.
2. Create a Security Profile. Capture `client_id` (`amzn1.application-oa2-client.…`) and `client_secret`.
3. Add Allowed Return URLs:
   - `https://flipstack.app/amazon/auth/callback`
   - `https://develop.flipstack.app/amazon/auth/callback`
4. Apps & Services → **Selling Partner API** → register a new app.
5. Pick the operations roles your sellers need. At minimum:
   - Inventory and Order Tracking
   - Pricing
   - Buyer-Seller Messaging
   - Brand Analytics (only if Brand Registry enrolled)
6. App lives in **Draft** until you click **Publish**. Sellers cannot authorize a Draft app.
7. Submit for Amazon review. **Timeline: 2-6 weeks.** Approval is required before any production seller can authorize.

## Step 2 — AWS SNS topic for SP-API notifications

The destination ARN is shared across all tenants — `RegisterAmazonNotifications` looks up an existing destination by ARN before creating one.

1. AWS console → SNS → Create topic → Standard. Name: `flipstack-amazon-spapi-prod`.
2. Add an HTTPS subscription pointing at `https://flipstack.app/api/webhooks/amazon`. SNS sends a `SubscriptionConfirmation`; `AmazonNotificationController::handleSubscriptionConfirmation` auto-confirms when the host matches `sns.<region>.amazonaws.com`.
3. Attach an IAM policy to the topic that grants Amazon's SP-API service principal `sns:Publish`. Amazon publishes the policy template at https://developer-docs.amazon.com/sp-api/docs/notifications-api-v1-use-case-guide#step-1-create-an-amazon-sns-topic.
4. Capture the topic ARN: `arn:aws:sns:us-east-1:000000000000:flipstack-amazon-spapi-prod`.

Repeat with a separate topic for develop (`flipstack-amazon-spapi-dev`) if you want SNS deliveries on the sandbox env. Sandbox does **not** emit real events; useful only for replay testing via `aws sns publish`.

## Step 3 — Production `.env`

Set on the prod server (Coolify):

```
AMAZON_LWA_CLIENT_ID=amzn1.application-oa2-client.<from step 1>
AMAZON_LWA_CLIENT_SECRET=<from step 1>
AMAZON_REDIRECT_URI=https://flipstack.app/amazon/auth/callback
AMAZON_REGION=us-east-1
AMAZON_PRODUCTION_ENDPOINT=https://sellingpartnerapi-na.amazon.com
AMAZON_MARKETPLACE_ID=ATVPDKIKX0DER
AMAZON_USE_SANDBOX=false
AMAZON_SNS_ARN=arn:aws:sns:us-east-1:000000000000:flipstack-amazon-spapi-prod
```

For non-US marketplaces:

| Region | Endpoint | Marketplace ID |
|---|---|---|
| US | sellingpartnerapi-na.amazon.com | ATVPDKIKX0DER |
| CA | sellingpartnerapi-na.amazon.com | A2EUQ1WTGCTBG2 |
| MX | sellingpartnerapi-na.amazon.com | A1AM78C64UM0Y8 |
| UK | sellingpartnerapi-eu.amazon.com | A1F83G8C2ARO7P |
| DE | sellingpartnerapi-eu.amazon.com | A1PA6795UKMFR9 |
| JP | sellingpartnerapi-fe.amazon.com | A1VC38T7YXB528 |

Multi-region requires per-region OAuth. Phase 0 question — defer until needed.

Deploy. The app picks up env via `php artisan config:cache` on container start.

## Step 4 — Verify the cutover

```bash
docker exec flipstack_app_1 php artisan amazon:health --skip-api
```

Expect:

- Configuration table shows `Environment: production`, all env keys `set`.
- "No active Amazon credentials" message until the first seller authorizes — that's correct.

## Step 5 — First production seller

1. Seller clicks **Connect Amazon** on `https://flipstack.app/marketplaces/amazon`.
2. Amazon redirects to `sellercentral.amazon.com/apps/authorize/consent` (NOT the LWA dialog — sellers come through SP-API authorization workflow).
3. Seller approves; Amazon redirects to `/amazon/auth/callback?spapi_oauth_code=…&selling_partner_id=…&state=…`.
4. `AmazonOAuthController::callback`:
   - exchanges the code for an LWA refresh token via `api.amazon.com/auth/o2/token`
   - calls `/sellers/v1/marketplaceParticipations` to capture seller_id + marketplace details
   - persists IntegrationCredential + OAuthToken
   - dispatches `RegisterAmazonNotifications` which creates SNS subscriptions
5. Run health check:
   ```bash
   docker exec flipstack_app_1 php artisan amazon:health --credential=<id>
   ```
   Expected: `verifyCredentials: ok`, `Subscribed types: 8 / 8` (or 6-7/8 if some types require additional roles).

## Step 6 — End-to-end smoke test

Either ask the seller to place a real order, or use a low-value test order:

| Test | Pass criteria |
|---|---|
| List a product | `AmazonListing` row created with `status=created`. After 24h ASIN propagation, SNS `LISTINGS_ITEM_STATUS_CHANGE` flips to `status=active`. |
| Receive an order | `Order` row with `marketplace=amazon`, items matched to products, `Product.available_quantity` decremented, `PushInventoryToMarketplaces` dispatched (visible in Horizon). |
| Ship the order | `UploadTrackingToAmazon` dispatched, `POST_ORDER_FULFILLMENT_DATA` feed accepted (`AmazonFeed` status `DONE`). |
| Backfill fees | Within 24h, hourly `SyncAmazonOrderFinancials` populates `Order.marketplace_fees` and creates `MarketplaceFee` rows. |
| Buy-box repricing | `UpdateAmazonBuyBoxPrices` populates `AmazonListing.buy_box_price`. Repricing rule with `strategy=match_buy_box` updates `Product.selling_price` → cascades to `MarketplacePriceSync` → SP-API patch. |

## Rate limits

`AmazonRateLimiter` reads `x-amzn-RateLimit-Limit` from response headers and adapts. Defaults are conservative. If you hit 429s in monitoring:

- Check Horizon for queue depth on `amazon-listings`, `amazon-inventory` queues.
- Listings Items PATCH is the tightest endpoint at 5 req/sec. Concurrent `PushInventoryToMarketplaces` jobs from a busy seller can saturate it. Reduce Horizon `maxProcesses` for those queues if needed.

## Monitoring + alerts

- `AmazonApiException` count over 24h. Spike = LWA token revoked, SP-API outage, or seller deauthorized.
- `AmazonListing.consecutive_errors >= 5` count. Per-listing flag — UI surfaces these on the Logs page.
- `failed_jobs` table filtered by job classes under `App\Jobs\Amazon\*` and `App\Jobs\Fulfillment\SyncAmazonOrders`.
- Horizon dashboard for queue depth on `amazon-listings`, `amazon-inventory`, `amazon`.

## Rollback

If the integration must be turned off (compliance, fraud, etc.):

1. Set `AMAZON_USE_SANDBOX=true` on production. Existing tokens remain valid against sandbox endpoint, but no real seller traffic flows. Quick reversible kill switch.
2. To fully disconnect a single seller: `IntegrationCredential::where('id', X)->update(['is_active' => false])`. The OAuth token row is also marked inactive. Listings, orders, history retained.
3. To purge a tenant entirely: `php artisan sandbox:cutover --confirm` — drops Amazon credentials whose env doesn't match current. Re-OAuth required.

## Open work post-launch

- **Inbound buyer messages** (Phase 7b): Amazon has no inbound API. Options: forward Seller Central notification emails to a webhook (Gmail MCP integration is already authenticated for the org).
- **Frontend gaps** (deferred from Phase 10): dedicated `Setup.vue`, `Listings.vue`, `Import.vue`, `Match/Review.vue`. Stubbed today; full UI lands when listing volume justifies.
- **Multi-region** support. Single-region today (US default). Adding EU/FE means per-region OAuth and currency-aware fee tracking.
- **Brand Registry** gating: pre-flight check before allowing new ASIN creation for branded products.
