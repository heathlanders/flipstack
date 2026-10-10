# Product Intake Queue System

The Product Intake Queue is the core pipeline for processing captured products through AI analysis to marketplace listing. This document provides comprehensive technical documentation for the system.

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Status State Machine](#status-state-machine)
3. [Processing Pipeline](#processing-pipeline)
4. [Enriched Data Structure](#enriched-data-structure)
5. [Location & Bin Capture](#location--bin-capture)
6. [API Endpoints](#api-endpoints)
7. [Frontend Wizard Flow](#frontend-wizard-flow)
8. [Error Handling & Retry Logic](#error-handling--retry-logic)
9. [Debugging Commands](#debugging-commands)

## Location & Bin Capture

Intake captures the physical placement of a product so the pick/pack/ship pipeline downstream knows
where to find it. As of 2026-05-05 the columns are:

| Column | Type | Purpose |
|---|---|---|
| `quick_location_id` | UUID FK -> `inventory_locations.id` | Source of truth, joined to fulfillment routing |
| `quick_bin_id` | UUID FK -> `warehouse_bins.id` | Optional bin within the chosen location |
| `quick_location` | string(255) | Legacy freetext, kept for backward compat with old rows / mobile clients |

When `IntakeQueueController::publish` (or `complete`, `autoCreateProduct`, `publishWithEbay`) runs,
it calls `seedProductInventory($product, $qty, $locationId, $binId, $freetextLocation)` which writes
`inventory_levels.location_id` and `inventory_levels.bin_id`. That `inventory_levels` row is what the
pick path reads — see [`PRODUCT_LIFECYCLE_OVERVIEW.md`](PRODUCT_LIFECYCLE_OVERVIEW.md).

Resolution order in `seedProductInventory()`:
1. Explicit `$locationId` (FK from intake form)
2. Match `$freetextLocation` against `inventory_locations.code` or `name` within the org
3. Walk `warehouse_bins -> warehouse_zones -> location_id` from `$binId`
4. Org's first active `InventoryLocation` (logs an info warning so you can audit fallbacks)

**Do not** write to `Product.location` or `Product.warehouse_location_id`. The former does not
exist in schema; the latter is a legacy bigint FK to a deprecated `warehouse_locations` table.

---

## Architecture Overview

```
+-------------------+     +------------------+     +-------------------+
|   Mobile App      |     |   Web Interface  |     | Chrome Extension  |
|  (Flutter/Dart)   |     |   (Vue.js)       |     |  (eBay Research)  |
+--------+----------+     +--------+---------+     +---------+---------+
         |                         |                         |
         v                         v                         v
+------------------------------------------------------------------------+
|                     IntakeQueueController                               |
|  store() | mobileStore() | show() | complete() | publish() | retry()   |
+------------------------------------------------------------------------+
         |                         |
         v                         v
+-------------------+     +------------------------+
| ProductIntakeQueue|<--->| ProcessQueueItem (Job) |
|     (Model)       |     |  (Queue Worker)        |
+-------------------+     +------------------------+
         |                         |
         |    +--------------------|--------------------+
         |    |                    |                    |
         v    v                    v                    v
+----------+ +------------------+ +------------------+ +------------------+
|Suppliers | |ProductResearch   | |BackgroundRemoval | |ShippingClass    |
|          | |Orchestrator      | |Service           | |Service          |
+----------+ +------------------+ +------------------+ +------------------+
                    |
         +---------|----------+
         |         |          |
         v         v          v
    +-------+ +--------+ +--------+
    | eBay  | | Gemini | |Barcode |
    | API   | | AI     | |Lookup  |
    +-------+ +--------+ +--------+
```

### Key Components

| Component | Location | Purpose |
|-----------|----------|---------|
| Controller | `app/Http/Controllers/Product/IntakeQueueController.php` | API & web endpoints |
| Model | `app/Models/Catalog/ProductIntakeQueue.php` | Data model & scopes |
| Job | `app/Jobs/ProcessQueueItem.php` | Background processing |
| AI Analysis | `app/Models/Catalog/ProductAiAnalysis.php` | AI results storage |
| Wizard | `resources/js/Components/IntakeQueue/VerificationWizard.vue` | UI workflow |
| Seeder | `database/seeders/ProductIntakeQueueSeeder.php` | Test data |

---

## Status State Machine

The queue item progresses through the following states:

```
                            +-------------------+
                            |      pending      |
                            +--------+----------+
                                     |
              +----------------------+----------------------+
              |                                             |
              v                                             v
    +------------------+                        +-----------------------+
    |   in_progress    |                        | blocked_sku_prefix    |
    | (user opens item)|                        | (org needs SKU setup) |
    +--------+---------+                        +-----------------------+
             |
             v
    +------------------+
    |   processing     |
    | (job executing)  |
    +--------+---------+
             |
    +--------+--------+--------+--------+
    |        |        |        |        |
    v        v        v        v        v
+-------+ +-------+ +-------+ +-------+ +-------+
|ready  | |minor  | |needs  | |major  | |failed |
|for    | |review | |review | |review | |       |
|listing| |(60-80)| |(40-60)| |(<40%) | |       |
|>=80%  | |       | |       | |       | |       |
+---+---+ +---+---+ +---+---+ +---+---+ +---+---+
    |         |         |         |         |
    |    +----+---------+---------+         |
    |    |                                  |
    v    v                                  v
+----------+                          +----------+
|completed | <-- manual approval      | pending  | <-- retry
+----+-----+                          +----------+
     |
     +------------+------------+
     |            |            |
     v            v            v
+--------+  +----------+  +--------+
|listed  |  |listing   |  |archived|
|on_ebay |  |failed    |  |        |
+--------+  +----------+  +--------+
```

### Status Values

| Status | Description | Transitions To |
|--------|-------------|----------------|
| `pending` | Initial state, awaiting processing | `in_progress`, `blocked_sku_prefix` |
| `in_progress` | User has opened the item | `processing` |
| `processing` | Job is actively processing | `ready_for_listing`, `needs_*_review`, `failed` |
| `ready_for_listing` | High confidence (>=80%), ready to list | `completed`, `listed_on_ebay`, `listing_failed` |
| `needs_minor_review` | Medium confidence (60-80%) | `completed` |
| `needs_review` | Low-medium confidence (40-60%) | `completed` |
| `needs_major_review` | Very low confidence (<40%) | `completed` |
| `failed` | Processing error | `pending` (via retry) |
| `completed` | Product created, not yet listed | `archived`, `listed_on_ebay` |
| `listed_on_ebay` | Successfully listed on eBay | `archived` |
| `listing_failed` | eBay listing failed | `ready_for_listing` (retry) |
| `retry_scheduled` | eBay listing failed with a retryable error; job will retry with backoff | `in_progress` (on retry), `listing_failed` (after max retries) |
| `already_exists` | eBay listing conflict -- SKU already exists on eBay | - (manual resolution required) |
| `archived` | Final archived state | - |
| `blocked_sku_prefix` | Org missing SKU prefix config | `pending` (after org setup) |

---

## Processing Pipeline

The `ProcessQueueItem` job executes an 8-step pipeline:

### Step 1: Supplier Context Analysis
```php
$supplierContext = $this->analyzeSupplierContext($supplierPrefs);
// Returns: reliability_boost, priority_boost, supplier_name
```

### Step 2: Physical Measurements Analysis
```php
$shippingData = $this->analyzePhysicalMeasurements($shippingClassifier);
// Returns: shipping_class, estimated_cost, dimensions, fragile, oversized
```

> **Dimensions are manual, not AI-estimated.** `weight_lbs`, `length_inches`, `width_inches`, and `height_inches` are captured from the user at intake (Create form) -- they are NOT estimated from product photos. This step only classifies shipping and estimates cost from those entered values. They remain editable in wizard Step 4 (Package Dimensions panel) and persist via the `update` / `mobileUpdate` endpoints.

### Step 3: Barcode Validation (Optional)
```php
if ($this->queueItem->barcode) {
    $barcodeData = $barcodeValidator->validate($this->queueItem->barcode);
}
// Returns: valid, type, formatted
```

### Step 4: Product Research
```php
$strategy = $this->determineResearchStrategy();
// Strategies: supplement_existing, comprehensive, barcode_focused, basic

$researchData = $orchestrator->researchWithStrategy($this->queueItem, $strategy, [...]);
// Returns: product_identity, market_pricing, product_details, listing_optimization
```
Internally the orchestrator first runs `gatherMarketEvidence()` (a raw-identifier eBay search) and feeds those real listing titles into Stage 1 identification as grounding -- so identity comes from real marketplace data, not the model's parametric guess. See [PRODUCT_RESEARCH_ORCHESTRATOR.md](PRODUCT_RESEARCH_ORCHESTRATOR.md).

### Step 5: Photo Processing
```php
$processedPhotos = $this->processPhotos($bgRemoval, $warnings, $sourcesUsed);
// Cropping options based on condition: tight (NEW), padded (parts/repair), default
```

### Step 6: Enhanced Confidence & Quality Scoring
```php
$scoringResults = $this->calculateEnhancedScoring($researchData, $supplierContext, $validator);
// Returns: confidence_score, confidence_breakdown, quality_score, completeness_score
```

**Scoring Formula:**
```
base_confidence = 0.5 + (brand ? 0.1 : 0) + (model ? 0.1 : 0) + (pricing ? 0.1 : 0) + (description ? 0.1 : 0)
completeness_boost = (completeness_score - 60) / 400  // max 0.10 at 100%
final_confidence = min(1.0, base_confidence + completeness_boost + supplier_reliability_boost)
```

### Step 7: AI Analysis Record Creation
```php
$aiAnalysis = $this->createAiAnalysisRecord($researchData, $scoringResults, $supplierContext);
// Creates ProductAiAnalysis record with detected fields
```

### Step 8: Queue Item Update
```php
$this->updateQueueItemWithResults(...);
// Updates in transaction: status, enriched_data, photos, timestamps
```

**Status Determination:** `determineProcessingStatus($scoringResults, $researchData)`
```php
// Base mapping by score:
if ($confidence >= 0.8 && $quality >= 80) $status = 'ready_for_listing';
elseif ($confidence >= 0.6 && $quality >= 60) $status = 'needs_minor_review';
elseif ($confidence >= 0.4) $status = 'needs_review';
else $status = 'needs_major_review';

// Guardrail: cap at needs_review when the result is unverified, so a hallucinated
// item never auto-passes. requiresHumanReview($researchData) is true when:
//   - Stage 1 identification_confidence is low/none, OR
//   - product_identified === false, OR
//   - warnings contains 'no_relevant_images_found'
if (in ['ready_for_listing','needs_minor_review'] && requiresHumanReview($researchData))
    $status = 'needs_review';
```

---

## Enriched Data Structure

The `enriched_data` JSON column contains:

```json
{
  "research_data": {
    "product_identity": {
      "title": "NCR 7167 POS Terminal",
      "brand": "NCR",
      "model": "7167",
      "category": "POS Equipment",
      "mpn": "7167-1015-9001",
      "upc": "012345678901",
      "ean": null
    },
    "product_details": {
      "description": "Full description from research...",
      "key_features": ["Feature 1", "Feature 2"],
      "specifications": {
        "Weight": "15 lbs",
        "Dimensions": "12x10x8 inches"
      }
    },
    "market_pricing": {
      "recommended_list_price": 299.99,
      "ebay_sold_listings": [
        {"price": 285.00, "sold_date": "2024-01-15", "condition": "Used"}
      ],
      "ebay_sold_summary": {
        "average_sold": 275.00,
        "median_sold": 280.00,
        "highest_sold": 350.00,
        "lowest_sold": 200.00,
        "total_sold": 25
      }
    },
    "listing_optimization": {
      "suggested_title": "NCR 7167 POS Terminal - Tested Working",
      "primary_keywords": ["NCR", "7167", "POS"],
      "bullet_points": ["Bullet 1", "Bullet 2"]
    }
  },
  "merged_product_fields": {
    "product_identity": {...},
    "product_details": {...},
    "listing_optimization": {...},
    "market_pricing": {...}
  },
  "user_item_specifics": {
    "Brand": "Dell",
    "Model": "Latitude 5520",
    "Processor": "Intel Core i5-11th Gen"
  },
  "user_listing_data": {
    "listing_title": "...",
    "bullet_points": [],
    "keywords": [],
    "target_platform": "ebay"
  },
  "supplier_context": {
    "reliability_boost": 0.05,
    "priority_boost": false,
    "supplier_name": "Acme Suppliers"
  },
  "shipping_data": {
    "shipping_class": "standard",
    "estimated_cost": 12.50,
    "dimensions": {...},
    "fragile": false,
    "oversized": false
  },
  "sources_used": ["barcode_validation", "research", "background_removal"],
  "warnings": [],
  "confidence_breakdown": {
    "base_confidence": 0.7,
    "completeness_boost": 0.05,
    "supplier_boost": 0.05,
    "final_confidence": 0.8
  }
}
```

---

## API Endpoints

### Web Routes (18 endpoints)

| Method | Route | Controller Action | Purpose |
|--------|-------|------------------|---------|
| GET | `/intake-queue` | `index` | Dashboard with filters |
| GET | `/intake-queue/create` | `create` | Create form |
| POST | `/intake-queue` | `store` | Create queue item |
| GET | `/intake-queue/{id}` | `show` | Item detail view |
| PATCH | `/intake-queue/{id}` | `update` | Save draft (accepts `user_notes`, `quick_quantity`, `quick_location`, `quick_location_id`, `quick_bin_id`, `priority`, and dimensions `weight_lbs`/`length_inches`/`width_inches`/`height_inches`; emits `intake.updated` audit) |
| POST | `/intake-queue/{id}/complete` | `complete` | Create product |
| POST | `/intake-queue/{id}/save-draft` | `saveDraft` | Save wizard draft |
| POST | `/intake-queue/{id}/publish` | `publish` | Publish listing |
| POST | `/intake-queue/{id}/retry` | `retry` | Retry failed item |
| POST | `/intake-queue/{id}/reresearch` | `reresearch` | Re-run with corrections |
| POST | `/intake-queue/bulk-approve` | `bulkApprove` | Bulk approve items |
| GET | `/intake-queue/{id}/extension-status` | `extensionStatus` | Chrome extension data status |
| POST | `/intake-queue/{id}/extension-research` | `storeExtensionResearch` | Store extension data |
| POST | `/intake-queue/{id}/publish-ebay` | `publishWithEbayIntegration` | Publish to eBay |
| POST | `/intake-queue/{id}/create-ebay-listing` | `createEbayListing` | Direct eBay listing |
| POST | `/intake-queue/bulk-ebay-listings` | `bulkCreateEbayListings` | Bulk eBay listings |
| GET | `/intake-queue/product-lookup` | `productLookup` | Lookup existing products |
| GET | `/intake-queue/classification-stats` | `getClassificationStats` | Classification statistics |

### Mobile API Routes (6 endpoints)

| Method | Route | Controller Action | Purpose |
|--------|-------|------------------|---------|
| POST | `/api/intake-queue/mobile` | `mobileStore` | Quick mobile capture |
| POST | `/api/intake-queue/scan` | `scanBarcode` | Barcode scanning |
| GET | `/api/intake-queue/mobile/stats` | `getMobileQueueStats` | Dashboard stats |
| GET | `/api/intake-queue/mobile/my-captures` | `getMyCaptures` | User's captures |
| POST | `/api/intake-queue/{id}/photos` | `addPhotos` | Add photos to item |
| PATCH | `/api/intake-queue/{id}/mobile` | `mobileUpdate` | Update from mobile (same fields as web `update`, incl. dimensions; emits `intake.updated` audit, source=mobile) |

### Classification API Routes

| Method | Route | Controller Action | Purpose |
|--------|-------|------------------|---------|
| GET | `/api/intake-queue/categories` | `getProductCategories` | List categories |
| GET | `/api/intake-queue/types` | `getProductTypes` | List product types |
| GET | `/api/intake-queue/identifier-types` | `getIdentifierTypes` | List identifier types |
| GET | `/api/intake-queue/filter-classification` | `filterByClassification` | Filter by category/type |

---

## Frontend Wizard Flow

The verification wizard guides users through 4 steps:

### Step 1: Product Verification
**File:** `resources/js/Pages/IntakeQueue/Steps/Step1ProductVerification.vue`
- Review/edit: name, brand, model, category
- Edit identifiers: MPN, UPC, EAN
- View and manage captured photos

### Step 2: Price Verification
**File:** `resources/js/Pages/IntakeQueue/Steps/Step2PriceVerification.vue`
- View market pricing data
- Set list price and cost basis
- Configure shipping options

### Step 3: Listing Verification
**File:** `resources/js/Pages/IntakeQueue/Steps/Step3ListingVerification.vue`
- Listing title with character count and AI suggestions
- Description editor with auto-build from AI data
- Bullet points and keywords management
- Condition selection (filtered by valid eBay category conditions)
- Item specifics editor
- Live listing preview (eBay, Amazon, Newegg)

### Step 4: Review & Publish
**File:** `resources/js/Pages/IntakeQueue/Steps/Step4ReviewAndPublish.vue`
- Final review of all listing details
- Photo selection for listing
- **Editable Package Dimensions panel** -- weight/length/width/height inputs (manually entered, not AI-estimated). "Save dimensions" PATCHes `intake-queue.update`; the values drive the live shipping-rate lookup and persist so corrections survive a failed publish (e.g. via the failure-notification deep-link).
- Platform selection (eBay, Amazon, etc.)
- Publish action

---

## Error Handling & Retry Logic

### Job Configuration
```php
public int $timeout = 180;      // 3 minutes
public int $tries = 3;          // Maximum attempts
public array $backoff = [60, 300, 900];  // Retry delays: 1min, 5min, 15min
```

### Automatic Retry Scenarios
- API timeout errors
- Rate limiting responses
- Temporary network failures

### Manual Retry
Users can retry failed items via:
1. Web: "Retry" button on failed item
2. API: `POST /intake-queue/{id}/retry`

### Re-research with Corrections
When AI research returns incorrect data:
1. User provides corrections (brand, model, UPC)
2. System clears stale research data
3. Re-dispatches ProcessQueueItem with corrections
4. Logs correction history

### CreateEbayListingJob Error Classification

`CreateEbayListingJob` error classification determines retry behavior:

- **`validation_error`** (HTTP 400 from eBay, or "Validation error:" in message): No retry. Status -> `needs_review`. Includes condition-category mismatch (error 25021), invalid data.
- **`conflict`** (HTTP 409): No retry. Status -> `already_exists`.
- **`authentication`**, **`rate_limit`**, **`server_error`**, **`network_error`**, **`unknown_error`**: Retried up to 5 times with backoff `[30s, 2m, 5m, 15m, 30m]`. Status -> `retry_scheduled` between attempts.

---

## Debugging Commands

### Check Queue Item Status
```bash
docker exec ecommerce_app php artisan tinker --execute="
  echo App\Models\Catalog\ProductIntakeQueue::find('UUID_HERE')->only([
    'processing_status', 'ai_analysis_status', 'ai_confidence_score', 'quality_score'
  ]);
"
```

### View Recent Failed Items
```bash
docker exec ecommerce_app php artisan tinker --execute="
  App\Models\Catalog\ProductIntakeQueue::where('processing_status', 'failed')
    ->orderByDesc('updated_at')
    ->take(10)
    ->get(['id', 'barcode', 'enriched_data->error']);
"
```

### Manually Dispatch Processing Job
```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$item = App\Models\Catalog\ProductIntakeQueue::find('UUID_HERE');
  App\Jobs\ProcessQueueItem::dispatch(\$item);
"
```

### Check Processing Queue Depth
```bash
docker exec ecommerce_app php artisan queue:monitor default
```

### View Horizon Job Metrics
```bash
docker exec ecommerce_app php artisan horizon:status
```

### Reset Stuck Items
```bash
docker exec ecommerce_app php artisan tinker --execute="
  App\Models\Catalog\ProductIntakeQueue::where('ai_analysis_status', 'processing')
    ->where('ai_started_at', '<', now()->subMinutes(30))
    ->update([
      'ai_analysis_status' => 'pending',
      'processing_status' => 'pending'
    ]);
"
```

### Seed Test Data
```bash
docker exec ecommerce_app php artisan db:seed --class=ProductIntakeQueueSeeder
```

### Run Intake Queue Tests
```bash
docker exec ecommerce_app php artisan test tests/Feature/IntakeQueue/
docker exec ecommerce_app php artisan test tests/Unit/Models/ProductIntakeQueueTest.php
```

### Check Why an eBay Listing Failed
```bash
# Get EbayListing error details
docker exec ecommerce_app php artisan tinker --execute="
\$q = App\Models\Catalog\ProductIntakeQueue::find(ID);
\$listing = App\Models\Ebay\EbayListing::where('product_id', \$q->product_id)->latest()->first();
echo json_encode([
  'status'             => \$listing?->status,
  'last_error_message' => \$listing?->last_error_message,
  'last_error_code'    => \$listing?->last_error_code,
  'consecutive_errors' => \$listing?->consecutive_errors,
], JSON_PRETTY_PRINT);
"

# Get EbaySyncLog entries for a listing (per-operation detail)
docker exec ecommerce_app php artisan tinker --execute="
\$q = App\Models\Catalog\ProductIntakeQueue::find(ID);
\$listing = App\Models\Ebay\EbayListing::where('product_id', \$q->product_id)->latest()->first();
\$logs = App\Models\Ebay\EbaySyncLog::where('ebay_listing_id', \$listing->id)
  ->latest()->take(5)
  ->get(['operation','status','error_code','error_message','http_status_code','created_at']);
echo json_encode(\$logs->toArray(), JSON_PRETTY_PRINT);
"

# Reset a retry_scheduled item to re-queue immediately
docker exec ecommerce_app php artisan tinker --execute="
App\Models\Catalog\ProductIntakeQueue::find(ID)->update([
  'processing_status'   => 'ready_for_listing',
  'processing_attempts' => 0,
]);
App\Models\Ebay\EbayListing::where('product_id',
  App\Models\Catalog\ProductIntakeQueue::find(ID)->product_id
)->latest()->first()?->update(['status' => 'pending', 'consecutive_errors' => 0]);
"
```

---

## Product Categories

| Key | Label |
|-----|-------|
| `pos_equipment` | POS Equipment |
| `network_equipment` | Network Equipment |
| `computer_hardware` | Computer Hardware |
| `server_equipment` | Server Equipment |
| `security_equipment` | Security Equipment |
| `peripherals` | Peripherals & Accessories |
| `telecom_equipment` | Telecom Equipment |
| `industrial_equipment` | Industrial Equipment |
| `consumer_electronics` | Consumer Electronics |
| `medical_equipment` | Medical Equipment |
| ... | (31 total categories) |

## Product Types

| Key | Label |
|-----|-------|
| `complete_unit` | Complete Unit/System |
| `replacement_part` | Replacement Part |
| `component` | Internal Component |
| `accessory` | Accessory/Add-on |
| `consumable` | Consumable Item |
| `upgrade_module` | Upgrade/Expansion Module |
| `cable_connector` | Cable/Connector |
| `software_media` | Software/Media |
| `documentation` | Documentation/Manual |
| `packaging_only` | Packaging Only |

---

## Related Documentation

- [Product Research Orchestrator](PRODUCT_RESEARCH_ORCHESTRATOR.md) - Deep dive into the two-stage AI research pipeline (Stage 1 identification, Stage 2 strategy selection, service dependencies, caching, rate limiting)
- [CLAUDE.md](../CLAUDE.md) - Project overview and development commands
- [Mobile App README](../mobile/README.md) - Flutter mobile app documentation
