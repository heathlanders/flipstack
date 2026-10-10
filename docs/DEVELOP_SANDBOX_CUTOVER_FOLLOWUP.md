# Develop Sandbox Cutover — Follow-up Checklist

Code shipped 2026-05-05 in commit `ac3f238` on `develop` branch. Deploy run `25399915847` succeeded. Code paths now route to sandbox when `APP_ENV !== 'production'`. Remaining work is **server-side env config** + **post-deploy verification**.

## Status

| Item | Status |
|---|---|
| eBay code: hardcoded prod URLs fixed (Browse, UpcLookup, Finding-guard) | DONE |
| Shopify code: env-aware ConfigService, OAuth no longer hardcodes 'production' | DONE |
| ECN webhook: env-aware token resolution | DONE |
| `.env.example` + `config/services.php` | DONE |
| Skill / agent docs updated | DONE |
| Tests passing (813), Pint clean, Vite build OK | DONE |
| Deployed to develop.flipstack.app | DONE (commit ac3f238) |
| Staging server `.env` keys populated | **PENDING** |
| Cutover SQL run to drop stale prod credentials | **PENDING** |
| eBay sandbox seller account + policies + merchant location | **PENDING** |
| Shopify Partner sandbox app registered | **PENDING** |
| ECN destination registered in eBay sandbox portal | **PENDING** |
| E2E verification (OAuth → list → webhook) | **PENDING** |
| QA test users seeded (`flipstack:seed-testing` — see [DEVELOP_TESTING_SEEDER.md](DEVELOP_TESTING_SEEDER.md)) | **PENDING** |

## Step 1 — Verify staging .env keys

Two ways:

**A. Artisan command (preferred — runs inside the container, sees actual loaded config):**

```bash
docker exec flipstack_staging_app_1 php artisan sandbox:status
```

Reports app env, eBay/Shopify env selection, presence of every required key, ECN tokens, and credential/oauth-token counts grouped by platform + environment. Highlights stale credentials (env mismatch).

**B. Direct .env grep (raw file, masks secrets):**

```bash
grep -E '^(APP_ENV|EBAY_USE_SANDBOX|EBAY_SANDBOX_CLIENT_ID|EBAY_SANDBOX_CLIENT_SECRET|EBAY_SANDBOX_RU_NAME|EBAY_SANDBOX_NOTIFICATION_TOKEN|EBAY_PRODUCTION_NOTIFICATION_TOKEN|SHOPIFY_USE_SANDBOX|SHOPIFY_SANDBOX_API_KEY|SHOPIFY_SANDBOX_API_SECRET|SHOPIFY_REDIRECT_URI|APP_URL)=' /data/flipstack-staging/shared/.env \
  | sed -E 's/(SECRET|TOKEN|KEY)=.+/\1=<set>/'
```

**Required:**
- `APP_ENV=staging` (already set by compose)
- `EBAY_SANDBOX_CLIENT_ID`, `EBAY_SANDBOX_CLIENT_SECRET`, `EBAY_SANDBOX_RU_NAME` populated
- `EBAY_SANDBOX_NOTIFICATION_TOKEN` populated (only required if using legacy `/api/webhooks/ebay` endpoint)
- `SHOPIFY_SANDBOX_API_KEY`, `SHOPIFY_SANDBOX_API_SECRET` populated
- `SHOPIFY_REDIRECT_URI=https://develop.flipstack.app/shopify/auth/callback`
- `APP_URL=https://develop.flipstack.app`

Missing keys → add them to `/data/flipstack-staging/shared/.env`, then:

```bash
cd /data/flipstack-staging
docker compose -f docker-compose.coolify-staging.yaml up -d --no-deps --force-recreate --no-build app-1 horizon scheduler
docker exec flipstack_staging_horizon php artisan horizon:terminate
```

## Step 2 — Provision sandbox accounts (one-time)

### eBay

1. https://developer.ebay.com → **Test Users** → create sandbox seller (and a buyer for E2E order tests).
2. In sandbox app **Application Keys**: copy App ID, Cert ID, RuName.
3. RuName redirect URL must match `https://develop.flipstack.app/ebay/oauth/callback`.
4. Set `EBAY_SANDBOX_CLIENT_ID/SECRET/RU_NAME` on staging server.

### Shopify

1. https://partners.shopify.com → create 2nd custom app (e.g. "FlipStack Develop").
2. App URL: `https://develop.flipstack.app`. Redirect: `https://develop.flipstack.app/shopify/auth/callback`.
3. Same scopes as production app.
4. Create a Shopify dev store for testing.
5. Set `SHOPIFY_SANDBOX_API_KEY/SECRET` on staging server.

## Step 3 — Cutover (run once)

Drop stale `environment='production'` rows on develop so the next OAuth recreates them with `environment='sandbox'`. Use the dedicated artisan command:

```bash
# Preview only
docker exec flipstack_staging_app_1 php artisan sandbox:cutover --dry-run

# Actually delete
docker exec flipstack_staging_app_1 php artisan sandbox:cutover --confirm
```

The command refuses to run on `APP_ENV=production`. It reads each platform's expected env from `EbayConfigService` / `ShopifyConfigService` and deletes credentials + oauth_tokens whose `environment` doesn't match.

## Step 4 — Register ECN destination (eBay sandbox)

eBay Developer Portal → **Sandbox** → **Alerts and Notifications**:

- Destination URL (recommended, per-credential): `https://develop.flipstack.app/api/webhooks/ebay/{credentialId}` — but you can only register this *after* connecting a sandbox credential and looking up its ID.
- Or legacy app-level: `https://develop.flipstack.app/api/webhooks/ebay` — uses `EBAY_SANDBOX_NOTIFICATION_TOKEN`.
- Subscribe topic: `MARKETPLACE_ACCOUNT_DELETION`.
- Use sandbox portal's **Send Test Notification** button to verify challenge + HMAC succeed.

## Step 5 — E2E verification

After steps 1–4 are done, on `develop.flipstack.app`:

1. **eBay OAuth**: connect sandbox seller. Then:
   ```bash
   docker exec flipstack_staging_app_1 php artisan tinker --execute="
   \App\Models\Integration\IntegrationCredential::where('platform','ebay')
       ->latest()->first()
       ->only(['id','environment','seller_username','is_active']);
   "
   ```
   Expect `environment='sandbox'`.

2. **Shopify OAuth**: connect dev store. Same check with `platform='shopify'`. Expect `environment='sandbox'`.

3. **Sandbox seller seed**: create fulfillment / payment / return policies + merchant location key on the sandbox account (use `EbayPolicyService` via tinker or the policy management UI). Sandbox sellers start empty.

4. **Push test listing**: from develop, create a product and push to eBay. Confirm appears on **sandbox seller account**, not prod. Confirm Shopify listing pushes to dev store.

5. **Order flow**: place a test order from sandbox buyer / dev store. Confirm `OrderSyncService` imports it on develop, `reserveInventoryForOrder` runs, listing qty decrements.

6. **Webhook**: trigger ECN test from sandbox portal → confirm `EbayNotificationController::challenge` returns 200 + valid hash, then a real notification dispatches `ProcessEbayNotification` job (check Horizon).

7. **No prod leak**:
   ```bash
   docker exec flipstack_staging_app_1 php artisan sandbox:status
   ```
   Expect "No stale credentials." Credential / OAuth-Token tables show only the expected env per platform.

## Known sandbox limitations to expect (not bugs)

- `EbayFindingService` returns empty — no eBay sandbox endpoint for Finding API. Logged warning each call.
- `EbayBrowseService` + `UpcLookupService` return stub/limited data. UPC lookup unreliable on develop. Logged warning each call.
- `EbayPromotedListingsService` is stubbed regardless — Marketing API not in sandbox.

## Artisan helpers

| Command | Purpose |
|---|---|
| `php artisan sandbox:status` | Read-only health check (env, key presence, credential counts, stale row warning) |
| `php artisan sandbox:cutover --dry-run` | Preview what would be deleted |
| `php artisan sandbox:cutover --confirm` | Delete stale (env-mismatched) credentials + oauth_tokens. Refuses on `APP_ENV=production`. |

## References

- Code: `app/Services/EbayConfigService.php`, `app/Services/Shopify/ShopifyConfigService.php`, `app/Console/Commands/Sandbox/`
- Setup docs: `docs/EBAY_SANDBOX_TESTING.md`, `docs/SHOPIFY_SANDBOX_TESTING.md`
- Memory: `.claude/projects/-home-user-ecommerce-saas/memory/project_develop_sandbox_switchover.md`
- Plan: `.claude/plans/ok-so-we-have-federated-milner.md`
- Commit: `ac3f238` ("Route develop env to eBay + Shopify sandbox")
- Deploy: GH Actions run `25399915847` (success, 2m08s, 2026-05-05)
