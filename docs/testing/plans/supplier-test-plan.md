# Supplier Test Plan
## Document ID: FLIPSTACK-TP-SUP-001
**Version:** 1.0 | **Last Updated:** 2026-04-15 | **Status:** Approved

## Scope
Verify supplier onboarding workflow and supplier product submission lifecycle including review and approval.

## Related Docs
| Resource | Path |
|----------|------|
| Feature docs | `docs/SUPPLIER_PORTAL.md` |
| Existing tests | `tests/Feature/ProductSubmissionTest.php` |

## Features Under Test
| # | Feature | Controller@method | Route Name | Priority |
|---|---------|-------------------|------------|----------|
| 1 | Onboarding start | SupplierOnboardingController@create | supplier.onboarding.create | High |
| 2 | Onboarding store | SupplierOnboardingController@store | supplier.onboarding.store | High |
| 3 | Onboarding status | SupplierOnboardingController@status | supplier.onboarding.status | Medium |
| 4 | Supplier dashboard | SupplierProductController@index | supplier.index | Medium |
| 5 | Product submission create | SupplierProductController@create | supplier.products.create | High |
| 6 | Product submission store | SupplierProductController@store | supplier.products.store | High |
| 7 | Submission review (admin) | AdminSubmissionController@review | admin.submissions.review | High |
| 8 | Submission approve/reject | AdminSubmissionController@decide | admin.submissions.decide | High |

## Test Cases
| ID | Title | File |
|----|-------|------|
| TC-SUP-001 | Supplier onboarding and portal access | `tests/Feature/Supplier/OnboardingFlowTest.php` |
| TC-SUP-002 | Product submission, review, and approval | `tests/Feature/Supplier/ProductSubmissionFlowTest.php` |

## Coverage Goals
| Metric | Current | Target |
|--------|---------|--------|
| Feature test coverage | Partial (ProductSubmissionTest.php) | 90% route coverage |
| SupplierProductSubmission model coverage | Medium | 85% |
| SupplierOnboarding model coverage | None | 80% |
