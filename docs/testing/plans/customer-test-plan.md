# Customer CRM Test Plan

## Document ID: FLIPSTACK-TP-CUS-001

**Version:** 1.0
**Last Updated:** 2026-04-15
**Status:** Approved

---

## 1. Overview

### 1.1 Scope

Customer management: auto-matching from marketplace orders, merge duplicates, flagging, order linking, addresses, and customer analytics (repeat rate, lifetime value).

### 1.2 Related Documentation

| Resource | Path |
|----------|------|
| Existing tests | `tests/Feature/Customers/CustomerManagementTest.php` |
| Test cases | `docs/testing/cases/customer/` |

---

## 2. Features Under Test

| # | Feature | Controller Method | Route Name | Priority |
|---|---------|------------------|-----------|----------|
| 1 | Customer listing & search | CustomerController@index | `customers.index` | P1 |
| 2 | Customer detail | CustomerController@show | `customers.show` | P1 |
| 3 | Update customer | CustomerController@update | `customers.update` | P1 |
| 4 | Merge duplicates | CustomerController@merge | `customers.merge` | P1 |
| 5 | Auto-match from orders | CustomerMatchingService@findOrCreateFromOrder | - (service) | P0 |
| 6 | Customer flagging | CustomerController@update (is_flagged) | `customers.update` | P2 |

---

## 3. Models

| Model | Key Fields |
|-------|-----------|
| Customer | email, name, phone, marketplace_source, marketplace_customer_id, total_orders, total_spent, average_order_value, is_flagged, flag_reason, tags |
| CustomerAddress | customer_id, type (shipping/billing), is_default, line1, city, state, postal_code, country |

---

## 4. Test Cases

| Test Case ID | Title | File |
|--------------|-------|------|
| TC-CUS-001 | Customer Management | `cases/customer/TC-CUS-001-customer-management.md` |
