# Fulfillment Routing and Shipping Rules

## Overview

The fulfillment routing system automatically selects which warehouse location should fulfill an order, and the shipping rules engine determines which carrier, service level, and package preset to use. Both systems evaluate rules in priority order (lowest number first) and return the first match, giving organizations full control over fulfillment logic without manual intervention.

---

## Architecture

Two independent engines work in sequence during order processing:

1. **FulfillmentRouter** -- selects the warehouse location for an order
2. **ShippingRuleEngine** -- selects the carrier, service, and packaging for an order

Both follow the same pattern: load active rules ordered by priority, evaluate each rule against the order, and return the first match. If no rule matches, the system returns null and the operator must assign manually.

### Key Files

| File | Purpose |
|------|---------|
| `app/Models/Fulfillment/FulfillmentRoutingRule.php` | Routing rule model with type constants and priority ordering |
| `app/Models/Fulfillment/ShippingRule.php` | Shipping rule model with carrier constants and condition storage |
| `app/Services/Fulfillment/FulfillmentRouter.php` | Evaluates routing rules and dispatches to strategy methods |
| `app/Services/Fulfillment/ShippingRuleEngine.php` | Evaluates shipping rules against order conditions |
| `app/Models/Fulfillment/PackagePreset.php` | Saved package dimensions/weight templates |
| `app/Models/Inventory/InventoryLocation.php` | Warehouse locations with postal codes for proximity matching |

---

## Data Model

### `fulfillment_routing_rules`

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `organization_id` | uuid | FK to organizations |
| `name` | string | Human-readable rule name |
| `priority` | integer | Evaluation order (lower = first) |
| `rule_type` | string | One of: `proximity`, `lowest_cost`, `highest_stock`, `round_robin`, `specific_location` |
| `parameters` | json | Type-specific config (e.g., `{"location_id": "..."}` for specific_location) |
| `is_active` | boolean | Whether the rule is evaluated |

### `shipping_rules`

| Column | Type | Description |
|--------|------|-------------|
| `id` | uuid | Primary key |
| `organization_id` | uuid | FK to organizations |
| `name` | string | Human-readable rule name |
| `priority` | integer | Evaluation order (lower = first) |
| `conditions` | json | Match criteria (weight, value, destination, marketplace, item count) |
| `action_carrier` | string | Carrier to use: `USPS`, `UPS`, `FedEx`, `DHL` |
| `action_service` | string | Service level (e.g., `Priority Mail`, `Ground`) |
| `action_package_preset_id` | uuid | FK to package_presets (optional) |
| `is_active` | boolean | Whether the rule is evaluated |

---

## Key Flows

### Routing Strategy Evaluation

```
Order arrives
    |
FulfillmentRouter::selectLocation(Order)
    |
Load active rules for org, ordered by priority ASC
    |
For each rule:
    match rule_type:
        proximity       -> Compare 3-digit zip prefix of order vs. each location
        lowest_cost     -> Same as proximity (zip-based approximation)
        highest_stock   -> SUM(inventory_levels.quantity_on_hand) per location for ordered products
        round_robin     -> COUNT orders per location in last 7 days, pick fewest
        specific_location -> Return the configured location_id directly
    |
    If strategy returns a location -> done
    |
    If null -> try next rule
    |
No rules matched -> return null (manual assignment required)
```

### Routing Strategies in Detail

**Proximity / Lowest Cost** (`FulfillmentRouter::proximityStrategy`): Extracts the first 3 digits of the order's `shipping_postal_code` and each active location's `postal_code`, then picks the location with the smallest numeric difference. This is a lightweight approximation -- it does not call shipping rate APIs.

**Highest Stock** (`FulfillmentRouter::highestStockStrategy`): Queries `inventory_levels` for all products in the order, groups by `location_id`, sums `quantity_on_hand`, and returns the location with the highest total. Only considers locations with `is_active = true`.

**Round Robin** (`FulfillmentRouter::roundRobinStrategy`): Counts orders assigned to each active location in the past 7 days and picks the one with the fewest assignments.

**Specific Location** (`FulfillmentRouter::specificLocationStrategy`): Returns the location stored in `parameters.location_id` if it exists and is active.

### Shipping Rule Evaluation

```
Order ready for shipping
    |
ShippingRuleEngine::evaluate(Order)
    |
Load active shipping rules for org, ordered by priority ASC
    |
For each rule, check ALL conditions (AND logic):
    - weight_min / weight_max  (sum of items' weight_oz)
    - value_min / value_max    (order total_amount)
    - destination_states       (shipping_state in list)
    - destination_countries    (shipping_country in list)
    - marketplace              (order marketplace matches)
    - item_count_min / max     (number of order items)
    |
    All conditions pass -> return {carrier, service, package_preset_id}
    |
    Any condition fails -> try next rule
    |
No rules matched -> return null (manual selection required)
```

### Shipping Rule Conditions Schema

The `conditions` JSON column supports these keys:

```json
{
    "weight_min": 0,
    "weight_max": 16,
    "value_min": 0,
    "value_max": 100,
    "destination_states": ["CA", "NV", "OR"],
    "destination_countries": ["US"],
    "marketplace": "ebay",
    "item_count_min": 1,
    "item_count_max": 5
}
```

All conditions are optional. An empty conditions array matches every order (catch-all rule).

### Testing a Rule Against an Order

`ShippingRuleEngine::testOrder(Order)` returns a detailed diagnostic showing which rules matched and which condition checks passed or failed for each rule. Useful for debugging why a particular carrier was selected.

---

## Integration Points

- **Order Import**: After `OrderSyncService` imports an order, the fulfillment routing can be triggered to auto-assign a location. The router reads `Order.shipping_postal_code` for proximity-based strategies.
- **Inventory Levels**: The highest_stock strategy queries `InventoryLevel` records, which are updated by the inventory sync system (see `docs/INVENTORY_SYNC_SYSTEM.md`).
- **Packing Workflow**: During pick/pack/ship, the shipping rule engine suggests carrier and packaging. The `action_package_preset_id` links to `PackagePreset` for saved box dimensions.
- **Organization Scoping**: Both models use `BelongsToOrganization` trait. Rules are always filtered by `organization_id`.
- **Routes**: Managed via `routes/fulfillment.php` through the fulfillment controllers.
