# TC-CUS-001: Customer Management

| Field             | Value                                      |
|-------------------|--------------------------------------------|
| Document ID       | TC-CUS-001                                 |
| Domain            | Customer                                   |
| Related Test Plan | TP-CUS-001 Customer CRM                   |
| Last Updated      | 2026-04-15                                 |
| Controller        | CustomerController                         |
| Routes            | customers.*                                |

---

## TC-CUS-001-01: View Customer List with Filters

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-CUS-001-01                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | Yes                                                          |
| Test File      | tests/Feature/CustomerManagementTest.php                     |

**Preconditions:**
- User is authenticated with customer management permissions.
- Organization has at least 5 customers from multiple marketplaces, including at least 1 flagged customer.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | Navigate to GET customers.index                         | Page loads with paginated customer list.                     |
| 2    | Enter search term matching a customer name              | Customer list filters to show matching results.              |
| 3    | Apply marketplace filter (e.g., marketplace=ebay)       | Only customers from eBay marketplace are displayed.          |
| 4    | Apply flagged filter (flagged=true)                     | Only flagged customers are displayed.                        |
| 5    | Clear all filters                                       | Full customer list is restored.                              |

**Test Data:**
- 5+ customers: 3 from eBay, 2 from Shopify, 1 flagged.
- Search term: partial name match.

**Postconditions:**
- No data is modified.

**Notes:**
- Filters should be combinable (e.g., marketplace=ebay AND flagged=true).

---

## TC-CUS-001-02: View Customer Detail

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-CUS-001-02                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with customer management permissions.
- A customer exists with at least 2 orders, a saved address, and calculated total_spent.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | Navigate to GET customers.show with customer ID         | Customer detail page loads.                                  |
| 2    | Verify customer profile fields                          | Name, email, phone, marketplace source, tags displayed.      |
| 3    | Verify order history section                            | All orders for this customer are listed with dates and totals. |
| 4    | Verify total_spent calculation                          | total_spent matches the sum of all completed order amounts.  |
| 5    | Verify addresses section                                | Customer addresses are displayed.                            |

**Test Data:**
- Customer with name, email, phone, 2 completed orders ($50.00 + $75.00 = $125.00 total_spent), 1 address.

**Postconditions:**
- No data is modified.

**Notes:**
- Order history should be sorted by date descending (most recent first).

---

## TC-CUS-001-03: Update Customer Fields

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-CUS-001-03                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with customer management permissions.
- An existing customer record exists.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | PUT customers.update with updated name, email, phone    | Customer record is updated.                                  |
| 2    | Verify response                                         | Redirect to customer detail with success message.            |
| 3    | Verify database record                                  | Customer fields reflect the updated values.                  |
| 4    | Update tags field with comma-separated values           | Tags are stored correctly on the customer record.            |
| 5    | Update notes field                                      | Notes are saved and visible on customer detail.              |

**Test Data:**
- Updated name: "Jane Smith Updated"
- Updated email: "jane.updated@example.com"
- Updated phone: "+1-555-0199"
- Tags: "vip,repeat-buyer"
- Notes: "Preferred customer, always ships to PO Box."

**Postconditions:**
- Customer record is updated with new values.

**Notes:**
- Email validation should enforce valid email format.

---

## TC-CUS-001-04: Flag Customer with Reason

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-CUS-001-04                                                |
| Priority       | P2                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with customer management permissions.
- An existing unflagged customer record exists.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | PUT customers.update with is_flagged=true, flag_reason="Frequent returns" | Customer is flagged.                                         |
| 2    | Verify database record                                  | is_flagged=true and flag_reason="Frequent returns".          |
| 3    | Verify customer appears in flagged filter                | Customer shows up when filtering by flagged=true on index.   |
| 4    | Unflag customer: PUT customers.update with is_flagged=false | Customer is unflagged, flag_reason cleared.                  |

**Test Data:**
- flag_reason: "Frequent returns"

**Postconditions:**
- Customer flag status is updated.

**Notes:**
- Flagged customers may trigger warnings during order processing.

---

## TC-CUS-001-05: Merge Duplicate Customers

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-CUS-001-05                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- User is authenticated with customer management permissions.
- Two customer records exist that represent the same person (e.g., same email, different marketplace sources).
- Each customer has at least 1 order.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | POST customers.merge with primary_id and secondary_id   | Merge operation executes.                                    |
| 2    | Verify secondary customer's orders moved to primary     | All orders previously linked to secondary now belong to primary. |
| 3    | Verify secondary customer deleted                       | Secondary customer record no longer exists in the database.  |
| 4    | Verify primary customer totals recalculated             | total_orders and total_spent reflect combined totals.        |
| 5    | Verify addresses consolidated                           | Secondary customer's addresses are transferred to primary.   |

**Test Data:**
- Primary customer: 2 orders, $100.00 total_spent.
- Secondary customer: 1 order, $50.00 total_spent.
- After merge: primary has 3 orders, $150.00 total_spent.

**Postconditions:**
- Only the primary customer record remains.
- All order and address associations are consolidated.

**Notes:**
- Merge is irreversible. UI should require confirmation before executing.

---

## TC-CUS-001-06: Auto-Match Customer from Order by Email

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-CUS-001-06                                                |
| Priority       | P0                                                           |
| Type           | Functional                                                   |
| Platform       | Backend                                                      |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- An existing customer record exists with email "buyer@example.com".
- An order is imported from eBay with buyer email "buyer@example.com".

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | Call CustomerMatchingService@findOrCreateFromOrder with order data | Service matches existing customer by email.                  |
| 2    | Verify order linked to existing customer                | Order's customer_id points to the existing customer record.  |
| 3    | Verify no duplicate customer created                    | Customer count remains the same.                             |
| 4    | Verify customer total_orders incremented                | total_orders reflects the newly linked order.                |

**Test Data:**
- Existing customer email: "buyer@example.com"
- Incoming order buyer email: "buyer@example.com"

**Postconditions:**
- Order is associated with the existing customer.

**Notes:**
- Email matching should be case-insensitive.

---

## TC-CUS-001-07: Auto-Match Customer from Order by Marketplace Customer ID

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-CUS-001-07                                                |
| Priority       | P0                                                           |
| Type           | Functional                                                   |
| Platform       | Backend                                                      |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- An existing customer record exists with marketplace_customer_id="ebay_user_123" and marketplace="ebay".
- An order is imported from eBay with the same buyer marketplace ID.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | Call CustomerMatchingService@findOrCreateFromOrder with order data containing marketplace_customer_id | Service matches existing customer by marketplace_customer_id. |
| 2    | Verify order linked to existing customer                | Order's customer_id points to the matched customer.          |
| 3    | Verify no duplicate customer created                    | Customer count remains the same.                             |

**Test Data:**
- Existing customer marketplace_customer_id: "ebay_user_123"
- Incoming order buyer ID: "ebay_user_123"

**Postconditions:**
- Order is associated with the existing customer matched by marketplace ID.

**Notes:**
- Marketplace customer ID matching takes priority over email matching if both are available.

---

## TC-CUS-001-08: Auto-Match Creates New Customer When No Match

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-CUS-001-08                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Backend                                                      |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- No existing customer matches the incoming order's buyer email or marketplace customer ID.
- An order is imported with new buyer details.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | Call CustomerMatchingService@findOrCreateFromOrder with order data | Service finds no match and creates a new customer.           |
| 2    | Verify new customer record created                      | A new Customer record exists with the buyer's details.       |
| 3    | Verify customer fields populated                        | Name, email, marketplace, marketplace_customer_id set from order data. |
| 4    | Verify order linked to new customer                     | Order's customer_id points to the newly created customer.    |

**Test Data:**
- New buyer name: "New Buyer"
- New buyer email: "newbuyer@example.com"
- marketplace_customer_id: "ebay_user_999"

**Postconditions:**
- A new customer record is created and linked to the order.

**Notes:**
- The customer address from the order shipping address should also be created as a CustomerAddress record.

---

## TC-CUS-001-09: Customer Aggregated Totals Accuracy

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-CUS-001-09                                                |
| Priority       | P1                                                           |
| Type           | Functional                                                   |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- A customer exists with multiple orders in various statuses (completed, cancelled, pending).

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | Navigate to GET customers.show                          | Customer detail page loads with aggregated data.             |
| 2    | Verify total_orders count                               | total_orders includes all non-cancelled orders.              |
| 3    | Verify total_spent amount                               | total_spent sums only completed/shipped order totals.        |
| 4    | Add a new order for this customer                       | Aggregated totals update to reflect the new order.           |

**Test Data:**
- Order 1: completed, $50.00
- Order 2: completed, $75.00
- Order 3: cancelled, $30.00 (should not count toward total_spent)
- Order 4: pending, $40.00 (should count toward total_orders but not total_spent depending on business rules)

**Postconditions:**
- No data is modified (read-only verification).

**Notes:**
- Confirm business rules for which order statuses contribute to total_orders vs total_spent.

---

## TC-CUS-001-10: Organization Isolation for Customers

| Field          | Value                                                        |
|----------------|--------------------------------------------------------------|
| ID             | TC-CUS-001-10                                                |
| Priority       | P0                                                           |
| Type           | Security                                                     |
| Platform       | Web                                                          |
| Automated      | No                                                           |
| Test File      | --                                                           |

**Preconditions:**
- Two organizations (Org A and Org B) each have customer records.
- User is authenticated as a member of Org A.

**Test Steps:**

| Step | Action                                                  | Expected Result                                              |
|------|---------------------------------------------------------|--------------------------------------------------------------|
| 1    | GET customers.index as Org A user                       | Only Org A's customers are returned.                         |
| 2    | Attempt GET customers.show on Org B's customer          | Returns 403 Forbidden or 404 Not Found.                      |
| 3    | Attempt PUT customers.update on Org B's customer        | Returns 403 Forbidden or 404 Not Found.                      |
| 4    | Attempt POST customers.merge using Org B's customer IDs | Returns 403 Forbidden or 404 Not Found.                      |

**Test Data:**
- Org A with 3 customers.
- Org B with 2 customers.

**Postconditions:**
- No cross-organization data access occurs.

**Notes:**
- Organization isolation is enforced at the query scope level and should be verified across all customer endpoints.
