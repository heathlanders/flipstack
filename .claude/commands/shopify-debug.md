---
description: Diagnose Shopify integration health. Run these checks and report findings:
---

Diagnose Shopify integration health. Run ALL of these checks sequentially and report a summary at the end.

## 1. Shopify Credential Status

Check if Shopify OAuth is connected and active:

```bash
docker exec ecommerce_app php artisan tinker --execute="
\$cred = App\Models\Integration\IntegrationCredential::where('platform', 'shopify')->where('is_active', true)->first();
if (!\$cred) { echo 'NO ACTIVE SHOPIFY CREDENTIAL FOUND'; exit; }
echo 'Credential ID: ' . \$cred->id . PHP_EOL;
echo 'Shop Domain: ' . (\$cred->config['shop_domain'] ?? 'N/A') . PHP_EOL;
echo 'Location ID: ' . (\$cred->config['shopify_location_id'] ?? 'NOT SET') . PHP_EOL;
echo 'Ready: ' . (\$cred->isShopifyReady() ? 'YES' : 'NO') . PHP_EOL;
\$token = \$cred->getActiveShopifyToken();
echo 'Token: ' . (\$token ? 'Active (ID: ' . \$token->id . ')' : 'MISSING') . PHP_EOL;
echo 'Created: ' . \$cred->created_at . PHP_EOL;
"
```

## 2. Rate Limiter Status

Check current API rate limit usage:

```bash
docker exec ecommerce_app php artisan tinker --execute="
\$cred = App\Models\Integration\IntegrationCredential::where('platform', 'shopify')->where('is_active', true)->first();
if (!\$cred || !isset(\$cred->config['shop_domain'])) { echo 'No credential'; exit; }
\$status = (new App\Services\Shopify\ShopifyRateLimiter())->getStatus(\$cred->config['shop_domain']);
echo 'Used: ' . (\$status['used'] ?? 0) . '/' . (\$status['max'] ?? 40) . PHP_EOL;
echo 'Available: ' . (\$status['available'] ?? 40) . PHP_EOL;
echo 'Usage: ' . (\$status['percentage_used'] ?? 0) . '%' . PHP_EOL;
echo 'Throttling: ' . ((new App\Services\Shopify\ShopifyRateLimiter())->shouldThrottle(\$cred->config['shop_domain']) ? 'YES' : 'No') . PHP_EOL;
"
```

## 3. Listing Status Distribution

Count listings by status:

```bash
docker exec ecommerce_app php artisan tinker --execute="
App\Models\Shopify\ShopifyListing::selectRaw(\"status, COUNT(*) as count\")
  ->groupBy('status')
  ->orderByDesc('count')
  ->get()->each(fn(\$r) => print(\$r->status . ': ' . \$r->count . PHP_EOL));
echo 'Total: ' . App\Models\Shopify\ShopifyListing::count() . PHP_EOL;
"
```

## 4. Recent Sync Errors (24h)

Check for API errors grouped by error code:

```bash
docker exec ecommerce_app php artisan tinker --execute="
\$errors = App\Models\Shopify\ShopifySyncLog::where('status', 'error')
  ->where('created_at', '>=', now()->subDay())
  ->selectRaw(\"error_code, COUNT(*) as count\")
  ->groupBy('error_code')
  ->orderByDesc('count')
  ->get();
if (\$errors->isEmpty()) { echo 'No sync errors in last 24h'; } else {
  \$errors->each(fn(\$r) => print((\$r->error_code ?? 'unknown') . ': ' . \$r->count . PHP_EOL));
  echo 'Total errors: ' . \$errors->sum('count') . PHP_EOL;
}
"
```

## 5. Listings with Excessive Errors

Find listings with 5+ consecutive errors (excluded from auto-retry):

```bash
docker exec ecommerce_app php artisan tinker --execute="
\$stuck = App\Models\Shopify\ShopifyListing::where('consecutive_errors', '>=', 5)
  ->select('id', 'shopify_product_id', 'status', 'consecutive_errors', 'last_error_message', 'last_error_at')
  ->get();
if (\$stuck->isEmpty()) { echo 'No stuck listings'; } else {
  echo \$stuck->count() . ' stuck listings:' . PHP_EOL;
  \$stuck->each(fn(\$l) => print('  ID: ' . \$l->id . ' | Errors: ' . \$l->consecutive_errors . ' | ' . Str::limit(\$l->last_error_message, 100) . PHP_EOL));
}
"
```

## 6. Sync Operations Summary (24h)

Check recent sync operations by type and direction:

```bash
docker exec ecommerce_app php artisan tinker --execute="
App\Models\Shopify\ShopifySyncLog::where('created_at', '>=', now()->subDay())
  ->selectRaw(\"operation, direction, status, COUNT(*) as count\")
  ->groupBy('operation', 'direction', 'status')
  ->orderBy('operation')
  ->get()->each(fn(\$r) => print(\$r->operation . ' [' . \$r->direction . '] ' . \$r->status . ': ' . \$r->count . PHP_EOL));
"
```

## 7. Horizon and Queue Status

Check if Shopify queue workers are running:

```bash
docker exec ecommerce_app php artisan horizon:status 2>/dev/null || echo 'Horizon not available in dev'
```

Check for failed Shopify jobs:

```bash
docker exec ecommerce_app php artisan tinker --execute="
\$failed = DB::table('failed_jobs')->where('payload', 'like', '%Shopify%')
  ->orderByDesc('failed_at')->limit(5)->get(['id', 'exception', 'failed_at']);
if (\$failed->isEmpty()) { echo 'No failed Shopify jobs'; } else {
  echo \$failed->count() . ' recent failed jobs:' . PHP_EOL;
  \$failed->each(fn(\$j) => print('  #' . \$j->id . ' | ' . \$j->failed_at . ' | ' . Str::limit(\$j->exception, 150) . PHP_EOL));
}
"
```

## Summary

After running all checks, provide a summary with:
- Overall health status (Healthy / Warning / Critical)
- Any issues found and recommended actions
- If listings are stuck, offer to reset their consecutive_errors and re-queue sync
- If rate limiting is active, recommend waiting or reducing sync frequency
