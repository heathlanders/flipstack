# Amazon SP-API Sandbox Testing

How develop / staging exercises the Amazon Selling Partner API safely. Production at `flipstack.app` continues to talk to the live SP-API.

## How environment selection works

`config/services.php` `amazon.use_sandbox` evaluates to `true` whenever `APP_ENV !== 'production'`. Override with `AMAZON_USE_SANDBOX=true|false`.

`AmazonConfigService` exposes:

- `getEnvironment()` -> `'sandbox'` or `'production'`
- `isSandbox()` -> bool
- `getSpApiEndpoint()` -> reads `services.amazon.{environment}.sp_api_endpoint`
- `getMarketplaceId()` -> reads `services.amazon.{environment}.marketplace_id`

Every Amazon API caller must go through `AmazonConfigService` or `AmazonBaseService` (which consumes it). No hardcoded `sellingpartnerapi-na.amazon.com` URLs.

Sandbox host: `https://sandbox.sellingpartnerapi-na.amazon.com`.
Production host: `https://sellingpartnerapi-na.amazon.com`.

## Required env vars

Set on develop / staging:

```
AMAZON_LWA_CLIENT_ID=<from developer.amazon.com app>
AMAZON_LWA_CLIENT_SECRET=<from developer.amazon.com app>
AMAZON_REDIRECT_URI=https://develop.flipstack.app/amazon/auth/callback
AMAZON_SANDBOX_ENDPOINT=https://sandbox.sellingpartnerapi-na.amazon.com   # default
AMAZON_SANDBOX_MARKETPLACE_ID=ATVPDKIKX0DER                                # US, default
AMAZON_USE_SANDBOX=true                                                   # auto when APP_ENV != production
```

Production uses `AMAZON_PRODUCTION_ENDPOINT` and `AMAZON_MARKETPLACE_ID`. The Amazon LWA app must be approved + published before any seller (sandbox or live) can authorize.

## SP-API sandbox quirks

The SP-API sandbox is **not** a free-form test marketplace. It is a request-shape validator with a mix of static and dynamic responses. Plan tests accordingly.

| Endpoint family | Sandbox behavior |
|---|---|
| Sellers | Returns hardcoded `marketplaceParticipations` for the LWA token. Useful for connection verification. |
| Catalog Items | Returns canned ASINs for known query params (e.g. `keywords=test`). |
| Listings Items | Validates request shape; returns `ACCEPTED` for valid PUT/PATCH; does not actually create a listing. |
| Inventory FBM | Accepts patches, returns `ACCEPTED`; quantity is not persisted across calls. |
| Inventory FBA | Returns canned summary for `MFN-SKU-001` etc. |
| Pricing | Returns canned competitive pricing for known ASINs. |
| Orders | Returns a small fixed list of test orders. Order details (`/orders/v0/orders/{id}`) work for those test IDs. |
| Reports / Feeds | Simulates async report/feed processing. Status transitions work; the document content is canned. |
| Notifications | `createDestination` and `createSubscription` succeed but no real events fire. Use `aws sns publish` to push test SNS messages to your endpoint. |

Practical implication: most of our integration can be exercised against sandbox to validate request bodies, signing, error handling, and rate-limit behavior. **Actual end-to-end listing -> order -> ship requires a production seller account in a low-volume marketplace, or a Vendor Central preview account.**

## Sandbox seller setup

1. Register an SP-API app at https://developer.amazon.com.
2. Open **Login With Amazon** -> create LWA security profile -> capture `client_id` + `client_secret`.
3. Add `https://develop.flipstack.app/amazon/auth/callback` as an **Allowed Return URL** on the LWA security profile.
4. In the SP-API app, set the OAuth redirect URI to the same URL.
5. Promote the app to **Draft -> Listed** (no review required for sandbox-only use).
6. Click "Connect to Amazon" on `https://develop.flipstack.app/marketplaces/amazon` -> log in with any Amazon account -> consent -> redirect back. The sandbox endpoint is hit on first `verifyCredentials()`.

## Verifying the connection

After OAuth callback completes:

```bash
docker exec ecommerce_app php artisan sandbox:status
```

The `Amazon` block should show `Environment: sandbox`, `lwa_client_id present: set`, and `SP-API endpoint: https://sandbox.sellingpartnerapi-na.amazon.com`. The `Credentials` table lists active Amazon rows.

To exercise the sandbox directly:

```bash
docker exec ecommerce_app php artisan tinker --execute="
\$cred = \App\Models\Integration\IntegrationCredential::where('platform','amazon')->where('is_active',true)->first();
\$svc = app(\App\Services\Amazon\AmazonOAuthService::class);
echo 'Verify: '.(\$svc->verifyCredentials(\$cred) ? 'OK' : 'FAIL').PHP_EOL;
echo 'Seller info: '.json_encode(\$svc->getSellerInfo(\$cred->getActiveAmazonToken()->decrypted_access_token));
"
```

## SNS notifications in sandbox

Sandbox does not emit real notifications. To exercise `AmazonNotificationController`:

1. Create a real SNS topic in your AWS dev account.
2. Subscribe `https://develop.flipstack.app/api/webhooks/amazon` as an HTTPS endpoint. The first delivery is `SubscriptionConfirmation`; the controller already handles it.
3. Publish test messages with `aws sns publish --topic-arn <ARN> --message '<JSON>'` using payloads that match the SP-API notification schema (`ORDER_CHANGE`, `LISTINGS_ITEM_STATUS_CHANGE`, etc.).

For known-good payload shapes, see https://developer-docs.amazon.com/sp-api/docs/notifications-api-v1-reference.

## Cutover from sandbox to production

When the LWA app is approved for production and a real seller is ready:

1. Set on production server:
   ```
   AMAZON_LWA_CLIENT_ID=<production app id>
   AMAZON_LWA_CLIENT_SECRET=<production app secret>
   AMAZON_REDIRECT_URI=https://flipstack.app/amazon/auth/callback
   AMAZON_USE_SANDBOX=false
   ```
2. Deploy. `sandbox:status` on production should show `Environment: production`.
3. The seller clicks "Connect to Amazon" on flipstack.app and re-authorizes. Sandbox tokens stay on develop, production tokens stay on production.
4. To clean stale rows that may have been created with the wrong env on develop:
   ```
   docker exec ecommerce_app php artisan sandbox:cutover --dry-run
   docker exec ecommerce_app php artisan sandbox:cutover --confirm
   ```

## Known Amazon-specific differences from eBay/Shopify sandbox

- **No real listing creation** in sandbox even on `ACCEPTED`. Treat success responses as "request shape valid", not "listing live".
- **24-hour ASIN propagation** does not apply in sandbox (nothing propagates).
- **Brand Registry gate** is enforced in production for new branded ASINs but not in sandbox; the sandbox accepts any productType.
- **FBA inventory** in sandbox returns canned summaries. Do not assume `fba_quantity` matches a real seller's stock.
- **Buyer email** is anonymized in *both* sandbox and production order responses. Customer matching by email will not work; match by `BuyerName` + ship address.
- **Rate limits** in sandbox are lower than production. `AmazonRateLimiter` adjusts via response headers (`x-amzn-RateLimit-Limit`).
