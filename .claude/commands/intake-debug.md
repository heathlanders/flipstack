Diagnose product intake queue health. Run these checks and report findings:

## 1. Status Distribution

```bash
docker exec ecommerce_app php artisan tinker --execute="
App\Models\Catalog\ProductIntakeQueue::selectRaw(\"processing_status, count(*) as total\")
    ->groupBy('processing_status')
    ->orderByDesc('total')
    ->get()
    ->each(fn(\$r) => print(\$r->processing_status . ': ' . \$r->total . PHP_EOL));
"
```

## 2. Items Stuck in Processing (>30 min)

```bash
docker exec ecommerce_app php artisan tinker --execute="
\$stuck = App\Models\Catalog\ProductIntakeQueue::where('ai_analysis_status', 'processing')
    ->where('ai_started_at', '<', now()->subMinutes(30))
    ->count();
echo 'Stuck in processing: ' . \$stuck . PHP_EOL;
if (\$stuck > 0) {
    App\Models\Catalog\ProductIntakeQueue::where('ai_analysis_status', 'processing')
        ->where('ai_started_at', '<', now()->subMinutes(30))
        ->take(5)->get(['id', 'ai_started_at', 'barcode'])
        ->each(fn(\$q) => print('  ' . \$q->id . ' started at ' . \$q->ai_started_at . PHP_EOL));
}
"
```

## 3. Recent Failures

```bash
docker exec ecommerce_app php artisan tinker --execute="
App\Models\Catalog\ProductIntakeQueue::where('processing_status', 'failed')
    ->orderByDesc('updated_at')
    ->take(5)
    ->get(['id', 'barcode', 'updated_at'])
    ->each(function(\$q) {
        \$error = \$q->enriched_data['error'] ?? \$q->enriched_data['research_data']['error'] ?? 'unknown';
        print(\$q->id . ' (' . \$q->updated_at . '): ' . substr(\$error, 0, 150) . PHP_EOL);
    });
"
```

## 4. Confidence Score Distribution

```bash
docker exec ecommerce_app php artisan tinker --execute="
\$ranges = [
    '>= 0.8 (ready)' => [0.8, 1.01],
    '0.6-0.8 (minor review)' => [0.6, 0.8],
    '0.4-0.6 (review)' => [0.4, 0.6],
    '< 0.4 (major review)' => [0, 0.4],
];
foreach (\$ranges as \$label => [\$min, \$max]) {
    \$count = App\Models\Catalog\ProductIntakeQueue::whereNotNull('ai_confidence_score')
        ->where('ai_confidence_score', '>=', \$min)
        ->where('ai_confidence_score', '<', \$max)
        ->count();
    print(\$label . ': ' . \$count . PHP_EOL);
}
"
```

## 5. Research Strategy Distribution (recent items)

```bash
docker exec ecommerce_app php artisan tinker --execute="
App\Models\Catalog\ProductIntakeQueue::whereNotNull('enriched_data')
    ->where('updated_at', '>=', now()->subDays(7))
    ->get()
    ->groupBy(fn(\$q) => \$q->enriched_data['research_data']['search_method'] ?? 'unknown')
    ->map(fn(\$g) => \$g->count())
    ->sortDesc()
    ->each(fn(\$count, \$method) => print(\$method . ': ' . \$count . PHP_EOL));
"
```

## 6. Horizon & Queue Status

```bash
docker exec ecommerce_app php artisan horizon:status
```

## 7. Failed Jobs (intake-related)

```bash
docker exec ecommerce_app php artisan tinker --execute="
DB::table('failed_jobs')
    ->where('payload', 'like', '%ProcessQueueItem%')
    ->orderByDesc('failed_at')
    ->take(5)
    ->get(['id', 'failed_at', 'exception'])
    ->each(fn(\$j) => print(\$j->failed_at . ': ' . substr(\$j->exception, 0, 200) . PHP_EOL . PHP_EOL));
"
```

After running all checks, provide a summary with:
- Overall pipeline health (healthy / degraded / critical)
- Any stuck or failed items and recommended fixes
- If stuck items found, offer to reset them
