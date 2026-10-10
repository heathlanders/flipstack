# Intake Queue Test Plan

## Document ID: FLIPSTACK-TP-ITQ-001

**Version:** 1.0
**Last Updated:** 2026-04-15
**Status:** Approved

---

## 1. Overview

### 1.1 Scope

Product intake pipeline: barcode scan submission, AI-powered research (Gemini), 8-step processing, manual review/approval, product creation, and eBay/Shopify listing dispatch. Covers web and mobile capture paths.

### 1.2 Related Documentation

| Resource | Path |
|----------|------|
| Feature docs | `docs/INTAKE_QUEUE_SYSTEM.md`, `docs/PRODUCT_RESEARCH_ORCHESTRATOR.md` |
| Existing tests | `tests/Feature/IntakeQueue/` (3 files), `tests/Unit/Models/ProductIntakeQueueTest.php` |
| Test cases | `docs/testing/cases/intake-queue/` |

---

## 2. Features Under Test

| # | Feature | Controller | Route Prefix | Priority |
|---|---------|------------|-------------|----------|
| 1 | Queue listing & filtering | IntakeQueueController@index | `intake-queue` | P0 |
| 2 | Barcode scan + submission (web) | IntakeQueueController@store | `intake-queue` | P0 |
| 3 | Mobile capture | IntakeQueueController@mobileStore,scanBarcode | `mobile.intake` | P1 |
| 4 | Queue item detail | IntakeQueueController@show | `intake-queue` | P0 |
| 5 | Status transitions | IntakeQueueController@update,complete | `intake-queue` | P0 |
| 6 | AI research processing | ProcessQueueItem job | - | P0 |
| 7 | Publish to product | IntakeQueueController@publish | `intake-queue` | P0 |
| 8 | Publish with eBay listing | IntakeQueueController@publishWithEbayIntegration | `intake-queue` | P1 |
| 9 | Retry failed items | IntakeQueueController@retry,reresearch | `intake-queue` | P1 |
| 10 | Bulk approve | IntakeQueueController@bulkApprove | `intake-queue` | P2 |
| 11 | Shipping rate estimation | IntakeQueueShippingController | `intake-queue.shipping-rates` | P2 |
| 12 | Chrome extension research | IntakeQueueController@storeExtensionResearch | `api.intake-queue` | P2 |

---

## 3. Test Strategy

### 3.1 Feature Tests

| Workflow | Test File | Status |
|----------|-----------|--------|
| Controller routes | `tests/Feature/IntakeQueue/IntakeQueueControllerTest.php` | Exists |
| Job processing | `tests/Feature/IntakeQueue/ProcessQueueItemTest.php` | Exists |
| Status transitions | `tests/Feature/IntakeQueue/StatusTransitionsTest.php` | Exists |
| **Publish to product flow** | - | **Needed** |
| **Mobile capture endpoints** | - | **Needed** |
| **Shipping rate estimation** | - | **Needed** |

### 3.2 External Dependencies

| Dependency | Strategy |
|------------|----------|
| Gemini AI API | `Http::fake()` — mock research response |
| Background removal service | `Http::fake()` — mock image processing |
| EasyPost (shipping rates) | `Http::fake()` — mock rate responses |

---

## 4. Test Cases

| Test Case ID | Title | File |
|--------------|-------|------|
| TC-ITQ-001 | Queue Management | `cases/intake-queue/TC-ITQ-001-queue-management.md` |
| TC-ITQ-002 | Status Transitions | `cases/intake-queue/TC-ITQ-002-status-transitions.md` |
| TC-ITQ-003 | Processing Pipeline | `cases/intake-queue/TC-ITQ-003-processing-pipeline.md` |

---

## 5. Coverage Goals

| Metric | Current | Target |
|--------|---------|--------|
| Controller tests | Exists (partial) | Full endpoint coverage |
| Status machine | Exists | All valid + invalid transitions |
| AI processing | Exists (partial) | Mock responses for all research steps |
| Publish flow | Not tested | Product creation from queue item |
| Mobile capture | Not tested | Both scan and full capture paths |
