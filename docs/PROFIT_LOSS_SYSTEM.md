# Profit and Loss System

How per-order financials are calculated, marketplace fees are tracked, and profitability reports are generated.

## Overview

```
Order Completed
       |
       v
FEE TRACKING -----------> Extract marketplace-specific fees
       |                   (final value, payment processing, referral, FBA)
       v
FINANCIAL CALCULATOR ----> Compute per-order P&L
       |                   Revenue - COGS - Fees - Shipping = Net Profit
       v
ORDER FINANCIAL ---------> Persisted record per order
       |
       v
P&L REPORTS -------------> Aggregate by date range, marketplace, product
                           Summary, daily trend, top products
```

---

## Architecture

### Key Files

| Layer | File |
|-------|------|
| Model | `app/Models/Analytics/OrderFinancial.php` |
| Model | `app/Models/Marketplace/MarketplaceFee.php` |
| Service | `app/Services/Analytics/OrderFinancialCalculator.php` |
| Service | `app/Services/Analytics/ProfitLossService.php` |
| Service | `app/Services/Marketplace/FeeTrackingService.php` |
| Controller | `app/Http/Controllers/Analytics/` |
| Routes | `routes/analytics.php` |
| Pages | `resources/js/Pages/Analytics/` |

---

## Data Model

### OrderFinancial

One record per order, storing the computed financial breakdown.

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID | Primary key |
| `organization_id` | UUID | Owning organization |
| `order_id` | UUID | The order being analyzed |
| `revenue` | decimal | Order total amount (includes shipping charged to buyer) |
| `cost_of_goods` | decimal | Sum of (item qty * product cost_price) |
| `marketplace_fees` | decimal | Sum of all fees from MarketplaceFee table |
| `shipping_label_cost` | decimal | Sum of shipment label_cost + insurance_cost |
| `shipping_charged` | decimal | Shipping amount charged to the buyer |
| `tax_collected` | decimal | Tax amount on the order |
| `net_profit` | decimal | revenue - COGS - fees - shipping label cost |
| `profit_margin_pct` | decimal | (net_profit / revenue) * 100 |
| `calculated_at` | datetime | When the calculation was last run |

### MarketplaceFee

Individual fee line items per order, per marketplace.

| Field | Type | Description |
|-------|------|-------------|
| `organization_id` | UUID | Owning organization |
| `order_id` | UUID | Associated order |
| `marketplace` | string | ebay, shopify, amazon |
| `fee_type` | string | Fee category (see fee types below) |
| `amount` | decimal | Fee amount |
| `currency` | string | Currency code (default USD) |
| `description` | string | Human-readable fee description |
| `marketplace_fee_id` | string | Marketplace's own fee/transaction ID |
| `fee_date` | date | Date the fee was incurred |

---

## Key Flows

### 1. Fee Extraction

`FeeTrackingService::recordFeesFromOrder()` parses marketplace-specific fee data from `order.marketplace_data` and persists individual `MarketplaceFee` records.

**eBay Fees:**
| Fee Type | Source Field | Description |
|----------|-------------|-------------|
| `final_value` | `totalMarketplaceFee.value` | eBay's commission on the sale |
| `payment_processing` | `paymentProcessingFee.value` | Managed payments processing fee |

**Shopify Fees:**
| Fee Type | Source Field | Description |
|----------|-------------|-------------|
| `payment_processing` | `transactions[].fees[].amount` | Per-transaction processing fees |

**Amazon Fees:**
| Fee Type | Source Field | Description |
|----------|-------------|-------------|
| `referral` | `commission` / `ReferralFee` | Amazon's category-based referral fee |
| `fba` | `FBAPerUnitFulfillmentFee` / `FBAWeightBasedFee` | Fulfillment by Amazon fees |
| `closing` | `VariableClosingFee` | Media category closing fee |
| `other` | Any unrecognized fee type | Catch-all for other Amazon fees |

Fees are upserted (`updateOrCreate`) keyed on `order_id + marketplace + fee_type`, so recalculation is idempotent.

### 2. Per-Order Financial Calculation

`OrderFinancialCalculator::calculate()` computes the full P&L for a single order:

1. **Revenue** = `order.total_amount` (the total the buyer paid)
2. **COGS** = Sum of (`item.quantity * item.product.cost_price`) for all order items
3. **Marketplace Fees** = Sum of all `MarketplaceFee` records for the order
4. **Shipping Label Cost** = Sum of (`shipment.label_cost + shipment.insurance_cost`) across all shipments
5. **Shipping Charged** = `order.shipping_cost` (what the buyer paid for shipping)
6. **Tax Collected** = `order.tax_amount`

**Net Profit Formula:**
```
net_profit = revenue - COGS - marketplace_fees - shipping_label_cost
```

Note: `shipping_charged` is already included in `revenue` (order total), so it is not added separately. The difference between shipping charged and shipping label cost represents shipping profit or loss.

**Profit Margin:**
```
profit_margin_pct = (net_profit / revenue) * 100
```

The result is persisted via `updateOrCreate` on `OrderFinancial`, making recalculation safe to run repeatedly.

### 3. Report Aggregation

`ProfitLossService` provides organization-wide analytics using a fluent builder pattern:

```php
$service->forOrganization($orgId)->dateRange($start, $end)->getSummary();
```

**Available Reports:**

**Summary** (`getSummary`)
- Order count, total revenue, total COGS, total fees, total shipping cost/charged
- Total net profit, average margin, gross profit

**By Marketplace** (`getByMarketplace`)
- Same metrics broken down by marketplace (eBay, Shopify, Amazon)
- Ordered by total revenue descending

**Daily Trend** (`getDailyTrend`)
- Per-day order count, revenue, and net profit
- Used for trend charts on the analytics dashboard

**Top Products** (`getTopProducts`)
- Products ranked by gross profit
- Includes units sold, revenue, COGS, gross profit, and margin percentage
- Default limit of 10

---

## Integration Points

| System | Integration |
|--------|-------------|
| **Order Sync** | After order import, `FeeTrackingService::recordFeesFromOrder()` extracts fees from marketplace data |
| **Fulfillment** | Shipping label costs come from `Shipment.label_cost` and `Shipment.insurance_cost` |
| **Inventory/COGS** | Product `cost_price` (set via PO receiving weighted average or manual entry) determines COGS per unit |
| **Purchase Orders** | Weighted average cost from `ReceivingService` flows into `Product.cost_price`, which feeds COGS |
| **Dashboard** | Analytics pages consume P&L data for visual reports and KPI cards |
