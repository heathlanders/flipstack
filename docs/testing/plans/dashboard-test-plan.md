# Dashboard Test Plan
## Document ID: FLIPSTACK-TP-DSH-001
**Version:** 1.0 | **Last Updated:** 2026-04-15 | **Status:** Approved

## Scope
Verify role-specific dashboard rendering and data loading for all user roles (owner, buyer, lister, fulfillment, support).

## Related Docs
| Resource | Path |
|----------|------|
| Feature docs | `docs/DASHBOARDS.md` |
| Existing tests | None |

## Features Under Test
| # | Feature | Controller@method | Route Name | Priority |
|---|---------|-------------------|------------|----------|
| 1 | Main dashboard redirect | MainDashboardController@index | dashboard.main | High |
| 2 | Owner dashboard | OwnerDashboardController@index | dashboard.owner | High |
| 3 | Buyer dashboard | BuyerDashboardController@index | dashboard.buyer | Medium |
| 4 | Lister dashboard | ListerDashboardController@index | dashboard.lister | Medium |
| 5 | Fulfillment dashboard | FulfillmentDashboardController@index | dashboard.fulfillment | Medium |
| 6 | Support dashboard | SupportDashboardController@index | dashboard.support | Low |

## Test Cases
| ID | Title | File |
|----|-------|------|
| TC-DSH-001 | Role-based dashboard rendering and data integrity | `tests/Feature/Dashboard/DashboardRenderTest.php` |

## Coverage Goals
| Metric | Current | Target |
|--------|---------|--------|
| Feature test coverage | None | 85% route coverage |
| Dashboard controller coverage | None | 80% |
| Role-based access verification | None | 100% |
