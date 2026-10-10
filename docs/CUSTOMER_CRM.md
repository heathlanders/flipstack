# Customer CRM

How buyer identities are resolved across marketplaces, matched to a unified customer record, and tracked over time.

## Overview

```
Order Imported (eBay / Shopify / Amazon)
         |
         v
  CUSTOMER MATCHING -----> Try email, marketplace ID, name+postal code
         |
         v
  MATCH FOUND? --yes----> Link order to existing customer
         |                 Update missing fields (email, marketplace ID)
         no                Upsert shipping address
         |                 Recalculate lifetime stats
         v
  CREATE CUSTOMER -------> New record from order data
                           First address saved as default
```

---

## Architecture

### Key Files

| Layer | File |
|-------|------|
| Model | `app/Models/Customer/Customer.php` |
| Model | `app/Models/Customer/CustomerAddress.php` |
| Service | `app/Services/Customer/CustomerMatchingService.php` |
| Controller | `app/Http/Controllers/Customer/` |
| Routes | `routes/customers.php` |
| Pages | `resources/js/Pages/Customers/` |

---

## Data Model

### Customer

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID | Primary key |
| `organization_id` | UUID | Owning organization |
| `email` | string | Buyer email (primary matching key) |
| `name` | string | Buyer name |
| `phone` | string | Phone number |
| `marketplace_source` | string | First marketplace seen (ebay, shopify, amazon) |
| `marketplace_customer_id` | string | Marketplace-specific buyer ID |
| `first_order_at` | datetime | Earliest order date |
| `last_order_at` | datetime | Most recent order date |
| `total_orders` | int | Lifetime order count |
| `total_spent` | decimal | Lifetime spend |
| `average_order_value` | decimal | Computed: total_spent / total_orders |
| `tags` | array (JSON) | User-defined tags for segmentation |
| `is_flagged` | boolean | Flagged for attention |
| `flag_reason` | string | Why the customer is flagged |

### CustomerAddress

| Field | Type | Description |
|-------|------|-------------|
| `customer_id` | UUID | Parent customer |
| `type` | string | shipping or billing |
| `is_default` | boolean | Default address for this type |
| `name` | string | Recipient name |
| `line1`, `line2` | string | Street address |
| `city`, `state`, `postal_code`, `country` | string | Address components |

---

## Key Flows

### 1. Customer Matching Algorithm

`CustomerMatchingService::findOrCreateFromOrder()` runs a three-strategy cascade within a DB transaction. The first match wins.

**Strategy 1: Email Match**
- Extracts buyer email from `order.marketplace_data` (checks `buyer_email`, `email`, `BuyerInfo.Email`)
- Queries for an existing customer in the same organization with a matching email
- Strongest signal -- email is considered unique per person

**Strategy 2: Marketplace Buyer ID Match**
- Extracts marketplace-specific buyer ID from `order.marketplace_data` (checks `buyer_id`, `buyer_user_id`, `BuyerInfo.BuyerUserId`)
- Queries for a customer with matching `marketplace_source` + `marketplace_customer_id`
- Handles cases where the same buyer uses different emails across orders

**Strategy 3: Name + Postal Code Match (Fuzzy)**
- Uses `shipping_name` from the order and `shipping_postal_code`
- Queries with case-insensitive name match (`ILIKE`) plus address postal code match
- Weakest signal -- used as a last resort before creating a new record

**No Match: Create New Customer**
- A new `Customer` record is created with all available data from the order
- `total_orders` starts at 1, `total_spent` set from order amount

### 2. Post-Match Updates

After matching (or creating), the service:
1. Fills in missing fields on the customer (email, marketplace ID) from the current order
2. Links the order to the customer via `order.customer_id`
3. Upserts the shipping address (deduplicates by `line1` + `postal_code`)
4. The first address for a customer is automatically set as the default
5. Recalculates lifetime stats via `Customer::recalculateStats()`

### 3. Customer Merge

`CustomerMatchingService::mergeCustomers()` combines two customer records into one, inside a DB transaction:

1. **Move orders** -- All orders from the secondary customer are reassigned to the primary
2. **Move addresses** -- Addresses are transferred unless a duplicate already exists (matched by `line1` + `postal_code`)
3. **Fill missing fields** -- Primary customer inherits email, phone, and marketplace ID from secondary if missing
4. **Merge tags** -- Tags from both customers are combined (deduplicated)
5. **Recalculate stats** -- Primary customer's lifetime metrics are recomputed
6. **Delete secondary** -- The secondary customer is permanently deleted (`forceDelete`)

### 4. Stat Recalculation

`Customer::recalculateStats()` recomputes all lifetime metrics from the orders table:
- Excludes cancelled orders
- Updates: `total_orders`, `total_spent`, `average_order_value`, `first_order_at`, `last_order_at`

---

## Query Scopes

| Scope | Description |
|-------|-------------|
| `search($term)` | Case-insensitive search across name, email, phone |
| `byMarketplace($marketplace)` | Filter by `marketplace_source` |
| `flagged()` | Only flagged customers |
| `topSpenders($limit)` | Ordered by `total_spent` descending |
| `repeatBuyers()` | Customers with more than one order |

---

## Integration Points

| System | Integration |
|--------|-------------|
| **Order Sync** | `OrderSyncService` calls `CustomerMatchingService::findOrCreateFromOrder()` after importing each order |
| **Orders** | Customer has many Orders via `customer_id` foreign key |
| **Addresses** | Each customer maintains a list of shipping/billing addresses, with a default per type |
| **Profit/Loss** | Customer lifetime value feeds from `total_spent`; per-customer profitability can be derived through linked orders |
| **Flagging** | Flagged customers surface in the CRM UI for manual review (problem buyers, high-value accounts) |
