# Audit System

Canonical spec for FlipStack's audit / activity logging.

**Audit logging is REQUIRED for every state-changing operation.** See `.claude/rules/audit-logging.md` for the enforced rule, this doc for the design.

## Why we audit

1. **Compliance & forensics** — reconstruct who changed what and when (security incidents, customer disputes, accounting reconciliation).
2. **Debugging** — when a mutation "should have happened" but didn't, query the log to confirm the call site executed.
3. **KPI derivation** — per-user productivity dashboards (`UserProductivityDaily`) read counts from the audit table.
4. **Operational visibility** — Admin Audit Hub timelines for any entity (Product, Order, PO, Customer, Listing).

## Tables

### `user_activity_logs` — canonical store

All new audit writes go here. Schema (see migration `database/migrations/2026_01_14_062832_enhance_team_management.php` + subsequent migrations adding polymorphic + severity/category):

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | bigint FK users | nullable for system/webhook events |
| `organization_id` | bigint FK organizations | always set |
| `team_id` | bigint FK teams | optional |
| `action` | string | `{domain}.{verb}` — see naming below |
| `description` | string | human-readable one-liner |
| `metadata` | jsonb | structured payload; includes `changes` diff for trait-emitted CRUD |
| `model_type` | string | polymorphic — class name of subject |
| `model_id` | string | polymorphic — id of subject (string supports UUID + bigint) |
| `severity` | enum | `critical`, `error`, `warning`, `info`, `debug` |
| `category` | enum | see categories table below |
| `ip_address`, `user_agent`, `session_id` | string | request context |
| `performed_at` | timestamp | event time (may differ from `created_at` for backfills) |
| `created_at`, `updated_at` | timestamp | record audit |

Helpers live on the model: `UserActivityLog::log()`, `logSecurityAction()`, `logTeamAction()`, `logOrganizationAction()`, plus aggregation statics `getMostActiveUsers()`, `getActivityByAction()`, `getDailyActivityCount()`, `getSecurityEvents()`, `getUserLoginHistory()`, `getTeamActivitySummary()`, and `cleanupOldLogs(int $days)`.

### `audit_logs` — DEPRECATED for new code

Polymorphic table created earlier (`database/migrations/2025_10_04_072431_create_audit_logs_table.php`). Currently only fed by the `LogUserActivity` middleware with `event='page_access'`. **Do not write to this from new features.** It remains in place for the existing page-access stream; a future cleanup may consolidate it into `user_activity_logs`.

## Action naming convention

Format: `{domain}.{verb}` in snake_case. Use sub-verbs for marketplace specificity.

| Pattern | Example |
|---|---|
| `{domain}.{verb}` | `product.created`, `order.shipped`, `customer.merged` |
| `{domain}.{subdomain}.{verb}` | `listing.ebay.published`, `credential.oauth.refreshed`, `po.receipt.recorded` |
| `{domain}.{verb}_{qualifier}` | `product.bulk_imported`, `auth.failed_login` |

Reserved domains: `product`, `category`, `brand`, `bundle`, `inventory`, `po`, `order`, `shipment`, `return`, `listing`, `ebay`, `shopify`, `amazon`, `customer`, `pricing`, `message`, `campaign`, `email`, `credential`, `auth`, `user`, `team`, `organization`, `supplier`, `part_out`.

Trait-emitted CRUD actions follow `{snake_class}.{event}`: e.g. `product.created`, `purchase_order.updated`, `inventory_movement.deleted`. The trait sets these automatically — service code emits domain-verb actions for higher-level lifecycle events (`order.shipped`, `listing.ebay.published`).

## Categories enum

Use the most specific match. Free-form strings allowed but the following are canonical and surface in the Admin Audit Hub filter chips:

`user`, `team`, `organization`, `security`, `general`, `inventory`, `product`, `order`, `fulfillment`, `procurement`, `marketplace`, `customer`, `pricing`, `communication`, `marketing`, `integration`, `mobile`, `supplier`, `system`.

## Severity guide

| Severity | Use for | Example actions |
|---|---|---|
| `critical` | Data corruption risk, security breach, payment failure | `credential.oauth.compromised`, `order.financial.recalc_failed` |
| `error` | Operation failed, user-visible impact | `ebay.sync.failed`, `email.bounced_hard` |
| `warning` | Notable but expected (deletes, security-sensitive) | `product.deleted`, `auth.failed_login`, `credential.revoked` |
| `info` | Normal lifecycle (default) | `product.created`, `order.shipped`, `po.received` |
| `debug` | High-volume internal events | `pricing.rule.evaluated` (per evaluation) |

`LogsAudit` trait defaults to `info`; `deleted` events auto-bump to `warning`. Override via `protected $auditSeverity` on the model.

## When to use which helper

| Situation | Helper | Example |
|---|---|---|
| Model CRUD (create/update/delete) | `LogsAudit` trait on model | `use LogsAudit;` then `$product->save()` auto-logs `product.created` with diff |
| Domain lifecycle event from a service | `UserActivityLog::log()` | `UserActivityLog::log('order.shipped', "Order #{$order->id} shipped", $user, ['carrier' => 'usps', 'tracking' => $t], 'info', 'fulfillment')` |
| Security event (login, 2FA, credential change) | `UserActivityLog::logSecurityAction()` | auto severity=warning, category=security |
| Inside a job/webhook (no `auth()`) | `withAuditActor($user, fn() => ...)` | sets actor context |
| Bulk operation, skip per-row audit | `$model->withoutAudit = true; $model->save();` | trait bypasses for that instance |

## Required metadata keys

Conventions — not enforced, but Admin Hub + KPI aggregators expect them:

| Key | When | Value |
|---|---|---|
| `source` | always (recommended) | `web`, `mobile`, `api`, `job`, `webhook`, `cron` |
| `actor_role` | when actor known | role slug (`organization_owner`, `manager`, etc.) |
| `entity_label` | for entity-keyed events | human-readable identifier (`Order #12345`, `Product: SKU-ABC`) |
| `changes` | trait-emitted on `updated` | `{field: {old, new}}` |
| `webhook_topic` | webhooks only | `ebay.order.created`, `shopify.orders.create` |
| `job_class` | jobs only | `App\Jobs\Ebay\SyncListingJob` |

## Retention

- Default cleanup: 90 days via `UserActivityLog::cleanupOldLogs(90)` (run nightly — wire in `routes/console.php` if not already).
- **Security category retained 7 years** — `cleanupOldLogs` skips `category='security'` automatically (update helper to enforce).
- Archival to cold storage TBD; treat current store as primary for at least 1 year of online queries.

## PII redaction

Never store in `metadata`:
- Raw passwords (even hashed if reversible)
- Full credit card numbers (last-4 only)
- Full OAuth access tokens (store token id / first-8 only)
- Full government IDs (last-4 only)
- Email body content (subject + recipient OK)

Use `metadata.{field}_last4` or `metadata.{field}_truncated` naming for redacted values.

## KPI consumption

`AggregateAuditDerivedKPIs` job (nightly, see `app/Jobs/Analytics/AggregateAuditDerivedKPIs.php`) counts rows per user/day by action prefix and upserts `user_productivity_daily`:

| KPI column | Source action prefix |
|---|---|
| `products_listed_count` | `product.created` |
| `pos_created_count` | `po.created` |
| `pos_approved_count` | `po.approved` |
| `pos_received_count` | `po.received` |
| `messages_answered_count` | `message.replied` |
| `inventory_moves_count` | `inventory.adjusted`, `inventory.moved`, `inventory.transferred` |
| `listings_published_ebay` | `listing.ebay.published` |
| `listings_published_shopify` | `listing.shopify.published` |
| `listings_published_amazon` | `listing.amazon.published` |
| `customer_records_touched` | `customer.created`, `customer.updated`, `customer.merged` |
| `pricing_rules_changed` | `pricing.rule.created`, `pricing.rule.updated`, `pricing.rule.deleted` |

Adding a new KPI = pick an action prefix, add a column to `user_productivity_daily`, add the count to the aggregator. The action must already exist in the audit stream — if it doesn't, instrument the service first.

`WorkforcePerformanceDaily` (fulfillment pick/pack/ship) is **separate** — sourced from `fulfillment_logs` for sub-second timing granularity. Audit-derived KPIs complement, not replace, it.

## Indexes (added by PR2 migration)

- `(organization_id, category, created_at)` — Hub filters by category + time
- `(organization_id, action, created_at)` — KPI aggregator queries by action prefix
- `(model_type, model_id, created_at)` — Entity timeline drilldown
- Existing: `(user_id, created_at)`, `(organization_id, action)`, `(performed_at)`

## Testing

Feature tests for any mutation path must assert audit emission:

```php
use Tests\Support\AssertsAudits;

class OrderShipTest extends TestCase
{
    use AssertsAudits;

    public function test_shipping_an_order_emits_audit(): void
    {
        // ... ship the order ...
        $this->assertAudited('order.shipped', $this->user, [
            'tracking_number' => 'XYZ123',
        ]);
    }
}
```

The trait provides `assertAudited(action, ?user, metadataPartial)` and `assertNotAudited(action)`.

## Failure handling (safety net)

**Audit emission MUST NOT crash the host operation** (login, ship-order, product save, webhook). The plumbing enforces this:

- `AuditHelper::event()` wraps the `UserActivityLog::create()` call in `try/catch`. Insert failures (FK violation, schema drift, encoding issue) log to `storage/logs/laravel.log` with the action + actor + org and return `null`.
- `AuditObserver` wraps every handler (`created`/`updated`/`deleted`/`restored`) in `safely()` — any exception in audit logic is caught, logged, and swallowed so the host save/delete still succeeds.
- `audit_event()` returns `null` (does not throw) when no organization can be resolved.

**You do NOT need to wrap your own `audit_event()` or `UserActivityLog::log()` calls in try/catch.** The helper handles it.

Use the `audit-coverage` agent's "null-actor + no-org" alert to surface skipped emissions in production.

## Common gotchas

- **Actor in jobs is null** — wrap `handle()` body in `withAuditActor($this->actor, fn() => ...)` or jobs will emit `user_id=NULL` rows.
- **Bulk imports flood the log** — set `$model->withoutAudit = true;` on each instance OR wrap loop in `UserActivityLog::log('product.bulk_imported', "...", $user, ['count' => N])` once after the loop.
- **PG NUMERIC in metadata** — Vue display must coerce strings to numbers via `useAnalyticsFormatters` (see `feedback_pg_numeric_aggregates_are_strings.md`).
- **Polymorphic id is string** — `model_id` is varchar to support UUID (Product) and bigint (Order). Cast in queries: `where('model_id', (string) $order->id)`.

## Admin surfaces

- `/admin/audit-hub` — filterable list (user, category, action, severity, date range, model_type, source)
- `/admin/audit-hub/entity/{type}/{id}` — per-entity timeline w/ old→new diff
- `/admin/audit-hub/security` — security-only feed
- `/admin/audit-hub/export?format=csv|xlsx` — export via `ReportExportService`

Controller: `app/Http/Controllers/Admin/AuditHubController.php`.

## Reference

- Trait: `app/Traits/LogsAudit.php`
- Observer: `app/Observers/AuditObserver.php`
- Helper: `app/Support/AuditHelper.php` (`audit_event`, `audit_security`, `withAuditActor`)
- Test trait: `tests/Support/AssertsAudits.php`
- Model: `app/Models/User/UserActivityLog.php`
- Aggregator: `app/Jobs/Analytics/AggregateAuditDerivedKPIs.php`
- KPI table: `user_productivity_daily` → `app/Models/Analytics/UserProductivityDaily.php`
- Hub UI: `resources/js/Pages/Admin/AuditHub/`
