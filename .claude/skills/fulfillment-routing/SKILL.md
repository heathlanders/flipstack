---
name: fulfillment-routing
description: Fulfillment routing rules (location selection strategies) and shipping rules engine (carrier/service auto-selection based on order conditions)
user-invocable: false
---

# Fulfillment Routing and Shipping Rules

## Key Files

| Area | File | Purpose |
|------|------|---------|
| **Models** | `app/Models/Fulfillment/FulfillmentRoutingRule.php` | Routing rule definition with type, priority, parameters |
| | `app/Models/Fulfillment/ShippingRule.php` | Shipping rule with conditions JSON and carrier/service action |
| **Services** | `app/Services/Fulfillment/FulfillmentRouter.php` | Evaluates routing rules to select a fulfillment location |
| | `app/Services/Fulfillment/ShippingRuleEngine.php` | Evaluates shipping rules to select carrier/service for an order |
| **Controllers** | `app/Http/Controllers/Fulfillment/RoutingController.php` | CRUD + toggle + test for routing rules |
| | `app/Http/Controllers/Fulfillment/ShippingRuleController.php` | CRUD + toggle + test for shipping rules |
| **Routes** | `routes/fulfillment.php` | All fulfillment routes including routing and shipping rules |
| **Pages** | `resources/js/Pages/Fulfillment/Routing/` | Vue pages for routing rule management |
| | `resources/js/Pages/Fulfillment/ShippingRules/` | Vue pages for shipping rule management |

## Routing Rule Types

| Type | Constant | Strategy | How It Works |
|------|----------|----------|--------------|
| Nearest Warehouse | `proximity` | `proximityStrategy()` | Compares first 3 digits of order zip to location zips, picks closest numeric match |
| Lowest Shipping Cost | `lowest_cost` | Falls back to `proximityStrategy()` | Currently uses proximity as a proxy for cost (placeholder) |
| Highest Stock | `highest_stock` | `highestStockStrategy()` | Sums `InventoryLevel.quantity_on_hand` per location for ordered products, picks highest |
| Round Robin | `round_robin` | `roundRobinStrategy()` | Counts orders per location in last 7 days, picks location with fewest |
| Fixed Location | `specific_location` | `specificLocationStrategy()` | Returns the location specified in `parameters.location_id` |

## Routing Evaluation Flow

1. `FulfillmentRouter::selectLocation(Order)` loads active rules ordered by `priority ASC`
2. Each rule is evaluated via `evaluateRule()` which dispatches to the correct strategy method
3. First rule that returns a non-null `InventoryLocation` wins
4. If no rule matches, returns `null` (no auto-assignment)

## Routing Rule Fields

| Field | Type | Purpose |
|-------|------|---------|
| `organization_id` | string | Org scope |
| `name` | string | Human-readable label |
| `rule_type` | string | One of: `proximity`, `lowest_cost`, `highest_stock`, `round_robin`, `specific_location` |
| `priority` | integer | Lower = evaluated first |
| `parameters` | JSON | Strategy-specific config (e.g., `{"location_id": "uuid"}` for specific_location) |
| `is_active` | boolean | Only active rules are evaluated |

## Shipping Rule Engine

The shipping rule engine auto-selects a carrier, service, and package preset for an order based on configurable conditions.

### Condition Types

| Condition Key | Type | Checked Against |
|---------------|------|-----------------|
| `weight_min` / `weight_max` | numeric (oz) | `order.items.sum('weight_oz')` |
| `value_min` / `value_max` | numeric ($) | `order.total_amount` |
| `destination_states` | array of state codes | `order.shipping_state` (case-insensitive) |
| `destination_countries` | array of country codes | `order.shipping_country` (case-insensitive) |
| `marketplace` | string | `order.marketplace` (case-insensitive) |
| `item_count_min` / `item_count_max` | integer | `order.items_count` or `order.items.count()` |

All conditions are AND-ed. A rule with empty conditions matches everything.

### Action Fields

| Field | Type | Purpose |
|-------|------|---------|
| `action_carrier` | string | Carrier name: `USPS`, `UPS`, `FedEx`, `DHL` |
| `action_service` | string | Service level (e.g., `Priority Mail`, `Ground`) |
| `action_package_preset_id` | uuid (nullable) | Links to `PackagePreset` for dimensions/weight |

### Evaluation Flow

1. `ShippingRuleEngine::evaluate(Order)` loads active rules ordered by `priority ASC`
2. For each rule, `matchesConditions()` checks all condition types
3. First fully matching rule returns `{carrier, service, package_preset_id, rule_id, rule_name}`
4. If no rule matches, returns `null`

### Test Mode

`ShippingRuleEngine::testOrder(Order)` runs all rules and returns detailed results showing which checks passed/failed for each rule, plus the matched rule and order summary. Used by the test endpoint.

## Routes

### Routing Rules
| Method | URI | Name | Action |
|--------|-----|------|--------|
| GET | `/fulfillment/routing` | `fulfillment.routing.index` | List rules |
| GET | `/fulfillment/routing/create` | `fulfillment.routing.create` | Create form |
| POST | `/fulfillment/routing` | `fulfillment.routing.store` | Store rule |
| GET | `/fulfillment/routing/{rule}/edit` | `fulfillment.routing.edit` | Edit form |
| PUT | `/fulfillment/routing/{rule}` | `fulfillment.routing.update` | Update rule |
| DELETE | `/fulfillment/routing/{rule}` | `fulfillment.routing.destroy` | Delete rule |
| POST | `/fulfillment/routing/{rule}/toggle` | `fulfillment.routing.toggle` | Toggle active |
| POST | `/fulfillment/routing/test` | `fulfillment.routing.test` | Test rule evaluation for an order |

### Shipping Rules
| Method | URI | Name | Action |
|--------|-----|------|--------|
| GET | `/fulfillment/shipping-rules` | `fulfillment.shipping-rules.index` | List rules |
| GET | `/fulfillment/shipping-rules/create` | `fulfillment.shipping-rules.create` | Create form |
| POST | `/fulfillment/shipping-rules` | `fulfillment.shipping-rules.store` | Store rule |
| GET | `/fulfillment/shipping-rules/{rule}/edit` | `fulfillment.shipping-rules.edit` | Edit form |
| PUT | `/fulfillment/shipping-rules/{rule}` | `fulfillment.shipping-rules.update` | Update rule |
| DELETE | `/fulfillment/shipping-rules/{rule}` | `fulfillment.shipping-rules.destroy` | Delete rule |
| POST | `/fulfillment/shipping-rules/{rule}/toggle` | `fulfillment.shipping-rules.toggle` | Toggle active |
| POST | `/fulfillment/shipping-rules/test` | `fulfillment.shipping-rules.test` | Test rule evaluation for an order |

## Integration Points

- **OrderSyncService**: After order import, the router can be called to auto-assign a fulfillment location
- **Packing workflow**: During packing, the shipping rule engine can suggest carrier/service/package
- **PackagePreset**: Shipping rules can reference a package preset for auto-filling dimensions

## Business Rules

1. Rules are evaluated in `priority` order (ascending) -- first match wins
2. Only `is_active = true` rules are considered
3. Routing uses `withoutGlobalScopes()` to bypass the org tenant scope (manually filters by `organization_id`)
4. The proximity strategy compares 3-digit zip prefixes numerically (not geographic distance)
5. Round robin looks at orders from the last 7 days to determine distribution
6. Highest stock strategy only considers locations with `quantity_on_hand > 0`
7. Shipping rule conditions are cleaned on save (null/empty values stripped)

## Debugging Commands

```php
// List active routing rules for an org
FulfillmentRoutingRule::withoutGlobalScopes()
  ->where('organization_id', $orgId)
  ->where('is_active', true)
  ->orderBy('priority')
  ->get(['id', 'name', 'rule_type', 'priority', 'parameters']);

// Test routing for a specific order
$order = Order::with('items')->find($orderId);
$router = new \App\Services\Fulfillment\FulfillmentRouter;
$location = $router->selectLocation($order);
dump($location?->only(['id', 'name', 'code', 'postal_code']));

// List active shipping rules with presets
ShippingRule::withoutGlobalScope('organization')
  ->where('organization_id', $orgId)
  ->active()->ordered()
  ->with('packagePreset')
  ->get(['id', 'name', 'priority', 'conditions', 'action_carrier', 'action_service']);

// Test shipping rule matching for an order
$order = Order::with('items')->find($orderId);
$engine = new \App\Services\Fulfillment\ShippingRuleEngine;
$result = $engine->testOrder($order);
dump($result);

// Check which orders have no location assigned
Order::where('organization_id', $orgId)
  ->whereNull('location_id')
  ->whereIn('status', ['pending', 'assigned'])
  ->count();
```

## Audit Logging

| Action | Category | Severity | Metadata keys |
|--------|----------|----------|---------------|
| `routing.rule.created` | fulfillment | info | rule_type |
| `routing.rule.updated` | fulfillment | info | changes |
| `routing.rule.deleted` | fulfillment | warning | |
| `routing.rule.applied` | fulfillment | debug | order_id, rule_id, chosen_location_id |
| `shipping.rule.created` | fulfillment | info | conditions, action_carrier |
| `shipping.rule.updated` | fulfillment | info | changes |
| `shipping.rule.deleted` | fulfillment | warning | |
| `shipping.rule.applied` | fulfillment | debug | shipment_id, rule_id, chosen_carrier |

Trait: models in this feature use `LogsAudit` (`app/Traits/LogsAudit.php`) for auto CRUD diff. Domain lifecycle events emitted from `app/Services/Fulfillment/{RoutingService,ShippingRulesService}`; `FulfillmentRoutingRule` + `ShippingRule` models (`LogsAudit`) via `UserActivityLog::log(action, desc, $user, $metadata, $severity, $category)`.

Use `severity: 'debug'` for the high-volume `.applied` events to keep noise out of default Hub views.

Spec: `docs/AUDIT_SYSTEM.md`. Enforced rule: `.claude/rules/audit-logging.md`. Tests: `use Tests\Support\AssertsAudits;` then `$this->assertAudited('action', $user, [...])`.
