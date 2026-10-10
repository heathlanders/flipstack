Diagnose eBay integration health. Run these checks and report findings:

## 1. OAuth Token Status

```bash
docker exec ecommerce_app php artisan tinker --execute="
\$tokens = App\Models\Integration\OAuthToken::where('platform', 'ebay')->where('is_active', true)->get();
foreach (\$tokens as \$t) {
    echo 'Token #' . \$t->id . ': expires ' . \$t->expires_at . ' (';
    echo \$t->expires_at->isPast() ? 'EXPIRED' : 'valid, ' . \$t->expires_at->diffForHumans();
    echo ')' . PHP_EOL;
}
echo 'Total active tokens: ' . \$tokens->count() . PHP_EOL;
"
```

## 2. Rate Limiter Status

```bash
docker exec ecommerce_app php artisan tinker --execute="echo json_encode(app(App\Services\EbayRateLimiter::class)->getStatus(), JSON_PRETTY_PRINT);"
```

## 3. Listing Status Distribution

```bash
docker exec ecommerce_app php artisan tinker --execute="
App\Models\Ebay\EbayListing::selectRaw(\"status, count(*) as total\")
    ->groupBy('status')
    ->orderByDesc('total')
    ->get()
    ->each(fn(\$r) => print(\$r->status . ': ' . \$r->total . PHP_EOL));
"
```

## 4. Recent Sync Errors (last 24h)

```bash
docker exec ecommerce_app php artisan tinker --execute="
App\Models\Ebay\EbaySyncLog::where('status', 'error')
    ->where('created_at', '>=', now()->subDay())
    ->selectRaw(\"error_code, count(*) as total\")
    ->groupBy('error_code')
    ->orderByDesc('total')
    ->get()
    ->each(fn(\$r) => print((\$r->error_code ?? 'null') . ': ' . \$r->total . PHP_EOL));
"
```

> A `"no more than 65 characters"` error message is an item-specific (aspect) value exceeding eBay's 65-char limit. This is capped at build time by `EbayInventoryService::sanitizeAspectValue()`; a fresh occurrence usually means Horizon is running stale code -- run `horizon:terminate`.

## 5. Listings with Excessive Errors (excluded from auto-retry)

```bash
docker exec ecommerce_app php artisan tinker --execute="
\$broken = App\Models\Ebay\EbayListing::where('consecutive_errors', '>=', 5)->count();
echo 'Listings with 5+ consecutive errors: ' . \$broken . PHP_EOL;
if (\$broken > 0) {
    App\Models\Ebay\EbayListing::where('consecutive_errors', '>=', 5)
        ->take(5)->get(['id', 'ebay_sku', 'last_error_message'])
        ->each(fn(\$l) => print('  ' . \$l->ebay_sku . ': ' . \$l->last_error_message . PHP_EOL));
}
"
```

## 6. Horizon Status

```bash
docker exec ecommerce_app php artisan horizon:status
```

## 7. Failed Jobs (eBay-related)

```bash
docker exec ecommerce_app php artisan tinker --execute="
DB::table('failed_jobs')
    ->where('payload', 'like', '%Ebay%')
    ->orderByDesc('failed_at')
    ->take(5)
    ->get(['id', 'failed_at', 'exception'])
    ->each(fn(\$j) => print(\$j->failed_at . ': ' . substr(\$j->exception, 0, 200) . PHP_EOL . PHP_EOL));
"
```

After running all checks, provide a summary with:
- Overall health status (healthy / degraded / critical)
- Any issues found and recommended fixes
