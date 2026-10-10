---
name: customer-management
description: Customer CRM with automated matching from marketplace orders, merge workflow, flagging, and order linking
user-invocable: false
---

# Customer Management System

## Key Files

| Area | File | Purpose |
|------|------|---------|
| **Models** | `app/Models/Customer/Customer.php` | Customer with marketplace source, stats, tags, flagging, soft deletes |
| | `app/Models/Customer/CustomerAddress.php` | Polymorphic addresses (shipping/billing) with default flag |
| **Services** | `app/Services/Customer/CustomerMatchingService.php` | 3-strategy matching, auto-create, address upsert, merge |
| **Controllers** | `app/Http/Controllers/Customer/CustomerController.php` | Customer CRUD, merge endpoint, stats |
| **Routes** | `routes/customers.php` | Customer routes under `customers.` prefix |

## Customer Model Fields

| Field | Type | Purpose |
|-------|------|---------|
| `email` | string, nullable | Primary match key |
| `name` | string | Display name |
| `phone` | string, nullable | Phone number |
| `marketplace_source` | string, nullable | `ebay`, `shopify`, `amazon` |
| `marketplace_customer_id` | string, nullable | Marketplace buyer ID |
| `first_order_at` / `last_order_at` | datetime | Order date range |
| `total_orders` | integer | Cached order count |
| `total_spent` | decimal | Cached total revenue |
| `average_order_value` | decimal | Cached AOV |
| `tags` | array (JSON) | User-defined tags |
| `is_flagged` | boolean | Problem customer flag |
| `flag_reason` | string, nullable | Reason for flagging |

## Matching Algorithm

`CustomerMatchingService::findOrCreateFromOrder()` runs three strategies in priority order, stopping at the first match:

### Strategy 1: Email Match
- Looks for `buyer_email` or `email` in `order.marketplace_data`
- Exact match on `email` column within the organization

### Strategy 2: Marketplace Customer ID Match
- Looks for `buyer_id` or `buyer_user_id` or `BuyerInfo.BuyerUserId` in `order.marketplace_data`
- Matches on `marketplace_source` + `marketplace_customer_id` within the organization

### Strategy 3: Name + Postal Code Match (Fuzzy)
- Uses `order.shipping_name` and `order.shipping_postal_code`
- ILIKE match on `name`, plus `whereHas('addresses')` matching postal code

### No Match Found
Creates a new Customer with all available data from the order.

### Post-Match Updates
- Fills in missing `marketplace_customer_id` or `email` on existing customer
- Links order: `order.update(['customer_id' => $customer->id])`
- Upserts shipping address (deduped by line1 + postal_code)
- Recalculates stats via `Customer::recalculateStats()`

## Customer Merge Workflow

`CustomerMatchingService::mergeCustomers($primary, $secondary)` runs in a DB transaction:

1. Moves all orders from secondary to primary
2. Moves non-duplicate addresses (deduped by line1 + postal_code)
3. Fills missing fields (email, phone, marketplace_customer_id) from secondary
4. Merges tags arrays (unique values)
5. Recalculates stats on primary
6. Force-deletes secondary (bypasses soft delete)

## Customer Stats Recalculation

`Customer::recalculateStats()` queries all non-cancelled orders and updates:
- `total_orders` = count
- `total_spent` = sum of `total_amount`
- `average_order_value` = total_spent / total_orders
- `first_order_at` = min created_at
- `last_order_at` = max created_at

## Scopes

| Scope | Purpose |
|-------|---------|
| `search($term)` | ILIKE on name, email, phone |
| `byMarketplace($mp)` | Filter by marketplace_source |
| `flagged()` | `is_flagged = true` |
| `topSpenders($limit)` | Order by total_spent DESC |
| `repeatBuyers()` | `total_orders > 1` |

## Routes

All routes require authentication under prefix `customers/`:

| Method | URI | Action |
|--------|-----|--------|
| GET | `/customers` | Index with search, marketplace, flagged, repeat filters |
| GET | `/customers/{customer}` | Show with addresses and recent orders |
| GET | `/customers/{customer}/edit` | Edit form |
| PUT | `/customers/{customer}` | Update customer fields |
| POST | `/customers/merge` | Merge two customers (primary_id, secondary_id) |

## Index Page Stats

The index page computes and displays:
- `total` -- total customer count
- `repeat_buyers` -- customers with more than 1 order
- `total_revenue` -- sum of all customer total_spent
- `avg_ltv` -- average total_spent across all customers
- `flagged` -- count of flagged customers

## Debugging Commands

```php
// Customer count by marketplace
DB::table('customers')->where('organization_id', $orgId)
  ->selectRaw("marketplace_source, count(*) as count")->groupBy('marketplace_source')->get();

// Repeat buyer count
Customer::where('organization_id', $orgId)->repeatBuyers()->count();

// Orphan orders (no customer linked)
Order::where('organization_id', $orgId)->whereNull('customer_id')->count();

// Top 10 spenders
Customer::where('organization_id', $orgId)->topSpenders(10)
  ->get(['name', 'email', 'total_orders', 'total_spent']);

// Flagged customers
Customer::where('organization_id', $orgId)->flagged()
  ->get(['name', 'email', 'flag_reason']);
```

## Audit Logging

| Action | Category | Severity | Metadata keys |
|--------|----------|----------|---------------|
| `customer.created` | customer | info | source (auto_match|manual|csv), marketplace_origin |
| `customer.updated` | customer | info | changes |
| `customer.merged` | customer | warning | merged_into_id, conflict_fields |
| `customer.flagged` | customer | warning | flag_reason |
| `customer.unflagged` | customer | info | |

Trait: models in this feature use `LogsAudit` (`app/Traits/LogsAudit.php`) for auto CRUD diff. Domain lifecycle events emitted from `app/Services/Customer/CustomerMatchingService`, `CustomerController`; `Customer` + `CustomerAddress` models (`LogsAudit`) via `UserActivityLog::log(action, desc, $user, $metadata, $severity, $category)`.

KPI consumer: `UserProductivityDaily.customer_records_touched` (from `customer.created|updated|merged`).

Spec: `docs/AUDIT_SYSTEM.md`. Enforced rule: `.claude/rules/audit-logging.md`. Tests: `use Tests\Support\AssertsAudits;` then `$this->assertAudited('action', $user, [...])`.
