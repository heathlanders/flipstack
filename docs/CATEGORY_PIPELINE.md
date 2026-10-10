# Category Pipeline

How products get categorized from intake through eBay listing.

## Three Category Systems

| System | Source | Purpose |
|--------|--------|---------|
| **App enum** (`PRODUCT_CATEGORIES`) | `ProductIntakeQueue` model constant | 32 values (e.g. `computer_hardware`, `consumer_electronics`). Guides AI research strategy during processing. |
| **Org categories** | `categories` DB table per organization | User-managed tree for internal inventory organization. |
| **eBay categories** | `ebay_categories` DB table (synced from eBay API) | 30k+ leaf categories. Required for listing creation. |

The app enum and eBay categories are connected only indirectly: the app enum guides AI research, which produces product data, which feeds eBay category resolution.

## Intake Categories (App Enum)

Defined in `app/Models/Catalog/ProductIntakeQueue.php` lines 107-140. These 32 values are set during capture (user selection or classification) and influence:

- **AI research prompts** -- `getAiResearchHint()` tailors research based on category
- **Processing strategy** -- barcode-focused vs comprehensive
- **UI filtering** -- queue can be filtered by category

These are NOT eBay categories. A `computer_hardware` item might map to any of dozens of eBay leaf categories.

## eBay Category Resolution

### Two-Pass Approach

**Pass 1: Preliminary (ProcessQueueItem Step 3.5)**
- File: `app/Services/EbayCategoryService.php` -> `findPreliminaryCategory()`
- Input: brand, model, MPN (limited data available pre-research)
- Purpose: feeds category-specific aspects into AI prompt so research is category-aware
- Stored in: `enriched_data.preliminary_ebay_category`
- Accuracy: often wrong when only limited data is available (e.g. generic brand/model)

**Pass 2: Final (CreateEbayListingJob)**
- File: `app/Services/EbayCategoryService.php` -> `findBestCategory()`
- Input: full enriched data (title, brand, model, category hints, aspects)
- Purpose: authoritative category for the eBay listing
- Much more accurate because it uses the complete AI-enriched product data

### 6-Strategy Fallback Chain

`findBestCategory()` tries strategies in order, returning the first match:

| # | Strategy | Confidence | Method |
|---|----------|------------|--------|
| 1 | eBay API Suggestions | 95% | `findByApiSuggestion()` -- eBay's ML-based category suggestion |
| 2 | Exact brand+model match | 95% | `findByExactMatch()` -- local DB lookup |
| 3 | Keyword search | 85% | `searchByKeywords()` -- combined keywords in local DB |
| 4 | Brand-specific | 75% | `findByBrand()` -- category commonly used for brand |
| 5 | Product type fallback | 60% | `findByProductType()` -- broad type-based match |
| 6 | Default fallback | 30% | `getDefaultCategory()` -- last resort |

Strategy 1 (API suggestions) is by far the most common path in production.

### Preliminary vs AI Disagreement

The conditions endpoint (`IntakeQueueController::getConditions()`) checks whether the AI's identified category matches the preliminary category. If they disagree (e.g. preliminary says "Other Components & Parts" but AI says "Laptops & Netbooks"), the preliminary category is discarded and `findBestCategory()` re-resolves using the richer AI data. This prevents the wrong category's conditions from being shown in the wizard.

## Condition Validation

Before listing, the product's condition must be valid for the resolved eBay category. Different categories allow different conditions (e.g. some don't allow `NEW_WITH_DEFECTS`).

### Flow

1. Frontend (VerificationWizard Step 3) calls `GET /intake-queue/{id}/conditions`
2. Controller resolves category (preliminary or re-resolved)
3. `EbayCategoryService::getValidConditionsForCategory()` calls eBay Sell Metadata API:
   ```
   GET /sell/metadata/v1/marketplace/{marketplaceId}/get_item_condition_policies?filter=categoryIds:{categoryId}
   ```
4. API returns `itemConditionPolicies[].itemConditions[].conditionId` (numeric IDs like "1000")
5. IDs are mapped to enum values via `mapConditionIdToEnum()`:

| Condition ID | Enum |
|-------------|------|
| 1000 | `NEW` |
| 1500 | `NEW_OTHER` |
| 1750 | `NEW_WITH_DEFECTS` |
| 2000 | `CERTIFIED_REFURBISHED` |
| 2010 | `EXCELLENT_REFURBISHED` |
| 2020 | `VERY_GOOD_REFURBISHED` |
| 2030 | `GOOD_REFURBISHED` |
| 2500 | `SELLER_REFURBISHED` |
| 3000 | `USED_EXCELLENT` |
| 4000 | `USED_VERY_GOOD` |
| 5000 | `USED_GOOD` |
| 6000 | `USED_ACCEPTABLE` |
| 7000 | `FOR_PARTS_OR_NOT_WORKING` |

6. Frontend filters the condition dropdown to only show valid options
7. `CreateEbayListingJob` validates again before submitting to eBay

Results are cached for 7 days per category+marketplace.

## Data Flow

```
User captures item (photos, barcode, category selection)
    |
    v
ProcessQueueItem job
    |-- Step 3.5: findPreliminaryCategory(brand, model, MPN)
    |       -> stores enriched_data.preliminary_ebay_category
    |-- Step 4: AI research (uses preliminary category for aspect hints)
    |       -> stores merged_product_fields (title, brand, model, category, etc.)
    |
    v
VerificationWizard (user review)
    |-- Step 3: GET /conditions
    |       -> compares preliminary vs AI category, re-resolves if different
    |       -> getValidConditionsForCategory() -> Sell Metadata API
    |       -> returns filtered condition options
    |-- User selects condition, edits title/price/etc.
    |
    v
CreateEbayListingJob
    |-- findBestCategory() with full enriched data (authoritative)
    |-- validates condition against category
    |-- creates eBay listing via Inventory API
```

## Debugging

```bash
# Check what preliminary category was resolved
docker exec ecommerce_app php artisan tinker --execute="
\$q = App\Models\Catalog\ProductIntakeQueue::find('UUID');
echo json_encode(\$q->enriched_data['preliminary_ebay_category'] ?? 'none');
"

# Check AI-identified category
docker exec ecommerce_app php artisan tinker --execute="
\$q = App\Models\Catalog\ProductIntakeQueue::find('UUID');
echo \$q->enriched_data['merged_product_fields']['product_identity']['category'] ?? 'none';
"

# Test category resolution
docker exec ecommerce_app php artisan tinker --execute="
\$svc = app(App\Services\EbayCategoryService::class);
echo json_encode(\$svc->findBestCategory(['title' => 'Dell Latitude 5520 Laptop', 'brand' => 'Dell']));
"

# Test condition fetch for a category
docker exec ecommerce_app php artisan tinker --execute="
\$svc = app(App\Services\EbayCategoryService::class);
\$cred = App\Models\Integration\IntegrationCredential::where('platform', 'ebay')->where('is_active', true)->first();
echo json_encode(\$svc->getValidConditionsForCategory('177', 'EBAY_US', \$cred));
"

# Clear cached conditions for a category
docker exec ecommerce_app php artisan tinker --execute="
Cache::forget('ebay_condition_policies_EBAY_US_177');
echo 'cleared';
"
```

## Key Files

| Component | Path |
|-----------|------|
| Category service | `app/Services/EbayCategoryService.php` |
| Category model | `app/Models/Ebay/EbayCategory.php` |
| Intake queue model | `app/Models/Catalog/ProductIntakeQueue.php` |
| Conditions endpoint | `app/Http/Controllers/Product/IntakeQueueController.php` (`getConditions`) |
| Listing job | `app/Jobs/CreateEbayListingJob.php` |
| Wizard frontend | `resources/js/Components/IntakeQueue/VerificationWizard.vue` |
| Condition ID mapping | `docs/EBAY_LISTING_SERVICE.md` (lines 93-112) |

See also: [INTAKE_QUEUE_SYSTEM.md](INTAKE_QUEUE_SYSTEM.md), [EBAY_LISTING_SERVICE.md](EBAY_LISTING_SERVICE.md)
