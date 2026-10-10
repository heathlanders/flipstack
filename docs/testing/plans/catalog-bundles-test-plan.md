# Catalog/Bundles Test Plan
## Document ID: FLIPSTACK-TP-CAT-001
**Version:** 1.0 | **Last Updated:** 2026-04-15 | **Status:** Approved

## Scope
Verify product bundle CRUD, component management (add/remove), and bundle type behavior for fixed and pick-n bundle types.

## Related Docs
| Resource | Path |
|----------|------|
| Feature docs | `docs/BUNDLES.md` |
| Existing tests | `tests/Feature/BundleTest.php` |

## Features Under Test
| # | Feature | Controller@method | Route Name | Priority |
|---|---------|-------------------|------------|----------|
| 1 | Bundle listing | BundleController@index | inventory.bundles.index | High |
| 2 | Create bundle form | BundleController@create | inventory.bundles.create | Medium |
| 3 | Store bundle | BundleController@store | inventory.bundles.store | High |
| 4 | Show bundle detail | BundleController@show | inventory.bundles.show | High |
| 5 | Update bundle | BundleController@update | inventory.bundles.update | Medium |
| 6 | Add component | BundleController@addComponent | inventory.bundles.addComponent | High |
| 7 | Remove component | BundleController@removeComponent | inventory.bundles.removeComponent | Medium |
| 8 | Delete bundle | BundleController@destroy | inventory.bundles.destroy | Low |

## Test Cases
| ID | Title | File |
|----|-------|------|
| TC-CAT-001 | Bundle CRUD, component management, and type behavior (fixed/pick_n) | `tests/Feature/Catalog/BundleFlowTest.php` |

## Coverage Goals
| Metric | Current | Target |
|--------|---------|--------|
| Feature test coverage | Partial (BundleTest.php) | 90% route coverage |
| ProductBundle model coverage | Medium | 85% |
| ProductBundleItem model coverage | Medium | 85% |
