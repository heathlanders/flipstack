# Status Transitions Test Cases

## Document ID: TC-ITQ-002

**Domain:** Intake Queue
**Related Test Plan:** `docs/testing/plans/intake-queue-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-ITQ-002-01: pending -> in_progress (ProcessQueueItem starts)

| Field | Value |
|-------|-------|
| **ID** | TC-ITQ-002-01 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | Yes |
| **Test File** | `tests/Feature/IntakeQueue/StatusTransitionsTest.php` |
| **Test Method** | Covers this transition |

**Preconditions:**

1. `ProductIntakeQueue` record with `processing_status = 'pending'`
2. `ProcessQueueItem` job dispatched and begins execution
3. Organization has `sku_prefix` set (required for processing)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | `ProcessQueueItem::handle()` begins execution | Job starts processing |
| 2 | Assert `ai_analysis_status` updated to `'processing'` | Processing flag set immediately |
| 3 | Assert `ai_started_at` set to approximately now | Processing start timestamp recorded |
| 4 | Assert `processing_attempts` incremented by 1 | Attempt counter tracks retries |
| 5 | Alternatively: user views queue item via `show()` endpoint | `markAsInProgress($user)` called |
| 6 | Assert `processing_status` set to `'in_progress'` via `markAsInProgress()` | Status transitioned |
| 7 | Assert `processed_by` set to the user who triggered the view | Processor tracked |

**Test Data:**

```php
$queueItem = ProductIntakeQueue::factory()->create([
    'processing_status' => 'pending',
    'ai_analysis_status' => 'pending',
    'processing_attempts' => 0,
    'organization_id' => $this->organization->id,
    'team_id' => $this->team->id,
    'captured_by' => $this->user->id,
]);
```

**Postconditions:**

- `processing_status` is `'in_progress'` (via show endpoint) or `ai_analysis_status` is `'processing'` (via job)
- `processing_attempts` incremented
- Timestamp fields set

**Notes:**

- Two paths trigger this transition: (1) `ProcessQueueItem` job sets `ai_analysis_status = 'processing'` and (2) the `show()` controller method calls `markAsInProgress()` which sets `processing_status = 'in_progress'`
- The job does not directly set `processing_status = 'in_progress'`; it sets `ai_analysis_status = 'processing'` and later determines the final `processing_status` based on scoring results

---

### TC-ITQ-002-02: in_progress -> ready (processing complete, confidence score set)

| Field | Value |
|-------|-------|
| **ID** | TC-ITQ-002-02 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Backend |
| **Automated** | Yes |
| **Test File** | `tests/Feature/IntakeQueue/StatusTransitionsTest.php` |
| **Test Method** | Covers this transition |

**Preconditions:**

1. `ProductIntakeQueue` record with `ai_analysis_status = 'processing'`
2. `ProcessQueueItem` job completes successfully
3. Confidence score >= 0.8 and quality score >= 80

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | `ProcessQueueItem` completes all 8 processing steps | Research, scoring, and analysis complete |
| 2 | `determineProcessingStatus()` evaluates confidence and quality scores | Status determined based on thresholds |
| 3 | Assert `processing_status` set to `'ready_for_listing'` when confidence >= 0.8 and quality >= 80 | High confidence items are ready immediately |
| 4 | Assert `ai_analysis_status` set to `'complete'` | AI processing finished |
| 5 | Assert `ai_confidence_score` set to calculated value | Confidence persisted as decimal |
| 6 | Assert `ai_completed_at` timestamp set | Processing end time recorded |
| 7 | Assert `enriched_data` populated with research results, merged fields, shipping data | Full enrichment data stored |

**Test Data:**

```php
// Mock orchestrator to return high-confidence research data
$researchData = [
    'product_identity' => [
        'brand' => 'Dell',
        'model' => 'OptiPlex 7080',
        'title' => 'Dell OptiPlex 7080 SFF Desktop',
        'category' => 'Desktops & All-In-Ones',
    ],
    'product_details' => [
        'description' => 'Business desktop computer with Intel Core i7.',
        'key_features' => ['Intel Core i7', '16GB RAM', '512GB SSD'],
    ],
    'market_pricing' => [
        'recommended_list_price' => 299.99,
    ],
    'listing_optimization' => [
        'suggested_title' => 'Dell OptiPlex 7080 SFF Desktop Intel Core i7 16GB 512GB SSD',
    ],
    'data_source' => 'comprehensive_ebay',
];
```

**Postconditions:**

- `processing_status` is one of: `ready_for_listing`, `needs_minor_review`, `needs_review`, `needs_major_review`
- `ai_analysis_status = 'complete'`
- `enriched_data` fully populated
- `ProductAiAnalysis` record created and linked via `ai_analysis_id`

**Notes:**

- The `determineProcessingStatus()` method uses these thresholds:
  - confidence >= 0.8 AND quality >= 80: `ready_for_listing`
  - confidence >= 0.6 AND quality >= 60: `needs_minor_review`
  - confidence >= 0.4: `needs_review`
  - below 0.4: `needs_major_review`
- The model scopes `ready()` and `validated()` check for `processing_status = 'ready'` and `'validated'` respectively, which differs from `ready_for_listing`

---

### TC-ITQ-002-03: ready -> validated (manual review passed)

| Field | Value |
|-------|-------|
| **ID** | TC-ITQ-002-03 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/IntakeQueue/StatusTransitionsTest.php` (needed) |
| **Test Method** | `test_ready_to_validated_on_manual_review()` (needed) |

**Preconditions:**

1. `ProductIntakeQueue` item with `processing_status` in a ready state (e.g., `ready`, `ready_for_listing`)
2. User authenticated with review permissions

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | User reviews enriched data on the show page | Data displayed correctly |
| 2 | User confirms data accuracy (e.g., verifies brand, model, pricing) | Review decision made |
| 3 | Send `POST`/`PUT` to validate the item | Status transitioned |
| 4 | Assert `processing_status` set to `'validated'` | Item marked as reviewed and approved |
| 5 | Assert `processed_by` updated to the reviewing user | Reviewer tracked |

**Test Data:**

```php
$readyItem = ProductIntakeQueue::factory()->create([
    'processing_status' => 'ready',
    'ai_analysis_status' => 'complete',
    'ai_confidence_score' => 0.72,
    'organization_id' => $this->organization->id,
    'team_id' => $this->team->id,
    'captured_by' => $this->user->id,
]);
```

**Postconditions:**

- `processing_status = 'validated'`
- Item now eligible for publish via `isReadyForListing()` which checks for `ready` or `validated`

**Notes:**

- The `isReadyForListing()` method returns true for both `ready` and `validated` statuses
- Validation is a manual step where a human confirms AI-generated data is correct
- This step is optional for high-confidence items but recommended for medium-confidence

---

### TC-ITQ-002-04: validated -> completed (publish to product)

| Field | Value |
|-------|-------|
| **ID** | TC-ITQ-002-04 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/IntakeQueue/StatusTransitionsTest.php` (needed) |
| **Test Method** | `test_validated_to_completed_on_publish()` (needed) |

**Preconditions:**

1. `ProductIntakeQueue` item with `processing_status = 'validated'` (or `ready`)
2. User submits publish form with product details

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | User triggers publish action via `route('intake-queue.publish', $queueItem)` | Publish flow initiated |
| 2 | `Product` record created in database | New product with UUID PK |
| 3 | `markAsCompleted($product, $user)` called on queue item | Status transition method invoked |
| 4 | Assert `processing_status` set to `'completed'` | Final status for standard publish |
| 5 | Assert `product_id` set to the new product's UUID | Queue item linked to created product |
| 6 | Assert `processed_by` set to the publishing user's ID | Publisher tracked |
| 7 | Assert `processed_at` set to approximately now | Completion timestamp recorded |

**Test Data:**

```php
$validatedItem = ProductIntakeQueue::factory()->create([
    'processing_status' => 'validated',
    'ai_analysis_status' => 'complete',
    'organization_id' => $this->organization->id,
    'team_id' => $this->team->id,
    'captured_by' => $this->user->id,
]);
```

**Postconditions:**

- `processing_status = 'completed'`
- `product_id` references a valid `Product` record
- `processed_at` timestamp set
- The `markAsCompleted()` model method handles all field updates atomically

**Notes:**

- The `markAsCompleted()` method on `ProductIntakeQueue` model updates `processing_status`, `processed_by`, `processed_at`, and `product_id` in a single `update()` call
- This transition is triggered by the publish controller action, not by a status update endpoint

---

### TC-ITQ-002-05: completed -> listed_on_ebay (eBay listing created)

| Field | Value |
|-------|-------|
| **ID** | TC-ITQ-002-05 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Backend |
| **Automated** | No |
| **Test File** | `tests/Feature/IntakeQueue/StatusTransitionsTest.php` (needed) |
| **Test Method** | `test_completed_to_listed_on_ebay_after_listing_job()` (needed) |

**Preconditions:**

1. `ProductIntakeQueue` item with `processing_status = 'completed'` and `product_id` set
2. `CreateEbayListingJob` dispatched and completes successfully
3. Active eBay integration credential exists

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | `CreateEbayListingJob` executes successfully | eBay listing created via Offer API |
| 2 | Job updates queue item status upon success | Status transition triggered |
| 3 | Assert `processing_status` set to `'listed_on_ebay'` | eBay listing confirmed |
| 4 | Assert `ebay_listing_id` set on the queue item | eBay listing ID reference stored |
| 5 | Assert `EbayListing` record created and linked to the `Product` | Listing record in database |

**Test Data:**

```php
$completedItem = ProductIntakeQueue::factory()->create([
    'processing_status' => 'completed',
    'product_id' => $product->id,
    'organization_id' => $this->organization->id,
    'team_id' => $this->team->id,
    'captured_by' => $this->user->id,
]);
```

**Postconditions:**

- `processing_status = 'listed_on_ebay'`
- `ebay_listing_id` populated
- `EbayListing` record exists with `product_id` referencing the product
- The `scopeListedOnEbay()` scope now includes this item

**Notes:**

- This transition happens asynchronously via the `CreateEbayListingJob`
- If the listing job fails, the status transitions to `listing_failed` instead
- The eBay Offer API requires valid OAuth tokens from `IntegrationCredential`

---

### TC-ITQ-002-06: in_progress -> failed (processing error)

| Field | Value |
|-------|-------|
| **ID** | TC-ITQ-002-06 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Backend |
| **Automated** | Yes |
| **Test File** | `tests/Feature/IntakeQueue/StatusTransitionsTest.php` |
| **Test Method** | Covers failure transitions |

**Preconditions:**

1. `ProductIntakeQueue` item with `ai_analysis_status = 'processing'`
2. `ProcessQueueItem` job encounters an unrecoverable error (after exhausting retries)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | `ProcessQueueItem::handle()` throws an exception | Exception propagated |
| 2 | Job's `failed()` method invoked after all retries exhausted | Failure handler runs |
| 3 | Assert `ai_analysis_status` set to `'failed'` | AI status reflects failure |
| 4 | Assert `processing_status` set to `'failed'` | Processing status reflects failure |
| 5 | Assert `enriched_data` contains error details: `error` message, `failed_permanently_at` timestamp, `total_attempts` count | Error context preserved for debugging |
| 6 | Assert error includes contextual data: `supplier_id`, `manufacturer_part_number`, `completeness_score` | Debugging context available |

**Test Data:**

```php
$processingItem = ProductIntakeQueue::factory()->create([
    'processing_status' => 'pending',
    'ai_analysis_status' => 'pending',
    'processing_attempts' => 2,
    'organization_id' => $this->organization->id,
    'team_id' => $this->team->id,
    'captured_by' => $this->user->id,
]);
```

**Postconditions:**

- Both `ai_analysis_status` and `processing_status` set to `'failed'`
- `enriched_data` contains structured error information
- Item visible via `scopeListingFailed()` which checks for `failed` or `listing_failed`
- Item eligible for retry via TC-ITQ-001-07

**Notes:**

- The `failed()` method on `ProcessQueueItem` logs the error and updates the queue item
- The job has `$tries = 3` and `$backoff = [60, 300, 900]` (1min, 5min, 15min)
- Both `handle()` catch block and `failed()` method set the failed status, but `failed()` is the permanent failure handler
- The catch block in `handle()` re-throws the exception so Laravel's retry mechanism can work

---

### TC-ITQ-002-07: in_progress -> needs_review (low confidence score)

| Field | Value |
|-------|-------|
| **ID** | TC-ITQ-002-07 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Backend |
| **Automated** | No |
| **Test File** | `tests/Feature/IntakeQueue/StatusTransitionsTest.php` (needed) |
| **Test Method** | `test_low_confidence_results_in_needs_review()` (needed) |

**Preconditions:**

1. `ProductIntakeQueue` item being processed by `ProcessQueueItem`
2. Research returns minimal data resulting in low confidence

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | `ProcessQueueItem` completes with confidence score between 0.4 and 0.59 | Scoring calculates low confidence |
| 2 | `determineProcessingStatus()` evaluates scores | Status determined |
| 3 | Assert `processing_status` set to `'needs_review'` | Manual review required |
| 4 | Assert `ai_confidence_score` persisted at the low value | Score recorded for triage |
| 5 | Assert item visible via `scopeNeedsReview()` | Filterable on index page |

**Test Data:**

```php
// Mock orchestrator returning minimal research data
$minimalResearch = [
    'product_identity' => [
        'brand' => 'Unknown',
        // Missing model, category, pricing
    ],
    'product_details' => [
        'description' => 'Electronic component.',
    ],
    'market_pricing' => [],
    'listing_optimization' => [],
];
// Results in base confidence ~0.5, quality score ~55
```

**Postconditions:**

- `processing_status = 'needs_review'` (confidence >= 0.4 but < 0.6, or quality < 60)
- `processing_status = 'needs_major_review'` if confidence < 0.4
- Item requires manual enrichment before publishing
- `scopeNeedsReview()` returns this item

**Notes:**

- The `determineProcessingStatus()` method thresholds:
  - `needs_minor_review`: confidence >= 0.6 AND quality >= 60
  - `needs_review`: confidence >= 0.4
  - `needs_major_review`: confidence < 0.4
- Low confidence can result from: missing brand, missing model, no pricing data, incomplete research
- Confidence breakdown stored in `confidence_breakdown` field for transparency

---

### TC-ITQ-002-08: failed -> pending (retry)

| Field | Value |
|-------|-------|
| **ID** | TC-ITQ-002-08 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/IntakeQueue/StatusTransitionsTest.php` (needed) |
| **Test Method** | `test_failed_to_pending_on_retry()` (needed) |

**Preconditions:**

1. `ProductIntakeQueue` item with `processing_status = 'failed'`
2. User authenticated with update permissions

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('intake-queue.retry', $queueItem)` | Request accepted |
| 2 | Assert `processing_status` reset to `'pending'` | Status rolled back for re-processing |
| 3 | Assert `ai_analysis_status` reset to `'pending'` | AI status also reset |
| 4 | Assert `ProcessQueueItem` job dispatched | New processing attempt queued |
| 5 | Assert previous `enriched_data` error details are handled appropriately | Old error data cleared or archived |

**Test Data:**

```php
$failedItem = ProductIntakeQueue::factory()->create([
    'processing_status' => 'failed',
    'ai_analysis_status' => 'failed',
    'processing_attempts' => 3,
    'enriched_data' => [
        'error' => 'Gemini API rate limit exceeded',
        'failed_permanently_at' => now()->subDay()->toIso8601String(),
        'total_attempts' => 3,
    ],
    'organization_id' => $this->organization->id,
    'team_id' => $this->team->id,
    'captured_by' => $this->user->id,
]);
```

**Postconditions:**

- `processing_status = 'pending'`, `ai_analysis_status = 'pending'`
- New `ProcessQueueItem` job dispatched (fresh retry context)
- The job will increment `processing_attempts` again when it runs
- Item no longer visible via `scopeListingFailed()`, now visible via `scopePending()`

**Notes:**

- This is the reverse of TC-ITQ-002-06
- The retry resets status but preserves `processing_attempts` count from the model
- The new job dispatch creates a fresh retry context (3 tries, backoff [60, 300, 900])
- There is also a `reresearch` endpoint that specifically re-triggers only the AI research step

---

### TC-ITQ-002-09: Invalid: completed -> pending (should fail)

| Field | Value |
|-------|-------|
| **ID** | TC-ITQ-002-09 |
| **Priority** | P1 |
| **Type** | Negative |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/IntakeQueue/StatusTransitionsTest.php` (needed) |
| **Test Method** | `test_cannot_transition_completed_to_pending()` (needed) |

**Preconditions:**

1. `ProductIntakeQueue` item with `processing_status = 'completed'` and `product_id` set
2. A `Product` record exists linked via `product_id`

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Attempt to call retry on a completed item: `POST` to `route('intake-queue.retry', $completedItem)` | Request rejected |
| 2 | Assert response is 403 Forbidden, 422 Validation Error, or redirect with error flash | Operation denied |
| 3 | Assert `processing_status` remains `'completed'` | No state change |
| 4 | Assert `product_id` link preserved | Product relationship intact |
| 5 | Assert no `ProcessQueueItem` job dispatched | No unnecessary processing triggered |

**Test Data:**

```php
$completedItem = ProductIntakeQueue::factory()->create([
    'processing_status' => 'completed',
    'product_id' => $product->id,
    'processed_at' => now()->subHour(),
    'processed_by' => $this->user->id,
    'organization_id' => $this->organization->id,
    'team_id' => $this->team->id,
    'captured_by' => $this->user->id,
]);
```

**Postconditions:**

- No state change on the queue item
- No jobs dispatched
- The linked Product record remains unaffected

**Notes:**

- Completed items have already created a Product; retrying could create duplicates
- The controller or policy should guard against invalid transitions
- If the guard is not yet implemented, this test documents the expected behavior

---

### TC-ITQ-002-10: Invalid: listed_on_ebay -> pending (should fail)

| Field | Value |
|-------|-------|
| **ID** | TC-ITQ-002-10 |
| **Priority** | P1 |
| **Type** | Negative |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/IntakeQueue/StatusTransitionsTest.php` (needed) |
| **Test Method** | `test_cannot_transition_listed_on_ebay_to_pending()` (needed) |

**Preconditions:**

1. `ProductIntakeQueue` item with `processing_status = 'listed_on_ebay'`
2. `product_id` and `ebay_listing_id` both set
3. Active eBay listing exists on the marketplace

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Attempt to call retry on a listed item: `POST` to `route('intake-queue.retry', $listedItem)` | Request rejected |
| 2 | Assert response is 403 Forbidden, 422 Validation Error, or redirect with error flash | Operation denied |
| 3 | Assert `processing_status` remains `'listed_on_ebay'` | No state change |
| 4 | Assert `product_id` and `ebay_listing_id` preserved | References intact |
| 5 | Assert no `ProcessQueueItem` job dispatched | No processing triggered |
| 6 | Assert no eBay API calls made (listing not affected) | Marketplace listing undisturbed |

**Test Data:**

```php
$listedItem = ProductIntakeQueue::factory()->create([
    'processing_status' => 'listed_on_ebay',
    'product_id' => $product->id,
    'ebay_listing_id' => $ebayListing->id,
    'processed_at' => now()->subHours(2),
    'processed_by' => $this->user->id,
    'organization_id' => $this->organization->id,
    'team_id' => $this->team->id,
    'captured_by' => $this->user->id,
]);
```

**Postconditions:**

- No state change on the queue item
- No jobs dispatched
- eBay listing remains active and unmodified
- Product record remains linked

**Notes:**

- Items that have been listed on eBay represent a terminal state in the intake pipeline
- Re-processing would risk creating duplicate products and listings
- The `scopeListedOnEbay()` scope identifies items in this terminal state
- To modify the product or listing, users should use the Product Detail Hub or eBay listing management pages instead
