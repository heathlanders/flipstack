---
name: profit-loss
description: Per-order financial calculation, marketplace fee tracking, profit and loss reporting with daily trends, marketplace breakdown, and top product analysis
user-invocable: false
---

# Profit and Loss System

## Key Files

| Area | File | Purpose |
|------|------|---------|
| **Models** | `app/Models/Analytics/OrderFinancial.php` | Per-order financial snapshot: revenue, COGS, fees, shipping, net profit, margin |
| | `app/Models/Marketplace/MarketplaceFee.php` | Individual fee records per order per marketplace |
| **Services** | `app/Services/Analytics/OrderFinancialCalculator.php` | Calculates financials for a single order |
| | `app/Services/Analytics/ProfitLossService.php` | Aggregated P&L reporting: summary, marketplace breakdown, daily trend, top products |
| | `app/Services/Marketplace/FeeTrackingService.php` | Extracts and records marketplace fees from order data |
| **Jobs** | `app/Jobs/Analytics/CalculateOrderFinancials.php` | Queue job: extract fees then calculate financials (queue: `default`, 120s timeout, 3 tries) |
| **Controllers** | `app/Http/Controllers/Analytics/ProfitLossController.php` | P&L dashboard with date range filtering |
| **Routes** | `routes/analytics.php` | P&L route at `analytics/profit-loss` |

## Calculation Formula

`OrderFinancialCalculator::calculate()` computes per order:

```
revenue           = order.total_amount
COGS              = SUM(item.quantity * item.product.cost_price)
marketplace_fees  = SUM(marketplace_fees WHERE order_id = order.id)
shipping_cost     = SUM(shipments.label_cost + shipments.insurance_cost)
shipping_charged  = order.shipping_cost (charged to buyer)
tax_collected     = order.tax_amount

net_profit        = revenue - COGS - marketplace_fees - shipping_cost
profit_margin_pct = (net_profit / revenue) * 100
```

Note: `shipping_charged` is already included in `revenue` (order.total_amount), so it is not added again.

## Marketplace Fee Types

`MarketplaceFee` constants:

| Constant | Value | Marketplaces |
|----------|-------|-------------|
| `TYPE_FINAL_VALUE` | `final_value` | eBay |
| `TYPE_INSERTION` | `insertion` | eBay |
| `TYPE_PROMOTED_LISTING` | `promoted_listing` | eBay |
| `TYPE_PAYMENT_PROCESSING` | `payment_processing` | eBay, Shopify |
| `TYPE_SHIPPING_LABEL` | `shipping_label` | All |
| `TYPE_SUBSCRIPTION` | `subscription` | All |
| `TYPE_REFUND_CREDIT` | `refund_credit` | All |
| `TYPE_REFERRAL` | `referral` | Amazon |
| `TYPE_FBA` | `fba` | Amazon |
| `TYPE_CLOSING` | `closing` | Amazon |
| `TYPE_OTHER` | `other` | All |

## Fee Extraction by Marketplace

`FeeTrackingService::recordFeesFromOrder()` dispatches to marketplace-specific extractors:

### eBay
- `totalMarketplaceFee.value` or `total_marketplace_fee` -> `final_value`
- `paymentProcessingFee.value` or `payment_processing_fee` -> `payment_processing`

### Shopify
- Iterates `transactions[].fees[]` -> `payment_processing` per fee

### Amazon
- Iterates `OrderFees[]` or `fees[]`, maps by FeeType:
  - `Commission`/`ReferralFee` -> `referral`
  - `FBAPerUnitFulfillmentFee`/`FBAWeightBasedFee` -> `fba`
  - `VariableClosingFee` -> `closing`
  - All others -> `other`

Fees are upserted (updateOrCreate) keyed on `order_id + marketplace + fee_type`.

## Trigger: CalculateOrderFinancials Job

Dispatched after order import or status changes. Execution order:
1. `FeeTrackingService::recordFeesFromOrder()` -- extract fees from marketplace data
2. `OrderFinancialCalculator::calculate()` -- compute and upsert OrderFinancial record

## P&L Dashboard

`ProfitLossController::dashboard()` renders with date range (default: current month). Provides:

### Summary
- `order_count`, `total_revenue`, `total_cogs`, `total_fees`
- `total_shipping_cost`, `total_shipping_charged`
- `total_net_profit`, `avg_margin`, `gross_profit`

### By Marketplace
Grouped by `orders.marketplace`: order count, revenue, COGS, fees, net profit, avg margin.

### Daily Trend
Revenue and net profit per day within the date range.

### Top Products (limit 10)
Joined through order_items to products: units sold, revenue, COGS, gross profit, margin.

### Fee Summary
`FeeTrackingService::getFeeSummary()`: total fees, breakdown by marketplace, breakdown by fee type.

## Routes

| Method | URI | Route Name |
|--------|-----|------------|
| GET | `/analytics/profit-loss` | `analytics.profit-loss.dashboard` |

Query parameters: `start_date`, `end_date` (default: start of current month to today).

## Debugging Commands

```php
// Orders missing financial calculations
$calculated = OrderFinancial::where('organization_id', $orgId)->pluck('order_id');
Order::where('organization_id', $orgId)->whereNotIn('id', $calculated)->count();

// Total profit for current month
OrderFinancial::where('organization_id', $orgId)
  ->whereHas('order', fn($q) => $q->where('created_at', '>=', now()->startOfMonth()))
  ->sum('net_profit');

// Fee breakdown by marketplace
DB::table('marketplace_fees')->where('organization_id', $orgId)
  ->selectRaw("marketplace, fee_type, SUM(amount) as total")
  ->groupBy('marketplace', 'fee_type')
  ->orderBy('marketplace')->orderByDesc('total')->get();

// Negative margin orders
OrderFinancial::where('organization_id', $orgId)
  ->where('net_profit', '<', 0)
  ->with('order:id,marketplace,marketplace_order_id,total_amount')
  ->orderBy('net_profit')
  ->limit(20)->get();

// Recalculate financials for all orders
Order::where('organization_id', $orgId)->each(fn($o) => CalculateOrderFinancials::dispatch($o->id));
```

## Audit Logging

| Action | Category | Severity | Metadata keys |
|--------|----------|----------|---------------|
| `financial.recalculated` | general | debug | order_id, revenue, net_profit, source: job|cron |
| `financial.recalc_failed` | general | error | order_id, error_message |
| `financial.fee_resync_requested` | general | info | marketplace, order_count, requested_by |

Trait: models in this feature use `LogsAudit` (`app/Traits/LogsAudit.php`) for auto CRUD diff. Domain lifecycle events emitted from `app/Services/Analytics/ProfitLossService`, `app/Jobs/Analytics/Recalculate*`; jobs use `withAuditActor` with `source: 'job'` via `UserActivityLog::log(action, desc, $user, $metadata, $severity, $category)`.

P&L recalcs are high-volume -- use `severity: 'debug'` and consider 7d retention for `financial.recalculated` to avoid bloating the log.

Spec: `docs/AUDIT_SYSTEM.md`. Enforced rule: `.claude/rules/audit-logging.md`. Tests: `use Tests\Support\AssertsAudits;` then `$this->assertAudited('action', $user, [...])`.
