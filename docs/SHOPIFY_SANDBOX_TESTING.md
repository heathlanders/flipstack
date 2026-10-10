# Shopify Sandbox Testing Guide

Two scopes covered here:

1. **Local sandbox test suite** (`.env.testing`) — runs the live sandbox feature tests against a Shopify dev store.
2. **`develop.flipstack.app` staging** — switches the deployed develop environment to a separate Shopify Partner sandbox app so it never touches live merchant stores.

## Environment selection (how the code picks sandbox vs production)

`config/services.php` `shopify.use_sandbox` is `true` whenever `APP_ENV !== 'production'`. Override with `SHOPIFY_USE_SANDBOX`. `ShopifyConfigService::getEnvironment()` resolves to `'sandbox'` or `'production'` and `getApiKey()` / `getApiSecret()` read from `services.shopify.{env}.api_key` / `api_secret`. `IntegrationCredential.environment` and `OAuthToken.environment` are stamped with whatever the controller resolves at OAuth time.

The legacy single-app keys (`SHOPIFY_API_KEY` / `SHOPIFY_API_SECRET`) are still read as a fallback when env-specific keys are unset, so existing prod deployments keep working without changes.

## Prerequisites

- A Shopify Partner account (free at [partners.shopify.com](https://partners.shopify.com))
- Access to this project's `.env.testing` file (for local) and the staging server `.env` (for develop deploy)

## Step 1: Create a Shopify Partner Account

1. Go to [partners.shopify.com](https://partners.shopify.com)
2. Sign up for a free Partner account
3. Complete the onboarding questionnaire

## Step 2: Create a Development Store

1. In the Partner Dashboard, go to **Stores > Add store**
2. Select **Development store**
3. Choose a name (e.g., `flipstack-dev`)
4. Set store password and save
5. Note the store domain: `your-dev-store.myshopify.com`

## Step 3: Create a Custom App

1. In the Partner Dashboard, go to **Apps > Create app**
2. Select **Create app manually**
3. Set the app name (e.g., `FlipStack Dev`)
4. Set the App URL to `https://localhost:8090`
5. Set the Allowed redirection URL to `https://localhost:8090/shopify/auth/callback`
6. Click **Create app**
7. In the app settings, go to **Client credentials**
8. Note the **API key** and **API secret key**

## Step 4: Configure App Scopes

In your app's configuration, ensure the following API access scopes are enabled:

- `read_products`
- `write_products`
- `read_inventory`
- `write_inventory`
- `read_orders`
- `read_locations`
- `write_fulfillments`

## Step 5: Install the App on the Dev Store

1. In the Partner Dashboard, go to your app
2. Select **Test your app** or use the install link
3. Choose your development store
4. Authorize the app
5. After installation, you will receive an access token

To get the access token programmatically, run:
```bash
docker exec ecommerce_app php artisan shopify:test-connection
```

Or retrieve it from the OAuth callback flow.

## Step 6: Configure `.env.testing`

Add the following variables to your `.env.testing` file:

```env
# Shopify Sandbox Testing
SHOPIFY_SANDBOX_API_KEY=your_dev_app_api_key
SHOPIFY_SANDBOX_API_SECRET=your_dev_app_api_secret
SHOPIFY_API_VERSION=2026-01
SHOPIFY_TEST_STORE_DOMAIN=your-dev-store.myshopify.com
SHOPIFY_TEST_ACCESS_TOKEN=shpat_xxxxx
SHOPIFY_TEST_LOCATION_ID=
```

The legacy `SHOPIFY_API_KEY` / `SHOPIFY_API_SECRET` keys also still work as fallback during cutover.

The `SHOPIFY_TEST_LOCATION_ID` can be left blank -- it will be fetched automatically during tests. To pre-populate it:

```bash
docker exec ecommerce_app php artisan shopify:test-connection --full
```

## Step 7: Seed Test Data

The sandbox tests and artisan command handle test data creation and cleanup automatically. Test products are prefixed with `[SANDBOX-TEST]` or `[CONNECTION-TEST]` for easy identification.

## Running Tests

### Mocked tests only (CI-safe, no external dependencies)
```bash
docker exec ecommerce_app php artisan test --filter=Shopify --exclude-group=sandbox
```

### Sandbox tests only (requires dev store credentials)
```bash
docker exec ecommerce_app php artisan test --group=sandbox
```

### All Shopify tests
```bash
docker exec ecommerce_app php artisan test --filter=Shopify
```

### Artisan connectivity test
```bash
docker exec ecommerce_app php artisan shopify:test-connection
docker exec ecommerce_app php artisan shopify:test-connection --full
docker exec ecommerce_app php artisan shopify:test-connection --full --cleanup
```

## Troubleshooting

### "401 Unauthorized" on sandbox tests
- Verify `SHOPIFY_TEST_ACCESS_TOKEN` is correct and the app is still installed on the dev store
- Check that the token has the required scopes

### "404 Not Found" on product operations
- The test product may have been manually deleted from the dev store
- Run with `--cleanup` flag and retry

### Rate limiting (429 responses)
- Shopify allows 2 requests/second for development stores
- The test suite includes delays between API calls
- If tests still fail, wait a few seconds and retry

### Test data cleanup
If test data accumulates on your dev store, you can:
1. Run `docker exec ecommerce_app php artisan shopify:test-connection --cleanup`
2. Manually delete products prefixed with `[SANDBOX-TEST]` or `[CONNECTION-TEST]` from the Shopify admin

---

## Deploying develop.flipstack.app against a sandbox Shopify app

Use a **separate Shopify Partner app** for develop so test traffic never reaches a live merchant store via OAuth.

### One-time Partner setup

1. In the Partner Dashboard, create a 2nd custom app (e.g. `FlipStack Develop`).
2. App URL: `https://develop.flipstack.app`
3. Allowed redirection URL: `https://develop.flipstack.app/shopify/auth/callback`
4. Same scopes as the production app.
5. Copy the dev app's API key + API secret.

### Server `.env` (`/data/flipstack-staging/shared/.env`)

```env
APP_ENV=staging
SHOPIFY_SANDBOX_API_KEY=<dev app API key>
SHOPIFY_SANDBOX_API_SECRET=<dev app API secret>
SHOPIFY_REDIRECT_URI="${APP_URL}/shopify/auth/callback"
```

`APP_ENV=staging` auto-flips `shopify.use_sandbox` to true, which makes `ShopifyConfigService` read the `SHOPIFY_SANDBOX_*` keys.

### Cutover existing develop credentials

Any `IntegrationCredential` rows on develop with `environment='production'` were created before this switch and still point at the live app. Force re-OAuth:

```sql
DELETE FROM integration_credentials WHERE platform = 'shopify' AND environment = 'production';
DELETE FROM oauth_tokens WHERE platform = 'shopify' AND environment = 'production';
```

After deploy, reconnect each Shopify dev store via the OAuth flow on develop. The new credential record will be stamped with `environment='sandbox'`.

### Webhook URL

Auto-derived from `APP_URL` via `url('/shopify/webhooks')` — develop registers `https://develop.flipstack.app/shopify/webhooks` automatically. No additional config.
