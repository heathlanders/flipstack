# TC-MKT-011: Marketplace Sync Rules

| Field             | Value                                      |
|-------------------|--------------------------------------------|
| Document ID       | TC-MKT-011                                 |
| Domain            | Marketplace                                |
| Related Test Plan | TP-MKT-003 Cross-Marketplace Operations    |
| Last Updated      | 2026-04-15                                 |
| Service           | MarketplaceSyncRuleEngine                  |
| Unit Test         | MarketplaceSyncRuleEngineTest              |

---

## TC-MKT-011-01: Sync Rule Evaluates shouldSync for Product

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-MKT-011-01                                                |
| Priority       | P1                                                           |
| Type           | Unit                                                         |
| Platform       | Backend                                                      |
| Automated      | Yes                                                          |
| Test File      | tests/Unit/MarketplaceSyncRuleEngineTest.php                 |

**Preconditions:**
- MarketplaceSyncRuleEngine is instantiated.
- At least 1 sync rule is configured for the organization.
- A product exists that matches the rule criteria.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | Call shouldSync(product, marketplace) on the engine     | Engine evaluates all applicable rules for the product.       |
| 2    | Verify return value when product matches a rule         | Returns true (product should be synced to the marketplace).  |
| 3    | Verify return value when product does not match any rule | Returns the permissive default (true -- see TC-MKT-011-06). |
| 4    | Verify rule conditions are evaluated correctly          | The engine checks all conditions defined on the matching rule. |

**Test Data:**
- Product with category="Electronics", price=49.99.
- Sync rule: marketplace=ebay, scope=all, is_active=true.

**Postconditions:**
- No data is modified. shouldSync is a pure evaluation.

**Notes:**
- The MarketplaceSyncRuleEngine is used by the inventory sync system to determine which products should be pushed to which marketplaces.

---

## TC-MKT-011-02: Rule with Marketplace Filter Only Syncs to Target

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-MKT-011-02                                                |
| Priority       | P1                                                           |
| Type           | Unit                                                         |
| Platform       | Backend                                                      |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- MarketplaceSyncRuleEngine is instantiated.
- A sync rule exists with marketplace=ebay.
- A product exists that matches the rule.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | Call shouldSync(product, "ebay")                        | Returns true (rule matches the marketplace).                 |
| 2    | Call shouldSync(product, "shopify")                     | Rule does not apply to Shopify; falls through to other rules or default. |
| 3    | Create another rule with marketplace=shopify            | Both marketplaces now have applicable rules.                 |
| 4    | Call shouldSync(product, "shopify") again               | Returns true (now matches the Shopify-specific rule).        |

**Test Data:**
- Rule A: marketplace="ebay", scope=all.
- Rule B: marketplace="shopify", scope=all (added in step 3).

**Postconditions:**
- No data is modified.

**Notes:**
- Rules are marketplace-specific. A rule for eBay has no effect on Shopify sync decisions.

---

## TC-MKT-011-03: Rule with Category Scope Filters Products

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-MKT-011-03                                                |
| Priority       | P1                                                           |
| Type           | Unit                                                         |
| Platform       | Backend                                                      |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- MarketplaceSyncRuleEngine is instantiated.
- A sync rule exists with scope=category and category_id pointing to "Electronics".
- Products exist in "Electronics" and "Clothing" categories.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | Call shouldSync(electronicsProduct, marketplace)        | Rule matches; returns true (product is in the Electronics category). |
| 2    | Call shouldSync(clothingProduct, marketplace)           | Rule does not match; product is not in the Electronics category. Falls through to other rules or default. |
| 3    | Verify category is checked via product's category_id   | The engine compares the product's category_id against the rule's scope value. |

**Test Data:**
- Rule: scope=category, scope_value=electronics_category_id, marketplace=ebay.
- Product A: category=Electronics (matches).
- Product B: category=Clothing (does not match).

**Postconditions:**
- No data is modified.

**Notes:**
- Category-scoped rules allow selective sync. Products not in the specified category are evaluated against subsequent rules.

---

## TC-MKT-011-04: Rule with Price Range Condition

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-MKT-011-04                                                |
| Priority       | P2                                                           |
| Type           | Unit                                                         |
| Platform       | Backend                                                      |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- MarketplaceSyncRuleEngine is instantiated.
- A sync rule exists with a price_range condition (e.g., min_price=10.00, max_price=100.00).

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | Call shouldSync with product price=$50.00               | Returns true (price is within range 10-100).                 |
| 2    | Call shouldSync with product price=$5.00                | Rule does not match (price is below min_price).              |
| 3    | Call shouldSync with product price=$150.00              | Rule does not match (price is above max_price).              |
| 4    | Call shouldSync with product price=$10.00 (boundary)    | Returns true (price equals min_price, inclusive).            |
| 5    | Call shouldSync with product price=$100.00 (boundary)   | Returns true (price equals max_price, inclusive).            |

**Test Data:**
- Rule: price_range condition, min_price=10.00, max_price=100.00.
- Products: $5.00, $10.00, $50.00, $100.00, $150.00.

**Postconditions:**
- No data is modified.

**Notes:**
- Boundary values (min and max) should be inclusive.

---

## TC-MKT-011-05: Multiple Rules Evaluated by Priority, First Match Wins

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-MKT-011-05                                                |
| Priority       | P1                                                           |
| Type           | Unit                                                         |
| Platform       | Backend                                                      |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- MarketplaceSyncRuleEngine is instantiated.
- Multiple sync rules exist with different priorities for the same marketplace.
- A product exists that matches multiple rules.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | Configure Rule A (priority=1): scope=category, Electronics, action=sync | Rule A has highest priority.                                 |
| 2    | Configure Rule B (priority=2): scope=all, action=do_not_sync | Rule B has lower priority.                                   |
| 3    | Call shouldSync with Electronics product                | Returns true (Rule A matches first, action=sync).            |
| 4    | Call shouldSync with Clothing product                   | Returns false (Rule A does not match, Rule B matches with action=do_not_sync). |
| 5    | Swap priorities: Rule A priority=2, Rule B priority=1   | Rule B now evaluates first.                                  |
| 6    | Call shouldSync with Electronics product                | Returns false (Rule B matches first with scope=all, action=do_not_sync). |

**Test Data:**
- Rule A: priority=1, scope=category (Electronics), action=sync.
- Rule B: priority=2, scope=all, action=do_not_sync.

**Postconditions:**
- No data is modified.

**Notes:**
- "First match wins" means the engine stops evaluating after the first rule that matches the product. Priority ordering is critical for correct behavior.

---

## TC-MKT-011-06: No Matching Rule Defaults to Sync (Permissive Default)

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-MKT-011-06                                                |
| Priority       | P1                                                           |
| Type           | Unit                                                         |
| Platform       | Backend                                                      |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- MarketplaceSyncRuleEngine is instantiated.
- No sync rules exist for the organization, OR existing rules do not match the product.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | Remove all sync rules for the organization              | No rules are configured.                                     |
| 2    | Call shouldSync(product, marketplace)                   | Returns true (permissive default: sync when no rules match). |
| 3    | Add a rule that does not match the product              | E.g., rule for category=Electronics, product is in Clothing. |
| 4    | Call shouldSync(clothingProduct, marketplace)           | Returns true (no matching rule, falls back to permissive default). |

**Test Data:**
- Product in "Clothing" category.
- No rules, or rules only for "Electronics" category.

**Postconditions:**
- No data is modified.

**Notes:**
- The permissive default ensures that all products are synced unless explicitly excluded by a rule. This is the safest default for users who have not configured any sync rules, as their products will continue to sync to marketplaces without additional configuration.
