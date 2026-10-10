# Product Intake to Listing End-to-End Test

## Document ID: TC-E2E-002

**Systems Involved:** Web, Mobile, Queue (Redis/Horizon), Gemini AI (ProductResearchOrchestrator), Background Removal Service, eBay API (Inventory/Offer/Publish), Shopify API (Product/Inventory)
**Business Process:** A product is scanned via barcode (web or mobile), enters the ProductIntakeQueue, undergoes 8-step AI-powered processing, is reviewed and approved by a team member, creates a Product record, and is listed on both eBay and Shopify marketplaces with inventory levels initialized.
**Last Updated:** 2026-04-15

---

## 1. Workflow Overview

This test validates the complete product onboarding pipeline from initial barcode scan through live marketplace listings. It exercises the ProductIntakeQueue state machine, the ProcessQueueItem job with its 8-step AI research pipeline (supplier context, physical measurements, barcode validation, eBay category lookup, product research via Gemini, photo processing, confidence scoring, and queue item update), manual review and approval, Product creation, the CreateEbayListingJob with its 10-step listing process (preflight -> credentials -> status update -> product creation -> category -> duplicate check -> inventory data -> offer data -> fullSync -> success handling), Shopify product sync, and inventory initialization.

```
  Barcode Scan                  Queue Item Created           ProcessQueueItem Job
  (Web UI / Mobile) ----------> (processing_status=pending)  (8-step pipeline)
        |                              |                           |
        |                              v                           v
        |                     Step 1: Supplier Context     Step 2: Physical Measurements
        |                              |                           |
        |                              v                           v
        |                     Step 3: Barcode Validation   Step 3.5: eBay Category Lookup
        |                              |                           |
        |                              v                           v
        |                     Step 4: AI Product Research   (Gemini API / Orchestrator)
        |                     (title, description, pricing)        |
        |                              |                           v
        |                     Step 5: Photo Processing     Step 6: Confidence Scoring
        |                     (background removal)                 |
        |                              |                           v
        |                     Step 7: AI Analysis Record   Step 8: Update Queue Item
        |                     (ProductAiAnalysis)          (enriched_data, quality_score)
        |                              |
        |                              v
        |                     processing_status = ready/needs_review
        |                     ai_analysis_status = complete
        |                              |
        |                              v
        |                     Manual Review (Web UI)
        |                     (verify title, price, category, condition)
        |                              |
        |                              v
        |                     Approve & Create Product
        |                     (processing_status = completed, product_id set)
        |                              |
        |              +---------------+---------------+
        |              |                               |
        |              v                               v
        |     CreateEbayListingJob              SyncProductToShopify Job
        |     (10-step process)                 (create product + set inventory)
        |              |                               |
        |              v                               v
        |     EbayInventoryService.fullSync:    ShopifyProductService.createProduct
        |       1. createOrUpdateInventoryItem   -> ShopifyListing created
        |          (status=inventory_created)     -> status=active
        |       2. createOffer                   ShopifyInventoryService.setInventoryLevel
        |          (status=offer_created)         -> inventory set at location
        |       3. publishOffer                        |
        |          (status=published)                  |
        |              |                               |
        |              v                               v
        v     EbayListing.ebay_listing_id      ShopifyListing.shopify_product_id
  Product live on     populated                  populated
  both marketplaces   ebay_listing_url set        shopify_handle set
```

**Trigger:** User scans a barcode via web intake form or mobile barcode scanner, submitting to ProductIntakeQueue
**End State:** Product record exists with all enriched fields populated. EbayListing with status=`published` and ebay_listing_id. ShopifyListing with status=`active` and shopify_product_id. Product.available_quantity set. Inventory levels initialized at warehouse location.

---

## 2. Preconditions

| # | Requirement | Setup Method |
|---|-------------|--------------|
| 1 | Organization with active subscription, configured SKU prefix (`OrganizationSkuService` can generate SKUs) | Factory / Seeder |
| 2 | Team exists under the organization | Factory |
| 3 | User with inventory permissions (can create products, can manage intake queue) | Factory with role assignment |
| 4 | eBay IntegrationCredential (platform=ebay, is_active=true, auto_sync_enabled=true) with valid OAuthToken and configured business policies (fulfillment, payment, return) | Factory with sandbox credentials |
| 5 | Shopify IntegrationCredential (platform=shopify, is_active=true, auto_sync_enabled=true) with shop_domain and valid access_token | Factory with dev store credentials |
| 6 | Gemini API key configured (GEMINI_API_KEY in .env) for ProductResearchOrchestrator | Environment variable |
| 7 | Background Removal Service accessible (BACKGROUND_REMOVAL_URL configured, service running) | Environment / Docker |
| 8 | InventoryLocation exists (for initial stock placement) | Factory |
| 9 | Redis and Horizon running (ProcessQueueItem, CreateEbayListingJob, SyncProductToShopify are queue jobs) | Docker environment |
| 10 | eBay credential has marketplace_id set (e.g., EBAY_US) and merchant_location_key configured | Factory / Manual |
| 11 | No existing product with the same barcode/UPC in the organization (to avoid duplicate detection) | Clean state |

---

## 3. Test Scenario

| Step | Actor | System | Action | Expected Result | Verification Method |
|------|-------|--------|--------|-----------------|---------------------|
| 1 | Intake User | Web UI / Mobile API | Submits barcode scan to ProductIntakeQueue. Fields: barcode (UPC/EAN), capture_method (web/mobile), photos (array of uploaded image URLs), user_notes, quick_quantity, quick_location, brand (if known), model_number, manufacturer_part_number, condition, weight_lbs, dimensions (L x W x H) | ProductIntakeQueue record created with processing_status=`pending`, ai_analysis_status=`pending`, captured_by=user.id, captured_at set, organization_id and team_id from user context | DB: `SELECT id, processing_status, ai_analysis_status, barcode, captured_by FROM product_intake_queue WHERE barcode = '{barcode}' AND organization_id = '{org_id}'` -- Assert processing_status='pending' |
| 2 | System | Queue / ProcessQueueItem | ProcessQueueItem job dispatched and begins execution. Updates ai_analysis_status to `processing`, ai_started_at set, processing_attempts incremented. Checks organization.canCreateProducts() for SKU prefix | Queue item status transitions: ai_analysis_status = `processing`. If SKU prefix missing, processing_status set to `blocked_sku_prefix` and processing halts | DB: `SELECT ai_analysis_status, ai_started_at, processing_attempts FROM product_intake_queue WHERE id = '{queue_item_id}'` -- Assert ai_analysis_status='processing' |
| 3 | System | ProcessQueueItem (Step 1-2) | Supplier context analysis via SupplierPreferenceService (if supplier_id set). Physical measurements analysis via ShippingClassService (weight, dimensions -> shipping class, estimated cost, fragile/oversized flags) | supplierContext populated with reliability_boost, priority_boost, supplier_name. shippingData contains shipping_class (standard/oversize/freight), estimated_cost, fragile flag | Log: Check 'SIMPLIFIED: Enhanced processing started' log entry with comprehensive_data and physical_data fields |
| 4 | System | ProcessQueueItem (Step 3) | Barcode validation via BarcodeValidationService. Validates format (UPC-A, EAN-13, etc.), checks digit, returns formatted barcode | barcodeData with valid=true, type (UPC/EAN), formatted barcode. Source 'barcode_validation' added to sourcesUsed | Log: Check 'Barcode validated' log entry with type and formatted fields |
| 5 | System | ProcessQueueItem (Step 3.5) | Preliminary eBay category lookup via EbayCategoryService.findPreliminaryCategory() using brand + model + MPN. If found, fetches category aspects via getAspectsForPrompt() for AI context injection | ebayAspectsContext populated with category_id, category_name, aspects_prompt (text for AI), aspects_data (required item specifics). Source 'ebay_category_aspects' added. Preliminary category stored in enriched_data | Log: Check 'Preliminary eBay category found for AI prompting' with category_id and aspect_count |
| 6 | System | ProcessQueueItem (Step 4) | Product research via ProductResearchOrchestrator. Strategy selected: 'comprehensive' (if brand/MPN present), 'barcode_focused' (if only barcode), 'basic' (fallback). Orchestrator calls Gemini AI with product identifiers, photos, supplier context, and eBay aspects context | researchData returned with: product_identity (title, brand, model, description), market_pricing (suggested_price, price_range), category_suggestion, condition_assessment, item_specifics (eBay-format key/value pairs). Source added to sourcesUsed | Log: Check 'Product research successful' with strategy, data_source, has_pricing fields. DB: `SELECT enriched_data FROM product_intake_queue WHERE id = '{queue_item_id}'` -- Assert enriched_data contains product_identity |
| 7 | System | ProcessQueueItem (Step 5) | Photo processing via BackgroundRemovalService. Each photo in queue item's photos array is processed for background removal | processedPhotos array with original and processed URLs. Source 'background_removal' added if processing occurred | Log: Check photo processing entries. Verify processed photo URLs accessible |
| 8 | System | ProcessQueueItem (Steps 6-8) | Enhanced confidence and quality scoring calculated. AI analysis record created in product_ai_analyses table. Queue item updated with all results: enriched_data (full research + pricing + category), ai_confidence_score, quality_score, quality_breakdown, completeness_score, data_sources, primary_source, brand, keywords, ai_analysis_id, ai_analysis_status=`complete`, processing_status=`ready` or `needs_review` | ProductIntakeQueue fully enriched. processing_status = `ready` (if high confidence) or `needs_review` (if low confidence or missing required fields). ai_analysis_status = `complete`, ai_completed_at set | DB: `SELECT processing_status, ai_analysis_status, ai_confidence_score, quality_score, enriched_data, data_sources FROM product_intake_queue WHERE id = '{queue_item_id}'` -- Assert ai_analysis_status='complete', enriched_data NOT NULL, quality_score > 0 |
| 9 | Lister/Reviewer | Web UI | Reviews queue item on the intake review page. Verifies/edits: title, description, brand, condition, selling price, eBay category, item specifics, photos. Approves the item for product creation | User interacts with review wizard. Edits saved to queue item fields and enriched_data. User clicks "Approve and Create Product" | UI: Navigate to intake queue review page. Verify all AI-populated fields display correctly and are editable |
| 10 | System | Web / ProductIntakeQueue | Product created from queue item data. `ProductIntakeQueue::markAsCompleted()` called with the new Product and reviewing User. processing_status = `completed`, processed_by set, processed_at set, product_id linked | Product record created with: name (from AI title), description, sku (auto-generated via OrganizationSkuService), brand, condition, selling_price, available_quantity (from quick_quantity), organization_id, team_id. Queue item processing_status = `completed`, product_id = new product UUID | DB: `SELECT id, name, sku, brand, condition, selling_price, available_quantity FROM products WHERE id = '{product_id}'` -- Assert fields match approved data. `SELECT processing_status, product_id, processed_by, processed_at FROM product_intake_queue WHERE id = '{queue_item_id}'` -- Assert processing_status='completed', product_id NOT NULL |
| 11 | System | Queue / CreateEbayListingJob | CreateEbayListingJob dispatched for the queue item. 10-step process executes: (1) preflight validation (ProductDataValidator), (2) credential verification (isEbayReady), (3) status update, (4) product linkage, (5) eBay category resolution, (6) duplicate listing check, (7) inventory item data preparation, (8) offer data preparation (price, category, condition, policies), (9) EbayInventoryService.fullSync() which calls createOrUpdateInventoryItem -> createOffer -> publishOffer, (10) success handling | EbayListing record created/updated. Status transitions: pending -> inventory_created -> offer_created -> published. EbayListing fields populated: ebay_listing_id (eBay item number), ebay_listing_url, ebay_sku, ebay_offer_id. ProductIntakeQueue.processing_status updated to `listed_on_ebay` | DB: `SELECT status, ebay_listing_id, ebay_listing_url, ebay_sku, ebay_offer_id FROM ebay_listings WHERE product_id = '{product_id}'` -- Assert status='published', ebay_listing_id NOT NULL. `SELECT processing_status FROM product_intake_queue WHERE id = '{queue_item_id}'` -- Assert 'listed_on_ebay' |
| 12 | System | Queue / SyncProductToShopify | SyncProductToShopify job dispatched. Creates Shopify product via REST API with title, body_html (description), vendor (brand), product_type, variants (price, sku, inventory_management=shopify), images. Sets inventory level at primary location via ShopifyInventoryService.setInventoryLevel() | ShopifyListing record created with: shopify_product_id, shopify_variant_id, shopify_inventory_item_id, shopify_handle, shopify_sku, status=`active`, listed_price matching product selling_price, inventory_quantity matching available_quantity | DB: `SELECT status, shopify_product_id, shopify_variant_id, shopify_inventory_item_id, listed_price, inventory_quantity FROM shopify_listings WHERE product_id = '{product_id}'` -- Assert status='active', shopify_product_id NOT NULL. Shopify API: `GET /admin/api/products/{shopify_product_id}.json` -- Verify product exists |
| 13 | System | Database | Verify final Product state with all marketplace linkages and inventory | Product.available_quantity matches initial quantity. Product has associated EbayListing (published) and ShopifyListing (active). Product images stored. Product identifiers (UPC/barcode) stored in product_identifiers table | DB: `SELECT p.available_quantity, (SELECT COUNT(*) FROM ebay_listings WHERE product_id = p.id AND status = 'published') as ebay_count, (SELECT COUNT(*) FROM shopify_listings WHERE product_id = p.id AND status = 'active') as shopify_count FROM products p WHERE p.id = '{product_id}'` -- Assert ebay_count=1, shopify_count=1 |

---

## 4. Data Flow Verification

| Checkpoint | After Step | Expected State | Verification Query/Check |
|------------|-----------|----------------|--------------------------|
| Queue Item Exists | Step 1 | ProductIntakeQueue record with status=pending, barcode stored, captured_by set, organization_id correct | `SELECT id, processing_status, ai_analysis_status, barcode, captured_by, organization_id FROM product_intake_queue WHERE barcode = '{barcode}' ORDER BY created_at DESC LIMIT 1` |
| AI Research Complete | Step 8 | Queue item has enriched_data with product_identity (title, description, brand), market_pricing (suggested_price), category suggestion, item_specifics. ai_confidence_score > 0, quality_score > 0 | `SELECT enriched_data->'product_identity'->>'title' as title, ai_confidence_score, quality_score, completeness_score, array_length(data_sources, 1) as source_count FROM product_intake_queue WHERE id = '{queue_item_id}'` -- Assert title NOT NULL, scores > 0 |
| AI Analysis Record | Step 8 | ProductAiAnalysis record exists with analysis data, linked to queue item | `SELECT id, product_intake_queue_id FROM product_ai_analyses WHERE product_intake_queue_id = '{queue_item_id}'` -- Assert record exists |
| Product Created | Step 10 | Product record with UUID PK, all fields from enriched_data, sku auto-generated, organization-scoped | `SELECT id, name, sku, brand, condition, selling_price, available_quantity, organization_id FROM products WHERE id = '{product_id}'` -- Assert sku matches org prefix pattern, available_quantity = quick_quantity |
| eBay Listing Published | Step 11 | EbayListing.status = published, ebay_listing_id is valid eBay item number, ebay_offer_id set, listing URL accessible | `SELECT status, ebay_listing_id, ebay_offer_id, ebay_listing_url FROM ebay_listings WHERE product_id = '{product_id}' AND integration_credential_id = '{credential_id}'` -- Assert status='published'. HTTP GET ebay_listing_url -- Assert 200 (sandbox) |
| Shopify Listing Active | Step 12 | ShopifyListing.status = active, shopify_product_id is valid Shopify product ID, inventory set at location | `SELECT status, shopify_product_id, shopify_variant_id, shopify_inventory_item_id, inventory_quantity FROM shopify_listings WHERE product_id = '{product_id}'` -- Assert status='active', inventory_quantity = available_quantity |
| Inventory Initialized | Step 13 | Product.available_quantity matches initial quantity from intake. Both marketplace listings reflect this quantity | Compare Product.available_quantity with EbayListing available quantity (via eBay API) and ShopifyListing inventory_quantity. All three should match |

---

## 5. Error Scenarios

| ID | Error Point | Error Condition | Expected Recovery | Verified |
|----|-------------|-----------------|-------------------|----------|
| E2E-2-ERR-01 | Step 2 | Organization has no SKU prefix configured (canCreateProducts() returns false) | ProcessQueueItem sets processing_status to `blocked_sku_prefix`, adds validation_errors message, returns early without processing. Queue item stays in blocked state until org configures SKU prefix | [ ] |
| E2E-2-ERR-02 | Step 4 | Barcode is invalid format (wrong check digit, unsupported format) | BarcodeValidationService returns valid=false or throws Exception. Processing continues without barcode source. Warning added to warnings array. Queue item still processed with other data sources | [ ] |
| E2E-2-ERR-03 | Step 5 | eBay category lookup fails (API error, no matching category found) | Exception caught in try/catch (non-blocking). ebayAspectsContext stays null. AI research proceeds without eBay aspects context. Category assigned later during listing creation | [ ] |
| E2E-2-ERR-04 | Step 6 | Gemini AI API timeout or error (GEMINI_API_KEY invalid, rate limit, service down) | ProductResearchOrchestrator throws Exception, caught in Step 4 try/catch. Warning added. Processing continues with whatever data is available. Queue item may get lower confidence score and processing_status='needs_review' | [ ] |
| E2E-2-ERR-05 | Step 6 | Gemini returns minimal/empty research data (product not identifiable from barcode alone) | researchData has empty product_identity. Warnings added. Quality/confidence scores low. processing_status set to 'needs_review' for manual data entry. User must fill in title, description, pricing manually during review | [ ] |
| E2E-2-ERR-06 | Step 7 | Background removal service unreachable (Cloudflare tunnel down, GPU server offline) | BackgroundRemovalService throws Exception. Photos kept as-is (no background removal). Warning logged. Processing continues without 'background_removal' source | [ ] |
| E2E-2-ERR-07 | Step 11 | eBay credential not fully configured (missing business policies, no merchant_location_key) | CreateEbayListingJob Step 2 preflight fails: credential.isEbayReady() returns false. Exception thrown: "eBay credential is not fully configured." Job fails after retries. Queue item processing_status set to `listing_failed`. Admin notified | [ ] |
| E2E-2-ERR-08 | Step 11 | eBay rejects condition value for the selected category | EbayInventoryService.publishOffer() returns error. CreateEbayListingJob calls storeConditionValidationError() to fetch valid conditions and store them in enriched_data.condition_validation_error. Queue item gets processing_status='listing_failed' with condition guidance for manual fix | [ ] |
| E2E-2-ERR-09 | Step 11 | Duplicate eBay listing detected (same product already listed on this eBay account) | CreateEbayListingJob Step 6 finds existing active EbayListing for the product + credential. Job skips listing creation. Logs duplicate detection. Queue item processing_status stays at 'completed' (product created but not double-listed) | [ ] |
| E2E-2-ERR-10 | Step 11 | eBay API rate limit exceeded | EbayRateLimiter detects rate limit. CreateEbayListingJob has backoff=[30, 120, 300, 900, 1800] with tries=5. Job requeued with exponential backoff. If all 5 attempts fail, job moved to failed_jobs. Queue item status set to 'listing_failed' | [ ] |
| E2E-2-ERR-11 | Step 12 | Shopify API error during product creation (invalid data, API down) | SyncProductToShopify job fails. ShopifySyncLog entry created with status='error'. Job retried per queue config. ShopifyListing stays in status='pending' or 'error'. Product still exists and eBay listing unaffected | [ ] |
| E2E-2-ERR-12 | Step 8 | ProcessQueueItem job itself fails (unhandled exception) | Exception caught in outer try/catch. Queue item updated: ai_analysis_status='failed', processing_status='failed', enriched_data contains error message and failed_at timestamp. Exception re-thrown for queue retry (tries=3, backoff=[60, 300, 900]) | [ ] |

---

## 6. Performance Expectations

| Metric | Acceptable | Measured |
|--------|-----------|----------|
| Queue item creation (Step 1, web form submission) | < 1 second (DB insert + photo upload) | |
| ProcessQueueItem total execution (Steps 2-8) | < 180 seconds (job timeout=180s). Typical: 30-90 seconds depending on AI response time | |
| Gemini AI research response (Step 6) | < 30 seconds for comprehensive strategy, < 15 seconds for barcode_focused | |
| Background removal per photo (Step 7) | < 10 seconds per photo (GPU-accelerated) | |
| Confidence/quality scoring (Step 6) | < 1 second (local computation) | |
| CreateEbayListingJob total execution (Step 11) | < 300 seconds (job timeout=300s). Typical: 15-45 seconds for all 3 eBay API calls | |
| eBay fullSync (inventory + offer + publish) | < 30 seconds total for 3 sequential API calls | |
| Shopify product creation + inventory set (Step 12) | < 15 seconds (2 API calls) | |
| End-to-end (scan to published, Steps 1-13, excluding manual review time) | < 10 minutes for all automated steps | |

---

## 7. Cleanup / Rollback

| Step | Action |
|------|--------|
| 1 | Delete test ProductIntakeQueue record: `DELETE FROM product_intake_queue WHERE id = '{queue_item_id}'` |
| 2 | Delete ProductAiAnalysis record: `DELETE FROM product_ai_analyses WHERE product_intake_queue_id = '{queue_item_id}'` |
| 3 | If eBay listing was published: end listing via EbayInventoryService::endListing() to remove from eBay marketplace, then delete EbayListing record |
| 4 | If Shopify listing was created: delete product via Shopify Admin API `DELETE /admin/api/products/{shopify_product_id}.json`, then delete ShopifyListing record |
| 5 | Delete Product record (cascade deletes product_images, product_identifiers, inventory_levels) |
| 6 | Delete uploaded photos from storage (S3/local disk) |
| 7 | Clear any processed background-removal images from storage |
| 8 | Clear EbaySyncLog and ShopifySyncLog entries created during test |
| 9 | Clear failed_jobs table entries: `DELETE FROM failed_jobs WHERE payload LIKE '%{queue_item_id}%'` |
| 10 | Flush Redis queue: `php artisan queue:clear` (only in test environment) |

---

## Notes

- **Processing status state machine:** The ProductIntakeQueue processing_status follows: `pending` -> `processing` (implicit via ai_analysis_status) -> `ready` (high confidence) or `needs_review` (low confidence) -> `in_progress` (manual review started) -> `completed` (product created) -> `listed_on_ebay` (eBay listing published). Alternative paths: `failed` (processing error), `listing_failed` (eBay error), `blocked_sku_prefix` (org config missing), `archived` (manually archived).
- **Research strategy selection:** The ProcessQueueItem job selects strategy based on available data: `comprehensive` (brand/MPN/product_number present), `barcode_focused` (only barcode), `supplement_existing` (lookup_attempted with existing_product_data), `basic` (fallback). Strategy affects Gemini prompt construction and expected confidence levels.
- **eBay Offer API three-step process:** eBay requires sequential API calls: (1) PUT createOrReplaceInventoryItem (creates SKU-level inventory), (2) POST createOffer (creates marketplace-specific offer with category, price, policies), (3) POST publishOffer (makes offer live, returns eBay listing ID). Each step must succeed before the next. Status on EbayListing tracks progress: pending -> inventory_created -> offer_created -> published.
- **eBay condition validation:** eBay categories have specific allowed condition values. If the condition from AI research or user input is not valid for the selected category, publishOffer fails. The job stores the valid conditions in enriched_data for the user to select from.
- **Photo ordering:** Photos array index 0 is treated as the primary photo for both eBay gallery image and Shopify featured image. Subsequent photos are additional images.
- **UUID primary keys:** Product uses the HasUuids trait. All product_id references are UUID strings, not integers. Jobs that accept product IDs must use string type.
- **Queue priority:** ProcessQueueItem runs on the default queue. CreateEbayListingJob runs on the `ebay-sync` queue (or `ebay-test` in test mode). These may have different Horizon supervisors with different concurrency settings. eBay jobs may queue behind other eBay operations.
- **Idempotency:** If CreateEbayListingJob is retried, the fullSync method checks for existing ebay_offer_id before creating a new offer (uses updateOffer instead). This prevents duplicate offers on retry.
