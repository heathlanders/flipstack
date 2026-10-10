# Pricing (Repricing) Test Plan

## Document ID: FLIPSTACK-TP-PRI-001

**Version:** 1.0
**Last Updated:** 2026-04-15
**Status:** Approved

---

## 1. Overview

### 1.1 Scope

Repricing engine: rule creation (strategies), priority-based evaluation, price guards (min/max), and repricing logs. Marketplace-specific (eBay/Shopify/Amazon) or global rules.

### 1.2 Related Documentation

| Resource | Path |
|----------|------|
| Feature docs | `docs/REPRICING_ENGINE.md` |
| Existing tests | `tests/Feature/Pricing/RepricingTest.php` |
| Test cases | `docs/testing/cases/pricing/` |

---

## 2. Features Under Test

| # | Feature | Controller Method | Route Name | Priority |
|---|---------|------------------|-----------|----------|
| 1 | Rule listing | RepricingController@index | `pricing.repricing.index` | P1 |
| 2 | Create rule | RepricingController@store | `pricing.repricing.store` | P0 |
| 3 | Update rule | RepricingController@update | `pricing.repricing.update` | P1 |
| 4 | Delete rule | RepricingController@destroy | `pricing.repricing.destroy` | P1 |
| 5 | Toggle rule active/inactive | RepricingController@toggle | `pricing.repricing.toggle` | P1 |
| 6 | View repricing logs | RepricingController@logs | `pricing.repricing.logs` | P2 |
| 7 | Run repricing engine | RepricingController@run | `pricing.repricing.run` | P0 |
| 8 | Price guards (min/max) | RepricingEngine@applyGuards | - (service) | P0 |

---

## 3. Models

| Model | Key Fields |
|-------|-----------|
| RepricingRule | name, marketplace (ebay/shopify/amazon/*), scope_type (all/category/product), scope_ids, strategy (match_lowest/beat_by_amount/beat_by_percent/cost_plus/map_floor), parameters, is_active, priority |
| RepricingLog | repricing_rule_id, product_id, old_price, new_price, competitor_price, reason, applied_at |

---

## 4. Test Cases

| Test Case ID | Title | File |
|--------------|-------|------|
| TC-PRI-001 | Repricing Rules | `cases/pricing/TC-PRI-001-repricing-rules.md` |
