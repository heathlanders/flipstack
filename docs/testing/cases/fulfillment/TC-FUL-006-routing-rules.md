# TC-FUL-006: Fulfillment Routing Rules Test Cases

| Field              | Value                                           |
|--------------------|------------------------------------------------|
| **Document ID**    | TC-FUL-006                                      |
| **Domain**         | Fulfillment                                     |
| **Related Test Plan** | `docs/testing/plans/fulfillment-test-plan.md` |
| **Last Updated**   | 2026-04-15                                      |

## Overview

Test cases for the fulfillment routing rules engine, which determines which inventory location fulfills an order based on configurable strategies. Covers CRUD operations, strategy types (proximity, lowest_cost, highest_stock, round_robin, specific_location), rule evaluation order, and admin-only access control.

**Controller:** `App\Http\Controllers\Fulfillment\RoutingController`
**Routes:** `fulfillment.routing.*`
**Model:** `App\Models\Fulfillment\FulfillmentRoutingRule`
**Authorization:** Requires `can:admin.access` permission.

---

## TC-FUL-006-01: View Routing Rules List

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-006-01                                                |
| **Priority**    | P1                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | Yes                                                          |
| **Test File**   | `tests/Feature/Fulfillment/RoutingRuleTest.php`              |

### Preconditions

1. Authenticated user with `admin.access` permission.
2. Organization has at least 3 FulfillmentRoutingRule records with varying types, priorities, and active states.

### Test Steps

| Step | Action                                                              | Expected Result                                                                                                   |
|------|---------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------|
| 1    | GET `fulfillment.routing.index`.                                    | Page loads successfully (200). Inertia renders the routing rules index page.                                      |
| 2    | Verify list contents.                                               | Each rule displays: name, type (e.g., `proximity`, `highest_stock`), priority (numeric), and `is_active` status.  |
| 3    | Verify sort order.                                                  | Rules are sorted by priority (ascending) so highest-priority rules appear first.                                  |
| 4    | Verify organization scoping.                                        | Only rules belonging to the authenticated user's organization are shown.                                         |

### Test Data

- FulfillmentRoutingRule records:
  - `{ name: "East Coast Proximity", type: "proximity", priority: 1, is_active: true }`
  - `{ name: "Cheapest Shipping", type: "lowest_cost", priority: 2, is_active: true }`
  - `{ name: "Overflow Warehouse", type: "specific_location", priority: 3, is_active: false }`

### Postconditions

- No data is modified. Read-only operation.

### Notes

- The automated test in `RoutingRuleTest.php` covers the basic index response and data structure.

---

## TC-FUL-006-02: Create Routing Rule

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-006-02                                                |
| **Priority**    | P1                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | Yes                                                          |
| **Test File**   | `tests/Feature/Fulfillment/RoutingRuleTest.php`              |

### Preconditions

1. Authenticated user with `admin.access` permission.
2. Organization has at least one InventoryLocation (needed for `specific_location` type).

### Test Steps

| Step | Action                                                                                                                                          | Expected Result                                                                                             |
|------|-------------------------------------------------------------------------------------------------------------------------------------------------|-------------------------------------------------------------------------------------------------------------|
| 1    | POST `fulfillment.routing.store` with payload: `name`, `type` = `proximity`, `priority` = 1, `is_active` = true, `parameters` = `{}`.          | Response 201/redirect. New FulfillmentRoutingRule created in database.                                       |
| 2    | Verify the created record in the database.                                                                                                      | All fields match submitted values. `organization_id` matches the user's organization.                       |
| 3    | POST with `type` = `specific_location` and `parameters` = `{ "location_id": "<valid-uuid>" }`.                                                 | Success. Rule created with the location reference in parameters.                                            |
| 4    | POST with `type` = `round_robin` and `parameters` = `{ "location_ids": ["<uuid1>", "<uuid2>"] }`.                                              | Success. Rule created with multiple location references.                                                    |
| 5    | POST with missing `name` field.                                                                                                                 | Validation error. `name` is required.                                                                       |
| 6    | POST with missing `type` field.                                                                                                                 | Validation error. `type` is required.                                                                       |
| 7    | POST with invalid `type` value (e.g., `random`).                                                                                                | Validation error. `type` must be one of: `proximity`, `lowest_cost`, `highest_stock`, `round_robin`, `specific_location`. |
| 8    | POST with missing `priority` field.                                                                                                             | Validation error or default priority assigned.                                                              |

### Test Data

```json
{
    "name": "Primary Warehouse Priority",
    "type": "proximity",
    "priority": 1,
    "is_active": true,
    "parameters": {}
}
```

### Postconditions

- New FulfillmentRoutingRule record exists in the database scoped to the organization.

### Notes

- The `parameters` field is a JSON column that stores type-specific configuration.
- For `specific_location`, the `parameters.location_id` must reference a valid InventoryLocation in the same organization.

---

## TC-FUL-006-03: Update Routing Rule

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-006-03                                                |
| **Priority**    | P1                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | Yes                                                          |
| **Test File**   | `tests/Feature/Fulfillment/RoutingRuleTest.php`              |

### Preconditions

1. Authenticated user with `admin.access` permission.
2. An existing FulfillmentRoutingRule belongs to the user's organization.

### Test Steps

| Step | Action                                                                                                           | Expected Result                                                                      |
|------|------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------|
| 1    | PUT `fulfillment.routing.update` with updated `name` and `priority`.                                             | Response indicates success (200/redirect).                                           |
| 2    | Reload the record from the database.                                                                             | `name` and `priority` reflect the updated values.                                    |
| 3    | PUT with updated `type` from `proximity` to `highest_stock`.                                                     | Success. `type` is updated.                                                          |
| 4    | PUT with updated `parameters` JSON.                                                                              | Success. `parameters` column reflects new JSON.                                      |
| 5    | PUT with a rule ID belonging to a different organization.                                                        | 403 Forbidden or 404 Not Found. No cross-tenant modification.                        |
| 6    | PUT with invalid `type` value.                                                                                   | Validation error.                                                                    |

### Test Data

```json
{
    "name": "Updated Rule Name",
    "type": "highest_stock",
    "priority": 5,
    "is_active": true,
    "parameters": {}
}
```

### Postconditions

- The FulfillmentRoutingRule record is updated with new values.
- Other rules in the organization are not affected.

### Notes

- Changing a rule's priority may affect the evaluation order of all rules (see TC-FUL-006-09).

---

## TC-FUL-006-04: Delete Routing Rule

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-006-04                                                |
| **Priority**    | P1                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | Yes                                                          |
| **Test File**   | `tests/Feature/Fulfillment/RoutingRuleTest.php`              |

### Preconditions

1. Authenticated user with `admin.access` permission.
2. An existing FulfillmentRoutingRule belongs to the user's organization.

### Test Steps

| Step | Action                                                                     | Expected Result                                                              |
|------|----------------------------------------------------------------------------|------------------------------------------------------------------------------|
| 1    | DELETE `fulfillment.routing.destroy` with the rule ID.                     | Response indicates success (200/redirect).                                   |
| 2    | Attempt to load the record from the database.                              | Record no longer exists (or is soft-deleted if applicable).                  |
| 3    | DELETE with a rule ID belonging to a different organization.               | 403 Forbidden or 404 Not Found.                                             |
| 4    | DELETE with a non-existent rule ID.                                        | 404 Not Found.                                                               |

### Test Data

- A FulfillmentRoutingRule record ID to delete.

### Postconditions

- The rule is removed from the database.
- Remaining rules continue to function; no gaps in evaluation logic.

### Notes

- Deleting a rule does not affect historical fulfillment decisions that were made using it.

---

## TC-FUL-006-05: Toggle Rule Active/Inactive

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-006-05                                                |
| **Priority**    | P2                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | Yes                                                          |
| **Test File**   | `tests/Feature/Fulfillment/RoutingRuleTest.php`              |

### Preconditions

1. Authenticated user with `admin.access` permission.
2. A FulfillmentRoutingRule exists with `is_active` = `true`.

### Test Steps

| Step | Action                                                                     | Expected Result                                                              |
|------|----------------------------------------------------------------------------|------------------------------------------------------------------------------|
| 1    | POST `fulfillment.routing.toggle` with the rule ID.                        | Response indicates success. `is_active` toggled to `false`.                  |
| 2    | Reload the record.                                                         | `is_active` is `false`.                                                      |
| 3    | POST `fulfillment.routing.toggle` again with the same rule ID.             | Response indicates success. `is_active` toggled back to `true`.              |
| 4    | Reload the record.                                                         | `is_active` is `true`.                                                       |
| 5    | Toggle a rule from a different organization.                               | 403 Forbidden or 404 Not Found.                                             |

### Test Data

- A FulfillmentRoutingRule with `is_active` = `true`.

### Postconditions

- The `is_active` field is toggled.
- Inactive rules are skipped during fulfillment routing evaluation.

### Notes

- Toggling is a convenience action; the same result can be achieved via the update endpoint.

---

## TC-FUL-006-06: Test Routing with Specific Order

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-006-06                                                |
| **Priority**    | P1                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | Yes                                                          |
| **Test File**   | `tests/Feature/Fulfillment/RoutingRuleTest.php`              |

### Preconditions

1. Authenticated user with `admin.access` permission.
2. At least one active FulfillmentRoutingRule exists.
3. An Order exists with a shipping address and order items that have associated products with inventory at one or more locations.

### Test Steps

| Step | Action                                                                                        | Expected Result                                                                                              |
|------|-----------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------|
| 1    | POST `fulfillment.routing.test` with `order_id`.                                              | Response returns the selected InventoryLocation (ID, name) and the rule that matched.                        |
| 2    | Verify the returned location matches the expected rule logic.                                  | For a `proximity` rule, the closest location to the order's shipping address is returned.                    |
| 3    | POST test with an order that has no matching inventory at any location.                        | Response indicates no location could be selected (graceful fallback or error message).                       |
| 4    | POST test with an invalid `order_id`.                                                         | 404 Not Found or validation error.                                                                           |

### Test Data

- An Order with shipping address in a known geographic region.
- InventoryLocations at varying distances from the shipping address.
- Active routing rules configured to select based on proximity.

### Postconditions

- No data is modified. The test endpoint is read-only / simulation only.
- No fulfillment actions are triggered.

### Notes

- The test endpoint allows admins to preview which location would be selected for a given order without actually routing it.
- Useful for debugging rule configuration before enabling new rules in production.

---

## TC-FUL-006-07: Proximity Strategy Selects Closest Location

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-006-07                                                |
| **Priority**    | P2                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | No                                                           |
| **Test File**   | --                                                           |

### Preconditions

1. Authenticated user with `admin.access` permission.
2. A FulfillmentRoutingRule with `type` = `proximity` is active with the highest priority.
3. Organization has at least 3 InventoryLocations with distinct geographic coordinates or addresses (e.g., New York, Chicago, Los Angeles).
4. All locations have sufficient stock for the test order items.

### Test Steps

| Step | Action                                                                                                         | Expected Result                                                                                              |
|------|----------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------|
| 1    | Create an order with a shipping address in New York, NY.                                                       | Order created successfully.                                                                                  |
| 2    | POST `fulfillment.routing.test` with the order ID.                                                             | Returns the New York location (closest to shipping address).                                                 |
| 3    | Create another order with a shipping address in Los Angeles, CA.                                               | Order created successfully.                                                                                  |
| 4    | POST `fulfillment.routing.test` with the second order ID.                                                      | Returns the Los Angeles location (closest to shipping address).                                              |
| 5    | Remove all stock from the Los Angeles location for the ordered product.                                        | Inventory updated.                                                                                           |
| 6    | POST `fulfillment.routing.test` with the second order ID again.                                                | Returns Chicago (next closest with stock) or indicates insufficient stock at the closest location.           |

### Test Data

- InventoryLocations:
  - `{ name: "NYC Warehouse", city: "New York", state: "NY" }`
  - `{ name: "Chicago Hub", city: "Chicago", state: "IL" }`
  - `{ name: "LA Fulfillment", city: "Los Angeles", state: "CA" }`
- Orders with shipping addresses near each location.

### Postconditions

- No fulfillment actions are triggered. Test endpoint only.

### Notes

- Proximity may be calculated via ZIP/postal code distance, geocoordinates, or a simplified state-based mapping depending on the implementation.
- Verify behavior when the closest location has zero stock for any of the order items.

---

## TC-FUL-006-08: Highest Stock Strategy Selects Location with Most Inventory

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-006-08                                                |
| **Priority**    | P2                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | No                                                           |
| **Test File**   | --                                                           |

### Preconditions

1. Authenticated user with `admin.access` permission.
2. A FulfillmentRoutingRule with `type` = `highest_stock` is active with the highest priority.
3. Organization has at least 2 InventoryLocations.
4. The ordered product has different stock levels at each location.

### Test Steps

| Step | Action                                                                                                    | Expected Result                                                                                          |
|------|-----------------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------|
| 1    | Set stock levels: Location A has 50 units, Location B has 200 units of the test product.                  | Inventory levels updated.                                                                                |
| 2    | Create an order for 5 units of the test product.                                                          | Order created.                                                                                           |
| 3    | POST `fulfillment.routing.test` with the order ID.                                                        | Returns Location B (200 units, highest stock).                                                           |
| 4    | Update stock: Location A now has 300 units, Location B still has 200 units.                               | Inventory levels updated.                                                                                |
| 5    | POST `fulfillment.routing.test` with the same order ID.                                                   | Returns Location A (300 units, now highest stock).                                                       |
| 6    | Create an order with multiple items. Location A has highest stock for item 1, Location B for item 2.      | Order created.                                                                                           |
| 7    | POST `fulfillment.routing.test` with the multi-item order.                                                | Returns the location with the highest aggregate stock across all order items (implementation-dependent). |

### Test Data

- Product with UUID PK.
- InventoryLevel records at two locations with different quantities.

### Postconditions

- No inventory changes. Test endpoint only.

### Notes

- For multi-item orders, the strategy may select the location that can fully fulfill the order, or the one with the highest total stock across all items. Verify the actual implementation logic.

---

## TC-FUL-006-09: Rules Evaluate in Priority Order

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-006-09                                                |
| **Priority**    | P1                                                           |
| **Type**        | Feature                                                      |
| **Platform**    | Web                                                          |
| **Automated**   | No                                                           |
| **Test File**   | --                                                           |

### Preconditions

1. Authenticated user with `admin.access` permission.
2. Organization has at least 3 active FulfillmentRoutingRules with different priorities and types.
3. Multiple InventoryLocations exist with stock for the test order.

### Test Steps

| Step | Action                                                                                                                      | Expected Result                                                                                                    |
|------|-----------------------------------------------------------------------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------|
| 1    | Create rules: Rule A (priority 1, type `specific_location`, location = Warehouse X), Rule B (priority 2, type `proximity`). | Rules created.                                                                                                     |
| 2    | Create an order where the closest location by proximity is Warehouse Y (not X).                                             | Order created.                                                                                                     |
| 3    | POST `fulfillment.routing.test` with the order ID.                                                                          | Returns Warehouse X (Rule A matched first at priority 1, overriding proximity).                                    |
| 4    | Deactivate Rule A (set `is_active` = false).                                                                                | Rule A is inactive.                                                                                                |
| 5    | POST `fulfillment.routing.test` with the same order ID.                                                                     | Returns Warehouse Y (Rule B now matches first via proximity, since Rule A is inactive).                            |
| 6    | Update Rule B priority to 3 and create Rule C (priority 2, type `highest_stock`). Warehouse Z has the most stock.           | Rules updated/created.                                                                                             |
| 7    | POST `fulfillment.routing.test` with the same order ID.                                                                     | Returns Warehouse Z (Rule C at priority 2 matches before Rule B at priority 3).                                    |

### Test Data

- Three FulfillmentRoutingRules with sequential priorities.
- Three InventoryLocations (Warehouse X, Y, Z) with stock.

### Postconditions

- No fulfillment actions triggered.
- Rule priority order determines which strategy is applied first.

### Notes

- "First match wins" means the engine stops evaluating further rules once a rule produces a valid location.
- Inactive rules are entirely skipped during evaluation.
- If no rule matches, the system should either use a default strategy or return an error.

---

## TC-FUL-006-10: Non-Admin User Cannot Access Routing Pages

| Field           | Value                                                        |
|-----------------|--------------------------------------------------------------|
| **ID**          | TC-FUL-006-10                                                |
| **Priority**    | P1                                                           |
| **Type**        | Security                                                     |
| **Platform**    | Web                                                          |
| **Automated**   | No                                                           |
| **Test File**   | --                                                           |

### Preconditions

1. Authenticated user WITHOUT `admin.access` permission (e.g., a user with `lister` or `fulfillment` role only).
2. At least one FulfillmentRoutingRule exists in the organization.

### Test Steps

| Step | Action                                                                     | Expected Result                                          |
|------|----------------------------------------------------------------------------|----------------------------------------------------------|
| 1    | GET `fulfillment.routing.index` as non-admin user.                         | 403 Forbidden.                                           |
| 2    | POST `fulfillment.routing.store` as non-admin user.                        | 403 Forbidden.                                           |
| 3    | PUT `fulfillment.routing.update` as non-admin user with a valid rule ID.   | 403 Forbidden.                                           |
| 4    | DELETE `fulfillment.routing.destroy` as non-admin user.                    | 403 Forbidden.                                           |
| 5    | POST `fulfillment.routing.toggle` as non-admin user.                       | 403 Forbidden.                                           |
| 6    | POST `fulfillment.routing.test` as non-admin user.                         | 403 Forbidden.                                           |

### Test Data

- A user account with a non-admin role (e.g., `fulfillment_worker` or `lister`).

### Postconditions

- No data is accessed or modified.
- All requests return 403.

### Notes

- The `can:admin.access` middleware is applied at the route group level for all `fulfillment.routing.*` routes.
- Verify that the 403 response does not leak any rule data in the error body.
