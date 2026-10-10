# Audit Logging (REQUIRED)

**Every state-changing operation MUST write a `user_activity_logs` row.** No exceptions.

Applies to: service methods, controller actions, queue jobs, webhook handlers, mobile API endpoints, scheduled tasks, console commands, model observers. Read-only operations do not require audit (the `LogUserActivity` middleware handles page_access).

Full spec: `docs/AUDIT_SYSTEM.md`.

## Three ways to emit audit

1. **Model CRUD** — add `use LogsAudit;` to the model. Auto-emits `{snake_class}.{created|updated|deleted}` with old→new diff on `updated`.
   ```php
   class Product extends Model {
       use HasUuids, LogsAudit;
       protected $auditCategory = 'product';
   }
   ```

2. **Domain lifecycle event from a service** — explicit call:
   ```php
   UserActivityLog::log(
       'order.shipped',
       "Order #{$order->id} shipped via {$carrier}",
       $user,
       ['carrier' => $carrier, 'tracking' => $tracking, 'source' => 'web'],
       'info',
       'fulfillment',
   );
   ```

3. **Security event** — always use the security helper:
   ```php
   UserActivityLog::logSecurityAction(
       'auth.failed_login',
       "Failed login attempt for {$email}",
       null,
       ['email' => $email, 'ip' => request()->ip()],
   );
   ```

## Actor resolution in non-HTTP contexts

Jobs, webhooks, scheduled tasks have no `auth()` user. Use `withAuditActor()`:

```php
public function handle(): void
{
    withAuditActor($this->actor, function () {
        // any code inside here that emits audit picks up $this->actor
        $this->service->processOrder($this->order);
    });
}
```

For webhooks where no actor exists, pass `null` and include `source` + `webhook_topic` in metadata:

```php
withAuditActor(null, fn() => $this->process($payload), [
    'source' => 'webhook',
    'webhook_topic' => 'ebay.order.created',
]);
```

For scheduled jobs: `source: 'cron'`, `job_class: self::class`.

## Action naming

`{domain}.{verb}` snake_case. Multi-marketplace: `{domain}.{subdomain}.{verb}`.

Examples: `product.created`, `order.shipped`, `listing.ebay.published`, `credential.oauth.refreshed`, `po.receipt.recorded`, `auth.failed_login`, `pricing.rule.evaluated`.

Reserved domains: `product`, `category`, `brand`, `bundle`, `inventory`, `po`, `order`, `shipment`, `return`, `listing`, `ebay`, `shopify`, `amazon`, `customer`, `pricing`, `message`, `campaign`, `email`, `credential`, `auth`, `user`, `team`, `organization`, `supplier`, `part_out`.

## Categories + severity

Always pass `category` + `severity`. Canonical categories: `user, team, organization, security, general, inventory, product, order, fulfillment, procurement, marketplace, customer, pricing, communication, marketing, integration, mobile, supplier, system`.

Severity: `critical, error, warning, info, debug`. Default `info`. `deleted` events auto-bump to `warning` when emitted via the trait.

## PII redaction

NEVER store in `metadata`:
- Raw passwords (even hashed/encrypted)
- Full credit cards (last-4 only)
- Full OAuth access tokens (token id or first-8 only)
- Full government IDs (last-4 only)
- Email body content (subject + recipient OK)

## Bulk operations

Skip per-row audit; emit one summary row:

```php
// Inside loop
$product->withoutAudit = true;
$product->save();

// After loop
UserActivityLog::log(
    'product.bulk_imported',
    "Bulk imported {$count} products from CSV",
    $user,
    ['count' => $count, 'source' => 'web', 'filename' => $filename],
    'info',
    'product',
);
```

## Test assertions

Every new mutation path needs a test that asserts the audit row was written. Use the `AssertsAudits` trait:

```php
use Tests\Support\AssertsAudits;

class ShipOrderTest extends TestCase
{
    use AssertsAudits;

    public function test_ship_emits_audit(): void
    {
        $this->actingAs($this->user);
        $this->post("/orders/{$order->id}/ship", [...]);
        $this->assertAudited('order.shipped', $this->user, [
            'tracking_number' => 'TEST123',
        ]);
    }
}
```

## You do NOT need to wrap audit calls in try/catch

`AuditHelper::event()` and the `AuditObserver` handlers both catch all exceptions internally — audit failures log to `storage/logs/laravel.log` and return `null` instead of bubbling. Write `audit_event(...)` calls inline with confidence; a broken audit cannot crash a login, save, webhook, or ship-order.

## Anti-patterns

- ❌ `\Log::info("Order shipped")` — file logs are not queryable, not joinable, not aggregatable into KPIs.
- ❌ Storing audit in domain tables (e.g. an `ebay_sync_logs` row for inventory sync). EbaySyncLog is for sync-specific debugging; audit goes to `user_activity_logs` in parallel.
- ❌ Emitting audit inside a transaction that may roll back without compensating — write audit AFTER the commit if the operation can fail mid-stream.
- ❌ Putting raw request payloads in `metadata` without redaction.
- ❌ Skipping audit "because it's an internal toggle" — internal toggles ARE state changes; emit with `severity: 'debug'` if noisy.

## When in doubt

If the action would matter to:
- A customer asking "who changed this?"
- An admin asking "who did this?"
- A KPI asking "how many did user X do today?"
- A security audit asking "when was this credential used?"

...it needs an audit row.
