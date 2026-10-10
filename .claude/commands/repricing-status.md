---
name: repricing-status
description: Repricing system status and recent activity
user-invocable: true
---

Check repricing system status and activity. Run ALL of these checks sequentially and report a summary at the end.

**IMPORTANT:** All PHP commands must run inside Docker: `docker exec ecommerce_app`

## Check 1: Active Repricing Rules by Strategy

```bash
docker exec ecommerce_app php artisan tinker --execute="
  echo '--- All Repricing Rules ---' . PHP_EOL;
  \$rules = DB::table('repricing_rules')
    ->selectRaw(\"strategy, is_active, count(*) as count\")
    ->groupBy('strategy', 'is_active')
    ->orderBy('strategy')
    ->get();
  foreach (\$rules as \$r) {
    \$status = \$r->is_active ? 'ACTIVE' : 'INACTIVE';
    echo \"  [\$status] \$r->strategy: \$r->count rules\" . PHP_EOL;
  }

  echo PHP_EOL . '--- Active Rules Detail ---' . PHP_EOL;
  \$active = DB::table('repricing_rules')
    ->where('is_active', true)
    ->select('id', 'name', 'marketplace', 'strategy', 'priority', 'last_run_at')
    ->orderBy('priority')
    ->get();
  echo 'Active rules: ' . \$active->count() . PHP_EOL;
  if (\$active->isNotEmpty()) echo \$active->toJson(JSON_PRETTY_PRINT);
"
```

## Check 2: Last Repricing Run per Rule

```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$rules = DB::table('repricing_rules')
    ->where('is_active', true)
    ->select('id', 'name', 'strategy', 'last_run_at')
    ->orderBy('last_run_at')
    ->get();
  echo 'Last run times for active rules:' . PHP_EOL;
  foreach (\$rules as \$r) {
    \$hoursAgo = \$r->last_run_at ? round(now()->diffInHours(\$r->last_run_at), 1) : null;
    \$status = is_null(\$r->last_run_at) ? '[NEVER RUN]' : (\$hoursAgo > 24 ? '[STALE]' : '[OK]');
    \$timeStr = \$r->last_run_at ?? 'never';
    echo \"  \$status \$r->name: \$timeStr\" . (\$hoursAgo !== null ? \" (\$hoursAgo hours ago)\" : '') . PHP_EOL;
  }
"
```

## Check 3: Price Changes in Last 24 Hours

```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$changes = DB::table('repricing_logs')
    ->where('applied_at', '>', now()->subDay())
    ->join('repricing_rules', 'repricing_logs.repricing_rule_id', '=', 'repricing_rules.id')
    ->selectRaw(\"
      repricing_rules.name as rule_name,
      repricing_rules.strategy,
      count(*) as change_count,
      avg(repricing_logs.new_price - repricing_logs.old_price) as avg_change,
      min(repricing_logs.new_price - repricing_logs.old_price) as max_decrease,
      max(repricing_logs.new_price - repricing_logs.old_price) as max_increase
    \")
    ->groupBy('repricing_rules.name', 'repricing_rules.strategy')
    ->get();
  \$totalChanges = \$changes->sum('change_count');
  echo 'Price changes (24h): ' . \$totalChanges . PHP_EOL;
  foreach (\$changes as \$c) {
    echo \"  \$c->rule_name [\$c->strategy]: \$c->change_count changes, avg \\\$\" . number_format(\$c->avg_change, 2) . \", range \\\$\" . number_format(\$c->max_decrease, 2) . \" to +\\\$\" . number_format(\$c->max_increase, 2) . PHP_EOL;
  }

  echo PHP_EOL . '--- Recent Price Changes (Last 5) ---' . PHP_EOL;
  \$recent = DB::table('repricing_logs')
    ->where('applied_at', '>', now()->subDay())
    ->join('products', 'repricing_logs.product_id', '=', 'products.id')
    ->select('products.sku', 'products.name', 'repricing_logs.old_price', 'repricing_logs.new_price', 'repricing_logs.competitor_price', 'repricing_logs.reason', 'repricing_logs.applied_at')
    ->orderByDesc('repricing_logs.applied_at')
    ->limit(5)
    ->get();
  if (\$recent->isNotEmpty()) echo \$recent->toJson(JSON_PRETTY_PRINT);
"
```

## Check 4: Rules with Errors (Failed Jobs)

```bash
docker exec ecommerce_app php artisan tinker --execute="
  echo '--- Failed Repricing Jobs (24h) ---' . PHP_EOL;
  \$failed = DB::table('failed_jobs')
    ->where('failed_at', '>', now()->subDay())
    ->where(function(\$q) {
      \$q->where('payload', 'like', '%Repricing%')
        ->orWhere('payload', 'like', '%RepricingEngine%');
    })
    ->orderByDesc('failed_at')
    ->get();
  echo 'Failed repricing jobs: ' . \$failed->count() . PHP_EOL;
  foreach (\$failed->take(5) as \$j) {
    \$p = json_decode(\$j->payload);
    echo '  ' . \$p->displayName . ' at ' . \$j->failed_at . PHP_EOL;
    echo '  ' . substr(\$j->exception, 0, 150) . PHP_EOL . PHP_EOL;
  }

  echo '--- Rules Never Run ---' . PHP_EOL;
  \$neverRun = DB::table('repricing_rules')
    ->where('is_active', true)
    ->whereNull('last_run_at')
    ->select('id', 'name', 'strategy', 'created_at')
    ->get();
  echo 'Active rules never run: ' . \$neverRun->count() . PHP_EOL;
  if (\$neverRun->isNotEmpty()) echo \$neverRun->toJson(JSON_PRETTY_PRINT);
"
```

## Summary

After running all checks, provide a summary:

1. **Health Status**: healthy / degraded / critical
   - Critical: failed repricing jobs > 3, or all active rules stale (> 24h since last run)
   - Degraded: any failed jobs, rules never run, or stale rules
   - Healthy: all active rules ran recently, no failed jobs

2. **Issues Found**: list each issue with severity

3. **Recommended Actions**:
   - Failed jobs: suggest checking error details and retrying
   - Stale rules: suggest verifying the repricing scheduler is running
   - Never-run rules: suggest triggering a manual repricing run
   - Large price swings: suggest reviewing min/max price guards on rules
