---
name: repricing
description: Automated repricing engine with multiple strategies, priority-based rule evaluation, price guards, and change logging
user-invocable: false
---

# Repricing System

## Key Files

| Area | File | Purpose |
|------|------|---------|
| **Models** | `app/Models/Pricing/RepricingRule.php` | Rule definition: strategy, scope, parameters, priority, marketplace |
| | `app/Models/Pricing/RepricingLog.php` | Price change audit trail with old/new/competitor prices |
| **Services** | `app/Services/Pricing/RepricingEngine.php` | Core engine: rule evaluation, strategy calculation, price guards |
| **Controllers** | `app/Http/Controllers/Pricing/RepricingController.php` | Rule CRUD, toggle, manual run, change logs |
| **Routes** | `routes/pricing.php` | All repricing routes under `pricing.repricing.` prefix |

## Strategies

| Constant | Value | Description | Key Parameter |
|----------|-------|-------------|---------------|
| `STRATEGY_COST_PLUS` | `cost_plus` | Price = cost * (1 + margin%) | `cost_plus_margin` (default 30) |
| `STRATEGY_BEAT_BY_AMOUNT` | `beat_by_amount` | Price = current - $amount | `beat_amount` (default 1) |
| `STRATEGY_BEAT_BY_PERCENT` | `beat_by_percent` | Price = current * (1 - percent%) | `beat_percent` (default 5) |
| `STRATEGY_MATCH_LOWEST` | `match_lowest` | Match lowest competitor price | (not yet implemented) |
| `STRATEGY_MAP_FLOOR` | `map_floor` | Price = product MSRP | -- |

## Rule Structure

```
RepricingRule {
  name: string
  marketplace: 'ebay' | 'shopify' | 'amazon' | '*'   // '*' matches all
  scope_type: 'all' | 'category' | 'product'
  scope_ids: array (category IDs or product IDs)
  strategy: string (one of the 5 strategies)
  parameters: {
    min_price: float?       // floor price guard
    max_price: float?       // ceiling price guard
    cost_plus_margin: float?
    beat_amount: float?
    beat_percent: float?
  }
  priority: integer         // lower = higher priority
  is_active: boolean
  last_run_at: datetime?
}
```

## Rule Evaluation Flow

`RepricingEngine::runForOrganization()`:

1. Load all active rules for the organization, ordered by priority (ascending)
2. For each rule:
   a. Get matching products based on scope (all active products, or filtered by category/product IDs)
   b. For each product:
      - Calculate new price using the rule's strategy
      - Skip if price change is less than $0.01
      - Apply price guards (min/max bounds, never below cost)
      - Skip if guarded price equals current price
      - Update `product.selling_price`
      - Create `RepricingLog` entry
3. Update `rule.last_run_at`
4. Return summary: `{processed, changed, errors}`

**Important:** Rules are evaluated independently -- all active rules run against their scoped products. A product matched by multiple rules will have the last rule's price win (highest priority number). This is first-match-wins by priority order.

## Price Guards

`RepricingEngine::applyGuards()` enforces three constraints in order:

1. **Never below cost**: If `product.cost_price > 0` and `newPrice < cost_price`, return null (skip)
2. **Min price floor**: If `rule.parameters.min_price` is set and `newPrice < min_price`, clamp to `min_price`
3. **Max price ceiling**: If `rule.parameters.max_price` is set and `newPrice > max_price`, clamp to `max_price`

## RepricingLog Fields

| Field | Purpose |
|-------|---------|
| `repricing_rule_id` | Which rule triggered the change |
| `product_id` | Affected product |
| `listing_type` / `listing_id` | Target listing (nullable) |
| `old_price` | Price before change |
| `new_price` | Price after change |
| `competitor_price` | Competitor price (for match/beat strategies) |
| `reason` | Human-readable reason string |
| `applied_at` | Timestamp of application |

Computed attributes: `price_change` (dollar diff), `price_change_percent` (percent diff).

## Routes

All routes require authentication under prefix `pricing/repricing`:

| Method | URI | Route Name | Action |
|--------|-----|------------|--------|
| GET | `/pricing/repricing` | `pricing.repricing.index` | List rules with stats |
| GET | `/pricing/repricing/create` | `pricing.repricing.create` | Create form |
| POST | `/pricing/repricing` | `pricing.repricing.store` | Store new rule |
| GET | `/pricing/repricing/logs` | `pricing.repricing.logs` | Change log with rule filter |
| POST | `/pricing/repricing/run` | `pricing.repricing.run` | Manual run all active rules |
| GET | `/pricing/repricing/{rule}/edit` | `pricing.repricing.edit` | Edit form |
| PUT | `/pricing/repricing/{rule}` | `pricing.repricing.update` | Update rule |
| DELETE | `/pricing/repricing/{rule}` | `pricing.repricing.destroy` | Delete rule |
| POST | `/pricing/repricing/{rule}/toggle` | `pricing.repricing.toggle` | Toggle active/paused |

## Index Page Stats

- `total_rules` -- total rule count
- `active_rules` -- active rule count
- `recent_changes` -- RepricingLog entries from the last 24 hours

## Debugging Commands

```php
// Active rules ordered by priority
RepricingRule::where('organization_id', $orgId)->active()->get(['name', 'strategy', 'priority', 'last_run_at']);

// Recent price changes
RepricingLog::whereHas('rule', fn($q) => $q->withoutGlobalScopes()->where('organization_id', $orgId))
  ->orderByDesc('applied_at')
  ->limit(20)->get(['product_id', 'old_price', 'new_price', 'reason', 'applied_at']);

// Products repriced below margin threshold
RepricingLog::whereHas('rule', fn($q) => $q->withoutGlobalScopes()->where('organization_id', $orgId))
  ->whereHas('product', fn($q) => $q->whereRaw('selling_price < cost_price * 1.1'))
  ->with('product:id,name,sku,selling_price,cost_price')
  ->get();

// Dry run: check what a rule would change
$engine = app(RepricingEngine::class);
$results = $engine->runForOrganization($orgId);
// Returns: ['processed' => X, 'changed' => Y, 'errors' => Z]
```

## Audit Logging

| Action | Category | Severity | Metadata keys |
|--------|----------|----------|---------------|
| `pricing.rule.created` | pricing | info | strategy, marketplace, priority |
| `pricing.rule.updated` | pricing | info | changes |
| `pricing.rule.deleted` | pricing | warning | rule_name |
| `pricing.rule.evaluated` | pricing | debug | product_id, rule_id, old_price, new_price, source: cron |
| `pricing.repriced` | pricing | info | product_id, marketplace, old_price, new_price, rule_id |
| `pricing.guard_blocked` | pricing | warning | product_id, attempted_price, guard_rule |

Trait: models in this feature use `LogsAudit` (`app/Traits/LogsAudit.php`) for auto CRUD diff. Domain lifecycle events emitted from `app/Services/Pricing/RepricingEngine`, `app/Jobs/Pricing/RunRepricingRules`; `RepricingRule` model (`LogsAudit`). Engine uses `withAuditActor(null, ..., ['source' => 'cron'])`. via `UserActivityLog::log(action, desc, $user, $metadata, $severity, $category)`.

KPI consumer: `UserProductivityDaily.pricing_rules_changed` (from `pricing.rule.created|updated|deleted` -- excludes the high-volume `.evaluated` events).

Spec: `docs/AUDIT_SYSTEM.md`. Enforced rule: `.claude/rules/audit-logging.md`. Tests: `use Tests\Support\AssertsAudits;` then `$this->assertAudited('action', $user, [...])`.
