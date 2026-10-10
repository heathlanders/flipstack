# Processing Pipeline Test Cases

## Document ID: TC-ITQ-003

**Domain:** Intake Queue
**Related Test Plan:** `docs/testing/plans/intake-queue-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-ITQ-003-01: ProcessQueueItem job dispatches on store

| Field | Value |
|-------|-------|
| **ID** | TC-ITQ-003-01 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Backend |
| **Automated** | Partial |
| **Test File** | `tests/Feature/IntakeQueue/ProcessQueueItemTest.php` |
| **Test Method** | Partial coverage of job dispatch |

**Preconditions:**

1. Organization fixtures set up with team and user
2. User is authenticated
3. Valid supplier exists in the organization
4. Queue is faked via `Queue::fake()` for dispatch assertion

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('intake-queue.store')` with valid data including photos, barcode, supplier_id | Response redirects to show page |
| 2 | Assert `ProductIntakeQueue` record created with `processing_status = 'pending'` and `ai_analysis_status = 'pending'` | Record persisted |
| 3 | Assert `ProcessQueueItem::class` dispatched exactly once | `Queue::assertPushed(ProcessQueueItem::class, 1)` |
| 4 | Assert dispatched job received the created queue item | `$job->queueItem->id === $createdItem->id` |
| 5 | Assert job configuration: `$timeout = 180`, `$tries = 3`, `$backoff = [60, 300, 900]` | Job configured for retry with exponential backoff |

**Test Data:**

```php
Queue::fake();

$supplier = Supplier::factory()->create([
    'organization_id' => $this->organization->id,
    'is_active' => true,
]);

$storeData = [
    'supplier_id' => $supplier->id,
    'product_category' => 'network_equipment',
    'product_type' => 'complete_unit',
    'photos' => [UploadedFile::fake()->image('switch.jpg')],
    'primary_identifier' => '0123456789012',
    'condition' => 'USED_VERY_GOOD',
    'quick_quantity' => 1,
];
```

**Postconditions:**

- Queue item created in database
- `ProcessQueueItem` job pushed to queue (not yet executed due to `Queue::fake()`)
- Job will execute asynchronously when queue worker processes it

**Notes:**

- The `ProcessQueueItem` job is dispatched at the end of the `store()` method (line 476)
- The job uses `SerializesModels` trait, so the `ProductIntakeQueue` model is serialized by ID
- Job timeout of 180 seconds accommodates AI research API calls
- Backoff array `[60, 300, 900]` means retries at 1 minute, 5 minutes, 15 minutes

---

### TC-ITQ-003-02: Barcode validation step identifies barcode type (UPC, EAN, MPN)

| Field | Value |
|-------|-------|
| **ID** | TC-ITQ-003-02 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Backend |
| **Automated** | No |
| **Test File** | `tests/Feature/IntakeQueue/ProcessQueueItemTest.php` (needed) |
| **Test Method** | `test_barcode_validation_identifies_type()` (needed) |

**Preconditions:**

1. `ProcessQueueItem` job executing for a queue item with a barcode
2. `BarcodeValidationService` available (injected via `handle()` parameters)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Job processes item with UPC barcode `'012345678901'` (12 digits) | `BarcodeValidationService::validate()` called |
| 2 | Assert barcode validation returns `['valid' => true, 'type' => 'UPC', 'formatted' => '012345678901']` | UPC identified correctly |
| 3 | Job processes item with EAN barcode `'4006381333931'` (13 digits) | Validation called |
| 4 | Assert barcode validation returns `['valid' => true, 'type' => 'EAN']` | EAN identified correctly |
| 5 | Job processes item with invalid barcode `'NOTABARCODE'` | Validation returns `['valid' => false]` or throws exception |
| 6 | Assert barcode validation failure is caught and logged as warning | Non-blocking error handling |
| 7 | Assert `'barcode_validation'` added to `sourcesUsed` array on success | Source tracking updated |

**Test Data:**

```php
// UPC barcode
$upcItem = ProductIntakeQueue::factory()->create([
    'barcode' => '012345678901',
    'organization_id' => $this->organization->id,
    'team_id' => $this->team->id,
    'captured_by' => $this->user->id,
]);

// EAN barcode
$eanItem = ProductIntakeQueue::factory()->create([
    'barcode' => '4006381333931',
    'organization_id' => $this->organization->id,
    'team_id' => $this->team->id,
    'captured_by' => $this->user->id,
]);
```

**Postconditions:**

- Barcode type identified and logged
- `sourcesUsed` array includes `'barcode_validation'` when successful
- Validation failure does not halt processing (caught in try-catch)
- Warning added to `warnings` array on validation failure

**Notes:**

- Barcode validation is Step 3 of the 8-step processing pipeline
- The validation is wrapped in a try-catch so failures are non-blocking
- UPC: 12 digits, EAN: 13 digits, MPN: variable format
- The `BarcodeValidationService` is injected as a parameter to `handle()`

---

### TC-ITQ-003-03: AI research returns title, description, category, price range; enriched_data populated

| Field | Value |
|-------|-------|
| **ID** | TC-ITQ-003-03 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Backend |
| **Automated** | No |
| **Test File** | `tests/Feature/IntakeQueue/ProcessQueueItemTest.php` (needed) |
| **Test Method** | `test_ai_research_populates_enriched_data()` (needed) |

**Preconditions:**

1. `ProcessQueueItem` job executing
2. `ProductResearchOrchestrator` mocked with `Http::fake()` or service mock
3. Queue item has barcode or MPN for research input

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Job calls `$orchestrator->researchWithStrategy($queueItem, $strategy, $options)` or `$orchestrator->research($queueItem)` | Research method invoked |
| 2 | Assert research returns `product_identity` with `brand`, `model`, `title`, `category` | Core identity fields populated |
| 3 | Assert research returns `product_details` with `description`, `key_features`, `specifications` | Detail fields populated |
| 4 | Assert research returns `market_pricing` with `recommended_list_price` | Pricing data available |
| 5 | Assert research returns `listing_optimization` with `suggested_title`, `primary_keywords` | Listing optimization data available |
| 6 | Assert `enriched_data` on queue item includes `research_data` and `merged_product_fields` keys | Full enrichment structure persisted |
| 7 | Assert `merged_product_fields` contains normalized `product_identity`, `product_details`, `listing_optimization`, `market_pricing` | Data transformer applied |

**Test Data:**

```php
// Mock the orchestrator response
$mockResearchData = [
    'product_identity' => [
        'title' => 'Cisco Catalyst 2960-X 48-Port PoE Switch',
        'brand' => 'Cisco',
        'model' => 'WS-C2960X-48FPD-L',
        'category' => 'Network Switches',
        'mpn' => 'WS-C2960X-48FPD-L',
    ],
    'product_details' => [
        'description' => '48-port Gigabit Ethernet PoE+ switch with 740W power budget.',
        'key_features' => ['48 PoE+ ports', '2 SFP+ uplinks', 'LAN Base'],
        'specifications' => ['Ports' => '48', 'PoE Budget' => '740W'],
    ],
    'market_pricing' => [
        'recommended_list_price' => 449.99,
        'price_range_low' => 350.00,
        'price_range_high' => 550.00,
    ],
    'listing_optimization' => [
        'suggested_title' => 'Cisco WS-C2960X-48FPD-L Catalyst 2960-X 48-Port PoE+ Switch',
        'primary_keywords' => ['Cisco', '2960X', 'PoE', 'switch', '48-port'],
    ],
    'data_source' => 'comprehensive_ebay',
    'search_method' => 'comprehensive_ebay',
];
```

**Postconditions:**

- `enriched_data` field on queue item contains full research results
- `merged_product_fields` structure created by `createMergedProductFields()`
- UPC/EAN sanitized (non-numeric characters stripped) via `sanitizeBarcode()`
- `data_sources` array tracks which sources contributed data

**Notes:**

- Step 4 of the 8-step pipeline is the core product research
- The `determineResearchStrategy()` method selects one of: `supplement_existing`, `comprehensive`, `barcode_focused`, `basic`
- Strategy selection based on: existing product data, MPN/brand availability, barcode presence
- The orchestrator may use `researchWithStrategy()` (enhanced) or `research()` (basic) depending on method availability

---

### TC-ITQ-003-04: Confidence score calculated from data completeness

| Field | Value |
|-------|-------|
| **ID** | TC-ITQ-003-04 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Backend |
| **Automated** | No |
| **Test File** | `tests/Feature/IntakeQueue/ProcessQueueItemTest.php` (needed) |
| **Test Method** | `test_confidence_score_reflects_data_completeness()` (needed) |

**Preconditions:**

1. `ProcessQueueItem` job executing in Step 6 (scoring)
2. Research data available from Step 4
3. `ProductDataValidator` available for quality scoring

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | `calculateEnhancedScoring()` invoked with research data | Scoring pipeline runs |
| 2 | Base confidence: empty research -> 0.3 | Minimum confidence for no data |
| 3 | Base confidence: brand present -> +0.1 (total 0.6) | Brand adds confidence |
| 4 | Base confidence: brand + model present -> +0.1 (total 0.7) | Model adds confidence |
| 5 | Base confidence: brand + model + pricing -> +0.1 (total 0.8) | Pricing adds confidence |
| 6 | Base confidence: brand + model + pricing + description -> +0.1 (total 0.9, capped) | Description adds confidence, max 0.9 |
| 7 | Completeness boost: completeness_score >= 60 adds up to 0.10 | Completeness factor applied |
| 8 | Supplier boost: `reliability_boost` from supplier context added | Supplier reliability factored in |
| 9 | Assert final confidence capped at 1.0 | `min(1.0, base + completeness + supplier)` |
| 10 | Assert `confidence_breakdown` stored with `base_confidence`, `completeness_boost`, `supplier_boost`, `final_confidence` | Transparency in scoring |

**Test Data:**

```php
// Full data -> high confidence
$fullResearchData = [
    'product_identity' => ['brand' => 'Cisco', 'model' => 'C2960X'],
    'product_details' => ['description' => 'Enterprise switch.'],
    'market_pricing' => ['recommended_list_price' => 449.99],
];
// Expected: base 0.9 (0.5 + 0.1 brand + 0.1 model + 0.1 pricing + 0.1 description)

// Minimal data -> low confidence
$minimalResearchData = [
    'product_identity' => [],
    'product_details' => [],
    'market_pricing' => [],
];
// Expected: base 0.5 (non-empty array but no fields)

// Empty data -> minimum confidence
// Expected: base 0.3
```

**Postconditions:**

- `ai_confidence_score` persisted as a decimal value between 0.0 and 1.0
- `confidence_breakdown` stored as JSON array with component scores
- `quality_score` calculated via `ProductDataValidator::calculateQualityScore()` (0-100 scale)
- `completeness_score` derived from `completeness_data` field
- Quality score boosted by up to 20% based on completeness

**Notes:**

- Base confidence starts at 0.5 for non-empty research, 0.3 for empty
- Each data point (brand, model, pricing, description) adds 0.1
- Base confidence capped at 0.9 before boosts
- Completeness boost formula: `(completeness_score - 60) / 400` when score >= 60, max 0.10
- On scoring failure, defaults to confidence 0.5, quality 50

---

### TC-ITQ-003-05: Background removal service processes photos

| Field | Value |
|-------|-------|
| **ID** | TC-ITQ-003-05 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Backend |
| **Automated** | No |
| **Test File** | `tests/Feature/IntakeQueue/ProcessQueueItemTest.php` (needed) |
| **Test Method** | `test_photo_processing_with_background_removal()` (needed) |

**Preconditions:**

1. `ProcessQueueItem` job executing in Step 5 (photo processing)
2. Queue item has `photos` array with at least one path
3. `BackgroundRemovalService` is available (`isAvailable()` returns true)
4. Background removal service reachable at configured URL

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | `processPhotos()` invoked with `BackgroundRemovalService` | Photo processing initiated |
| 2 | Assert `bgRemoval->isAvailable()` checked before processing | Service availability verified |
| 3 | Assert `processMultipleWithCropping()` called with photos and cropping option | Enhanced processing used when available |
| 4 | Assert cropping option determined by condition: `NEW` -> `'tight'`, `FOR_PARTS_OR_NOT_WORKING` -> `'padded'`, default -> `'default'` | Condition-based cropping presets |
| 5 | Assert processed photo paths replace original paths in queue item | `photos` field updated with processed paths |
| 6 | Assert `'background_removal'` added to `sourcesUsed` | Source tracking updated |
| 7 | Assert service unavailable case: original photos preserved, no error thrown | Graceful degradation |

**Test Data:**

```php
$queueItem = ProductIntakeQueue::factory()->create([
    'photos' => ['queue-photos/photo1.jpg', 'queue-photos/photo2.jpg'],
    'condition' => 'USED_GOOD',
    'organization_id' => $this->organization->id,
    'team_id' => $this->team->id,
    'captured_by' => $this->user->id,
]);

// Mock BackgroundRemovalService
Http::fake([
    'bg.flipstack.app/*' => Http::response(['processed' => true], 200),
]);
```

**Postconditions:**

- Photos processed with background removed (when service available)
- Cropping option applied based on product condition
- Original photos preserved if service unavailable or fails
- Warning added to `warnings` array on failure

**Notes:**

- The background removal service runs on the local dev machine (GPU), reachable via Cloudflare Tunnel at `https://bg.flipstack.app`
- In production, `BACKGROUND_REMOVAL_URL` env var must point to the tunnel URL
- This is Step 5 of the 8-step pipeline
- Photo processing failure is non-blocking (caught in try-catch)

---

### TC-ITQ-003-06: Shipping class estimated from dimensions/weight

| Field | Value |
|-------|-------|
| **ID** | TC-ITQ-003-06 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Backend |
| **Automated** | No |
| **Test File** | `tests/Feature/IntakeQueue/ProcessQueueItemTest.php` (needed) |
| **Test Method** | `test_shipping_class_estimated_from_measurements()` (needed) |

**Preconditions:**

1. `ProcessQueueItem` job executing in Step 2 (physical measurements analysis)
2. Queue item has `weight_lbs` set (and optionally dimensions)
3. `ShippingClassService` available

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | `analyzePhysicalMeasurements()` invoked with `ShippingClassService` | Analysis runs |
| 2 | Assert standard-weight item (<= threshold) classified as `'standard'` | Normal shipping class |
| 3 | Assert heavy/large item classified as `'oversized'` or `'freight'` | Appropriate class for dimensions |
| 4 | Assert `estimated_shipping_cost` calculated from dimensions and class | Cost estimate populated |
| 5 | Assert fragile detection checks `user_notes` for keywords: `fragile`, `glass`, `ceramic`, `delicate`, `handle with care`, `breakable` | Fragility flag set |
| 6 | Assert `suggested_shipping_class` and `estimated_shipping_cost` stored on queue item | Fields persisted |
| 7 | Assert item with no weight returns `shipping_class = 'standard'` and `estimated_cost = null` | Graceful handling of missing data |

**Test Data:**

```php
// Standard item
$standardItem = ProductIntakeQueue::factory()->create([
    'weight_lbs' => 5.50,
    'length_inches' => 12.00,
    'width_inches' => 8.00,
    'height_inches' => 4.00,
    'user_notes' => 'Working condition',
    'organization_id' => $this->organization->id,
    'team_id' => $this->team->id,
    'captured_by' => $this->user->id,
]);

// Fragile item
$fragileItem = ProductIntakeQueue::factory()->create([
    'weight_lbs' => 3.00,
    'user_notes' => 'Handle with care - glass components',
    'organization_id' => $this->organization->id,
    'team_id' => $this->team->id,
    'captured_by' => $this->user->id,
]);
```

**Postconditions:**

- `suggested_shipping_class` stored (if column exists)
- `estimated_shipping_cost` stored (if column exists)
- `enriched_data.shipping_data` contains full analysis results
- Fragile flag available in shipping data

**Notes:**

- This is Step 2 of the 8-step pipeline
- Uses `Schema::hasColumn()` checks before writing to optional columns
- Fragile detection uses case-insensitive keyword matching on `user_notes`
- The `ShippingClassService` is injected via `handle()` parameters

---

### TC-ITQ-003-07: Processing failure sets status=failed with error in enriched_data

| Field | Value |
|-------|-------|
| **ID** | TC-ITQ-003-07 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Backend |
| **Automated** | No |
| **Test File** | `tests/Feature/IntakeQueue/ProcessQueueItemTest.php` (needed) |
| **Test Method** | `test_processing_failure_sets_failed_with_error_data()` (needed) |

**Preconditions:**

1. `ProcessQueueItem` job executing
2. An exception occurs during processing (e.g., API timeout, service unavailable)
3. Job has exhausted all 3 retry attempts

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | `handle()` method throws an exception | Exception caught in outer try-catch |
| 2 | Catch block sets `ai_analysis_status = 'failed'` and `processing_status = 'failed'` | Immediate failure status |
| 3 | Assert `enriched_data` contains error details: `error` (message), `failed_at` (timestamp), `attempt` (attempt number) | Error context stored |
| 4 | Exception re-thrown for Laravel queue retry mechanism | `throw $e` executed |
| 5 | After all retries exhausted, `failed()` method invoked | Permanent failure handler |
| 6 | Assert `failed()` sets `enriched_data` with: `error`, `failed_permanently_at`, `total_attempts`, `supplier_id`, `manufacturer_part_number`, `completeness_score` | Extended debugging context in permanent failure |

**Test Data:**

```php
// Mock orchestrator to throw exception
$this->mock(ProductResearchOrchestrator::class, function ($mock) {
    $mock->shouldReceive('researchWithStrategy')->andThrow(new \RuntimeException('Gemini API timeout'));
    $mock->shouldReceive('research')->andThrow(new \RuntimeException('Gemini API timeout'));
});

$queueItem = ProductIntakeQueue::factory()->create([
    'processing_status' => 'pending',
    'ai_analysis_status' => 'pending',
    'processing_attempts' => 0,
    'supplier_id' => $supplier->id,
    'manufacturer_part_number' => 'WS-C2960X-48FPD-L',
    'organization_id' => $this->organization->id,
    'team_id' => $this->team->id,
    'captured_by' => $this->user->id,
]);
```

**Postconditions:**

- `processing_status = 'failed'` and `ai_analysis_status = 'failed'`
- `enriched_data` contains structured error information for debugging
- Error logged via `Log::error()` with queue item ID and context
- Item eligible for manual retry via TC-ITQ-002-08

**Notes:**

- The `handle()` catch block and `failed()` method both set the failed status
- `handle()` catch: sets status and re-throws (allows retry)
- `failed()`: permanent failure after all retries exhausted (sets `failed_permanently_at`)
- The `failed()` method also logs `supplier_id` and `manufacturer_part_number` for debugging

---

### TC-ITQ-003-08: Retry increments processing_attempts, max 3 retries with backoff [60, 300, 900]

| Field | Value |
|-------|-------|
| **ID** | TC-ITQ-003-08 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Backend |
| **Automated** | No |
| **Test File** | `tests/Feature/IntakeQueue/ProcessQueueItemTest.php` (needed) |
| **Test Method** | `test_retry_increments_attempts_with_backoff()` (needed) |

**Preconditions:**

1. `ProcessQueueItem` job configured with `$tries = 3` and `$backoff = [60, 300, 900]`
2. Job encounters a retryable failure

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Job executes for the first time | `processing_attempts` incremented from 0 to 1 |
| 2 | Assert `processing_attempts` on queue item equals 1 after first run | Attempt counter updated in `handle()` |
| 3 | Job fails and is retried after 60 seconds (first backoff) | Second execution begins |
| 4 | Assert `processing_attempts` incremented to 2 | Counter reflects second attempt |
| 5 | Job fails again and is retried after 300 seconds (second backoff) | Third execution begins |
| 6 | Assert `processing_attempts` incremented to 3 | Counter reflects third attempt |
| 7 | Job fails a third time, `failed()` method invoked (no more retries) | Permanent failure handler runs |
| 8 | Assert `total_attempts` in `enriched_data` equals 3 | Final attempt count stored |
| 9 | Assert job `$timeout = 180` (3 minutes per attempt) | Timeout configured correctly |

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

// Verify job properties
$job = new ProcessQueueItem($queueItem);
// $job->tries === 3
// $job->backoff === [60, 300, 900]
// $job->timeout === 180
```

**Postconditions:**

- After all retries: `processing_attempts = 3`, `processing_status = 'failed'`
- Backoff sequence: 60s -> 300s -> 900s (1min -> 5min -> 15min)
- Total maximum processing time: 3 attempts x 180s timeout + backoff delays = up to ~25 minutes
- `failed()` method called once after final failure

**Notes:**

- `processing_attempts` is incremented in `handle()` at the start of each execution (line 79)
- The `$backoff` array is a Laravel queue feature: `[60, 300, 900]` specifies seconds between retries
- The `$tries = 3` limits total attempts (including the initial run)
- Horizon's `supervisor-1` timeout (in `config/horizon.php`) must exceed the job's `$timeout` of 180s to prevent premature kills
- If Horizon kills the worker before `failed()` runs, the item may get stuck in "processing" state (see production deployment docs for `queue:reset-stuck` command)
