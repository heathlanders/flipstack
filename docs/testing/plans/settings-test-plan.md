# Settings Test Plan
## Document ID: FLIPSTACK-TP-SET-001
**Version:** 1.0 | **Last Updated:** 2026-04-15 | **Status:** Approved

## Scope
Verify currency settings management including listing supported currencies, syncing exchange rates, and performing currency conversions.

## Related Docs
| Resource | Path |
|----------|------|
| Feature docs | `docs/CURRENCY.md` |
| Existing tests | `tests/Feature/CurrencyTest.php` |

## Features Under Test
| # | Feature | Controller@method | Route Name | Priority |
|---|---------|-------------------|------------|----------|
| 1 | Currency listing | CurrencyController@index | settings.currency.index | High |
| 2 | Sync exchange rates | CurrencyController@sync | settings.currency.sync | High |
| 3 | Convert amount | CurrencyController@convert | settings.currency.convert | Medium |

## Test Cases
| ID | Title | File |
|----|-------|------|
| TC-SET-001 | Currency listing and exchange rate sync | `tests/Feature/Settings/CurrencySyncTest.php` |
| TC-SET-002 | Currency conversion accuracy and edge cases | `tests/Feature/Settings/CurrencyConversionTest.php` |

## Coverage Goals
| Metric | Current | Target |
|--------|---------|--------|
| Feature test coverage | Partial (CurrencyTest.php) | 90% route coverage |
| CurrencyController coverage | Medium | 90% |
| ExchangeRate model coverage | Low | 80% |
