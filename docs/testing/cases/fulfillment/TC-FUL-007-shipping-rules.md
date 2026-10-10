# TC-FUL-007: Shipping Rules Engine Test Cases

| Field              | Value                                           |
|--------------------|------------------------------------------------|
| **Document ID**    | TC-FUL-007                                      |
| **Domain**         | Fulfillment                                     |
| **Related Test Plan** | `docs/testing/plans/fulfillment-test-plan.md` |
| **Last Updated**   | 2026-04-15                                      |

## Overview

Test cases for the shipping rules engine, which auto-selects carrier and service level based on order conditions. Covers CRUD operations, individual condition types (weight, value, destination, marketplace, item count), combined AND logic, priority-based evaluation, and admin-only access control.

**Controller:** `App\Http\Controllers\Fulfillment\ShippingRuleController`
**Routes:** `fulfillment.shipping-rules.*`
**Model:** `App\Models\Fulfillment\ShippingRule`
**Service:** `App\Services\Fulfillment\ShippingRulesEngine`
**Authorization:** Requires `can:admin.access` permission.

---

## TC-FUL-007-01: View Shipping Rules List

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-007-01                                                |
| **Priority**    | P1                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | Yes                                                          |
| **Test File**   | `tests/Feature/Fulfillment/ShippingRuleTest.php`             |

### Preconditions

1. Authenticated user with `admin.access` permission.
2. Organization has at least 3 ShippingRule records with varying conditions, carriers, and active states.

### Test Steps

| Step | Action                                                              | Expected Result                                                                                                    |
|------|---------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------|
| 1    | GET `fulfillment.shipping-rules.index`.                             | Page loads successfully (200). Inertia renders the shipping rules index page.                                      |
| 2    | Verify list contents.                                               | Each rule displays: name, carrier, service level, conditions summary, priority, and `is_active` status.            |
| 3    | Verify sort order.                                                  | Rules are sorted by priority (ascending). Lower priority number = higher precedence.                               |
| 4    | Verify organization scoping.                                        | Only rules belonging to the authenticated user's organization are shown.                                          |

### Test Data

- ShippingRule records:
  - `{ name: "Heavy Items UPS Ground", carrier: "UPS", service: "Ground", conditions: { weight_min: 10 }, priority: 1, is_active: true }`
  - `{ name: "High Value FedEx", carrier: "FedEx", service: "Priority Overnight", conditions: { value_min: 500 }, priority: 2, is_active: true }`
  - `{ name: "Default USPS", carrier: "USPS", service: "Priority Mail", conditions: {}, priority: 99, is_active: true }`

### Postconditions

- No data is modified. Read-only operation.

### Notes

- The conditions summary in the list view should provide a human-readable representation of the rule's matching criteria.

---

## TC-FUL-007-02: Create Shipping Rule with Conditions and Carrier

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-007-02                                                |
| **Priority**    | P1                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | Yes                                                          |
| **Test File**   | `tests/Feature/Fulfillment/ShippingRuleTest.php`             |

### Preconditions

1. Authenticated user with `admin.access` permission.

### Test Steps

| Step | Action                                                                                                                                                                                       | Expected Result                                                                                                       |
|------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------|
| 1    | POST `fulfillment.shipping-rules.store` with payload: `name`, `carrier` = `USPS`, `service` = `Priority Mail`, `conditions` = `{ "weight_min": 0, "weight_max": 5 }`, `priority`, `is_active`. | Response 201/redirect. New ShippingRule created.                                                                      |
| 2    | Verify the created record in the database.                                                                                                                                                   | All fields match submitted values. `organization_id` matches the user's organization.                                |
| 3    | POST with `carrier` = `UPS` and multiple conditions: `{ "weight_min": 5, "value_min": 100, "destination_countries": ["US"] }`.                                                               | Success. Rule created with combined conditions stored in the conditions JSON column.                                 |
| 4    | POST with `carrier` = `FedEx`, `service` = `2Day`, and destination states condition: `{ "destination_states": ["CA", "NY", "TX"] }`.                                                         | Success. Rule created with state-based destination condition.                                                        |
| 5    | POST with `carrier` = `DHL` and marketplace condition: `{ "marketplace": "ebay" }`.                                                                                                          | Success. Rule created with marketplace condition.                                                                    |
| 6    | POST with missing `name` field.                                                                                                                                                              | Validation error. `name` is required.                                                                                |
| 7    | POST with missing `carrier` field.                                                                                                                                                           | Validation error. `carrier` is required.                                                                             |
| 8    | POST with invalid `carrier` value (e.g., `InvalidCarrier`).                                                                                                                                  | Validation error. `carrier` must be one of: `USPS`, `UPS`, `FedEx`, `DHL`.                                          |
| 9    | POST with `weight_min` greater than `weight_max`.                                                                                                                                            | Validation error. `weight_min` must be less than or equal to `weight_max`.                                           |
| 10   | POST with `value_min` greater than `value_max`.                                                                                                                                              | Validation error. `value_min` must be less than or equal to `value_max`.                                             |

### Test Data

```json
{
    "name": "Lightweight USPS Priority",
    "carrier": "USPS",
    "service": "Priority Mail",
    "conditions": {
        "weight_min": 0,
        "weight_max": 5
    },
    "priority": 1,
    "is_active": true
}
```

### Postconditions

- New ShippingRule record exists in the database scoped to the organization.

### Notes

- The `conditions` field is a JSON column supporting keys: `weight_min`, `weight_max`, `value_min`, `value_max`, `destination_states`, `destination_countries`, `marketplace`, `item_count_min`, `item_count_max`.
- Supported carriers: USPS, UPS, FedEx, DHL.

---

## TC-FUL-007-03: Update Shipping Rule

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-007-03                                                |
| **Priority**    | P1                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | Yes                                                          |
| **Test File**   | `tests/Feature/Fulfillment/ShippingRuleTest.php`             |

### Preconditions

1. Authenticated user with `admin.access` permission.
2. An existing ShippingRule belongs to the user's organization.

### Test Steps

| Step | Action                                                                                                    | Expected Result                                                                |
|------|-----------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------|
| 1    | PUT `fulfillment.shipping-rules.update` with updated `name`, `carrier`, and `conditions`.                 | Response indicates success (200/redirect).                                     |
| 2    | Reload the record from the database.                                                                      | All updated fields reflect the new values.                                     |
| 3    | PUT with updated `priority` value.                                                                        | Success. Priority updated, affecting rule evaluation order.                    |
| 4    | PUT with updated `conditions` to add a new condition key.                                                 | Success. Conditions JSON includes the new key.                                 |
| 5    | PUT with a rule ID belonging to a different organization.                                                 | 403 Forbidden or 404 Not Found. No cross-tenant modification.                 |
| 6    | PUT with an invalid `carrier` value.                                                                      | Validation error.                                                              |

### Test Data

```json
{
    "name": "Updated Heavy FedEx Ground",
    "carrier": "FedEx",
    "service": "Ground",
    "conditions": {
        "weight_min": 15,
        "weight_max": 70,
        "destination_countries": ["US", "CA"]
    },
    "priority": 3,
    "is_active": true
}
```

### Postconditions

- The ShippingRule record is updated.
- Other rules in the organization are not affected.

### Notes

- Updating conditions replaces the entire conditions JSON (not a partial merge).

---

## TC-FUL-007-04: Delete Shipping Rule

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-007-04                                                |
| **Priority**    | P1                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | Yes                                                          |
| **Test File**   | `tests/Feature/Fulfillment/ShippingRuleTest.php`             |

### Preconditions

1. Authenticated user with `admin.access` permission.
2. An existing ShippingRule belongs to the user's organization.

### Test Steps

| Step | Action                                                                     | Expected Result                                                     |
|------|----------------------------------------------------------------------------|---------------------------------------------------------------------|
| 1    | DELETE `fulfillment.shipping-rules.destroy` with the rule ID.              | Response indicates success (200/redirect).                          |
| 2    | Attempt to load the record from the database.                              | Record no longer exists (or is soft-deleted).                       |
| 3    | DELETE with a rule ID belonging to a different organization.               | 403 Forbidden or 404 Not Found.                                     |
| 4    | DELETE with a non-existent rule ID.                                        | 404 Not Found.                                                      |

### Test Data

- A ShippingRule record ID to delete.

### Postconditions

- The rule is removed from the database.
- Remaining rules continue to evaluate correctly.

### Notes

- Deleting a rule does not affect shipments that were previously assigned carriers based on this rule.

---

## TC-FUL-007-05: Toggle Shipping Rule

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-007-05                                                |
| **Priority**    | P2                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | Yes                                                          |
| **Test File**   | `tests/Feature/Fulfillment/ShippingRuleTest.php`             |

### Preconditions

1. Authenticated user with `admin.access` permission.
2. A ShippingRule exists with `is_active` = `true`.

### Test Steps

| Step | Action                                                                          | Expected Result                                                     |
|------|---------------------------------------------------------------------------------|---------------------------------------------------------------------|
| 1    | POST `fulfillment.shipping-rules.toggle` with the rule ID.                      | Response indicates success. `is_active` toggled to `false`.         |
| 2    | Reload the record.                                                              | `is_active` is `false`.                                             |
| 3    | POST `fulfillment.shipping-rules.toggle` again with the same rule ID.           | Response indicates success. `is_active` toggled back to `true`.     |
| 4    | Reload the record.                                                              | `is_active` is `true`.                                              |
| 5    | Toggle a rule from a different organization.                                    | 403 Forbidden or 404 Not Found.                                     |

### Test Data

- A ShippingRule with `is_active` = `true`.

### Postconditions

- The `is_active` field is toggled.
- Inactive rules are skipped by the shipping rules engine.

### Notes

- Toggling provides a quick way to disable a rule without deleting it, preserving the configuration for re-enablement.

---

## TC-FUL-007-06: Test Rule Against Order Returns Carrier/Service Match

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-007-06                                                |
| **Priority**    | P1                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | Yes                                                          |
| **Test File**   | `tests/Feature/Fulfillment/ShippingRuleTest.php`             |

### Preconditions

1. Authenticated user with `admin.access` permission.
2. At least one active ShippingRule exists with specific conditions.
3. An Order exists with attributes that match (or do not match) the rule conditions.

### Test Steps

| Step | Action                                                                                        | Expected Result                                                                                                       |
|------|-----------------------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------------------------------------|
| 1    | POST `fulfillment.shipping-rules.test` with `order_id`.                                       | Response returns the matched carrier, service level, and the rule name/ID that matched.                              |
| 2    | Create an order that matches a weight-based rule and test.                                     | Returns the carrier/service specified by the weight-based rule.                                                      |
| 3    | Create an order that matches no rules and test.                                                | Response indicates no rule matched. May return a default carrier or an explicit "no match" message.                   |
| 4    | POST test with an invalid `order_id`.                                                         | 404 Not Found or validation error.                                                                                    |

### Test Data

- ShippingRule: `{ conditions: { weight_min: 0, weight_max: 5 }, carrier: "USPS", service: "First Class" }`
- Order with total weight 3 lbs (should match).
- Order with total weight 15 lbs (should not match this rule).

### Postconditions

- No data is modified. Simulation/preview only.
- No shipments are created or updated.

### Notes

- The test endpoint runs the full shipping rules engine against the specified order and returns the result without side effects.
- Useful for admins to validate rule configuration before going live.

---

## TC-FUL-007-07: Weight Condition Matching

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-007-07                                                |
| **Priority**    | P1                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | No                                                           |
| **Test File**   | --                                                           |

### Preconditions

1. Authenticated user with `admin.access` permission.
2. A ShippingRule exists with conditions: `{ "weight_min": 5, "weight_max": 20 }`, carrier `UPS`, service `Ground`.
3. Orders exist with varying total weights.

### Test Steps

| Step | Action                                                                                           | Expected Result                                                              |
|------|--------------------------------------------------------------------------------------------------|------------------------------------------------------------------------------|
| 1    | Test rule against an order with total weight 10 lbs (within range 5-20).                         | Rule matches. Returns carrier `UPS`, service `Ground`.                       |
| 2    | Test rule against an order with total weight 5 lbs (exact minimum boundary).                     | Rule matches. Boundary is inclusive.                                         |
| 3    | Test rule against an order with total weight 20 lbs (exact maximum boundary).                    | Rule matches. Boundary is inclusive.                                         |
| 4    | Test rule against an order with total weight 4.9 lbs (below minimum).                            | Rule does not match.                                                         |
| 5    | Test rule against an order with total weight 20.1 lbs (above maximum).                           | Rule does not match.                                                         |
| 6    | Create a rule with only `weight_min` (no `weight_max`): `{ "weight_min": 30 }`.                 | Rule created successfully.                                                   |
| 7    | Test against an order with total weight 50 lbs.                                                  | Rule matches (no upper bound).                                               |
| 8    | Test against an order with total weight 29 lbs.                                                  | Rule does not match (below minimum).                                         |

### Test Data

- Orders with total weights: 4.9, 5.0, 10.0, 20.0, 20.1, 29.0, 50.0 lbs.

### Postconditions

- No data is modified.

### Notes

- Weight is typically the sum of all order item weights.
- Boundary conditions (min/max inclusive vs exclusive) should be documented and tested.

---

## TC-FUL-007-08: Value Condition Matching

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-007-08                                                |
| **Priority**    | P1                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | No                                                           |
| **Test File**   | --                                                           |

### Preconditions

1. Authenticated user with `admin.access` permission.
2. A ShippingRule exists with conditions: `{ "value_min": 100, "value_max": 500 }`, carrier `FedEx`, service `2Day`.
3. Orders exist with varying total values.

### Test Steps

| Step | Action                                                                                           | Expected Result                                                              |
|------|--------------------------------------------------------------------------------------------------|------------------------------------------------------------------------------|
| 1    | Test rule against an order with total value $250 (within range 100-500).                         | Rule matches. Returns carrier `FedEx`, service `2Day`.                       |
| 2    | Test rule against an order with total value $100 (exact minimum boundary).                       | Rule matches. Boundary is inclusive.                                         |
| 3    | Test rule against an order with total value $500 (exact maximum boundary).                       | Rule matches. Boundary is inclusive.                                         |
| 4    | Test rule against an order with total value $99.99 (below minimum).                              | Rule does not match.                                                         |
| 5    | Test rule against an order with total value $500.01 (above maximum).                             | Rule does not match.                                                         |
| 6    | Create a rule with only `value_min` = 1000 (no `value_max`).                                    | Rule created successfully.                                                   |
| 7    | Test against an order with total value $2000.                                                    | Rule matches (no upper bound).                                               |

### Test Data

- Orders with total values: $99.99, $100.00, $250.00, $500.00, $500.01, $2000.00.

### Postconditions

- No data is modified.

### Notes

- Value is typically the sum of order item prices (subtotal before tax/shipping).
- Currency should be consistent (organization's base currency).

---

## TC-FUL-007-09: Destination Condition Matching

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-007-09                                                |
| **Priority**    | P1                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | No                                                           |
| **Test File**   | --                                                           |

### Preconditions

1. Authenticated user with `admin.access` permission.
2. ShippingRule A exists with conditions: `{ "destination_states": ["CA", "OR", "WA"] }`, carrier `USPS`, service `Priority Mail`.
3. ShippingRule B exists with conditions: `{ "destination_countries": ["CA", "MX"] }`, carrier `DHL`, service `Express`.
4. Orders exist with shipping addresses in various states and countries.

### Test Steps

| Step | Action                                                                                                  | Expected Result                                                                              |
|------|---------------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------|
| 1    | Test Rule A against an order shipping to California (CA).                                               | Rule matches. Returns carrier `USPS`, service `Priority Mail`.                               |
| 2    | Test Rule A against an order shipping to Oregon (OR).                                                   | Rule matches.                                                                                |
| 3    | Test Rule A against an order shipping to New York (NY).                                                 | Rule does not match (NY not in state list).                                                  |
| 4    | Test Rule B against an order shipping to Canada (country code CA).                                      | Rule matches. Returns carrier `DHL`, service `Express`.                                      |
| 5    | Test Rule B against an order shipping to Mexico (country code MX).                                      | Rule matches.                                                                                |
| 6    | Test Rule B against an order shipping to US.                                                            | Rule does not match (US not in country list).                                                |
| 7    | Create a rule with both `destination_states` and `destination_countries`.                                | Rule created. Both conditions must be satisfied (AND logic).                                 |
| 8    | Test combined rule against an order shipping to CA, US (state matches, country matches if US included). | Result depends on whether both conditions must match simultaneously.                         |

### Test Data

- Orders with shipping addresses:
  - `{ state: "CA", country: "US" }`
  - `{ state: "OR", country: "US" }`
  - `{ state: "NY", country: "US" }`
  - `{ state: "ON", country: "CA" }` (Ontario, Canada)
  - `{ state: "DF", country: "MX" }` (Mexico City, Mexico)

### Postconditions

- No data is modified.

### Notes

- State codes are US state abbreviations (2 letters).
- Country codes are ISO 3166-1 alpha-2 (2 letters).
- Ensure case-insensitive matching or document expected case format.

---

## TC-FUL-007-10: Marketplace Condition Matching

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-007-10                                                |
| **Priority**    | P2                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | No                                                           |
| **Test File**   | --                                                           |

### Preconditions

1. Authenticated user with `admin.access` permission.
2. ShippingRule exists with conditions: `{ "marketplace": "ebay" }`, carrier `USPS`, service `Priority Mail`.
3. Orders exist from different marketplaces (eBay, Shopify, Amazon, manual).

### Test Steps

| Step | Action                                                                                | Expected Result                                                              |
|------|---------------------------------------------------------------------------------------|------------------------------------------------------------------------------|
| 1    | Test rule against an eBay order.                                                      | Rule matches. Returns carrier `USPS`, service `Priority Mail`.               |
| 2    | Test rule against a Shopify order.                                                    | Rule does not match (marketplace is `shopify`, not `ebay`).                  |
| 3    | Test rule against an Amazon order.                                                    | Rule does not match.                                                         |
| 4    | Test rule against a manually created order (no marketplace).                          | Rule does not match.                                                         |
| 5    | Create a rule with `{ "marketplace": "shopify" }` and test against a Shopify order.   | Rule matches.                                                                |

### Test Data

- Orders with `marketplace` field values: `ebay`, `shopify`, `amazon`, `null`/`manual`.

### Postconditions

- No data is modified.

### Notes

- The marketplace value on the Order model is set during order import/creation.
- Marketplace matching should be case-insensitive or use a consistent lowercase format.

---

## TC-FUL-007-11: Item Count Condition Matching

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-007-11                                                |
| **Priority**    | P2                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | No                                                           |
| **Test File**   | --                                                           |

### Preconditions

1. Authenticated user with `admin.access` permission.
2. A ShippingRule exists with conditions: `{ "item_count_min": 1, "item_count_max": 3 }`, carrier `USPS`, service `First Class`.
3. Orders exist with varying numbers of line items.

### Test Steps

| Step | Action                                                                                    | Expected Result                                                              |
|------|-------------------------------------------------------------------------------------------|------------------------------------------------------------------------------|
| 1    | Test rule against an order with 2 items.                                                  | Rule matches. Returns carrier `USPS`, service `First Class`.                 |
| 2    | Test rule against an order with 1 item (exact minimum).                                   | Rule matches.                                                                |
| 3    | Test rule against an order with 3 items (exact maximum).                                  | Rule matches.                                                                |
| 4    | Test rule against an order with 4 items (above maximum).                                  | Rule does not match.                                                         |
| 5    | Test rule against an order with 0 items (edge case).                                      | Rule does not match (below minimum of 1).                                    |
| 6    | Create a rule with only `item_count_min` = 5 (no max).                                    | Rule created.                                                                |
| 7    | Test against an order with 10 items.                                                      | Rule matches (no upper bound).                                               |

### Test Data

- Orders with item counts: 0, 1, 2, 3, 4, 10.

### Postconditions

- No data is modified.

### Notes

- Item count refers to the number of distinct line items (order_items), not the total quantity across all items. Verify which interpretation the engine uses.

---

## TC-FUL-007-12: Multiple Conditions with AND Logic

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-007-12                                                |
| **Priority**    | P1                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | No                                                           |
| **Test File**   | --                                                           |

### Preconditions

1. Authenticated user with `admin.access` permission.
2. A ShippingRule exists with multiple conditions:
   ```json
   {
       "weight_min": 0,
       "weight_max": 10,
       "value_min": 50,
       "value_max": 200,
       "destination_states": ["CA", "NY"],
       "marketplace": "ebay"
   }
   ```
   Carrier: `FedEx`, Service: `Ground`.

### Test Steps

| Step | Action                                                                                                                              | Expected Result                                       |
|------|-------------------------------------------------------------------------------------------------------------------------------------|-------------------------------------------------------|
| 1    | Test against order: weight 5 lbs, value $100, destination CA, marketplace ebay. (All conditions met.)                               | Rule matches. Returns `FedEx` / `Ground`.             |
| 2    | Test against order: weight 5 lbs, value $100, destination CA, marketplace shopify. (Marketplace does not match.)                    | Rule does not match.                                  |
| 3    | Test against order: weight 5 lbs, value $100, destination TX, marketplace ebay. (Destination state does not match.)                 | Rule does not match.                                  |
| 4    | Test against order: weight 15 lbs, value $100, destination CA, marketplace ebay. (Weight exceeds maximum.)                          | Rule does not match.                                  |
| 5    | Test against order: weight 5 lbs, value $300, destination CA, marketplace ebay. (Value exceeds maximum.)                            | Rule does not match.                                  |
| 6    | Test against order: weight 5 lbs, value $100, destination NY, marketplace ebay. (All conditions met, different state.)              | Rule matches.                                         |
| 7    | Test against order: weight 0.5 lbs, value $50, destination CA, marketplace ebay. (Boundary values, all conditions met.)             | Rule matches.                                         |

### Test Data

- Orders with combinations of weight, value, destination, and marketplace attributes as listed in the test steps.

### Postconditions

- No data is modified.

### Notes

- All conditions within a single rule use AND logic: every condition must be satisfied for the rule to match.
- If any one condition fails, the entire rule fails to match, and the engine moves to the next rule by priority.

---

## TC-FUL-007-13: Rules Evaluate in Priority Order

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-007-13                                                |
| **Priority**    | P1                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | No                                                           |
| **Test File**   | --                                                           |

### Preconditions

1. Authenticated user with `admin.access` permission.
2. Organization has 3 active ShippingRules that could all match the same order:
   - Rule A: priority 1, conditions `{ "weight_min": 0 }`, carrier `USPS`, service `Priority Mail`.
   - Rule B: priority 2, conditions `{ "weight_min": 0 }`, carrier `UPS`, service `Ground`.
   - Rule C: priority 3, conditions `{ "weight_min": 0 }`, carrier `FedEx`, service `Express`.
3. An order exists with weight > 0 (matches all three rules).

### Test Steps

| Step | Action                                                                          | Expected Result                                                                         |
|------|---------------------------------------------------------------------------------|-----------------------------------------------------------------------------------------|
| 1    | POST `fulfillment.shipping-rules.test` with the order ID.                       | Returns `USPS` / `Priority Mail` (Rule A, priority 1, first match wins).                |
| 2    | Deactivate Rule A.                                                              | Rule A is inactive.                                                                     |
| 3    | POST test again with the same order ID.                                         | Returns `UPS` / `Ground` (Rule B, priority 2, now first active match).                  |
| 4    | Update Rule C priority to 1 and Rule B priority to 3.                           | Priorities swapped.                                                                     |
| 5    | POST test again with the same order ID.                                         | Returns `FedEx` / `Express` (Rule C now has priority 1).                                |
| 6    | Deactivate all rules.                                                           | All rules inactive.                                                                     |
| 7    | POST test with the order ID.                                                    | No rule matches. Response indicates no carrier/service selected (fallback or no-match).  |

### Test Data

- Three ShippingRules with overlapping conditions but different priorities and carriers.

### Postconditions

- Rule active states and priorities may be modified during the test.

### Notes

- "First match wins" means the engine evaluates rules in ascending priority order and stops at the first rule whose conditions are all satisfied.
- This is the same evaluation pattern as fulfillment routing rules (TC-FUL-006-09).

---

## TC-FUL-007-14: Non-Admin User Cannot Access Shipping Rules

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-007-14                                                |
| **Priority**    | P1                                                           |
| **Type**        | Security                                                     |
| **Platform**    | Web                                                          |
| **Automated**   | No                                                           |
| **Test File**   | --                                                           |

### Preconditions

1. Authenticated user WITHOUT `admin.access` permission (e.g., a user with `lister` or `fulfillment` role only).
2. At least one ShippingRule exists in the organization.

### Test Steps

| Step | Action                                                                          | Expected Result                                          |
|------|---------------------------------------------------------------------------------|----------------------------------------------------------|
| 1    | GET `fulfillment.shipping-rules.index` as non-admin user.                       | 403 Forbidden.                                           |
| 2    | POST `fulfillment.shipping-rules.store` as non-admin user.                      | 403 Forbidden.                                           |
| 3    | PUT `fulfillment.shipping-rules.update` as non-admin user with a valid rule ID. | 403 Forbidden.                                           |
| 4    | DELETE `fulfillment.shipping-rules.destroy` as non-admin user.                  | 403 Forbidden.                                           |
| 5    | POST `fulfillment.shipping-rules.toggle` as non-admin user.                     | 403 Forbidden.                                           |
| 6    | POST `fulfillment.shipping-rules.test` as non-admin user.                       | 403 Forbidden.                                           |

### Test Data

- A user account with a non-admin role (e.g., `fulfillment_worker` or `lister`).

### Postconditions

- No data is accessed or modified.
- All requests return 403.

### Notes

- The `can:admin.access` middleware is applied at the route group level for all `fulfillment.shipping-rules.*` routes.
- Verify that the 403 response does not leak any rule data in the error body.
