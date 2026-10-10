---
name: intake-pipeline
description: Product intake queue system, AI research orchestrator, and processing pipeline - status machine, 8-step processing, strategy selection, scoring, debugging
user-invocable: false
---

# Product Intake Pipeline

## Key Files

| Component | Path |
|-----------|------|
| Controller | `app/Http/Controllers/Product/IntakeQueueController.php` |
| Job | `app/Jobs/ProcessQueueItem.php` |
| Model | `app/Models/Catalog/ProductIntakeQueue.php` |
| AI Analysis Model | `app/Models/Catalog/ProductAiAnalysis.php` |
| Orchestrator | `app/Services/ProductResearchOrchestrator.php` |
| Stage 1 (ID) | `app/Services/ProductIdentificationService.php` |
| Stage 2 (Research) | `app/Services/GeminiProductResearch.php` |
| eBay Browse | `app/Services/EbayBrowseService.php` |
| eBay Sold | `app/Services/EbaySoldListingsService.php` |
| Validator | `app/Services/ProductDataValidator.php` |
| Rate Limiter | `app/Services/EbayRateLimiter.php` |
| Category Service | `app/Services/EbayCategoryService.php` |
| Listing Job | `app/Jobs/CreateEbayListingJob.php` |
| Wizard | `resources/js/Components/IntakeQueue/VerificationWizard.vue` |
| Tests | `tests/Feature/IntakeQueue/`, `tests/Unit/Models/ProductIntakeQueueTest.php` |

## Location & Bin Capture (added 2026-05-05)

`ProductIntakeQueue` carries the operator's chosen warehouse placement:

- `quick_location_id` (UUID FK -> `inventory_locations`) -- canonical, joined to fulfillment routing.
- `quick_bin_id` (UUID FK -> `warehouse_bins`) -- optional bin within the location.
- `quick_location` (string) -- legacy freetext, kept for old rows / mobile clients only.

`IntakeQueueController::seedProductInventory($product, $qty, $locationId, $binId, $freetext)`
writes `inventory_levels.location_id` + `bin_id`. Pick/pack/ship reads from `inventory_levels`
filtered by `Order.location_id`. **Never write `Product.location` (column doesn't exist) or
`Product.warehouse_location_id` (legacy bigint to a deprecated table).**

Resolution order if `$locationId` is null: freetext match (code/name) -> derive from bin's zone
-> org's first active location (logs an info warning).

See `docs/PRODUCT_LIFECYCLE_OVERVIEW.md` for the full hand-off map.

## Status State Machine

```
pending -> in_progress -> processing -> ready_for_listing (>=80%) -> completed -> listed_on_ebay
                                     |-> needs_minor_review (60-80%)
                                     |-> needs_review (40-60%)
                                     |-> needs_major_review (<40%)
                                     |-> failed -> pending (retry)

completed -> listed_on_ebay | listing_failed | archived
listing_failed -> retry_scheduled (retryable, backoff: 30s,2m,5m,15m,30m) -> listed_on_ebay | listing_failed
pending <- blocked_sku_prefix (org missing SKU prefix)
listing_failed -> already_exists (conflict, manual resolution)
```

## 8-Step Processing Pipeline (ProcessQueueItem)

1. **Supplier Context** (`analyzeSupplierContext`) - reliability_boost, priority_boost from supplier prefs
2. **Physical Measurements** (`analyzePhysicalMeasurements`) - shipping_class, dimensions, fragile, oversized. NOTE: `weight_lbs`/`length_inches`/`width_inches`/`height_inches` are **manually entered at intake (Create form), NOT AI-estimated from photos**. They feed `ShippingClassifier` for the estimated shipping cost. Editable later in wizard Step 4 (Package Dimensions panel) -> PATCH `intake-queue.update` (web) or `mobile.intake.update` (mobile); both validate the 4 fields and emit an `intake.updated` audit row.
3. **Barcode Validation** - validate UPC/EAN if present
4. **Product Research** - Two-stage orchestrator (see below)
5. **Photo Processing** - Background removal (`BackgroundRemovalService::processMultipleWithCropping`), condition-based cropping (tight=NEW, padded=parts). The pristine pre-removal paths are captured into `enriched_data['original_photos']` (parallel by index) so the product hub can later revert/reprocess an image. See the bg-removal + image-editing doc: `docs/IMAGE_PROCESSING.md`.
6. **Confidence Scoring** (`calculateEnhancedScoring`) - see formula below
7. **AI Analysis Record** - Create ProductAiAnalysis with detected fields
8. **Queue Item Update** - Set status in transaction based on confidence/quality thresholds

## Two-Stage Research Pipeline

**Stage 0 -- Market evidence grounding** (`ProductResearchOrchestrator::gatherMarketEvidence`): BEFORE Stage 1, runs an eBay search by the raw identifier and feeds the real listing titles into Stage 1 as authoritative ground truth. Category-agnostic, free, and the main defense against brand-name hallucination (e.g. Protech-HVAC vs Protech-PC). Skipped only when there is no identifier to search.

**Stage 1** (ProductIdentificationService): Gemini 2.5 Flash (temp 0.1) identifies WHAT the product is using category-specific expert prompts, grounded by the Stage 0 evidence. Signature: `identify($item, array $marketEvidence = [])`. Only 2 of 29 categories have bespoke prompts; the rest use the **hardened generic template** (user_notes authoritative + brand-disambiguation + anti-hallucination/honesty rules). Cached 24h (evidence is part of the cache key).

**Stage 2** (Orchestrator selects strategy): Researches HOW to sell it. Temp lowered to 0.1 and **locked to the Stage 1 identity** -- it enriches, never re-identifies. Images are filtered by a relevance floor (`MIN_IMAGE_RELEVANCE = 30`); if no eBay listing clears it, no images attach and a `no_relevant_images_found` warning fires.

### Strategy Cascade

| # | Strategy | Conditions | Services |
|---|----------|-----------|----------|
| 1 | High-confidence eBay | Stage1 confidence=high + identified + eBay available | EbayBrowse -> Gemini enrichment |
| 2 | Confident web research | Stage1 high/medium + identified | Gemini research with ID context |
| 3 | Standard eBay + ID | Identified (any confidence) + eBay available | EbayBrowse -> Gemini enrichment |
| 4 | Fallback web research | Gemini available | Gemini research (accepts any ID) |
| 5 | Enhanced template | All above failed | Template with manual_research_needed=true |

## Scoring Formula

```
base_confidence = 0.5 + (brand ? 0.1 : 0) + (model ? 0.1 : 0) + (pricing ? 0.1 : 0) + (description ? 0.1 : 0)
completeness_boost = (completeness_score - 60) / 400  // max 0.10 at 100%
final_confidence = min(1.0, base_confidence + completeness_boost + supplier_reliability_boost)
```

Status determination: >=0.8 && quality>=80 -> ready_for_listing; >=0.6 && quality>=60 -> needs_minor_review; >=0.4 -> needs_review; else needs_major_review.

**Review guardrail** (`requiresHumanReview`): regardless of score, status is capped at `needs_review` when Stage 1 identification confidence is low/none, the product was not identified, or `no_relevant_images_found` was warned. A hallucinated/unverified item never auto-passes to `ready_for_listing`.

## enriched_data JSON Structure (top-level keys)

- `research_data` - Raw orchestrator output (product_identity, product_details, market_pricing, listing_optimization, search_method, stage1_identification)
- `merged_product_fields` - Curated merge of research + user edits (product_identity, product_details, listing_optimization, market_pricing)
- `user_item_specifics` - User-edited eBay aspects from wizard. Values are sanitized to eBay's 65-char max at listing-creation time (`EbayInventoryService::sanitizeAspectValue()`), so verbose entries are trimmed rather than rejected.
- `user_listing_data` - User-edited listing title, bullets, keywords, target_platform
- `supplier_context` - reliability_boost, priority_boost, supplier_name
- `shipping_data` - shipping_class, estimated_cost, dimensions, fragile, oversized
- `preliminary_ebay_category` - Early category guess for AI prompt injection
- `original_photos` - Pristine pre-background-removal photo paths (parallel by index to `photos`); used to set `ProductImage.metadata.original_url` so images can be reverted/reprocessed post-intake
- `sources_used`, `warnings`, `confidence_breakdown`

**On product creation** (`IntakeQueueController`, all create paths), structured eBay
specifics are persisted onto `Product.attributes` via `buildIntakeAttributes()` (top-level
`brand/model/mpn/upc/ean` + nested `specifications`, merging AI specs < AI eBay specifics <
`user_item_specifics`). This makes the Product the durable source of truth for eBay item
specifics on both create and update — not the queue's `enriched_data`. ProductImage rows are
also created with `metadata.original_url` + `bg_removed` so the hub can revert/reprocess.

## Category Pipeline (Two-Pass)

**Pass 1 (Preliminary)**: `EbayCategoryService::findPreliminaryCategory()` during Step 3.5 - rough lookup from brand/model/MPN. Feeds required eBay aspects into AI prompt.

**Pass 2 (Final)**: `EbayCategoryService::findBestCategory()` during CreateEbayListingJob - authoritative, uses full enriched data.

### 6-Strategy Fallback Chain

1. eBay API Suggestions (95%) - ML-based, cached 24h, auto-saves to local DB
2. Exact brand+model match (95%) - local DB lookup
3. Keyword search (85%) - full-text across name, keywords, category_path
4. Brand-specific (75%) - hardcoded brand-to-category map
5. Product type fallback (60%) - keyword-to-leaf-category map
6. Default fallback (30%) - "Everything Else > Other" (10290)

## Cache Layers

| Service | Key Pattern | TTL |
|---------|------------|-----|
| ProductIdentificationService | `expert_identification_{md5(...)}` | 24h |
| GeminiProductResearch | `stage2_research_{md5(inputs)}` | 72h |
| EbayBrowseService (token) | `ebay_oauth_token` | 110min |
| EbayBrowseService (results) | `ebay_browse_smart_{md5(query)}` | 6h |
| EbaySoldListingsService | `ebay_sold_smart_{md5(query)}` | 6h |

## Error Classification (CreateEbayListingJob)

- **validation_error** (HTTP 400, "Validation error:"): No retry -> needs_review
- **conflict** (HTTP 409): No retry -> already_exists
- **authentication, rate_limit, server_error, network_error**: Retry up to 5x with backoff [30s,2m,5m,15m,30m] -> retry_scheduled

## Debugging Commands

```bash
# Check queue item status
docker exec ecommerce_app php artisan tinker --execute="App\Models\Catalog\ProductIntakeQueue::find('UUID')->only(['processing_status', 'ai_analysis_status', 'ai_confidence_score', 'quality_score']);"

# View research strategy used
docker exec ecommerce_app php artisan tinker --execute="\$q=App\Models\Catalog\ProductIntakeQueue::find('UUID'); echo \$q->enriched_data['research_data']['search_method'] ?? 'none';"

# View recent failures
docker exec ecommerce_app php artisan tinker --execute="App\Models\Catalog\ProductIntakeQueue::where('processing_status','failed')->latest()->take(5)->get(['id','enriched_data->error']);"

# Reset stuck items (processing > 30 min)
docker exec ecommerce_app php artisan tinker --execute="App\Models\Catalog\ProductIntakeQueue::where('ai_analysis_status','processing')->where('ai_started_at','<',now()->subMinutes(30))->update(['ai_analysis_status'=>'pending','processing_status'=>'pending']);"

# Check Stage 1 identification
docker exec ecommerce_app php artisan tinker --execute="\$q=App\Models\Catalog\ProductIntakeQueue::find('UUID'); \$s1=\$q->enriched_data['research_data']['stage1_identification'] ?? null; echo json_encode(['confidence'=>\$s1['identification_results']['identification_confidence'] ?? 'none','product'=>\$s1['identification_results']['most_likely_product']['product_name'] ?? 'unknown'],JSON_PRETTY_PRINT);"

# Check eBay rate limit
docker exec ecommerce_app php artisan tinker --execute="echo json_encode(app(App\Services\EbayRateLimiter::class)->getStatus(), JSON_PRETTY_PRINT);"

# Manually dispatch processing
docker exec ecommerce_app php artisan tinker --execute="\$item=App\Models\Catalog\ProductIntakeQueue::find('UUID'); App\Jobs\ProcessQueueItem::dispatch(\$item);"
```

## Audit Logging

| Action | Category | Severity | Metadata keys |
|--------|----------|----------|---------------|
| `intake.queued` | inventory | info | source (csv|api|supplier), supplier_id |
| `intake.ai_research_started` | inventory | debug | strategy |
| `intake.ai_research_completed` | inventory | info | confidence_score, quality_score |
| `intake.published` | inventory | info | product_id, location_id, bin_id |
| `intake.failed` | inventory | error | step, error_code, error_message |

Trait: models in this feature use `LogsAudit` (`app/Traits/LogsAudit.php`) for auto CRUD diff. Domain lifecycle events emitted from `app/Jobs/ProcessQueueItem*`, `app/Services/ProductIdentificationService`, `GeminiProductResearch`; `ProductIntakeQueue` model (`LogsAudit`). Jobs MUST wrap in `withAuditActor($queueItem->user, ..., ['source' => 'job'])`. via `UserActivityLog::log(action, desc, $user, $metadata, $severity, $category)`.

Audit drives the per-queue-item timeline shown in Admin Audit Hub and supports the supplier-submission audit story.

Spec: `docs/AUDIT_SYSTEM.md`. Enforced rule: `.claude/rules/audit-logging.md`. Tests: `use Tests\Support\AssertsAudits;` then `$this->assertAudited('action', $user, [...])`.
