# Repricing Engine

How automated pricing rules evaluate products and adjust selling prices based on configurable strategies.

## Overview

```
REPRICING RULES ---------> Defined per organization, ordered by priority
       |
       v
PRODUCT SCOPE -----------> Rules target all products, a category, or specific products
       |
       v
STRATEGY EVALUATION -----> Calculate new price using the rule's strategy
       |
       v
GUARD RAILS -------------> Apply min/max bounds, never price below cost
       |
       v
PRICE UPDATE ------------> Update product selling_price, log the change
```

---

## Architecture

### Key Files

| Layer | File |
|-------|------|
| Model | `app/Models/Pricing/RepricingRule.php` |
| Model | `app/Models/Pricing/RepricingLog.php` |
| Service | `app/Services/Pricing/RepricingEngine.php` |
| Controller | `app/Http/Controllers/Pricing/` |
| Routes | `routes/pricing.php` |
| Pages | `resources/js/Pages/Pricing/` |

---

## Data Model

### RepricingRule

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID | Primary key |
| `organization_id` | UUID | Owning organization |
| `name` | string | Human-readable rule name |
| `marketplace` | string | Target marketplace, or `*` for all |
| `scope_type` | string | `category`, `product`, or null (all products) |
| `scope_ids` | array (JSON) | Category IDs or product IDs the rule applies to |
| `strategy` | string | Pricing strategy (see strategies below) |
| `parameters` | array (JSON) | Strategy-specific configuration |
| `is_active` | boolean | Whether the rule is currently enabled |
| `priority` | int | Lower number = higher priority (evaluated first) |
| `last_run_at` | datetime | When the rule was last executed |

### RepricingLog

| Field | Type | Description |
|-------|------|-------------|
| `repricing_rule_id` | UUID | Rule that triggered the change |
| `product_id` | UUID | Product whose price changed |
| `old_price` | decimal | Previous selling price |
| `new_price` | decimal | New selling price |
| `reason` | string | Description of strategy applied |
| `applied_at` | datetime | When the change was made |

---

## Strategies

### match_lowest

Match the lowest competitor price. Requires competitor pricing data.

### beat_by_amount

Reduce the current selling price by a fixed dollar amount.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `beat_amount` | decimal | 1.00 | Dollar amount to subtract from current price |

Formula: `new_price = current_selling_price - beat_amount`

### beat_by_percent

Reduce the current selling price by a percentage.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `beat_percent` | decimal | 5 | Percentage to reduce by |

Formula: `new_price = current_selling_price * (1 - beat_percent / 100)`

### cost_plus

Set price as a markup over cost.

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `cost_plus_margin` | decimal | 30 | Percentage markup over cost |

Formula: `new_price = cost_price * (1 + cost_plus_margin / 100)`

Requires the product to have a `cost_price > 0`. Returns null otherwise.

### map_floor

Set price to the manufacturer's suggested retail price (MSRP). Used to enforce MAP (Minimum Advertised Price) policies.

Formula: `new_price = product.msrp`

Requires the product to have an `msrp > 0`. Returns null otherwise.

---

## Key Flows

### 1. Rule Evaluation

`RepricingEngine::runForOrganization()` executes all active rules:

1. Load all active rules for the organization, ordered by `priority` (ascending)
2. For each rule:
   a. Resolve the product scope (all active products, or filtered by category/product IDs)
   b. For each product in scope, calculate a new price using the rule's strategy
   c. Skip if the new price is null or within $0.01 of the current price
   d. Apply guard rails (see below)
   e. If the guarded price still differs, update `product.selling_price` and create a `RepricingLog` entry
   f. Update `rule.last_run_at`

### 2. Guard Rails

`RepricingEngine::applyGuards()` enforces price boundaries on every calculated price:

1. **Never below cost** -- If the product has a `cost_price > 0` and the new price would be below cost, the price change is rejected entirely (returns null)
2. **Minimum price** -- If `parameters.min_price` is set and the new price is below it, the price is raised to the minimum
3. **Maximum price** -- If `parameters.max_price` is set and the new price exceeds it, the price is capped at the maximum

Guard rail evaluation order matters: cost floor is checked first (hard reject), then min/max bounds are applied (soft clamp).

### 3. Product Scoping

Rules target products based on `scope_type`:

| Scope Type | Behavior |
|------------|----------|
| `null` (empty) | All active products in the organization |
| `category` | Products whose `category_id` is in `scope_ids` |
| `product` | Specific products whose `id` is in `scope_ids` |

Only products with `status = 'active'` are considered.

### 4. Scheduling

The repricing engine is designed to be triggered on a schedule (via Laravel's scheduler or a queued job). Each run processes all active rules for an organization and returns a summary:

```php
['processed' => 150, 'changed' => 12, 'errors' => 0]
```

---

## Integration Points

| System | Integration |
|--------|-------------|
| **Products** | Directly updates `Product.selling_price` when a rule fires |
| **Inventory Sync** | Price changes on products should trigger marketplace listing price updates via the inventory/listing sync system |
| **Profit/Loss** | New selling prices affect future order profitability calculations |
| **Audit Trail** | Every price change is logged in `RepricingLog` with old/new price, rule reference, and timestamp |
| **Marketplace Filtering** | Rules can target specific marketplaces or all (`*`), allowing different pricing strategies per channel |
