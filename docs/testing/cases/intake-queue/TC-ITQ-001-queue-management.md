# Queue Management Test Cases

## Document ID: TC-ITQ-001

**Domain:** Intake Queue
**Related Test Plan:** `docs/testing/plans/intake-queue-test-plan.md`
**Last Updated:** 2026-04-15

---

### TC-ITQ-001-01: View queue index with status/confidence filters and search

| Field | Value |
|-------|-------|
| **ID** | TC-ITQ-001-01 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | Partial |
| **Test File** | `tests/Feature/IntakeQueue/IntakeQueueControllerTest.php` |
| **Test Method** | Covers basic index load; filter and search paths not yet covered |

**Preconditions:**

1. Organization exists with team via `CreatesOrganizationFixtures` trait
2. User is authenticated and belongs to the team
3. Multiple `ProductIntakeQueue` records exist across statuses (`pending`, `in_progress`, `completed`, `failed`, `archived`) and confidence tiers (high >= 0.85, medium 0.60-0.84, low < 0.60)
4. At least one record has `barcode` or `user_notes` matching a known search term

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `GET` request to `route('intake-queue.index')` as authenticated user | Response status 200, Inertia renders `IntakeQueue/Index` |
| 2 | Assert response contains `items` prop with paginated collection (20 per page) | Paginator present with queue items ordered by `priority` desc, `created_at` asc |
| 3 | Assert response contains `stats` with `total`, `pending`, `in_progress`, `completed_today`, `high_confidence` counts | Stats computed from the team's queue items |
| 4 | Send `GET` with `?status=pending` | Only items with `processing_status = 'pending'` returned |
| 5 | Send `GET` with `?status=failed` | Only items with `processing_status = 'failed'` returned |
| 6 | Send `GET` with `?confidence=high` | Only items with `ai_confidence_score >= 0.85` returned |
| 7 | Send `GET` with `?confidence=low` | Only items with `ai_confidence_score < 0.60` or null returned |
| 8 | Send `GET` with `?search=WIDGET-123` | Items matching barcode, user_notes, or enriched_data text returned |
| 9 | Assert each item in response includes `id`, `barcode`, `processing_status`, `confidence_level`, `confidence_color`, `status_badge`, `category_label`, `type_label` | Computed attributes populated from model accessors |
| 10 | Assert `filters` prop echoes back applied filter values | `status`, `confidence`, `search` reflected |

**Test Data:**

```php
$this->setUpOrganizationFixtures();

$pendingItem = ProductIntakeQueue::factory()->create([
    'team_id' => $this->team->id,
    'organization_id' => $this->organization->id,
    'processing_status' => 'pending',
    'ai_confidence_score' => 0.92,
    'barcode' => 'WIDGET-123',
    'captured_by' => $this->user->id,
]);

$failedItem = ProductIntakeQueue::factory()->create([
    'team_id' => $this->team->id,
    'organization_id' => $this->organization->id,
    'processing_status' => 'failed',
    'ai_confidence_score' => 0.45,
    'captured_by' => $this->user->id,
]);
```

**Postconditions:**

- No database mutations; read-only endpoint
- Items scoped to user's current team via `forTeam()` scope
- Pagination preserves query string parameters

**Notes:**

- The controller handles users without a team (e.g., `super_admin`) by skipping the `forTeam()` scope
- Search uses `LIKE` on `barcode` and `user_notes`, plus `enriched_data::text LIKE` for JSON field searching
- Items are transformed through a closure in `paginate()->through()`, so raw model attributes are not exposed

---

### TC-ITQ-001-02: Submit new item via barcode scan (web)

| Field | Value |
|-------|-------|
| **ID** | TC-ITQ-001-02 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/IntakeQueue/IntakeQueueControllerTest.php` (needed) |
| **Test Method** | `test_store_creates_queue_item_and_dispatches_job()` (needed) |

**Preconditions:**

1. Organization fixtures set up with team and user
2. User is authenticated with appropriate permissions
3. At least one active `Supplier` exists in the organization
4. Organization has `sku_prefix` set (required for processing)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('intake-queue.store')` with required fields: `supplier_id`, `product_category`, `product_type`, `photos` (file upload), `primary_identifier`, `condition`, `quick_quantity` | Response redirects to `route('intake-queue.show', $queueItem)` with success flash |
| 2 | Assert `ProductIntakeQueue` record created in database | Record exists with `processing_status = 'pending'`, `ai_analysis_status = 'pending'` |
| 3 | Assert `captured_by` set to authenticated user's ID | Creator tracked |
| 4 | Assert `captured_at` set to approximately now | Capture timestamp recorded |
| 5 | Assert `photos` array contains storage paths for uploaded files | Photos stored under `queue-photos/` in public disk |
| 6 | Assert `ProcessQueueItem` job dispatched | `Queue::assertPushed(ProcessQueueItem::class)` |
| 7 | Assert optional fields (`barcode`, `brand`, `manufacturer_part_number`, `weight_lbs`, `condition_description`, `completeness_data`) persisted when provided | Nullable fields stored correctly |
| 8 | Assert `priority` calculated from input data via `calculateEnhancedPriority()` | Priority integer set on the record |

**Test Data:**

```php
$supplier = Supplier::factory()->create([
    'organization_id' => $this->organization->id,
    'is_active' => true,
]);

$data = [
    'supplier_id' => $supplier->id,
    'product_category' => 'computer_hardware',
    'product_type' => 'complete_unit',
    'photos' => [UploadedFile::fake()->image('photo1.jpg', 800, 600)],
    'primary_identifier' => '012345678901',
    'barcode' => '012345678901',
    'brand' => 'Dell',
    'manufacturer_part_number' => 'OPTIPLEX-7080',
    'condition' => 'USED_GOOD',
    'quick_quantity' => 1,
    'user_notes' => 'Tested and working',
    'lookup_attempted' => 'false',
];
```

**Postconditions:**

- One new `ProductIntakeQueue` record with `processing_status = 'pending'`
- Photos stored on `public` disk under `queue-photos/`
- `ProcessQueueItem` job queued for async processing
- `organization_id` and `team_id` set from authenticated user's context

**Notes:**

- Validation requires `product_category` to be one of `ProductIntakeQueue::PRODUCT_CATEGORIES` keys
- Validation requires `product_type` to be one of `ProductIntakeQueue::PRODUCT_TYPES` keys
- `condition` must be one of the 10 valid eBay-style condition values
- Photos: min 1, max 5, each max 10MB image
- The `capture_method` is auto-determined by `determineCaptureMethod()` based on input data

---

### TC-ITQ-001-03: Submit via mobile capture

| Field | Value |
|-------|-------|
| **ID** | TC-ITQ-001-03 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Mobile |
| **Automated** | No |
| **Test File** | `tests/Feature/IntakeQueue/MobileCaptureTest.php` (needed) |
| **Test Method** | `test_mobile_intake_creates_queue_item()` (needed) |

**Preconditions:**

1. User authenticated via Sanctum API token
2. Organization and team context available from token
3. Mobile app sends multipart form data with photos

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to mobile intake endpoint with Sanctum token in `Authorization` header | Response status 200/201 with JSON payload |
| 2 | Assert `ProductIntakeQueue` record created with `capture_method` indicating mobile origin | `captured_via` or `capture_method` reflects mobile source |
| 3 | Assert photos uploaded and stored | Photo paths persisted in `photos` array field |
| 4 | Assert `ProcessQueueItem` job dispatched | Job queued for processing |
| 5 | Assert response JSON includes created queue item ID | Client can track the submitted item |

**Test Data:**

```php
$data = [
    'barcode' => '9780134685991',
    'photos' => [UploadedFile::fake()->image('mobile_capture.jpg', 1200, 900)],
    'condition' => 'USED_VERY_GOOD',
    'quick_quantity' => 1,
    'supplier_id' => $supplier->id,
    'product_category' => 'consumer_electronics',
    'product_type' => 'complete_unit',
    'primary_identifier' => '9780134685991',
];
```

**Postconditions:**

- Queue item created and associated with mobile user's organization
- `ProcessQueueItem` job dispatched
- API response is JSON (not Inertia redirect)

**Notes:**

- Mobile endpoints use Sanctum token auth, not session-based auth
- The Flutter app uses the `mobile.intake` route prefix
- Photo uploads from mobile may be larger resolution; same 10MB limit applies
- Error responses should follow the API JSON format, not redirect

---

### TC-ITQ-001-04: View queue item detail with enriched data and AI analysis

| Field | Value |
|-------|-------|
| **ID** | TC-ITQ-001-04 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/IntakeQueue/IntakeQueueControllerTest.php` (needed) |
| **Test Method** | `test_show_loads_queue_item_with_ai_analysis()` (needed) |

**Preconditions:**

1. Organization fixtures set up
2. User is authenticated and authorized (`ProductIntakeQueuePolicy::view`)
3. A `ProductIntakeQueue` record exists with `processing_status = 'pending'` and populated `enriched_data`, `ai_analysis_id`
4. Related `ProductAiAnalysis` record exists

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `GET` to `route('intake-queue.show', $queueItem)` | Response status 200, Inertia renders `IntakeQueue/Show` |
| 2 | Assert response `queueItem` prop contains core fields: `id`, `barcode`, `photos`, `user_notes`, `processing_status`, `ai_confidence_score`, `confidence_level`, `enriched_data` | All fields populated from model |
| 3 | Assert response `aiAnalysis` prop contains: `detected_category`, `detected_brand`, `detected_model`, `ai_description`, `ai_specifications`, `confidence_score` | AI analysis data loaded from relationship |
| 4 | Assert `queueItem` includes classification fields: `product_category`, `category_label`, `product_type`, `type_label`, `classification_context` | Classification data populated |
| 5 | Assert viewing a `pending` item auto-transitions it to `in_progress` | `$queueItem->fresh()->processing_status` equals `'in_progress'` |
| 6 | Assert `processed_by` set to authenticated user on status transition | User tracked as processor |
| 7 | Assert `nextItemId` prop contains the ID of the next pending item (or null) | "Save and Next" navigation data provided |

**Test Data:**

```php
$queueItem = ProductIntakeQueue::factory()->create([
    'team_id' => $this->team->id,
    'organization_id' => $this->organization->id,
    'processing_status' => 'pending',
    'ai_analysis_status' => 'complete',
    'ai_confidence_score' => 0.87,
    'enriched_data' => [
        'research_data' => ['product_identity' => ['brand' => 'Dell', 'model' => 'OptiPlex 7080']],
        'merged_product_fields' => ['name' => 'Dell OptiPlex 7080 SFF Desktop'],
    ],
    'product_category' => 'computer_hardware',
    'product_type' => 'complete_unit',
    'captured_by' => $this->user->id,
]);
```

**Postconditions:**

- If item was `pending`, `processing_status` now `in_progress` and `processed_by` set
- If item was already `in_progress` or beyond, no status change
- `capturedBy` and `processedBy` relationships eagerly loaded

**Notes:**

- The `authorize('view', $queueItem)` call uses `ProductIntakeQueuePolicy`
- The show method loads `capturedBy`, `processedBy`, and `aiAnalysis` relationships
- Photos are returned as full URLs via the `photo_urls` accessor
- The `nextItem` query finds the next `pending` item in the same team, ordered by priority

---

### TC-ITQ-001-05: Publish approved item creates Product record

| Field | Value |
|-------|-------|
| **ID** | TC-ITQ-001-05 |
| **Priority** | P0 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/IntakeQueue/PublishFlowTest.php` (needed) |
| **Test Method** | `test_publish_creates_product_and_marks_completed()` (needed) |

**Preconditions:**

1. Organization fixtures with SKU prefix set
2. User authenticated with `inventory.create` permission
3. A `ProductIntakeQueue` item in `ready` or `validated` status with enriched data
4. Valid `category_id` exists in the organization

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('intake-queue.publish', $queueItem)` with product fields: `name`, `sku`, `category_id`, `description`, `cost_basis`, `list_price`, `quantity`, `condition` | Response redirects with success flash |
| 2 | Assert new `Product` record created in database | Product exists with UUID primary key, correct `organization_id` |
| 3 | Assert product fields populated from submission | `name`, `sku`, `cost_price`, `selling_price`, `category_id`, `condition` match input |
| 4 | Assert queue item `processing_status` updated to `completed` | `$queueItem->fresh()->processing_status === 'completed'` |
| 5 | Assert queue item `product_id` linked to created product | Foreign key reference established |
| 6 | Assert queue item `processed_by` and `processed_at` set | Audit trail recorded |
| 7 | Assert product images created from queue item photos | `ProductImage` records created with `product_id` linking to new product |

**Test Data:**

```php
$queueItem = ProductIntakeQueue::factory()->create([
    'team_id' => $this->team->id,
    'organization_id' => $this->organization->id,
    'processing_status' => 'ready',
    'ai_analysis_status' => 'complete',
    'photos' => ['queue-photos/photo1.jpg', 'queue-photos/photo2.jpg'],
    'captured_by' => $this->user->id,
]);

$publishData = [
    'name' => 'Dell OptiPlex 7080 SFF Desktop',
    'sku' => 'TEST-DELL-7080-001',
    'category_id' => $category->id,
    'description' => 'Refurbished Dell desktop computer.',
    'cost_basis' => 120.00,
    'list_price' => 249.99,
    'quantity' => 1,
    'condition' => 'USED_GOOD',
];
```

**Postconditions:**

- New `Product` record with UUID PK and `organization_id` matching user's org
- Queue item marked `completed` with `product_id` set
- `ProductImage` records created from queue photos
- Product `stock_quantity` and `available_quantity` set from `quantity` input

**Notes:**

- The `publish` method is at line 615 of `IntakeQueueController`
- SKU validation ensures uniqueness within the organization via `unique:products,sku`
- Product uses `HasUuids` trait so the ID is auto-generated as UUID v4
- The `markAsCompleted()` model method handles the status transition

---

### TC-ITQ-001-06: Publish with eBay integration creates Product and dispatches CreateEbayListingJob

| Field | Value |
|-------|-------|
| **ID** | TC-ITQ-001-06 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/IntakeQueue/PublishFlowTest.php` (needed) |
| **Test Method** | `test_publish_with_ebay_creates_product_and_dispatches_listing_job()` (needed) |

**Preconditions:**

1. All preconditions from TC-ITQ-001-05
2. Active eBay `IntegrationCredential` exists for the organization (`platform = 'ebay'`, `is_active = true`)
3. Valid eBay category resolved (either from `preliminary_ebay_category` in enriched_data or via `EbayCategoryService`)

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('intake-queue.publish-with-ebay', $queueItem)` with product fields plus eBay-specific data (condition, pricing) | Response redirects with success flash |
| 2 | Assert `Product` record created (same as TC-ITQ-001-05 steps 2-7) | Product and images created |
| 3 | Assert `CreateEbayListingJob` dispatched with product ID | `Queue::assertPushed(CreateEbayListingJob::class)` |
| 4 | Assert queue item status set appropriately (may remain `completed` until listing confirmation) | Status reflects publish-with-eBay path |
| 5 | Assert condition validation passes against eBay category's valid conditions | No `condition_validation_error` stored |

**Test Data:**

```php
IntegrationCredential::factory()->create([
    'organization_id' => $this->organization->id,
    'platform' => 'ebay',
    'is_active' => true,
]);

$publishData = [
    'name' => 'Dell OptiPlex 7080 SFF Desktop',
    'sku' => 'TEST-DELL-7080-002',
    'category_id' => $category->id,
    'condition' => 'USED_GOOD',
    'list_price' => 249.99,
    'quantity' => 1,
    // ... other required fields
];
```

**Postconditions:**

- Product created and linked to queue item
- `CreateEbayListingJob` queued for async eBay listing creation
- On successful listing, the job updates queue item `processing_status` to `listed_on_ebay` and sets `ebay_listing_id`

**Notes:**

- The `publishWithEbayIntegration` method is at line 1556 of `IntakeQueueController`
- Condition validation uses `validateConditionForDispatch()` which checks the eBay category's allowed conditions
- If condition is invalid and no within-family fallback exists, the request is rejected with an error
- The `CreateEbayListingJob` handles OAuth, Offer API calls, and error handling independently

---

### TC-ITQ-001-07: Retry failed item resets status to pending and re-dispatches ProcessQueueItem

| Field | Value |
|-------|-------|
| **ID** | TC-ITQ-001-07 |
| **Priority** | P1 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/IntakeQueue/IntakeQueueControllerTest.php` (needed) |
| **Test Method** | `test_retry_resets_failed_item_and_dispatches_job()` (needed) |

**Preconditions:**

1. Organization fixtures set up
2. User authenticated and authorized
3. A `ProductIntakeQueue` item with `processing_status = 'failed'` and `ai_analysis_status = 'failed'`

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('intake-queue.retry', $queueItem)` | Response redirects with success flash |
| 2 | Assert `processing_status` reset to `pending` | `$queueItem->fresh()->processing_status === 'pending'` |
| 3 | Assert `ai_analysis_status` reset to `pending` | Ready for re-processing |
| 4 | Assert `ProcessQueueItem` job dispatched | `Queue::assertPushed(ProcessQueueItem::class)` |
| 5 | Assert previous error data in `enriched_data` is preserved or cleared as appropriate | Error context available for debugging if needed |

**Test Data:**

```php
$failedItem = ProductIntakeQueue::factory()->create([
    'team_id' => $this->team->id,
    'organization_id' => $this->organization->id,
    'processing_status' => 'failed',
    'ai_analysis_status' => 'failed',
    'enriched_data' => [
        'error' => 'API timeout during research',
        'failed_at' => now()->subHours(2)->toIso8601String(),
    ],
    'processing_attempts' => 1,
    'captured_by' => $this->user->id,
]);
```

**Postconditions:**

- Queue item status reset to `pending`
- New `ProcessQueueItem` job dispatched (will increment `processing_attempts` on execution)
- The job's `$tries = 3` and `$backoff = [60, 300, 900]` apply to the new dispatch

**Notes:**

- The `retry` method is at line 817 of `IntakeQueueController`
- There is also a `reresearch` method at line 842 that specifically re-triggers AI research
- The `ProcessQueueItem` job has max 3 tries with exponential backoff [60s, 300s, 900s]
- Retrying an item that has already exhausted all attempts will still work (retry resets the attempt context for the new job dispatch)

---

### TC-ITQ-001-08: Bulk approve multiple items

| Field | Value |
|-------|-------|
| **ID** | TC-ITQ-001-08 |
| **Priority** | P2 |
| **Type** | Feature |
| **Platform** | Web |
| **Automated** | No |
| **Test File** | `tests/Feature/IntakeQueue/BulkOperationsTest.php` (needed) |
| **Test Method** | `test_bulk_approve_transitions_multiple_items()` (needed) |

**Preconditions:**

1. Organization fixtures set up
2. User authenticated with appropriate permissions
3. Multiple `ProductIntakeQueue` items in `ready` or `validated` status within the same team

**Test Steps:**

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send `POST` to `route('intake-queue.bulk-approve')` with `item_ids` array containing 3+ queue item IDs | Response redirects with success flash indicating count |
| 2 | Assert all specified items have their status updated | Items transitioned to approved/validated state |
| 3 | Assert items from other teams are not affected | Team scoping enforced |
| 4 | Assert response indicates the number of items processed | Flash message includes count (e.g., "3 items approved") |
| 5 | Assert items not in an approvable state are skipped with appropriate feedback | Already-completed or failed items excluded from bulk operation |

**Test Data:**

```php
$readyItems = ProductIntakeQueue::factory()->count(3)->create([
    'team_id' => $this->team->id,
    'organization_id' => $this->organization->id,
    'processing_status' => 'ready',
    'ai_confidence_score' => 0.90,
    'captured_by' => $this->user->id,
]);

$otherTeamItem = ProductIntakeQueue::factory()->create([
    'team_id' => $otherTeam->id,
    'organization_id' => $this->organization->id,
    'processing_status' => 'ready',
    'captured_by' => $otherUser->id,
]);

$bulkData = [
    'item_ids' => $readyItems->pluck('id')->toArray(),
];
```

**Postconditions:**

- All specified items updated in a single operation
- Items from other teams remain unchanged
- Flash message reflects actual count of items processed

**Notes:**

- The `bulkApprove` method is at line 906 of `IntakeQueueController`
- Bulk operations should be wrapped in a database transaction
- Consider rate limiting for large bulk operations
- The UI typically selects items via checkboxes on the index page
