# eBay Listing Service

## Listing Creation Flow

```
Product Intake Queue -> AI Analysis -> Verification Wizard -> CreateEbayListingJob -> eBay API
```

1. **Product Intake Queue** (`ProductIntakeQueue`) - Product captured via scanner/manual entry
2. **AI Analysis** (`ProcessQueueItem`) - Gemini research enriches product data (identity, specs, pricing). Includes preliminary eBay category lookup to feed required aspects into the AI prompt.
3. **Verification Wizard** (`VerificationWizard.vue`) - User reviews/edits title, description, price, item specifics, condition
4. **CreateEbayListingJob** - Orchestrates the eBay listing process:
   - Creates/finds the `Product` record
   - Finds the best eBay category (final, authoritative)
   - Validates product condition against category-specific eBay policies
   - Creates inventory item (product data + aspects with fuzzy matching)
   - Creates offer (pricing + policies)
   - Publishes to eBay

   Status progression:
   ```
   in_progress -> listed_on_ebay (success)
               -> retry_scheduled (retryable error, up to 5 attempts with backoff: 30s, 2m, 5m, 15m, 30m)
               -> needs_review (validation_error -- no retry)
               -> listing_failed (permanent failure after max retries)
               -> already_exists (conflict -- no retry)
   ```

### Key Files

| Component | Path |
|-----------|------|
| Listing Job | `app/Jobs/CreateEbayListingJob.php` |
| Category Service | `app/Services/EbayCategoryService.php` |
| Inventory Service | `app/Services/EbayInventoryService.php` |
| Sell Service | `app/Services/EbaySellService.php` |
| Config Service | `app/Services/EbayConfigService.php` |
| Policy Rules Engine | `app/Services/Ebay/EbayPolicyRulesEngine.php` |
| Gemini Research | `app/Services/GeminiProductResearch.php` |
| Research Orchestrator | `app/Services/ProductResearchOrchestrator.php` |
| Wizard Frontend | `resources/js/Components/IntakeQueue/VerificationWizard.vue` |
| Category Seeder | `database/seeders/Production/SystemEbayCategorySeeder.php` |

## Category Matching

`EbayCategoryService::findBestCategory()` uses a strategy chain with confidence levels:

| Priority | Strategy | Confidence | Description |
|----------|----------|------------|-------------|
| 1 | eBay API Suggestions | 95% | Calls `getCategorySuggestions` -- eBay's ML-based matching. Caches 24h. Auto-saves results to local DB. |
| 2 | Exact brand+model match | 95% | Searches local `ebay_categories` table by keywords |
| 3 | Keyword search | 85% | Full-text search across name, keywords, category_path |
| 4 | Brand-specific | 75% | Hardcoded brand-to-category map (NCR->POS, Cisco->Networking) |
| 5 | Product type fallback | 60% | Keyword-to-leaf-category map covering electronics, fashion, home, sports, music, toys, and more |
| 6 | Default fallback | 30% | Falls back to "Everything Else > Other" (10290) -- a generic leaf category |

### Two-Pass Category Approach

Category detection happens **twice**:

1. **Preliminary (research time)** - In `ProcessQueueItem` Step 3.5, a rough category lookup runs using brand/model/part number. This is used solely to fetch eBay's required aspects and inject them into the Gemini AI prompt. The preliminary result is NOT stored as the final category.

2. **Final (listing time)** - In `CreateEbayListingJob` Step 5, the authoritative category is selected using the user-edited product title and full enriched data. This is the category used for the actual eBay listing.

The preliminary pass is correct 90%+ of the time (a laptop will still match a laptop category). Even when slightly off, required aspects overlap heavily within product verticals, so the AI still gets useful guidance.

### Local Category DB

The `ebay_categories` table stores categories from two sources:
- **Seeder** (`SystemEbayCategorySeeder`) - Pre-loaded common categories for offline/API-down fallback
- **API auto-population** - When the API suggestion strategy finds a category, it's saved locally via `upsertCategoryFromApi()` for future offline lookups

### Data Flow for Category Selection

```
CreateEbayListingJob builds $categorySearchData:
  - title, description, brand, model
  - category, subcategory (from Gemini research)
  - keywords (from listing optimization)
      |
      v
EbayCategoryService::findBestCategory($categorySearchData)
      |
      v
Returns { category_id, name, path, confidence, is_leaf }
```

## Condition-Category Validation

Before calling `fullSync()`, `CreateEbayListingJob` validates the product's condition against eBay's
per-category condition policies. Some categories restrict which condition enums are accepted.

### eBay conditionEnum Values

Valid values for the Inventory API `condition` field:

| Enum | Condition ID | Description |
|------|-------------|-------------|
| `NEW` | 1000 | Brand new, unopened |
| `NEW_OTHER` | 1500 | New but not in original packaging |
| `NEW_WITH_DEFECTS` | 1750 | New with manufacturing defects |
| `CERTIFIED_REFURBISHED` | 2000 | Manufacturer-certified refurbished |
| `EXCELLENT_REFURBISHED` | 2010 | Excellent refurbished |
| `VERY_GOOD_REFURBISHED` | 2020 | Very good refurbished |
| `GOOD_REFURBISHED` | 2030 | Good refurbished |
| `SELLER_REFURBISHED` | 2500 | Seller refurbished |
| `USED_EXCELLENT` | 3000 | Used, excellent condition |
| `USED_VERY_GOOD` | 4000 | Used, very good condition |
| `USED_GOOD` | 5000 | Used, good condition |
| `USED_ACCEPTABLE` | 6000 | Used, acceptable condition |
| `FOR_PARTS_OR_NOT_WORKING` | 7000 | For parts or not working |

### How Validation Works

`EbayCategoryService::getValidConditionsForCategory(categoryId, marketplaceId)` calls the eBay Sell
Metadata API to get the allowed conditions for a specific category:

```
GET /sell/metadata/v1/marketplace/{marketplaceId}/get_item_condition_policies?filter=categoryIds:{categoryId}
```

Results are cached in Redis for 7 days (`ebay_condition_policies_{marketplaceId}_{categoryId}`).

**Fail-open behavior:** If the API call fails (network error, rate limit), an empty array is returned
and listing proceeds without condition validation -- never block a listing due to an unrelated API error.

**On validation failure:** The exception message includes "Validation error:" which causes
`categorizeError()` to classify it as `validation_error` -> no retry -> item set to `needs_review`
with the list of valid conditions in `validation_errors`.

### Debugging Condition Issues

```bash
# Check what conditions are valid for a category
docker exec ecommerce_app php artisan tinker --execute="
\$svc = app(App\Services\EbayCategoryService::class);
\$conditions = \$svc->getValidConditionsForCategory('177');
echo implode(', ', \$conditions);
"

# Clear cached condition policies to force fresh fetch
docker exec ecommerce_app php artisan tinker --execute="
Cache::forget('ebay_condition_policies_EBAY_US_177');
echo 'Cache cleared';
"
```

## Item Specifics Pipeline

Item specifics (eBay "aspects") are populated through a multi-layer pipeline designed to work for ANY product type:

### Data Flow

```
Step 1: Preliminary Category Lookup (ProcessQueueItem Step 3.5)
    EbayCategoryService::findPreliminaryCategory()
    EbayCategoryService::getAspectsForPrompt()
        -> Returns text block of required/recommended aspects with allowed values
                                    |
                                    v
Step 2: AI-Powered Spec Generation (ProcessQueueItem Step 4)
    Gemini prompt includes eBay aspects requirements section
    AI returns item_specifics populated with category-aware values
        -> enriched_data.listing_optimization.platform_specific_tips.ebay.item_specifics
        -> enriched_data.product_details.specifications
                                    |
                                    v
Step 3: User Review (Verification Wizard)
    Wizard merges AI specs + ebay item_specifics
    User can add/edit/remove any aspect
        -> enriched_data.user_item_specifics
                                    |
                                    v
Step 4: Aspect Assembly (CreateEbayListingJob -> EbayInventoryService)
    buildAspects() merges from all sources (priority order):
      1. Standard identifiers: Brand, MPN, Model, UPC, EAN
      2. Specifications via $specMapping (67-key safety net)
      3. Attributes via $specMapping
      4. Custom aspects from wizard (HIGHEST PRIORITY -- overwrites)
      5. fillRequiredAspects() for any still-missing REQUIRED aspects
                                    |
                                    v
Step 5: Fuzzy Value Matching
    Each aspect value is matched against eBay's allowed values:
      - Exact case-insensitive match
      - Normalized match (strip spaces/dashes/dots)
      - Substring containment match
      - If no match: value sent as-is, logged to aspect_audit
                                    |
                                    v
Step 6: Value Sanitization (65-char cap)
    Every assembled aspect value passes through sanitizeAspectValue():
      - Strip trailing parenthetical ("Not specified (long aside)" -> "Not specified")
      - Cap at 65 chars on a word boundary (EBAY_ASPECT_VALUE_MAX_LENGTH)
      - Drop empties; unset the aspect if no value survives
                                    |
                                    v
Step 7: Final Fallback
    Any remaining unfilled REQUIRED aspects get "Does Not Apply"
    All defaulted/unmatched aspects logged to EbayListing.aspect_audit
```

### eBay Taxonomy API Integration

`fetchCategorySpecifics()` calls the eBay Taxonomy API and preserves full metadata:

```php
// Each aspect includes:
[
    'name' => 'Processor',           // Aspect display name
    'cardinality' => 'REQUIRED',     // REQUIRED or OPTIONAL
    'mode' => 'SELECTION_ONLY',      // SELECTION_ONLY or FREE_TEXT
    'allowed_values' => [            // Valid options (for SELECTION_ONLY)
        'Intel Core i7',
        'Intel Core i5',
        'AMD Ryzen 5',
        ...
    ],
]
```

`getAspectsForPrompt()` formats this into a concise text block for AI prompt injection:
```
- Processor (REQUIRED): Intel Core i7, Intel Core i5, AMD Ryzen 5 ... (45 total options)
- RAM Size (REQUIRED): 8 GB, 16 GB, 32 GB, 64 GB
- Screen Size (REQUIRED)
- Operating System (RECOMMENDED) [examples: Windows 11, macOS, Chrome OS]
```

### Spec-to-Aspect Mapping

The `$specMapping` array acts as a safety net for specs that arrive with known internal keys. This is supplementary to the AI-driven approach -- specs that the AI populates directly with correct eBay aspect names bypass this mapping entirely.

**Electronics**

| Source Key | eBay Aspect Name |
|-----------|------------------|
| ram, memory | RAM Size |
| processor, cpu | Processor |
| storage, storage_capacity | Storage Capacity |
| screen_size, display_size | Screen Size |
| operating_system, os | Operating System |
| gpu, graphics_card | GPU |
| resolution | Maximum Resolution |
| color | Color |
| weight | Item Weight |
| type | Type |
| connectivity | Connectivity |
| form_factor | Form Factor |

**Universal / Cross-Category**

| Source Key | eBay Aspect Name |
|-----------|------------------|
| size | Size |
| material | Material |
| style | Style |
| pattern | Pattern |
| theme | Theme |
| gender, department | Department |
| age_group | Age Group |
| year, year_manufactured | Year Manufactured |
| country_of_manufacture | Country/Region of Manufacture |
| features | Features |
| finish | Finish |
| power_source | Power Source |

**Fashion**

| Source Key | eBay Aspect Name |
|-----------|------------------|
| shoe_size, us_shoe_size | US Shoe Size |
| waist_size | Waist Size |
| inseam | Inseam |
| sleeve_length | Sleeve Length |
| closure | Closure |
| occasion | Occasion |
| season | Season |

**Home / Sports / Music**

| Source Key | eBay Aspect Name |
|-----------|------------------|
| room | Room |
| capacity | Capacity |
| sport | Sport/Activity |
| skill_level | Skill Level |
| number_of_strings | Number of Strings |
| body_type | Body Type |
| handedness | Handedness |

### Fuzzy Value Matching

When `buildAspects()` has a value for an aspect, it runs through `matchAllowedValue()` to find the best match against eBay's allowed options:

1. **Exact match** (case-insensitive) -- "16gb" matches "16 GB"? No. "16 GB" matches "16 GB"? Yes.
2. **Normalized match** -- Strips spaces, dashes, dots. "16GB" matches "16 GB" via normalization.
3. **Substring containment** -- "Intel Core i7-1365U" matches "Intel Core i7" if the allowed value is a substring.
4. **No match** -- Value sent as-is. For `SELECTION_ONLY` aspects, logged to `aspect_audit`.

### Value Sanitization (65-char cap)

eBay rejects any item-specific value longer than 65 characters (error: `"...value of 'X' is too long. Enter a value of no more than 65 characters"`). AI research frequently emits verbose non-answers like `"Not specified (typically low current for ignitors, controlled by furnace board)"`. A final pass in `buildAspects()` runs every value (from every source -- identifiers, specs, attributes, wizard, filled-required) through `sanitizeAspectValue()`:

1. **Strip parenthetical** -- cut at the first `" ("`, so `"Not specified (typically...)"` becomes `"Not specified"` (keeps a meaningful short value for required aspects instead of a truncated fragment).
2. **Cap at 65** -- truncate to `EBAY_ASPECT_VALUE_MAX_LENGTH = 65` on the last word boundary (no ellipsis; eBay counts characters).
3. **Drop empties** -- a value that sanitizes to `''` is removed; an aspect with no surviving value is unset.

This is the single chokepoint that guarantees no value exceeds 65 chars regardless of source. The Gemini enrichment prompt also instructs the model to keep values <=65 chars and omit unknowns, but the sanitizer is the hard guarantee.

### Aspect Audit Trail

Every listing stores an `aspect_audit` (JSON) on the `EbayListing` record. This captures:

- Aspects that defaulted to "Does Not Apply" (no value found from any source)
- Aspects with values that didn't match any allowed eBay option (for SELECTION_ONLY aspects)

```php
// Example audit entry
[
    'aspect' => 'Processor',
    'provided_value' => 'Intel 13th Gen i7',
    'status' => 'no_match',
    'reason' => 'Value does not match any of 45 allowed options',
    'sample_allowed' => ['Intel Core i7', 'Intel Core i5', 'AMD Ryzen 5', ...],
]
```

This enables review of problematic aspects without blocking listing creation.

## Debugging Guide

### Wrong category assigned

```bash
# Check what category was selected and why
docker exec ecommerce_app php artisan tinker --execute="
\$item = App\Models\Catalog\ProductIntakeQueue::find('UUID');
\$merged = \$item->enriched_data['merged_product_fields'] ?? [];
echo 'Brand: ' . (\$merged['product_identity']['brand'] ?? 'none') . PHP_EOL;
echo 'Model: ' . (\$merged['product_identity']['model'] ?? 'none') . PHP_EOL;
echo 'Category: ' . (\$merged['product_identity']['category'] ?? 'none') . PHP_EOL;
echo 'Title: ' . (\$merged['listing_optimization']['suggested_title'] ?? 'none') . PHP_EOL;
"

# Test category matching manually
docker exec ecommerce_app php artisan tinker --execute="
\$svc = app(App\Services\EbayCategoryService::class);
\$result = \$svc->findBestCategory([
    'title' => 'Dell Latitude 5520 Laptop',
    'brand' => 'Dell',
    'model' => 'Latitude 5520',
]);
print_r(\$result);
"

# Check API suggestions directly
docker exec ecommerce_app php artisan tinker --execute="
\$svc = app(App\Services\EbayCategoryService::class);
\$suggestions = \$svc->getCategorySuggestionsFromApi('Dell Latitude laptop');
foreach (\$suggestions as \$s) {
    echo \$s['category']['categoryId'] . ' - ' . \$s['category']['categoryName'] . PHP_EOL;
}
"
```

### Missing item specifics

```bash
# Check what specs were extracted by AI
docker exec ecommerce_app php artisan tinker --execute="
\$item = App\Models\Catalog\ProductIntakeQueue::find('UUID');
\$merged = \$item->enriched_data['merged_product_fields'] ?? [];
echo 'Specifications:' . PHP_EOL;
print_r(\$merged['product_details']['specifications'] ?? []);
echo 'eBay item_specifics from AI:' . PHP_EOL;
print_r(\$merged['listing_optimization']['platform_specific_tips']['ebay']['item_specifics'] ?? []);
echo 'Product attributes:' . PHP_EOL;
\$product = App\Models\Catalog\Product::find(\$item->product_id);
print_r(\$product?->attributes ?? []);
echo 'Wizard item_specifics:' . PHP_EOL;
print_r(\$item->enriched_data['user_item_specifics'] ?? []);
"
```

### Reviewing aspect audit (unmatched/defaulted aspects)

```bash
# Check which aspects had issues for a specific listing
docker exec ecommerce_app php artisan tinker --execute="
\$listing = App\Models\Ebay\EbayListing::where('product_id', 'PRODUCT_UUID')->first();
echo 'Aspect audit:' . PHP_EOL;
echo json_encode(\$listing->aspect_audit ?? [], JSON_PRETTY_PRINT) . PHP_EOL;
echo 'Sent aspects:' . PHP_EOL;
echo json_encode(\$listing->last_sync_payload['product']['aspects'] ?? [], JSON_PRETTY_PRINT);
"

# Find all listings with aspect issues
docker exec ecommerce_app php artisan tinker --execute="
\$listings = App\Models\Ebay\EbayListing::whereNotNull('aspect_audit')
    ->where('aspect_audit', '!=', '[]')
    ->latest()
    ->take(10)
    ->get(['id', 'ebay_sku', 'aspect_audit']);
foreach (\$listings as \$l) {
    echo \$l->ebay_sku . ': ' . count(\$l->aspect_audit) . ' issues' . PHP_EOL;
    foreach (\$l->aspect_audit as \$a) {
        echo '  - ' . \$a['aspect'] . ': ' . \$a['status'] . ' (' . \$a['reason'] . ')' . PHP_EOL;
    }
}
"
```

### Listing created but specs not showing on eBay

Check the `last_sync_payload` on the EbayListing record -- it contains the exact payload sent to eBay:

```bash
docker exec ecommerce_app php artisan tinker --execute="
\$listing = App\Models\Ebay\EbayListing::where('product_id', 'PRODUCT_UUID')->first();
echo json_encode(\$listing->last_sync_payload['product']['aspects'] ?? [], JSON_PRETTY_PRINT);
"
```

### Testing the full aspects pipeline

```bash
# Test what aspects would be generated for a category
docker exec ecommerce_app php artisan tinker --execute="
\$svc = app(App\Services\EbayCategoryService::class);
\$categoryId = '177';  // Laptops & Netbooks
\$aspects = \$svc->getRequiredItemSpecifics(\$categoryId);
echo 'Required aspects for category ' . \$categoryId . ':' . PHP_EOL;
foreach (\$aspects as \$a) {
    \$vals = ! empty(\$a['allowed_values']) ? ' (' . count(\$a['allowed_values']) . ' options)' : ' (free text)';
    echo '  ' . \$a['cardinality'] . ': ' . \$a['name'] . \$vals . PHP_EOL;
}
echo PHP_EOL . 'Prompt text:' . PHP_EOL;
echo \$svc->getAspectsForPrompt(\$categoryId);
"
```

## Common Issues

| Issue | Cause | Fix |
|-------|-------|-----|
| Laptop mapped to POS category | Gemini defaulted to "Business & Industrial"; local DB only had POS leaf categories | Fixed: API suggestions now primary strategy; Gemini no longer defaults to B&I |
| Item specifics only show Brand/MPN/Model | `buildAspects()` only mapped 5 fields | Fixed: Now maps specifications, attributes, and wizard item_specifics |
| Category suggestions API returns empty | Unauthenticated request to Taxonomy API | Fixed: Both `EbayCategoryService` and `EbayInventoryService` now use app-level OAuth tokens |
| Brand fallback always returns POS | `findByBrand()` had a catch-all POS search | Fixed: Returns null for unknown brands so next strategy runs |
| Non-electronics product gets electronics category | All fallback strategies and defaults were hardcoded for electronics/POS | Fixed: Universal product terms, type maps, and spec mappings; generic "Everything Else > Other" default |
| Non-electronics specs (size, material) silently dropped | `buildAspects()` / `mapSpecificationToEbayAspect()` only mapped electronics keys | Fixed: Added universal, fashion, home, sports, and music spec mappings |
| Default `type` set to "Electronic Component" | Hardcoded fallback in `getDefaultValueForSpecific()` and `getProductType()` | Fixed: Default is now "N/A" / empty string |
| Required aspects missing -> API rejection | No fallback for unfilled required aspects | Fixed: "Does Not Apply" fallback for all required aspects; logged to aspect_audit |
| AI specs use wrong keys for eBay | Gemini prompt had no awareness of eBay's expected aspect names | Fixed: Preliminary category lookup feeds required aspects + allowed values into AI prompt |
| Item specifics all show "Does Not Apply" | `IntakeQueueController` stored item_specifics into `product.metadata` (non-existent column); `CreateEbayListingJob` read from same null field | Fixed: Item specifics now stored in `enriched_data.user_item_specifics`; job reads from enriched_data with AI fallback |
| Aspect value rejected by eBay (SELECTION_ONLY) | AI returns freeform value that doesn't match eBay's allowed options | Fixed: Fuzzy matching (case-insensitive, normalized, substring) before sending; mismatches logged to aspect_audit |
| Aspect value rejected: "no more than 65 characters" | AI emitted verbose non-answers ("Not specified (long explanation...)"); no length cap before send | Fixed: `sanitizeAspectValue()` strips parentheticals + caps at 65 chars on every value in `buildAspects()`; Gemini prompt also forbids verbose values |
| Listing retries forever with condition error | `categorizeError()` matched on message keywords only; eBay 400 errors without "validation"/"400" in message text fell to `unknown_error` (retryable) | Fixed: EbayApiException HTTP status checked first -- 400 -> `validation_error` (no retry) |
| EbaySyncLog shows CONNECTION_ERROR / null HTTP status for eBay API errors | `request()` outer catch overwrote the correctly-logged error from `handleResponse()` | Fixed: `EbayApiException` re-thrown without re-logging; only true network exceptions log CONNECTION_ERROR |
| Condition rejected by eBay (error 25021) | Product condition not valid for the auto-selected category | `getValidConditionsForCategory()` now validates before publish; error surfaces as `validation_error` with list of valid conditions |
