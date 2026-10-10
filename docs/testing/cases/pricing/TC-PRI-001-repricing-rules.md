# TC-PRI-001: Repricing Rules Management

| Field             | Value                                      |
|-------------------|--------------------------------------------|
| Document ID       | TC-PRI-001                                 |
| Domain            | Pricing                                    |
| Related Test Plan | TP-PRI-001 Repricing Engine                |
| Last Updated      | 2026-04-15                                 |
| Controller        | RepricingController                        |
| Routes            | pricing.repricing.*                        |

---

## TC-PRI-001-01: View Repricing Rules List

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-PRI-001-01                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | Yes                                                          |
| Test File      | tests/Feature/RepricingTest.php                              |

**Preconditions:**
- User is authenticated with an organization that has at least 3 repricing rules configured.
- User has permission to manage pricing.

**Test Steps:**

| Step | Action                                      | Expected Result                                                              |
|------|---------------------------------------------|------------------------------------------------------------------------------|
| 1    | Navigate to GET pricing.repricing.index     | Page loads with Inertia response containing repricing rules data.            |
| 2    | Verify rules list rendering                 | All rules for the organization are displayed in a table/list.                |
| 3    | Verify rule details shown                   | Each rule shows name, strategy, marketplace, scope_type, active status, priority. |
| 4    | Verify pagination                           | Rules are paginated if more than the page limit.                             |

**Test Data:**
- Organization with 3+ repricing rules using different strategies.

**Postconditions:**
- No data is modified.

**Notes:**
- Verify that rules from other organizations are not visible (covered separately in TC-PRI-001-12).

---

## TC-PRI-001-02: Create Rule with Match Lowest Strategy

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-PRI-001-02                                                |
| Priority       | P0                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | Yes                                                          |
| Test File      | tests/Feature/RepricingTest.php                              |

**Preconditions:**
- User is authenticated with pricing management permissions.
- eBay integration credential exists for the organization.

**Test Steps:**

| Step | Action                                                                 | Expected Result                                                                    |
|------|------------------------------------------------------------------------|------------------------------------------------------------------------------------|
| 1    | POST pricing.repricing.store with name, strategy=match_lowest, marketplace=ebay, scope_type=all | Rule is created and stored in the database.                                        |
| 2    | Verify response                                                        | Redirect to repricing index with success message.                                  |
| 3    | Verify database record                                                 | RepricingRule record exists with correct strategy, marketplace, and scope_type.     |
| 4    | Verify default active status                                           | Rule is created with is_active=true by default.                                    |

**Test Data:**
- name: "Match Lowest eBay"
- strategy: "match_lowest"
- marketplace: "ebay"
- scope_type: "all"

**Postconditions:**
- One new RepricingRule record exists in the database.

**Notes:**
- match_lowest strategy requires no additional parameters as it automatically matches the lowest competitor price found.

---

## TC-PRI-001-03: Create Rule with Cost Plus Strategy

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-PRI-001-03                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with pricing management permissions.

**Test Steps:**

| Step | Action                                                                 | Expected Result                                                                    |
|------|------------------------------------------------------------------------|------------------------------------------------------------------------------------|
| 1    | POST pricing.repricing.store with strategy=cost_plus, parameters={margin_percent: 30} | Rule is created successfully.                                                      |
| 2    | Verify database record                                                 | RepricingRule has strategy=cost_plus and parameters JSON contains margin_percent=30. |
| 3    | Verify validation rejects missing margin_percent                       | POST without margin_percent returns validation error.                              |
| 4    | Verify validation rejects negative margin_percent                      | POST with margin_percent=-10 returns validation error.                             |

**Test Data:**
- name: "Cost Plus 30%"
- strategy: "cost_plus"
- parameters: {"margin_percent": 30}
- marketplace: "ebay"
- scope_type: "all"

**Postconditions:**
- One new RepricingRule record exists with cost_plus strategy.

**Notes:**
- The cost_plus strategy calculates the listing price as cost_price * (1 + margin_percent/100).

---

## TC-PRI-001-04: Create Rule with Beat by Amount Strategy

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-PRI-001-04                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with pricing management permissions.

**Test Steps:**

| Step | Action                                                                 | Expected Result                                                                    |
|------|------------------------------------------------------------------------|------------------------------------------------------------------------------------|
| 1    | POST pricing.repricing.store with strategy=beat_by_amount, parameters={amount: 5.00} | Rule is created successfully.                                                      |
| 2    | Verify database record                                                 | RepricingRule has strategy=beat_by_amount and parameters JSON contains amount=5.00. |
| 3    | Verify validation rejects missing amount                               | POST without amount returns validation error.                                      |
| 4    | Verify validation rejects zero amount                                  | POST with amount=0 returns validation error.                                       |

**Test Data:**
- name: "Beat Competitors by $5"
- strategy: "beat_by_amount"
- parameters: {"amount": 5.00}
- marketplace: "ebay"
- scope_type: "all"

**Postconditions:**
- One new RepricingRule record exists with beat_by_amount strategy.

**Notes:**
- The beat_by_amount strategy sets the listing price to lowest_competitor_price minus the specified amount.

---

## TC-PRI-001-05: Update Rule Strategy and Parameters

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-PRI-001-05                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | Yes                                                          |
| Test File      | tests/Feature/RepricingTest.php                              |

**Preconditions:**
- User is authenticated with pricing management permissions.
- An existing RepricingRule with strategy=match_lowest exists for the organization.

**Test Steps:**

| Step | Action                                                                 | Expected Result                                                                    |
|------|------------------------------------------------------------------------|------------------------------------------------------------------------------------|
| 1    | PUT pricing.repricing.update with new strategy=cost_plus, parameters={margin_percent: 25} | Rule is updated successfully.                                                      |
| 2    | Verify response                                                        | Redirect with success message.                                                     |
| 3    | Verify database record                                                 | RepricingRule now has strategy=cost_plus and updated parameters.                    |
| 4    | Verify old parameters are replaced                                     | Previous strategy parameters are no longer present in the parameters JSON.         |

**Test Data:**
- Existing rule ID.
- Updated strategy: "cost_plus"
- Updated parameters: {"margin_percent": 25}

**Postconditions:**
- The existing RepricingRule record is updated with the new strategy and parameters.

**Notes:**
- Changing strategy should clear any strategy-specific parameters from the previous strategy.

---

## TC-PRI-001-06: Delete Repricing Rule

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-PRI-001-06                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | Yes                                                          |
| Test File      | tests/Feature/RepricingTest.php                              |

**Preconditions:**
- User is authenticated with pricing management permissions.
- An existing RepricingRule exists for the organization.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | DELETE pricing.repricing.destroy with rule ID           | Rule is deleted from the database.                           |
| 2    | Verify response                                         | Redirect to repricing index with success message.            |
| 3    | Verify database                                         | RepricingRule record no longer exists.                       |
| 4    | Verify associated RepricingLog records                  | Log records referencing this rule are preserved (not cascade deleted). |

**Test Data:**
- Existing repricing rule ID.

**Postconditions:**
- The RepricingRule record is removed from the database.
- Any associated RepricingLog records remain intact for historical reference.

**Notes:**
- None.

---

## TC-PRI-001-07: Toggle Rule Active/Inactive

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-PRI-001-07                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with pricing management permissions.
- An existing active RepricingRule exists for the organization.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | POST pricing.repricing.toggle with rule ID              | Rule is_active toggled from true to false.                   |
| 2    | Verify response                                         | Success response with updated active status.                 |
| 3    | POST pricing.repricing.toggle again with same rule ID   | Rule is_active toggled from false to true.                   |
| 4    | Verify database record                                  | is_active reflects the latest toggle state.                  |

**Test Data:**
- Existing repricing rule ID.

**Postconditions:**
- The rule's is_active field is toggled.

**Notes:**
- Inactive rules are skipped during repricing engine execution.

---

## TC-PRI-001-08: Run Repricing Engine Processes Active Rules

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-PRI-001-08                                                |
| Priority       | P0                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with pricing management permissions.
- At least 2 active repricing rules exist with different priorities.
- Products exist that match the rules' scope criteria.
- Products have current listing prices on the target marketplace.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | POST pricing.repricing.run                              | Repricing engine is triggered.                               |
| 2    | Verify rules processed in priority order                | Rules with lower priority number are evaluated first.        |
| 3    | Verify active rules only                                | Only rules with is_active=true are processed.                |
| 4    | Verify price changes applied                            | Product prices are updated according to each rule's strategy. |
| 5    | Verify RepricingLog entries created                     | A log entry is created for each price change.                |

**Test Data:**
- Rule A: priority=1, strategy=match_lowest, is_active=true.
- Rule B: priority=2, strategy=cost_plus, is_active=true.
- Rule C: priority=3, strategy=match_lowest, is_active=false (should be skipped).

**Postconditions:**
- Product prices are updated per the active rules.
- RepricingLog records document all changes.

**Notes:**
- The repricing engine should process rules sequentially by priority to avoid conflicting price updates on the same product.

---

## TC-PRI-001-09: Price Guards Prevent Invalid Prices

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-PRI-001-09                                                |
| Priority       | P0                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with pricing management permissions.
- An active repricing rule exists (e.g., match_lowest).
- A product exists with cost_price=20.00 and a configured min/max price guard.
- Competitor price data exists that would trigger a price below cost.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | Configure rule to match_lowest with a competitor price of $10.00 | Engine calculates new price as $10.00.                       |
| 2    | Run repricing engine                                    | Price guard prevents price from going below cost_price ($20.00). |
| 3    | Verify product price unchanged or set to min            | Product price remains at the minimum allowed (cost_price).   |
| 4    | Verify RepricingLog records the guard activation        | Log entry shows reason indicating price guard was triggered. |
| 5    | Test max price guard                                    | If a max price is configured, price does not exceed it.      |

**Test Data:**
- Product cost_price: $20.00
- Competitor price: $10.00
- Expected: price stays at $20.00 (min guard).

**Postconditions:**
- Product price is not set below cost_price or above max price guard.

**Notes:**
- Price guards are critical to preventing losses from automated repricing.

---

## TC-PRI-001-10: Repricing Log Records Price Changes

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-PRI-001-10                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with pricing management permissions.
- An active repricing rule exists.
- A product exists with a current listing price.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | Run repricing engine that triggers a price change       | Price is updated on the product/listing.                     |
| 2    | Query RepricingLog for the product                      | A log entry exists for this repricing action.                |
| 3    | Verify log fields: old_price                            | old_price matches the previous listing price.                |
| 4    | Verify log fields: new_price                            | new_price matches the updated listing price.                 |
| 5    | Verify log fields: competitor_price                     | competitor_price reflects the competitor data used.          |
| 6    | Verify log fields: reason                               | reason describes the strategy applied (e.g., "matched lowest competitor"). |

**Test Data:**
- Product with listing price $50.00.
- Competitor price $45.00.
- Rule strategy: match_lowest.
- Expected log: old_price=50.00, new_price=45.00, competitor_price=45.00.

**Postconditions:**
- RepricingLog record exists with accurate historical data.

**Notes:**
- Repricing logs are essential for auditing pricing decisions and diagnosing unexpected price changes.

---

## TC-PRI-001-11: Scope Type Category Limits Rule Application

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-PRI-001-11                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with pricing management permissions.
- An active repricing rule exists with scope_type=category and a specific category_id.
- Products exist both inside and outside the specified category.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | Run repricing engine                                    | Engine evaluates only products in the specified category.    |
| 2    | Verify in-scope product repriced                        | Product in the matching category has its price updated.      |
| 3    | Verify out-of-scope product unchanged                   | Product outside the category is not affected by this rule.   |
| 4    | Verify RepricingLog only contains in-scope products     | Log entries exist only for products matching the category.   |

**Test Data:**
- Category: "Electronics" (ID: category_1).
- Product A: in "Electronics" category -- should be repriced.
- Product B: in "Clothing" category -- should not be repriced by this rule.

**Postconditions:**
- Only products in the specified category are affected.

**Notes:**
- Other scope_type values (e.g., "all", "product") have different filtering behavior.

---

## TC-PRI-001-12: Organization Isolation for Repricing Rules

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-PRI-001-12                                                |
| Priority       | P0                                                           |
| Type           | Security                                                     |
| Platform       | Web                                                          |
| Automated      | Yes                                                          |
| Test File      | tests/Feature/RepricingTest.php                              |

**Preconditions:**
- Two organizations (Org A and Org B) each have repricing rules.
- User is authenticated as a member of Org A.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | GET pricing.repricing.index as Org A user               | Only Org A's repricing rules are returned.                   |
| 2    | Attempt PUT pricing.repricing.update on Org B's rule    | Returns 403 Forbidden or 404 Not Found.                      |
| 3    | Attempt DELETE pricing.repricing.destroy on Org B's rule | Returns 403 Forbidden or 404 Not Found.                      |
| 4    | Run repricing engine as Org A                           | Only Org A's rules and products are processed.               |

**Test Data:**
- Org A with 2 repricing rules.
- Org B with 3 repricing rules.

**Postconditions:**
- No cross-organization data leakage occurs.

**Notes:**
- Organization isolation is a critical security requirement across all features.
