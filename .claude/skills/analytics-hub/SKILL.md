---
name: analytics-hub
description: Enterprise KPI Analytics Hub - hub landing page, executive, fulfillment, workforce, per-user drill-down, compensation, procurement, marketplace health, customer, communication, marketing, pricing, plus saved/scheduled report exports (CSV/XLSX/PDF)
user-invocable: false
---

# Enterprise KPI Analytics Hub

Unified analytics system providing 11 dashboard pages plus a per-user drill-down view, an exports/reports management page, and 2 settings pages for targets + labor rates. Covers all 26 enterprise KPIs from the original analytics expansion plan.

## URL Map (canonical, post 2026-05-04)

| URL | Purpose |
|-----|---------|
| `/analytics/hub` | Landing page (`Hub/Index.vue`) — health gauge + 10 report cards |
| `/analytics/hub/executive` | Executive Dashboard (revenue, margins, top products) |
| `/analytics/hub/fulfillment` | Order-to-ship, on-time rate, pick accuracy, backlog, returns, carriers |
| `/analytics/hub/workforce` | Team overview, leaderboard, period comparison, error attribution, labor cost, **Team Members panel** (always visible to managers) |
| `/analytics/hub/users/{user}` | **Per-user drill-down** — KPI cards, ranking, attainment, labor cost, sessions, timeline, heatmap, error trend |
| `/analytics/hub/compensation` | Settlement-ready per-user reports + commission attribution + tier bonuses |
| `/analytics/hub/procurement` | Supplier ranking, lead times, on-time rate, cost trends |
| `/analytics/hub/marketplace` | Sync health, listing status, response times, fees |
| `/analytics/hub/customers` | LTV, repeat rate, AOV, segments |
| `/analytics/hub/communication` | Response time SLA, message volume, unread backlog |
| `/analytics/hub/marketing` | Campaign performance, deliverability, engagement trend |
| `/analytics/hub/pricing` | Repricing frequency, margin impact, competitive position |
| `/analytics/reports` | Saved + scheduled report management (CSV/XLSX/PDF) |
| `/settings/targets` | Manager-only — target CRUD (per user, role, team, or org) |
| `/settings/labor-rates` | Manager-only — effective-dated rates + per-task time estimates |
| `/inventory/analytics` | Inventory Health (separate namespace, linked from Hub) |

## Manager-gate (CRITICAL — always check this first)

Use the array form. The slug `owner` does NOT exist:

```php
$isManager = $user->hasRole(['super_admin', 'organization_owner', 'admin', 'manager']);
```

Canonical slugs: `super_admin`, `organization_owner`, `admin`, `manager`. Anything else (including `owner`) silently locks managers out. See `~/.claude/projects/-home-user-ecommerce-saas/memory/feedback_manager_role_slugs.md`.

## Services (in `app/Services/Analytics/`)

| Service | Purpose | Notes |
|---------|---------|-------|
| `AnalyticsQueryTrait` (Concerns) | Shared `forOrganization`, `dateRange`, `compareTo`, `withComparison`, `cached` | `cached()` feature-detects tag support — see `feedback_cache_tags_fallback.md` |
| `ExecutiveAnalyticsService` | Revenue/profit/margins by marketplace+category, top products | |
| `FulfillmentAnalyticsService` | Order-to-ship, on-time, pick accuracy, backlog, carriers, time-in-stage | Carrier transit days uses `(date - date)` integer subtraction, NOT `EXTRACT(EPOCH FROM ...)` |
| `WorkforceAnalyticsService` | Team overview, leaderboard, individual perf, error rates, team comparison, period comparison, percentile ranking, vs-team deltas, error trend, **session stats**, heatmap, concurrent users, **getAllOrgUsersWithStats** | Most extended service. Heatmap uses `EXTRACT(DOW FROM performed_at)` |
| `WorkforceCompensationService` | Settlement summary per user (joins suppliers→users), commission attribution by structure, tier bonus breakdown | |
| `ErrorAttributionService` | Returns by packer, cancellations by handler (picker+packer side-by-side), refunds by lister via `products.created_by` | `ebay_listings`/`shopify_listings` have NO creator column — `products.created_by` is the only viable lister attribution |
| `LaborCostService` | Per-user effective-rate lookup, total labor cost, cost per picked unit/listed item/ticket/served-order | Pulls effective comp record overlapping the active date range |
| `UserTargetService` | Applicable targets w/ precedence user > team > role > org. Pro-rates target by date range length | New target table |
| `BusinessHealthScoreService` | Composite 0-100 score, 8 dimensions, alerts | Returns `{overall_score, dimensions, alerts}` — Vue must extract `.overall_score` for the gauge prop |
| `ReportRegistry` | Central map: view name → fetcher closure + columns + label. Single source of truth for what's exportable | Add new exportable views here |
| `ReportExportService` | CSV stream / XLSX (maatwebsite) / PDF (dompdf via blade) | |
| `ProcurementAnalyticsService` | Supplier lead time, on-time rate, receiving accuracy, cost trends, ranking | |
| `MarketplaceHealthService` | Sync health, listing status, response times, fees, cross-marketplace comparison | |
| `CustomerAnalyticsService` | LTV distribution, repeat rate, AOV, segments | |
| `CommunicationAnalyticsService` | Response time avg/median/p95, SLA compliance, volume, unread backlog | |
| `MarketingAnalyticsService` | Campaigns sent, deliverability, engagement trend | `email_campaigns` has NO `status` column (only `is_active`); `email_campaign_sends` FK is `campaign_id` not `email_campaign_id` |
| `PricingAnalyticsService` | Repricing frequency, margin impact, competitive position, rule performance | `repricing_logs` has NO `organization_id` — uses shared `logsForOrg()` helper that joins `repricing_rules` |
| `InventoryAnalyticsService` | Stock value, low/out, ABC, turnover, aging — separate namespace at `/inventory/analytics` | Has its own `cacheRemember()` helper with the same fallback pattern. Models live at `App\Models\Inventory\InventoryMovement` (NOT `App\Models\InventoryMovement`) |

## Aggregation Tables

| Table | Purpose | Populated by |
|-------|---------|--------------|
| `workforce_performance_daily` | Per-user daily pick/pack/ship counts + durations + errors | Nightly `AggregateDailyKPIs` job |
| `daily_kpi_snapshots` | JSONB metrics per org/date/category — flexible bucket | Nightly `AggregateDailyKPIs` |
| `marketplace_health_snapshots` | Per-marketplace daily sync stats | Nightly `AggregateDailyKPIs` |
| `business_health_scores` | Daily 0-100 composite + dimensions + alerts | `BusinessHealthScoreService::calculate()` (also writes here) |
| `user_targets` | Goals/attainment configs (P2) | `UserTargetController` (manager UI) |
| `user_compensations` | Effective-dated hourly rates + minutes_per_listing/ticket (P4). **Set `protected $table` explicitly** — Eloquent pluralizes wrong | `UserCompensationController` (manager UI) |
| `saved_reports`, `scheduled_reports` | Saved filter snapshots + email digest configs (P5) | `ReportController` |

All Analytics models in `app/Models/Analytics/`. `AggregateDailyKPIs` reads from `fulfillment_logs` (NOT `user_activity_logs`).

## Frontend (in `resources/js/Pages/Analytics/Hub/`)

| Page | Notable additions (post-expansion) |
|------|-----------------------------------|
| `Index.vue` | Hub landing — health gauge + 10 report cards, jump-to-Executive |
| `Executive.vue` | Health score uses `healthScore.overall_score`, NOT raw object. Health history maps `score_date` + `overall_score` (not `date`/`score`) |
| `Workforce.vue` | Period strip (W/W toggle), Error Attribution panel, Labor Cost analytics card, **Team Members panel always visible** (drill-in even when aggregations empty), export bar (CSV/XLSX/PDF) |
| `Users/Show.vue` | 26 KPIs in one page: 6 KPI cards, attainment, ranking, vs-team deltas, productivity trend, error trend, sessions+idle gaps, heatmap, concurrent users, timeline |
| `Compensation.vue` | Per-user settlement table, commission attribution by structure, tier bonus breakdown |

Settings pages: `resources/js/Pages/Settings/Targets/Index.vue`, `Settings/LaborRates/Index.vue`, `Settings/Reports/Index.vue`.

## Shared Vue helpers

- **`resources/js/Composables/useAnalyticsFormatters.js`** — formatters that coerce PG NUMERIC strings before `.toFixed`/`Intl`. Use these everywhere; never call `(x || 0).toFixed(...)` inline. See `feedback_pg_numeric_aggregates_are_strings.md`.
- `useAnalyticsFilters.js` — date ranges, presets, comparison
- `Components/Analytics/AnalyticsSidebar.vue` — 12-entry nav. Route names MUST be `analytics.hub.X` (not `analytics.X` — caused dead links)
- `Components/Analytics/ComparisonDatePicker.vue` — presets include `Today`, `7d`, `30d`, `90d`, `YTD`, `1Y`, `All` (last preset anchors at `2000-01-01`). Preset clicks auto-apply
- `Components/Analytics/BusinessHealthGauge.vue` — accepts `compact` prop to hide dimension pills + alerts
- `Components/Analytics/KPICard.vue` — supported colors: blue, emerald, amber, red, violet, slate, gray, indigo, cyan, pink. Unmapped colors fall back to blue safely

## Service Pattern (unchanged)

```php
$service->forOrganization($orgId)
    ->dateRange('2026-01-01', '2026-03-31')
    ->compareTo('2025-10-01', '2025-12-31')  // optional
    ->getOverview();
```

`withComparison()` returns `{ current, previous, changes }` with % change calcs. Caching via `cached()` trait method (with the fallback for non-tagging stores).

## Postgres gotchas (apply to every new analytics query)

- **Always qualify** `organization_id`, `created_at`, `deleted_at` when joining. Both sides usually have those columns. See `feedback_postgres_join_qualifications.md`.
- `repricing_logs` has no `organization_id` — must scope through `repricing_rules`.
- `EXTRACT(EPOCH FROM (date - date))` doesn't work — date subtraction returns integer days. Use the integer directly.
- Use double-quoted PHP strings in `DB::raw()` with single-quoted SQL values (PG identifier quoting).

## Manager Gate Pattern

```php
$user->hasRole(['super_admin', 'organization_owner', 'admin', 'manager']);
```

Use array form. Six controllers used the wrong `'owner'` slug — silently locked org owners out of every per-user view.

## Cron Schedule (`routes/console.php`)

| Job | Frequency |
|-----|-----------|
| `AggregateDailyKPIs` | Daily 02:00 UTC |
| `SendScheduledReports` | Every 15 minutes |

## Backfill Command

```bash
docker exec ecommerce_app php artisan analytics:backfill --days=90 [--org={id}]
```

(Command is `analytics:backfill`. Options: `--days=90` (default 90), `--org={id}` to scope.)

## ReportRegistry (P5 Export Layer)

Each entry: `label`, `columns` (key → display label), `fetch` (closure receives `$orgId + $filters`, returns rows). Currently wired:

- `workforce_individual`, `workforce_leaderboard`, `workforce_errors`
- `compensation`
- `attribution_returns`, `attribution_cancels`, `attribution_refunds`
- `labor_cost`

To add a new exportable view: add one entry to `ReportRegistry::definitions()`. Settings/Reports page picks it up automatically. Export endpoint: `GET /analytics/reports/export?view=X&format=csv|xlsx|pdf`.

## Composer Deps (P5)

- `maatwebsite/excel ^3.1` — XLSX
- `barryvdh/laravel-dompdf ^3.0` — PDF rendering of `resources/views/reports/export.blade.php` (landscape letter)

## Debugging

```php
// Aggregation health
\App\Models\Analytics\WorkforcePerformanceDaily::where('organization_id', $orgId)
    ->orderByDesc('performance_date')->limit(5)->get();

// Force aggregation for a date
\App\Jobs\Analytics\AggregateDailyKPIs::dispatch($orgId, '2026-05-01');

// Backfill historicals
docker exec ecommerce_app php artisan analytics:backfill --days=90

// Test a service
$svc = app(\App\Services\Analytics\WorkforceAnalyticsService::class);
$svc->forOrganization($orgId)->dateRange('2026-04-01', '2026-04-30');
$svc->getAllOrgUsersWithStats();  // returns even when aggregations are empty

// Check labor rate resolution
$cost = app(\App\Services\Analytics\LaborCostService::class);
$cost->forOrganization($orgId)->dateRange('2026-04-01', '2026-04-30');
$cost->getCostForUser($userId);

// Test scheduled-report due math
$r = new \App\Models\Analytics\ScheduledReport(['frequency' => 'weekly', 'day_of_period' => 1, 'send_time' => '07:00']);
$r->calculateNextDueAt(now());

// Test report exporter
$exporter = app(\App\Services\Analytics\ReportExportService::class);
$exporter->fetchRows('workforce_individual', $orgId, ['start_date' => '2026-04-01', 'end_date' => '2026-04-30']);
```

## When extending

- New analytics service: extend `AnalyticsQueryTrait`, qualify all joined columns, use the trait's `cached()` (handles tag-store fallback).
- New exportable view: add to `ReportRegistry::definitions()`, that's it.
- New manager-gated controller: copy the canonical `hasRole(['super_admin', 'organization_owner', 'admin', 'manager'])` line from any of the existing analytics controllers.
- New Vue page consuming analytics props: import formatters from `useAnalyticsFormatters`, never call `.toFixed` on raw props.

## Audit Logging

Reads from `user_activity_logs` -- does not write. `UserProductivityDaily` consumer of audit action prefixes (see `docs/AUDIT_SYSTEM.md` action->column map). Aggregator: `app/Jobs/Analytics/AggregateAuditDerivedKPIs.php`.

Trait: models in this feature use `LogsAudit` (`app/Traits/LogsAudit.php`) for auto CRUD diff. Domain lifecycle events emitted from none -- this is the consumer via `UserActivityLog::log(action, desc, $user, $metadata, $severity, $category)`.

KPI consumer: `UserProductivityDaily` columns (products_listed_count, pos_created_count, messages_answered_count, inventory_moves_count, listings_published_{ebay,shopify,amazon}, customer_records_touched, pricing_rules_changed) all populated from audit action prefixes.

Spec: `docs/AUDIT_SYSTEM.md`. Enforced rule: `.claude/rules/audit-logging.md`. Tests: `use Tests\Support\AssertsAudits;` then `$this->assertAudited('action', $user, [...])`.
