# FlipStack Master Test Plan

## Document ID: FLIPSTACK-MTP-001

**Version:** 1.0
**Created:** 2026-04-15
**Status:** Active
**Owner:** Development Team

---

## 1. Introduction

### 1.1 Purpose

This document defines the overall testing strategy for FlipStack, a multi-tenant ecommerce SaaS platform. It establishes standards, processes, and coverage goals for all automated and manual testing across web, mobile, and API surfaces.

### 1.2 Scope

All software components of FlipStack:
- Laravel 11 backend (PHP)
- Vue 3 / Inertia.js frontend
- Flutter mobile application
- REST API layer (Sanctum-authenticated)
- Queue jobs and scheduled tasks
- Third-party integrations (eBay, Shopify, ShippingEasy)

### 1.3 Audience

- Developers writing and maintaining tests
- Reviewers assessing test coverage
- QA personnel executing manual test procedures

### 1.4 References

- Feature Matrix: `docs/FEATURE_MATRIX.md`
- Coverage Matrix: `docs/testing/COVERAGE_MATRIX.md`
- Regression Suite: `docs/testing/REGRESSION_SUITE.md`

---

## 2. Test Items

| Component | Technology | Location |
|-----------|-----------|----------|
| Backend application | Laravel 11 / PHP 8.3 | `app/` |
| Frontend pages | Vue 3 / Inertia.js | `resources/js/Pages/` |
| Frontend components | Vue 3 | `resources/js/Components/` |
| Mobile application | Flutter / Dart | `mobile/lib/` |
| REST API | Laravel / Sanctum | `routes/api.php` |
| Queue jobs | Laravel Queues / Horizon | `app/Jobs/` |
| Database migrations | PostgreSQL | `database/migrations/` |

---

## 3. Features to be Tested

Organized by domain. Each domain has a dedicated test plan in `docs/testing/plans/`.

| # | Domain | Test Plan | Business Risk | Existing Tests |
|---|--------|-----------|---------------|----------------|
| 1 | Fulfillment | `fulfillment-test-plan.md` | Critical | 8 files |
| 2 | Inventory | `inventory-test-plan.md` | Critical | 9 files |
| 3 | Marketplace - eBay | `marketplace-ebay-test-plan.md` | Critical | 5 files |
| 4 | Marketplace - Shopify | `marketplace-shopify-test-plan.md` | Critical | 10 files |
| 5 | Intake Queue | `intake-queue-test-plan.md` | High | 3 files |
| 6 | Procurement | `procurement-test-plan.md` | High | 1 file |
| 7 | Analytics | `analytics-test-plan.md` | Medium | 2 files |
| 8 | Pricing | `pricing-test-plan.md` | High | 1 file |
| 9 | Customer | `customer-test-plan.md` | Medium | 1 file |
| 10 | Communication | `communication-test-plan.md` | Medium | 1 file |
| 11 | Marketing | `marketing-test-plan.md` | Medium | 1 file |
| 12 | Auth & Organization | `auth-org-test-plan.md` | Critical | 2 files |
| 13 | Supplier | `supplier-test-plan.md` | Medium | 1 file |
| 14 | Catalog & Bundles | `catalog-bundles-test-plan.md` | Medium | 1 file |
| 15 | Part-Out | `part-out-test-plan.md` | Medium | 0 files |
| 16 | Warehouse | `warehouse-test-plan.md` | Medium | 1 file |
| 17 | Printing | `printing-test-plan.md` | Low | 0 files |
| 18 | Dashboard | `dashboard-test-plan.md` | Low | 0 files |
| 19 | Notifications | `notifications-test-plan.md` | Low | 1 file |
| 20 | Settings | `settings-test-plan.md` | Low | 1 file |
| 21 | Mobile App | `mobile-app-test-plan.md` | High | 0 files |
| 22 | API Endpoints | `api-endpoints-test-plan.md` | High | 0 files |

---

## 4. Features Not to be Tested

- Third-party admin UIs (eBay Seller Hub, Shopify Admin, ShippingEasy dashboard)
- Pusher/WebSocket infrastructure (verified by Pusher's own SLA)
- Email delivery (verified at SMTP level; content tested via Mail::fake)
- Browser-specific CSS rendering (tested manually on major browsers only)
- Cloudflare/Traefik routing (infrastructure-level, tested via deploy health checks)

---

## 5. Testing Approach

### 5.1 Unit Tests (PHPUnit)

**Purpose:** Isolate and verify individual classes — services, models, helpers.
**Location:** `tests/Unit/`
**Convention:** `tests/Unit/[Layer]/[ClassNameTest].php`
**Execution:** `docker exec ecommerce_app php artisan test tests/Unit/`

### 5.2 Feature Tests (PHPUnit)

**Purpose:** Test complete request/response cycles through the Laravel stack.
**Location:** `tests/Feature/[Domain]/`
**Convention:** `tests/Feature/[Domain]/[FeatureNameTest].php`
**Traits:** `RefreshDatabase`, `CreatesOrganizationFixtures`, domain-specific traits
**Execution:** `docker exec ecommerce_app php artisan test tests/Feature/`

### 5.3 Frontend Tests (Planned: Vitest + Vue Test Utils)

**Purpose:** Test Vue component rendering, interactions, and state management.
**Location:** `resources/js/__tests__/` (to be created)
**Status:** Not yet implemented. Priority: Phase 5+.

### 5.4 Mobile Tests (Planned: flutter_test)

**Purpose:** Widget tests, integration tests, and provider tests for Flutter app.
**Location:** `mobile/test/`
**Status:** 1 placeholder test exists. Requires comprehensive test suite.

### 5.5 E2E Tests (Planned: Playwright)

**Purpose:** Full browser automation for critical user workflows.
**Location:** `e2e/` (to be created)
**Status:** Not yet implemented. Priority: Phase 5+.

### 5.6 Manual Testing

**Purpose:** Verify visual correctness, UX flows, and scenarios that are impractical to automate.
**Procedures:** Documented in `docs/testing/cases/` with UI test templates.
**Frequency:** Per release (regression suite) and per feature (acceptance testing).

### 5.7 Sandbox / Integration Testing

**Purpose:** Verify third-party API integrations against sandbox environments.
**Location:** `tests/Sandbox/`
**Execution:** Manual trigger only (not part of CI). Requires valid sandbox credentials.

---

## 6. Test Data Strategy

### 6.1 Factories

All models have Laravel factories in `database/factories/`. Tests use factories for data setup.

### 6.2 Shared Fixtures (Traits)

| Trait | Location | Purpose |
|-------|----------|---------|
| `CreatesOrganizationFixtures` | `tests/Traits/` | Standard org, user, team, role setup |
| `CreatesIntakeQueueItems` | `tests/Traits/` | Product intake queue test data |
| `CreatesShopifyFixtures` | `tests/Traits/` | Shopify credential, listing, product setup |

### 6.3 External API Mocking

- `Http::fake()` for all external HTTP calls in unit/feature tests
- `Http::preventStrayRequests()` in base TestCase to catch unmocked calls
- Sandbox tests (in `tests/Sandbox/`) hit real APIs and are excluded from CI

---

## 7. Pass/Fail Criteria

### 7.1 CI Gate (per PR)

- All automated tests pass (zero failures)
- Code formatting passes (`pint --test`)
- No new test files skip or mark incomplete without justification

### 7.2 Release Gate

- All regression suite items verified (see `REGRESSION_SUITE.md`)
- No open P0 or P1 bugs for the release milestone
- Coverage matrix updated for all changed domains

---

## 8. Test Environment

| Component | Configuration |
|-----------|---------------|
| Runtime | Docker container `ecommerce_app` |
| Database | PostgreSQL (test database, `RefreshDatabase` trait) |
| Queue | `sync` driver (jobs execute inline) |
| Cache | `array` driver (in-memory, per-test) |
| Mail | `array` driver (captured, not sent) |
| External HTTP | `Http::fake()` (no real calls) |
| Broadcasting | `null` driver |

**Execution command:** `docker exec ecommerce_app php artisan test`

---

## 9. Test Documentation Structure

```
docs/testing/
    MASTER_TEST_PLAN.md          <-- This document
    COVERAGE_MATRIX.md           <-- Feature-to-test mapping
    REGRESSION_SUITE.md          <-- Pre-release checklist
    templates/                   <-- Reusable templates
        test-plan-template.md
        test-case-template.md
        api-test-template.md
        ui-test-template.md
        mobile-test-template.md
        integration-test-template.md
        e2e-workflow-template.md
    plans/                       <-- Domain-level test plans
    cases/                       <-- Individual test case documents
        fulfillment/
        inventory/
        marketplace/
        ...14 domain subdirectories
```

### 9.1 Test Case ID Scheme

Format: `TC-[DOMAIN_CODE]-[FILE_NUMBER]-[SEQUENCE]`

| Code | Domain |
|------|--------|
| FUL | Fulfillment |
| INV | Inventory |
| MKT | Marketplace (eBay + Shopify) |
| ITQ | Intake Queue |
| PRC | Procurement |
| ANL | Analytics |
| PRI | Pricing |
| CUS | Customer |
| COM | Communication |
| MRK | Marketing |
| AUT | Auth & Organization |
| SUP | Supplier |
| PRT | Printing |
| DSH | Dashboard |
| NTF | Notifications |
| SET | Settings |
| CAT | Catalog & Bundles |
| WHS | Warehouse |
| MOB | Mobile |
| API | API Endpoints |
| E2E | End-to-End Workflows |
| INT | Integration (marketplace-specific) |

---

## 10. Existing Test Inventory

### 10.1 Feature Tests (52 files)

| Directory | Files | Key Tests |
|-----------|-------|-----------|
| `Fulfillment/` | 7 | Order sync (Shopify, Amazon), returns, tracking, routing rules, shipping rules, ShippingEasy |
| `Inventory/` | 9 | Product CRUD, categories, operations, suppliers, deletion, batches, warehouse, bundles, sync |
| `Jobs/` | 7 | Shopify sync/import/retry, eBay import, bulk sync, webhooks, inventory update |
| `IntakeQueue/` | 3 | Controller, processing, status transitions |
| `Integration/` | 3 | eBay connect, Shopify connect, import prompt |
| `Marketplace/` | 2 | Cross-listing, scheduled listings |
| `Marketplaces/` | 2 | eBay marketplace, Shopify marketplace |
| `Shopify/` | 1 | Webhook handling |
| `Ebay/` | 1 | Promoted listings |
| `Analytics/` | 2 | Forecasting, profit/loss |
| `Communication/` | 1 | Messages |
| `Customers/` | 1 | Customer management |
| `Marketing/` | 1 | Email campaigns |
| `Notifications/` | 1 | User invitation |
| `Pricing/` | 1 | Repricing |
| `Procurement/` | 1 | Purchase orders |
| `Settings/` | 1 | Currency |
| `Supplier/` | 1 | Product submissions |
| `Admin/` | 1 | AI prompt management |
| `Mail/` | 1 | Organization owner invitation |
| Root | 3 | Authentication, team management, example |

### 10.2 Unit Tests (14 files)

| Directory | Files | Key Tests |
|-----------|-------|-----------|
| `Services/` | 8 | eBay category/store import, Shopify product/rate limiter/store import, prompt service, marketplace sync rules, warehouse auto-builder |
| `Models/` | 3 | AI category prompt, product intake queue, Shopify listing |
| `Jobs/` | 1 | eBay listing condition |
| Root | 2 | eBay SKU parser, SKU parser service |

### 10.3 Other Test Files

| File | Purpose |
|------|---------|
| `tests/Sandbox/ShopifySandboxTest.php` | Live Shopify API testing (manual only) |
| `tests/Traits/Creates*.php` (3 files) | Shared test fixtures |

---

## 11. Risks and Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| Third-party API changes break mocks | Tests pass but integration fails | Periodic sandbox testing; version-pin API schemas in mocks |
| Multi-tenant data leakage | Critical security issue | Every feature test must verify org scoping; dedicated isolation tests per domain |
| Test database drift from production | Tests pass on wrong schema | `RefreshDatabase` runs all migrations; never skip migrations in tests |
| Slow test suite as coverage grows | Developer friction, CI slowdown | Parallelize with `--parallel`; separate slow integration tests from fast unit tests |
| Mobile test suite nonexistent | App regressions undetected | Priority: build Flutter test suite in Phase 4 |

---

## 12. Rollout Schedule

| Phase | Focus | Domain Test Plans | Test Case Files |
|-------|-------|-------------------|-----------------|
| 0 | Foundation (templates, matrix, regression) | 0 | 0 |
| 1 | Revenue-critical paths | 4 | 24 |
| 2 | Core operations | 5 | 18 |
| 3 | Supporting features | 11 | 19 |
| 4 | Mobile and API | 2 | 16 |
| 5+ | Ongoing maintenance | As needed | As needed |

---

## 13. Execution Tracking

Test cycles are tracked on **GitHub Projects v2** Kanban boards. See [`EXECUTION_LIFECYCLE.md`](EXECUTION_LIFECYCLE.md) for the full lifecycle: cycle kickoff, run, failure handling, close.

| Concern | Source of truth |
|---------|----------------|
| What to test | `docs/testing/cases/<domain>/TC-XXX-*.md` |
| What was tested (per cycle) | `docs/testing/runs/<YYYY-MM-DD>-<cycle-id>.md` (from `templates/test-execution-run.md`) |
| Live execution status | GitHub Project board (one per cycle) |
| Bugs found | GitHub issues from `bug-report.yml`, linked to test-case issues |

A cycle is **canonically tracked** on the project board. Markdown run logs are immutable evidence after close. Use `scripts/testing/seed-test-cycle.sh <cycle-id>` to provision a board and bulk-create issues from in-scope TC docs.
