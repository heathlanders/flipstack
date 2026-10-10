# ProductResearchOrchestrator -- Technical Documentation

The ProductResearchOrchestrator is the core AI-powered research engine that coordinates product identification and market research. It uses a two-stage pipeline: **Stage 1** identifies WHAT the product is, **Stage 2** researches HOW to sell it.

**Evidence-first grounding (2026-06):** Before Stage 1, the orchestrator runs `gatherMarketEvidence()` -- a raw-identifier eBay search whose listing titles are passed into Stage 1 identification as authoritative ground truth. This keeps the model from hallucinating an identity from a similarly-named brand (the failure that turned a Protech Rheem furnace ignition part into a "PC power supply" with sandal images). The identifier is universal, so this grounding works for any product regardless of whether a category-specific prompt exists.

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Two-Stage Pipeline](#two-stage-pipeline)
4. [Strategy Selection](#strategy-selection)
5. [Service Dependencies](#service-dependencies)
6. [Data Contracts](#data-contracts)
7. [Caching Strategy](#caching-strategy)
8. [Rate Limiting](#rate-limiting)
9. [Error Handling & Resilience](#error-handling--resilience)
10. [Configuration Reference](#configuration-reference)
11. [Debugging & Observability](#debugging--observability)
12. [Cross-References](#cross-references)

---

## Overview

The orchestrator is called by the `ProcessQueueItem` job as **Step 4** of the 8-step processing pipeline. It coordinates five downstream services to produce listing-ready product data, including identity, pricing, specifications, and optimization recommendations.

### Key Files

| File | Role |
|------|------|
| `app/Services/ProductResearchOrchestrator.php` | Main orchestrator -- strategy selection, data assembly |
| `app/Services/ProductIdentificationService.php` | Stage 1 -- AI product identification via Gemini |
| `app/Services/GeminiProductResearch.php` | Stage 2 -- AI research and eBay data enrichment |
| `app/Services/EbayBrowseService.php` | Active eBay listings via Browse API (OAuth 2.0) |
| `app/Services/EbaySoldListingsService.php` | Historical sold listings via Finding API (AppID) |
| `app/Services/ProductDataValidator.php` | Quality scoring and field validation |
| `app/Services/EbayRateLimiter.php` | Token-bucket rate limiting for eBay APIs |
| `app/Services/EbayConfigService.php` | eBay environment and credential config |
| `app/Services/EbaySearchResult.php` | DTO for eBay search results |
| `app/Jobs/ProcessQueueItem.php` | Job that invokes the orchestrator |

---

## Architecture

```
                         ProcessQueueItem (Job)
                                |
                                | Step 4: researchWithTwoStageProcess()
                                v
                  +-----------------------------+
                  | ProductResearchOrchestrator  |
                  |                             |
                  |  gatherMarketEvidence()      |  <-- pre-Stage-1 eBay grounding
                  |  gatherEnhancedIdentifiers() |
                  |  executeStage2Research()     |
                  |  finalizeResults()           |
                  +------+----------+-----------+
                         |          |
            STAGE 1      |          |      STAGE 2
                         v          v
         +------------------+   +-------------------------------+
         | ProductIdent-    |   | Strategy Selector             |
         | ificationService |   |   1. High-confidence eBay     |
         |                  |   |   2. Confident web research   |
         | Gemini 2.5 Flash |   |   3. Standard eBay + ID      |
         | (temp 0.1)       |   |   4. Fallback web research   |
         +--------+---------+   |   5. Enhanced template        |
                  |             +------+--------+-------+------+
                  |                    |        |       |
                  v                   v        v       v
          Expert category      +-------+ +--------+ +----------+
          prompts (29          | eBay  | | eBay   | | Gemini   |
          categories)          |Browse | | Sold   | | Product  |
                               |Service| |Listings| | Research |
                               +---+---+ +---+----+ +----+-----+
                                   |         |           |
                                   v         v           v
                              Browse API  Finding API  Gemini 2.5
                              (OAuth)     (AppID)      Flash
                                   |         |         (temp 0.1)
                                   +----+----+
                                        |
                                        v
                               EbayRateLimiter
                               (5/sec, 5000/day)

         +------------------+
         | ProductData-     |  <-- Called in finalizeResults()
         | Validator        |
         | (quality score)  |
         +------------------+
```

### Entry Points

The orchestrator exposes three public methods that all route to the same two-stage pipeline:

| Method | Purpose |
|--------|---------|
| `researchWithTwoStageProcess($item, $options)` | Primary entry point |
| `research($item)` | Backward compatibility (no options) |
| `researchWithEnhancedContext($item, $options)` | Alias for primary entry point |
| `researchWithStrategy($item, $strategy, $options)` | Strategy hint passed via options |

---

## Two-Stage Pipeline

### Stage 1: Product Identification

**Service:** `ProductIdentificationService`
**Purpose:** Expert reverse-engineering to identify WHAT the product is using category-specific AI prompts, grounded in real marketplace evidence.

**Signature:** `identify(ProductIntakeQueue $item, array $marketEvidence = [])`. The `$marketEvidence` argument carries the eBay listing rows gathered by the orchestrator's `gatherMarketEvidence()` before this call.

#### Flow

1. **Category resolution** -- If the queue item has `product_category` set by the user, it maps directly to an expert category via `mapUserCategoryToExpertCategory()`. Otherwise, `detectComprehensiveProductCategory()` auto-detects from brand, part numbers, notes, and keywords using regex pattern matching.

2. **Evidence normalization** -- `normalizeMarketEvidence()` reduces the eBay rows to a deduped list of up to 15 listing titles.

3. **Cache check** -- Results are cached for 24 hours. Cache key: `expert_identification_{md5(item_id + updated_at + category + product_category + evidence_titles)}`. Evidence is part of the key so a different marketplace snapshot yields a fresh identification.

4. **Expert prompt construction** -- `buildExpertCategoryPrompt()` first checks the database via `PromptService` for organization-customizable prompts. If none found, falls back to hardcoded category-specific prompt builders. Only `computer_enterprise_it` and `industrial_automation_motion_controls` have bespoke prompts; the other 27 categories route through `__call()` (which now logs) to the **hardened generic template** (`buildRemainingExpertPrompts`). The generic template renders `user_notes` as authoritative and includes brand-disambiguation + anti-hallucination/honesty instructions.

5. **Evidence injection** -- `performExpertIdentification()` prepends `buildMarketEvidenceBlock()` (the real listing titles, framed as ground truth) to whichever prompt was built. This applies to every variant -- expert, generic, and DB-stored.

6. **Gemini API call** -- Sends the prompt to `gemini-2.5-flash` with conservative generation settings.

7. **Response parsing** -- Extracts JSON from the response, adds metadata, returns structured identification results.

#### Gemini API Configuration (Stage 1)

| Setting | Value |
|---------|-------|
| Model | `gemini-2.5-flash` |
| Temperature | `0.1` |
| Top P | `0.7` |
| Top K | `20` |
| Timeout | `120s` |

#### Supported Expert Categories (29)

| Expert Category Key | User Category Mappings |
|--------------------|-----------------------|
| `computer_enterprise_it` | network_equipment, computer_hardware, server_equipment, security_equipment, peripherals, telecom_equipment |
| `pos_retail_systems` | pos_equipment |
| `industrial_automation_motion_controls` | industrial_equipment |
| `test_measurement_equipment` | test_measurement |
| `electrical_equipment_supplies` | electrical_supplies |
| `hydraulics_pneumatics` | hydraulics_pneumatics |
| `cnc_metalworking` | cnc_metalworking |
| `material_handling` | material_handling |
| `heavy_equipment` | heavy_equipment |
| `healthcare_medical_equipment` | medical_equipment |
| `automotive_parts_motors` | automotive_parts |
| `hvac_refrigeration` | hvac_refrigeration |
| `restaurant_food_service` | restaurant_food_service |
| `printing_graphic_arts` | printing_graphic_arts |
| `agriculture_forestry` | agriculture_forestry |
| `video_games_consoles` | video_games_consoles |
| `sporting_goods_fitness` | sporting_goods_fitness |
| `toys_collectibles` | toys_collectibles |
| `consumer_electronics` | consumer_electronics |
| `musical_instruments_audio` | musical_instruments_audio |
| `cameras_photography` | cameras_photography |
| `crafts_hobby` | crafts_hobby |
| `books_magazines` | books_magazines |
| `art_antiques` | art_antiques |
| `cell_phones_accessories` | cell_phones_accessories |
| `home_garden` | home_garden |
| `health_beauty` | health_beauty |
| `pet_supplies` | pet_supplies |
| `vintage_computing` | (auto-detect only) |
| `advanced_general_product` | other, fallback default |

#### Stage 1 Output Schema

```json
{
  "identification_results": {
    "product_identified": true,
    "identification_confidence": "high|medium|low|none",
    "user_type_validation": {
      "user_expected_type": "complete_unit",
      "ai_determined_type": "complete_unit|replacement_part|component|accessory",
      "type_classification_matches": true,
      "type_confidence": "high|medium|low",
      "type_reasoning": "Explanation..."
    },
    "most_likely_product": {
      "complete_part_number": "FS-148F-FPOE",
      "product_name": "FortiSwitch 148F-FPOE",
      "manufacturer": "Fortinet",
      "product_type": "Complete_Network_Device",
      "device_category": "Switch",
      "specifications": { ... }
    },
    "reasoning": "Expert analysis...",
    "confidence_factors": ["Factor 1", "Factor 2"]
  },
  "technical_specifications": { ... },
  "source_links": [
    {
      "type": "manufacturer_datasheet",
      "url": "https://...",
      "description": "Official documentation"
    }
  ],
  "search_strategy_for_stage2": {
    "primary_search_terms": ["Fortinet", "FS-148F"],
    "secondary_search_terms": ["FortiSwitch", "48-port"],
    "network_databases": ["CDW", "SHI"],
    "recommended_approach": "Focus on enterprise networking distributors"
  },
  "expert_analysis": true,
  "category_expertise": "computer_enterprise_it",
  "identification_stage": "expert_stage_1_complete",
  "analysis_timestamp": "2026-02-28T12:00:00Z",
  "queue_item_id": "uuid",
  "user_provided_classification": true
}
```

---

### Stage 2: Strategy Selection & Research

**Service:** `ProductResearchOrchestrator` (orchestrates), `GeminiProductResearch` (executes AI calls), `EbayBrowseService` / `EbaySoldListingsService` (market data)

After Stage 1 completes (already grounded by the pre-Stage-1 market evidence), the orchestrator:

1. **Gathers enhanced identifiers** -- Merges queue item fields with Stage 1 confirmed data (`gatherEnhancedIdentifiers()`).
2. **Selects a strategy** -- Based on identification confidence and service availability.
3. **Executes research** -- Calls the appropriate services.
4. **Finalizes results** -- Validates, adds metadata, merges Stage 1 data.

#### Identifier Assembly (`gatherEnhancedIdentifiers`)

Builds a merged identifier map from the queue item and Stage 1 results:

```
Queue Item Fields:
  manufacturer_part_number, product_number, barcode, user_notes, brand, model_number, series

Stage 1 Enhancements (if available):
  confirmed_part_number    <- most_likely_product.complete_part_number
  confirmed_product_name   <- most_likely_product.product_name
  stage1_primary_terms     <- search_strategy_for_stage2.primary_search_terms

Computed:
  comprehensive_query      <- Brand + (confirmed_part_number || MPN) + model_number
                              + product_number + stage1_primary_terms (deduplicated)
```

---

## Strategy Selection

The orchestrator cascades through five strategies. Each strategy is attempted in order; if it returns a valid result, that result is used. If it fails or returns null, the next strategy is tried.

### Decision Tree

| Strategy | Conditions | Services Used | `search_method` Value |
|----------|-----------|---------------|----------------------|
| **1. High-confidence eBay** | Stage 1 confidence = `high` AND product identified AND eBay Browse available | `EbayBrowseService` -> `GeminiProductResearch.enrichWithEbayDataAndIdentification()` | `high_confidence_ebay` |
| **2. Confident web research** | Stage 1 confidence = `high` or `medium` AND product identified | `GeminiProductResearch.researchWithIdentification()` | `confident_web_research` |
| **3. Standard eBay + ID** | Product identified (any confidence) AND eBay Browse available | `EbayBrowseService` -> `GeminiProductResearch.enrichWithEbayDataAndIdentification()` | `standard_ebay_with_id` |
| **4. Fallback web research** | Gemini API available (no other conditions) | `GeminiProductResearch.researchWithIdentification()` | `fallback_web_research` |
| **5. Enhanced template** | All above strategies failed | None (generates template from available data) | `two_stage_template` |

### Strategy Details

**Strategy 1: High-Confidence eBay Search**
- Uses Stage 1's `search_strategy_for_stage2.primary_search_terms` to build an optimized query
- `buildOptimizedSearchQuery()` assembles: Brand + Primary Terms + Complete Part Number + MPN/Model/Product Number
- Searches eBay Browse API for active listings
- If listings found, passes them to Gemini for enrichment with identification context
- Result includes eBay market data + AI-generated listing optimization

**Strategy 2: Confident Web Research**
- Passes Stage 1 identification results directly to Gemini for web-based research
- Gemini uses the confirmed product identity to perform targeted research
- Returns product details, market pricing, and listing optimization
- Must pass `hasSubstantialData()` check (non-empty brand, model, title, description, or pricing)

**Strategy 3: Standard eBay Search**
- Uses `comprehensive_query` or `manufacturer_part_number` as search query
- Same eBay search + Gemini enrichment flow as Strategy 1
- Wider search, lower confidence threshold

**Strategy 4: Fallback Web Research**
- Same as Strategy 2 but accepts any identification (even low/no confidence)
- Last attempt at AI-powered research before template fallback

**Strategy 5: Enhanced Template**
- Generates a structured template with `manual_research_needed: true`
- Populates whatever fields are available from Stage 1 and queue item data
- Sets `user_action_required: true` -- requires manual human research

### Query Building (`buildOptimizedSearchQuery`)

For Strategies 1 and 3, the eBay search query is built with priority ordering:

```
1. Brand (if available)
2. Stage 1 primary_search_terms (deduplicated against prior parts)
3. Stage 1 most_likely_product.complete_part_number
4. Queue item: manufacturer_part_number, product_number, model_number (fallbacks)
```

Parts are joined with spaces and filtered for empties.

---

## Service Dependencies

### EbayBrowseService

**API:** eBay Browse API v1 (`/buy/browse/v1/item_summary/search`)
**Auth:** OAuth 2.0 Client Credentials (`client_id` + `client_secret` from `EbayConfigService`)
**Credentials source:** `config/services.php` -> `services.ebay.{environment}.client_id|client_secret`

| Aspect | Detail |
|--------|--------|
| Token cache | `ebay_oauth_token`, TTL 110 minutes (tokens valid ~2 hours) |
| Result cache | `ebay_browse_smart_{md5(query)}`, TTL 6 hours |
| Max results | 200 per search (fixed price only) |
| Rate limiting | Via `EbayRateLimiter`, max 3 retry attempts |

**Smart Query Building (`buildSmartQuery`):**
```
1. If a prebuilt query was passed (identifiers['search_query'] or ['query']), use it verbatim.
2. Otherwise: real brand (no NCR default) + FULL part number (dashes -> spaces, NOT truncated)
   + top 2 keywords.
Example: "Protech 62 24164 01 DSI ignition"
```
The old behavior (brand defaulted to `NCR`, MPN truncated to its first 4 chars -- a leftover
NCR-POS hack) caused garbage queries like `"Protech 6224 ..."` that returned unrelated
listings. Removed 2026-06.

**Active Listing Schema:**
```json
{
  "item_id": "123456789",
  "title": "NCR 7703 POS Terminal",
  "price": 299.99,
  "currency": "USD",
  "condition": "Used",
  "shipping": 15.00,
  "link": "https://www.ebay.com/itm/123456789",
  "image": "https://i.ebayimg.com/images/...",
  "seller": "seller_username",
  "feedback": 5000,
  "buy_it_now": true
}
```

### EbaySoldListingsService

**API:** eBay Finding API v1 (`findCompletedItems` operation)
**Auth:** AppID (no OAuth required) -- `IntegrationCredential` where `platform=ebay`, `environment=production`
**Credentials source:** `decrypted_client_id` from `IntegrationCredential`

| Aspect | Detail |
|--------|--------|
| Result cache | `ebay_sold_smart_{md5(query)}`, TTL 6 hours |
| Max results | 100 per search |
| Filter | `SoldItemsOnly=true`, sorted by `EndTimeSoonest` |
| Rate limiting | Via `EbayRateLimiter`, max 3 retry attempts |

**Sold Listing Schema:**
```json
{
  "item_id": "123456789",
  "title": "NCR 7703 POS Terminal",
  "price": 285.00,
  "currency": "USD",
  "condition": "Used",
  "sold_date": "2026-01-15T00:00:00Z",
  "shipping": 12.00,
  "total_price": 297.00,
  "link": "https://www.ebay.com/itm/123456789",
  "image": "https://thumbs.ebaystatic.com/...",
  "seller": "seller_username",
  "feedback": 3500
}
```

### GeminiProductResearch

**API:** Google Generative Language API (`gemini-2.5-flash:generateContent`)
**Auth:** API key from `IntegrationCredential` where `platform=gemini`, `environment=production`

| Aspect | Detail |
|--------|--------|
| Model | `gemini-2.5-flash` |
| Temperature | `0.1` (lowered from 0.2 for identity lock, 2026-06) |
| Top P | `0.7` |
| Top K | `20` |
| Timeout | `90s` |
| Result cache | `stage2_research_{md5(inputs)}`, TTL 72 hours (3 days) |

Stage 2 is **locked to the Stage 1 identity**: the enrichment prompt instructs the model to enrich (specs, pricing, listing copy) the product Stage 1 identified, not to re-identify or reinterpret the brand. Low confidence stays low.

**Two Methods:**

1. **`researchWithIdentification()`** -- Pure web research guided by Stage 1 results. Used by Strategies 2 and 4.
2. **`enrichWithEbayDataAndIdentification()`** -- eBay data enrichment with Stage 1 context. Used by Strategies 1 and 3.

**Pricing Calculation (enrichment path):**
- Collects all prices from active listings where `price > 0`
- Sorts prices ascending
- `median_price` = middle value (or average of two middle values)
- `recommended_list_price` = median price
- `pricing_confidence` = `"high"` if sample size >= 10, else `"medium"`

**Image Sourcing (`extractImages`) -- relevance floor (2026-06):**
- Each eBay listing is scored by `scoreListingRelevance()` (part number +30/+50, product name +20, brand +15, model +10, primary terms +5 each).
- Only listings scoring `>= MIN_IMAGE_RELEVANCE` (30 -- a full MPN/part-number match) contribute images. Below that, images are not trusted.
- If listings existed but none cleared the floor, **no images are attached** and a `no_relevant_images_found` warning is emitted (which caps processing status at `needs_review`). Better an empty image set than a sandal on a furnace part.

### ProductDataValidator

Called during `finalizeResults()` to validate and score the research output.

**Required Fields (for eBay listing):**

| Field | Max Length | Weight |
|-------|-----------|--------|
| `title` | 80 chars | Required |
| `category` | -- | Required |
| `condition` | -- | Required |
| `price` | -- | Required |
| `description` | 32,000 chars | Required |

**Recommended Fields:**

| Field | Min Count |
|-------|-----------|
| `brand` | -- |
| `model` | -- |
| `mpn` | -- |
| `upc` | -- |
| `images` | 1 |
| `specifications` | 3 |
| `shipping_weight` | -- |
| `shipping_dimensions` | -- |

**Quality Score Breakdown (0-100):**

| Component | Max Points | Criteria |
|-----------|-----------|----------|
| Title quality | 20 | Length (0-10), keyword richness: brand (4), model (4), condition (2) |
| Description quality | 20 | Length (0-10), content: features (3), condition (4), specs (3) |
| Image quality | 30 | User photos: 8+ = 30, 6-7 = 25, 4-5 = 20, 2-3 = 15, 1 = 10 |
| Product specifics | 20 | Identifiers: brand (3), model (3), mpn (2), upc/ean (2); specs count (0-10) |
| Pricing data | 10 | Has price (3), sold comparables (4), active comparables (2), recommendation (1) |

**ValidationResult Object:**
```json
{
  "is_complete": true,
  "score": 85,
  "missing_required": [],
  "missing_recommended": ["upc", "shipping_weight"],
  "errors": [],
  "warnings": [
    {"field": "specifications", "message": "Should have at least 3 item(s)"}
  ]
}
```

---

## Data Contracts

### Final Orchestrator Return Schema

The return value of `researchWithTwoStageProcess()` varies by strategy, but always passes through `finalizeResults()` which adds these standard fields:

```json
{
  "product_identity": {
    "brand": "Fortinet",
    "model": "FS-148F-FPOE",
    "manufacturer_part_number": "FS-148F-FPOE",
    "verified_complete_part_number": "FS-148F-FPOE",
    "upc": "",
    "ean": "",
    "title": "Fortinet FortiSwitch 148F-FPOE 48-Port PoE+ Managed Switch",
    "category": "Business & Industrial",
    "subcategory": "Networking"
  },
  "product_details": {
    "condition": "used",
    "description": "Comprehensive product description...",
    "key_features": ["48 GE ports with PoE+", "4 10GE SFP+ uplinks"],
    "specifications": { ... },
    "weight": "15 lbs",
    "dimensions": "17.3 x 10.2 x 1.7 in",
    "package_contents": [],
    "warranty": ""
  },
  "market_pricing": {
    "recommended_list_price": 450.00,
    "average_price": 475.50,
    "median_price": 450.00,
    "price_range": "350 - 600",
    "lowest_price": 350.00,
    "highest_price": 600.00,
    "pricing_confidence": "high",
    "sample_size": 15,
    "ebay_sold_listings": [ ... ],
    "ebay_active_listings": [ ... ]
  },
  "listing_optimization": {
    "suggested_title": "Fortinet FortiSwitch FS-148F-FPOE 48-Port PoE+ Switch - Tested",
    "primary_keywords": ["Fortinet", "FortiSwitch", "FS-148F"],
    "secondary_keywords": ["PoE+", "48-port", "managed switch"],
    "bullet_points": ["Feature 1", "Feature 2"],
    "platform_specific_tips": { ... }
  },
  "competitive_analysis": { ... },

  "stage1_identification": { ... },
  "identification_confidence": "high",
  "identification_reasoning": "Expert analysis...",
  "identification_sources": [ ... ],

  "search_method": "high_confidence_ebay",
  "data_source": "stage2_ebay_enrichment",
  "research_stage": "stage_2_complete",
  "two_stage_process": true,
  "research_timestamp": "2026-02-28T12:00:00Z",
  "queue_item_id": "uuid",

  "validation": {
    "is_complete": true,
    "score": 85,
    "missing_required": [],
    "missing_recommended": ["upc"],
    "errors": [],
    "warnings": []
  },
  "quality_score": 85,
  "is_ebay_ready": true,

  "supplier_context": {
    "supplier_name": "Acme",
    "reliability_score": 90,
    "priority_supplier": true
  },
  "supplier_confidence_boost": "high"
}
```

### Strategy 5 Template Schema

When all strategies fail, the enhanced template returns:

```json
{
  "product_identity": {
    "brand": "Unknown",
    "model": "",
    "manufacturer_part_number": "",
    "title": "Product Requires Manual Research"
  },
  "product_details": {
    "condition": "unknown",
    "description": "Product information not available through automated research",
    "key_features": [],
    "specifications": [],
    "user_notes": ""
  },
  "market_pricing": {
    "ebay_sold_listings": [],
    "ebay_active_listings": [],
    "recommended_list_price": 0,
    "pricing_confidence": "none"
  },
  "listing_optimization": {
    "suggested_title": "Query - Manual Research Required",
    "primary_keywords": [],
    "bullet_points": []
  },
  "data_sources": [],
  "data_source": "enhanced_template",
  "search_method": "two_stage_template",
  "user_action_required": true,
  "manual_research_needed": true,
  "stage1_identification": { ... },
  "identification_available": true,
  "identification_confidence": "low"
}
```

### enriched_data Field Structure

The `ProcessQueueItem` job wraps the orchestrator output and stores it in `ProductIntakeQueue.enriched_data`:

```json
{
  "research_data": { ... },
  "merged_product_fields": {
    "product_identity": { ... },
    "product_details": { ... },
    "listing_optimization": { ... },
    "market_pricing": { ... }
  },
  "supplier_context": {
    "reliability_boost": 0.05,
    "priority_boost": false,
    "supplier_name": "Acme Suppliers"
  },
  "shipping_data": {
    "shipping_class": "standard",
    "estimated_cost": 12.50,
    "dimensions": { ... },
    "fragile": false,
    "oversized": false
  },
  "sources_used": ["supplier_context", "research", "background_removal"],
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

## Caching Strategy

Five independent cache layers prevent redundant API calls:

| Service | Cache Key Pattern | TTL | Store |
|---------|------------------|-----|-------|
| `ProductIdentificationService` | `expert_identification_{md5(id+updated_at+category+product_category+evidence_titles)}` | 24 hours | Default (Redis) |
| `GeminiProductResearch` (web) | `stage2_research_{md5(inputs)}` | 72 hours | Default (Redis) |
| `EbayBrowseService` (token) | `ebay_oauth_token` | 110 minutes | Default (Redis) |
| `EbayBrowseService` (results) | `ebay_browse_smart_{md5(query)}` | 6 hours | Default (Redis) |
| `EbaySoldListingsService` | `ebay_sold_smart_{md5(query)}` | 6 hours | Default (Redis) |

**Cache invalidation:** Caches expire naturally via TTL. The Stage 1 cache key includes `updated_at`, so editing a queue item invalidates its identification cache. Re-research via the UI clears stale data before re-dispatching.

---

## Rate Limiting

### EbayRateLimiter

Centralized rate limiter shared by all eBay API services. Uses Redis for distributed state.

| Parameter | Value |
|-----------|-------|
| Per-second limit | 5 requests/second |
| Daily limit | 5,000 requests/day |
| Algorithm | Token bucket (per-second), counter (daily) |
| Initial backoff | 500ms |
| Max backoff | 30,000ms (30 seconds) |
| Backoff formula | `min(500ms * 2^(attempt-1), 30000ms)` |
| Max retry attempts | 3 (per API call) |
| Redis keys | `ebay_api_rate_limit` (bucket), `ebay_api_daily_limit:YYYY-MM-DD` (counter) |

**Backoff Schedule:**

| Attempt | Wait Time |
|---------|-----------|
| 1 | 500ms |
| 2 | 1,000ms |
| 3 | 2,000ms |

**Rate limit detection:** Checks the exception message for `rate limit`, `429`, or `too many requests` (case-insensitive). Rate limit errors trigger retry; other errors are re-thrown.

**Status inspection:**
```php
$limiter = app(EbayRateLimiter::class);
$status = $limiter->getStatus();
// Returns: per_second.{limit, available, used}, daily.{limit, used, remaining, percentage_used}
```

---

## Error Handling & Resilience

### Graceful Degradation

The system is designed to produce results even when individual services fail:

1. **Stage 1 failure does not block Stage 2.** If `ProductIdentificationService` is unavailable or throws an exception, the orchestrator proceeds to Stage 2 with `$identificationResults = null`. Strategy selection adjusts accordingly (Strategies 1-3 require identification, so they are skipped).

2. **Strategy cascade.** Each strategy catches its own exceptions. A failed strategy logs the error and falls through to the next strategy. Only after all five strategies fail does the orchestrator return a template result (Strategy 5 never throws).

3. **Service availability checks.** Every service implements `isAvailable()` that is checked before use:
   - `ProductIdentificationService::isAvailable()` -- has Gemini API key
   - `GeminiProductResearch::isAvailable()` -- has Gemini API key
   - `EbayBrowseService::isAvailable()` -- has client ID and secret
   - `EbaySoldListingsService::isAvailable()` -- has app ID

### Constructor Safety

All services that load credentials wrap the loading in try-catch:

```php
protected function loadCredentials(): void
{
    try {
        $credential = IntegrationCredential::where('platform', 'gemini')...;
        $this->apiKey = $credential->decrypted_client_secret;
    } catch (\Throwable $e) {
        Log::warning('Failed to load credentials', ['error' => $e->getMessage()]);
    }
}
```

If `decrypted_client_secret` accessor returns null for an encrypted field, it throws a `TypeError`. The try-catch prevents this from crashing the service constructor. The service remains instantiated but `isAvailable()` returns false.

### Job-Level Retry

The `ProcessQueueItem` job has its own retry configuration:

| Setting | Value |
|---------|-------|
| `$timeout` | 180 seconds (3 minutes) |
| `$tries` | 3 attempts |
| `$backoff` | `[60, 300, 900]` -- 1 min, 5 min, 15 min |

On final failure, `failed()` sets `processing_status = 'failed'` and records the error in `enriched_data`.

### Validation Resilience

The validator is wrapped in try-catch within `finalizeResults()`. If validation fails, the result is returned without validation metadata rather than crashing the pipeline.

---

## Configuration Reference

### Environment Variables

| Variable | Required By | Description |
|----------|------------|-------------|
| `EBAY_CLIENT_ID` | EbayBrowseService | eBay OAuth Client ID (via `config/services.php`) |
| `EBAY_CLIENT_SECRET` | EbayBrowseService | eBay OAuth Client Secret |
| `EBAY_USE_SANDBOX` | EbayConfigService | `true` for sandbox, defaults based on `APP_ENV` |

### IntegrationCredential Records

| Platform | Environment | Fields Used |
|----------|------------|-------------|
| `ebay` | `production` | `decrypted_client_id` (AppID for Finding API) |
| `gemini` | `production` | `decrypted_client_secret` (API key) |

### config/services.php eBay Settings

```php
'ebay' => [
    'use_sandbox' => env('EBAY_USE_SANDBOX', false),
    'production' => [
        'client_id' => env('EBAY_CLIENT_ID'),
        'client_secret' => env('EBAY_CLIENT_SECRET'),
        'ru_name' => env('EBAY_RU_NAME'),
    ],
    'sandbox' => [
        'client_id' => env('EBAY_SANDBOX_CLIENT_ID'),
        'client_secret' => env('EBAY_SANDBOX_CLIENT_SECRET'),
        'ru_name' => env('EBAY_SANDBOX_RU_NAME'),
    ],
],
```

---

## Debugging & Observability

### Log Entry Points

All major operations are logged at INFO level with structured context:

| Log Message | Level | Context Keys |
|-------------|-------|-------------|
| `Starting Two-Stage Enhanced Product Research` | INFO | queue_item_id, brand, mpn, has_existing_data |
| `STAGE 1: Product Identification Starting` | INFO | -- |
| `STAGE 1: Identification Complete` | INFO | product_identified, confidence, category |
| `STAGE 2: Choosing research strategy` | INFO | identification_confidence, product_identified, ebay_available |
| `STRATEGY N: ...` | INFO | strategy-specific context |
| `Enhanced identifiers built` | INFO | comprehensive_query, has_stage1_data, confirmed_part_number |
| `Two-Stage Research Complete` | INFO | stage1_confidence, stage2_method, quality_score, is_listing_ready |

Failures are logged at ERROR level with exception messages.

### Tinker Commands

**Inspect orchestrator results for a queue item:**
```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$item = App\Models\Catalog\ProductIntakeQueue::find('UUID');
  echo json_encode(\$item->enriched_data['research_data']['search_method'] ?? 'none');
  echo json_encode(\$item->enriched_data['confidence_breakdown'] ?? []);
"
```

**Check Stage 1 identification results:**
```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$item = App\Models\Catalog\ProductIntakeQueue::find('UUID');
  \$s1 = \$item->enriched_data['research_data']['stage1_identification'] ?? null;
  echo json_encode([
    'confidence' => \$s1['identification_results']['identification_confidence'] ?? 'none',
    'identified' => \$s1['identification_results']['product_identified'] ?? false,
    'product' => \$s1['identification_results']['most_likely_product']['product_name'] ?? 'unknown',
  ], JSON_PRETTY_PRINT);
"
```

**Check eBay rate limit status:**
```bash
docker exec ecommerce_app php artisan tinker --execute="
  echo json_encode(app(App\Services\EbayRateLimiter::class)->getStatus(), JSON_PRETTY_PRINT);
"
```

**Verify pre-Stage-1 market evidence grounded the identification:**
```bash
# The titles eBay returned for the raw identifier are what Stage 1 was grounded on.
# If identification is wrong, check whether this returned junk (bad query) or nothing.
docker exec ecommerce_app php artisan tinker --execute="
  \$svc = app(App\Services\EbayBrowseService::class);
  \$item = App\Models\Catalog\ProductIntakeQueue::find('UUID');
  \$r = \$svc->search(['brand'=>\$item->brand,'manufacturer_part_number'=>\$item->manufacturer_part_number,'keywords'=>\$item->keywords ?? []]);
  echo json_encode(array_map(fn(\$l)=>\$l['title'] ?? '', \$r->activeListings), JSON_PRETTY_PRINT);
"
```

**Check whether images were withheld by the relevance floor:**
```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$item = App\Models\Catalog\ProductIntakeQueue::find('UUID');
  echo json_encode(\$item->enriched_data['warnings'] ?? \$item->enriched_data['research_data']['warnings'] ?? [], JSON_PRETTY_PRINT);
"
```

**Check which strategy was used:**
```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$item = App\Models\Catalog\ProductIntakeQueue::find('UUID');
  echo 'Method: ' . (\$item->enriched_data['research_data']['search_method'] ?? 'unknown');
  echo 'Source: ' . (\$item->enriched_data['research_data']['data_source'] ?? 'unknown');
"
```

**View AI analysis record:**
```bash
docker exec ecommerce_app php artisan tinker --execute="
  \$analysis = App\Models\Catalog\ProductAiAnalysis::where('queue_item_id', 'UUID')->first();
  echo json_encode(\$analysis->only([
    'analysis_type', 'ai_provider', 'confidence_score', 'quality_score', 'detected_brand', 'detected_model'
  ]), JSON_PRETTY_PRINT);
"
```

### Common Failure Scenarios

| Symptom | Cause | Fix |
|---------|-------|-----|
| All items get `two_stage_template` method | Gemini API key missing or invalid | Check `IntegrationCredential` for `gemini` platform |
| Stage 1 returns null, Stage 2 falls to Strategy 4 | Gemini API timeout or quota exceeded | Check Gemini API dashboard; increase timeout if needed |
| eBay strategies skipped (Strategies 1, 3) | eBay credentials not configured | Check `config/services.php` eBay settings and `IntegrationCredential` for `ebay` platform |
| `quality_score = 0` | Validator can't find expected fields in nested data | Check `flattenForValidation()` mapping in `ProcessQueueItem` |
| Rate limit errors in logs | Too many concurrent queue workers hitting eBay | Reduce Horizon worker count or increase rate limit window |

---

## Cross-References

- [Product Intake Queue System](INTAKE_QUEUE_SYSTEM.md) -- Full queue pipeline documentation (the orchestrator is Step 4)
- [CLAUDE.md](../CLAUDE.md) -- Project overview, development commands, and deployment procedures
