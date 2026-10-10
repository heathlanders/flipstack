<?php

namespace App\Jobs;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductIntakeQueue;
use App\Models\Ebay\EbayListing;
use App\Models\Integration\IntegrationCredential;
use App\Models\User\User;
use App\Notifications\Ebay\EbayListingCompleted;
use App\Services\Ebay\EbayPolicyRulesEngine;
use App\Services\EbayCategoryService;
use App\Services\EbayInventoryService;
use App\Services\EbayRateLimiter;
use App\Services\NotificationService;
use App\Services\ProductDataValidator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * BULLETPROOF eBay Listing Job
 *
 * Features:
 * - Pre-validation before eBay API calls
 * - Intelligent retry logic with exponential backoff
 * - Comprehensive error categorization
 * - Rate limit aware processing
 * - Automatic fallback to manual review queue
 * - Detailed status tracking and notifications
 * - Transaction safety
 */
class CreateEbayListingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes

    public int $tries = 5; // More tries for better resilience

    public array $backoff = [30, 120, 300, 900, 1800]; // 30s, 2m, 5m, 15m, 30m

    protected ProductIntakeQueue $queueItem;

    protected bool $testMode;

    protected array $processingAttempts = [];

    public function __construct(ProductIntakeQueue $queueItem, bool $testMode = false)
    {
        $this->queueItem = $queueItem;
        $this->testMode = $testMode;

        // Set queue for processing
        $this->onQueue($testMode ? 'ebay-test' : 'ebay-sync');
    }

    public function handle(
        EbayRateLimiter $rateLimiter,
        ProductDataValidator $validator,
        NotificationService $notificationService,
        EbayCategoryService $categoryService
    ): void {
        $attemptNumber = $this->attempts();

        try {
            Log::info('Starting eBay listing job', [
                'queue_item_id' => $this->queueItem->id,
                'attempt' => $attemptNumber,
                'test_mode' => $this->testMode,
                'organization_id' => $this->queueItem->organization_id,
                'sku' => $this->queueItem->product_number,
            ]);

            // STEP 1: Pre-flight checks
            $this->performPreflightChecks($validator, $rateLimiter);

            // STEP 2: Get eBay credentials and verify they're properly configured
            // If the queue item specifies a credential, use it; otherwise use the first active one
            $credentialQuery = IntegrationCredential::where('organization_id', $this->queueItem->organization_id)
                ->where('platform', 'ebay')
                ->where('is_active', true);

            $preferredCredentialId = ($this->queueItem->enriched_data ?? [])['credential_id'] ?? null;
            if ($preferredCredentialId) {
                $credentialQuery->where('id', $preferredCredentialId);
            }

            $credential = $credentialQuery->first();

            if (! $credential) {
                throw new \Exception('No active eBay credentials found for organization');
            }

            if (! $credential->isEbayReady()) {
                throw new \Exception(
                    'eBay credentials not fully configured. Missing: '.
                    (! $credential->hasEbayPolicies() ? 'business policies ' : '').
                    (! $credential->hasMerchantLocation() ? 'merchant location' : '')
                );
            }

            // Instantiate the proper eBay Inventory Service
            $ebayInventoryService = new EbayInventoryService($credential);

            // STEP 3: Update status to in-progress
            $this->updateStatus('in_progress', [
                'started_at' => now(),
                'attempt' => $attemptNumber,
            ]);

            // STEP 4: Create product if not exists
            $product = $this->getOrCreateProduct();

            // STEP 5: Find eBay category — prefer pre-resolved value from wizard/processing
            $enrichedData = $this->queueItem->enriched_data ?? [];
            $preliminaryCategory = $enrichedData['preliminary_ebay_category'] ?? null;
            $mergedFields = $this->getMergedFields();

            if (! empty($preliminaryCategory['category_id'])) {
                $categoryResult = [
                    'category_id' => $preliminaryCategory['category_id'],
                    'name' => $preliminaryCategory['category_name'] ?? '',
                ];
            } else {
                $categorySearchData = [
                    'title' => $product->name,
                    'description' => $product->description ?? '',
                    'brand' => $mergedFields['product_identity']['brand'] ?? '',
                    'model' => $mergedFields['product_identity']['model'] ?? '',
                    'category' => $mergedFields['product_identity']['category'] ?? '',
                    'subcategory' => $mergedFields['product_identity']['subcategory'] ?? '',
                    'keywords' => $mergedFields['listing_optimization']['primary_keywords'] ?? [],
                ];
                $categoryResult = $categoryService->findBestCategory($categorySearchData);
            }

            $categoryId = $categoryResult['category_id'];

            $requiredSpecifics = $categoryService->getRequiredItemSpecifics($categoryId);

            $requiredCount = count(array_filter($requiredSpecifics, fn ($s) => ($s['cardinality'] ?? '') === 'REQUIRED'));
            Log::info('eBay category selected', [
                'category_id' => $categoryId,
                'category_name' => $categoryResult['name'] ?? 'unknown',
                'confidence' => $categoryResult['confidence'] ?? 0,
                'required_aspects_total' => count($requiredSpecifics),
                'required_aspects_mandatory' => $requiredCount,
            ]);

            // STEP 6: Check for existing active listing on any eBay account
            $existingListing = EbayListing::where('product_id', $product->id)
                ->whereNotIn('status', [EbayListing::STATUS_DELETED, EbayListing::STATUS_ENDED])
                ->where('integration_credential_id', '!=', $credential->id)
                ->first();

            if ($existingListing) {
                throw new \Exception(
                    'Product already has an active eBay listing on another account (credential #'.$existingListing->integration_credential_id.'). '.
                    'End or delete the existing listing first.'
                );
            }

            // Get or create EbayListing record
            $ebayListing = EbayListing::firstOrCreate(
                [
                    'product_id' => $product->id,
                    'integration_credential_id' => $credential->id,
                ],
                [
                    'team_id' => $credential->team_id,
                    'ebay_sku' => $product->sku,
                    'status' => EbayListing::STATUS_PENDING,
                ]
            );

            // STEP 7: Prepare product data for inventory item
            $productData = [
                'title' => $product->name,
                'description' => $product->description ?? $product->name,
                'brand' => $mergedFields['product_identity']['brand'] ?? null,
                'mpn' => $mergedFields['product_identity']['manufacturer_part_number'] ?? null,
                'model' => $mergedFields['product_identity']['model'] ?? null,
                'upc' => $mergedFields['product_identity']['upc'] ?? null,
                'ean' => $mergedFields['product_identity']['ean'] ?? null,
                'condition' => $this->mapConditionToEbay($product->condition),
                'condition_description' => $product->condition_description,
                'quantity' => $product->stock_quantity ?? 1,
                'images' => $product->gallery_images ?: ($this->queueItem->photos ?? []),
                'specifications' => $mergedFields['product_details']['specifications'] ?? [],
                'attributes' => $product->attributes ?? [],
                'aspects' => $this->extractItemSpecifics($mergedFields),
                'required_aspects' => $requiredSpecifics,
            ];

            // STEP 8: Prepare offer data
            $offerData = [
                'categoryId' => $categoryId,
                'price' => $product->selling_price,
                'quantity' => $product->stock_quantity ?? 1,
                'currency' => 'USD',
                'description' => $product->description,
                'format' => 'FIXED_PRICE',
            ];

            // STEP 8.5: Determine policies (user-selected via wizard or rules-based)
            $policyIds = $this->determinePolicyIds($credential, $product);

            // STEP 8.7: Validate condition against category-specific eBay policies
            $condition = $this->mapConditionToEbay($this->queueItem->condition ?? $product->condition);
            $validConditions = $categoryService->getValidConditionsForCategory(
                (string) $categoryId,
                $credential->marketplace_id ?? 'EBAY_US',
                $credential  // pass credential so user token is used (Sell Metadata API requires seller scope)
            );

            if (empty($validConditions)) {
                Log::warning('eBay condition pre-flight bypassed: no valid conditions returned', [
                    'queue_item_id' => $this->queueItem->id,
                    'condition' => $condition,
                    'category_id' => $categoryId,
                ]);
            }

            if (! empty($validConditions) && ! in_array($condition, $validConditions)) {
                $fallback = $this->findClosestValidCondition($condition, $validConditions);

                if ($fallback === null) {
                    $family = $this->getConditionFamily($condition);
                    $familyLabel = match ($family) {
                        'used' => 'Used',
                        'refurbished' => 'Refurbished',
                        'new' => 'New',
                        'parts' => 'For Parts',
                        default => $condition,
                    };
                    $validList = implode(', ', $validConditions);

                    // Store validation error so the wizard can surface it on retry
                    $this->queueItem->update([
                        'enriched_data' => array_merge($this->queueItem->enriched_data ?? [], [
                            'condition_validation_error' => [
                                'invalid_condition' => $condition,
                                'category_id' => $categoryId,
                                'valid_conditions' => $validConditions,
                            ],
                        ]),
                    ]);

                    throw new \Exception(
                        "Condition mismatch: eBay category {$categoryId} does not support {$familyLabel} conditions "
                        ."(valid: {$validList}). "
                        .'To fix this, open the verification wizard and select a different eBay category that matches your item.'
                    );
                }

                Log::warning('eBay condition auto-mapped within same family for category', [
                    'requested' => $condition,
                    'mapped_to' => $fallback,
                    'category' => $categoryId,
                    'valid_list' => $validConditions,
                ]);

                $condition = $fallback;
            }

            // Ensure productData uses the validated/mapped condition
            $productData['condition'] = $condition;

            // STEP 9: Run full sync (inventory item -> offer -> publish)
            $ebayListingId = $ebayInventoryService->fullSync($ebayListing, $productData, $offerData, $policyIds);

            // STEP 10: Handle success
            $listingResult = [
                'listing_id' => $ebayListingId,
                'sku' => $product->sku,
                'ebay_listing_id' => $ebayListing->ebay_listing_id,
                'ebay_url' => $ebayListing->ebay_listing_url,
            ];

            $this->handleSuccess($product, $listingResult);

            Log::info('eBay listing job completed successfully', [
                'queue_item_id' => $this->queueItem->id,
                'product_id' => $product->id,
                'ebay_listing_id' => $ebayListingId,
                'ebay_url' => $ebayListing->ebay_listing_url,
                'attempt' => $attemptNumber,
                'processing_time' => $this->getProcessingTime(),
            ]);

        } catch (\Exception $e) {
            $this->handleFailure($e, $attemptNumber, $rateLimiter, $notificationService);
        }
    }

    /**
     * Map internal condition to eBay condition enum for API
     */
    protected function mapConditionToEbay(?string $condition): string
    {
        $conditionMap = [
            // Full eBay enum values (what Product.condition stores)
            'NEW' => 'NEW',
            'NEW_OTHER' => 'NEW_OTHER',
            'NEW_WITH_DEFECTS' => 'NEW_WITH_DEFECTS',
            'MANUFACTURER_REFURBISHED' => 'MANUFACTURER_REFURBISHED',
            'CERTIFIED_REFURBISHED' => 'CERTIFIED_REFURBISHED',
            'USED_EXCELLENT' => 'USED_EXCELLENT',
            'USED_VERY_GOOD' => 'USED_VERY_GOOD',
            'USED_GOOD' => 'USED_GOOD',
            'USED_ACCEPTABLE' => 'USED_ACCEPTABLE',
            'FOR_PARTS_OR_NOT_WORKING' => 'FOR_PARTS_OR_NOT_WORKING',
            // Legacy shorthand values (kept for backwards compatibility)
            'new' => 'NEW',
            'excellent' => 'USED_EXCELLENT',
            'very_good' => 'USED_VERY_GOOD',
            'good' => 'USED_GOOD',
            'acceptable' => 'USED_ACCEPTABLE',
            'parts' => 'FOR_PARTS_OR_NOT_WORKING',
            'refurbished' => 'CERTIFIED_REFURBISHED',
        ];

        return $conditionMap[$condition] ?? $conditionMap[strtolower($condition ?? '')] ?? 'NEW';
    }

    /**
     * When eBay rejects a condition at publish time, fetch the category's valid
     * conditions and save them to enriched_data so the wizard can surface them.
     */
    private function storeConditionValidationError(string $ebayErrorMessage): void
    {
        try {
            $categoryId = ($this->queueItem->enriched_data ?? [])['preliminary_ebay_category']['category_id'] ?? null;

            if (! $categoryId) {
                return;
            }

            $credential = IntegrationCredential::where('organization_id', $this->queueItem->organization_id)
                ->where('platform', 'ebay')
                ->where('is_active', true)
                ->first();

            $categoryService = app(EbayCategoryService::class);
            $validConditions = $categoryService->getValidConditionsForCategory(
                (string) $categoryId,
                $credential->marketplace_id ?? 'EBAY_US',
                $credential
            );

            $this->queueItem->update([
                'enriched_data' => array_merge($this->queueItem->enriched_data ?? [], [
                    'condition_validation_error' => [
                        'invalid_condition' => $this->mapConditionToEbay(
                            $this->queueItem->condition ?? null
                        ),
                        'category_id' => $categoryId,
                        'valid_conditions' => $validConditions,
                        'ebay_error' => $ebayErrorMessage,
                    ],
                ]),
            ]);

            Log::info('Stored condition validation error for wizard', [
                'queue_item_id' => $this->queueItem->id,
                'category_id' => $categoryId,
                'valid_conditions' => $validConditions,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to store condition validation error', [
                'queue_item_id' => $this->queueItem->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Find the closest valid eBay condition for a category when the requested
     * condition is not in the category's allowed list.
     *
     * Only falls back within the same condition family (Used, Refurbished, New).
     * Cross-family mapping is never performed because it would misrepresent the
     * item (e.g. labelling a used item as refurbished).
     * Returns null when no within-family match exists.
     */
    protected function findClosestValidCondition(string $requested, array $validConditions): ?string
    {
        // Within-family adjacents only, ordered by proximity (prefer downgrade over upgrade)
        $adjacents = [
            // Used family
            'USED_EXCELLENT' => ['USED_VERY_GOOD', 'USED_GOOD', 'USED_ACCEPTABLE'],
            'USED_VERY_GOOD' => ['USED_GOOD', 'USED_EXCELLENT', 'USED_ACCEPTABLE'],
            'USED_GOOD' => ['USED_VERY_GOOD', 'USED_ACCEPTABLE', 'USED_EXCELLENT'],
            'USED_ACCEPTABLE' => ['USED_GOOD', 'USED_VERY_GOOD', 'USED_EXCELLENT'],
            // Refurbished family
            'EXCELLENT_REFURBISHED' => ['VERY_GOOD_REFURBISHED', 'GOOD_REFURBISHED'],
            'VERY_GOOD_REFURBISHED' => ['GOOD_REFURBISHED', 'EXCELLENT_REFURBISHED'],
            'GOOD_REFURBISHED' => ['VERY_GOOD_REFURBISHED', 'EXCELLENT_REFURBISHED'],
            'CERTIFIED_REFURBISHED' => ['MANUFACTURER_REFURBISHED', 'SELLER_REFURBISHED', 'EXCELLENT_REFURBISHED', 'VERY_GOOD_REFURBISHED', 'GOOD_REFURBISHED'],
            'MANUFACTURER_REFURBISHED' => ['CERTIFIED_REFURBISHED', 'SELLER_REFURBISHED', 'EXCELLENT_REFURBISHED', 'VERY_GOOD_REFURBISHED', 'GOOD_REFURBISHED'],
            'SELLER_REFURBISHED' => ['CERTIFIED_REFURBISHED', 'MANUFACTURER_REFURBISHED', 'EXCELLENT_REFURBISHED', 'VERY_GOOD_REFURBISHED', 'GOOD_REFURBISHED'],
            // New family
            'NEW' => ['NEW_OTHER', 'NEW_WITH_DEFECTS'],
            'NEW_OTHER' => ['NEW', 'NEW_WITH_DEFECTS'],
            'NEW_WITH_DEFECTS' => ['NEW_OTHER', 'NEW'],
            // Parts — no within-family fallback (only one condition in family)
            'FOR_PARTS_OR_NOT_WORKING' => [],
        ];

        $chain = $adjacents[$requested] ?? [];

        foreach ($chain as $candidate) {
            if (in_array($candidate, $validConditions)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Return the condition family name for a given eBay condition enum.
     */
    protected function getConditionFamily(string $condition): string
    {
        return match (true) {
            in_array($condition, ['NEW', 'NEW_OTHER', 'NEW_WITH_DEFECTS']) => 'new',
            in_array($condition, ['CERTIFIED_REFURBISHED', 'MANUFACTURER_REFURBISHED', 'SELLER_REFURBISHED', 'EXCELLENT_REFURBISHED', 'VERY_GOOD_REFURBISHED', 'GOOD_REFURBISHED']) => 'refurbished',
            in_array($condition, ['USED_EXCELLENT', 'USED_VERY_GOOD', 'USED_GOOD', 'USED_ACCEPTABLE']) => 'used',
            $condition === 'FOR_PARTS_OR_NOT_WORKING' => 'parts',
            default => 'unknown',
        };
    }

    /**
     * Determine which eBay policies to use for this listing.
     * Priority: 1) User-selected via wizard, 2) Rules-based, 3) Credential defaults
     */
    protected function determinePolicyIds(IntegrationCredential $credential, Product $product): array
    {
        $enrichedData = $this->queueItem->enriched_data ?? [];

        // Check for user-selected policies from wizard
        $selectedPolicies = $enrichedData['selected_policies'] ?? null;
        if ($selectedPolicies && ! empty($selectedPolicies['fulfillment'])) {
            Log::info('Using user-selected policies from wizard', [
                'queue_item_id' => $this->queueItem->id,
                'policies' => $selectedPolicies,
            ]);

            return [
                'fulfillment' => $selectedPolicies['fulfillment'],
                'payment' => $selectedPolicies['payment'],
                'return' => $selectedPolicies['return'],
            ];
        }

        // Try rules-based policy selection
        try {
            $rulesEngine = new EbayPolicyRulesEngine($credential);
            $productData = [
                'price' => $product->selling_price,
                'weight_lbs' => $this->queueItem->weight_lbs ?? $enrichedData['weight_lbs'] ?? null,
                'shipping_class' => $this->queueItem->suggested_shipping_class ?? $enrichedData['shipping_class'] ?? null,
                'condition' => $product->condition,
                'category' => $this->queueItem->product_category ?? $enrichedData['category'] ?? null,
                'estimated_shipping_cost' => $this->queueItem->estimated_shipping_cost ?? $enrichedData['estimated_shipping_cost'] ?? null,
            ];

            $applicablePolicies = $rulesEngine->getApplicablePolicies($productData);

            if ($applicablePolicies['matched_rule']) {
                Log::info('Using rules-based policies', [
                    'queue_item_id' => $this->queueItem->id,
                    'matched_rule' => $applicablePolicies['matched_rule']['name'],
                    'policies' => [
                        'fulfillment' => $applicablePolicies['fulfillment'],
                        'payment' => $applicablePolicies['payment'],
                        'return' => $applicablePolicies['return'],
                    ],
                ]);
            }

            return [
                'fulfillment' => $applicablePolicies['fulfillment'],
                'payment' => $applicablePolicies['payment'],
                'return' => $applicablePolicies['return'],
            ];

        } catch (\Exception $e) {
            Log::warning('Failed to evaluate policy rules, using defaults', [
                'queue_item_id' => $this->queueItem->id,
                'error' => $e->getMessage(),
            ]);

            // Fall back to credential defaults
            return [];
        }
    }

    /**
     * Pre-flight checks before attempting eBay listing
     */
    protected function performPreflightChecks(
        ProductDataValidator $validator,
        EbayRateLimiter $rateLimiter
    ): void {
        // Check if queue item is still in a valid state for listing
        $this->queueItem->refresh();

        // Accept 'ready', 'validated', 'completed' (if product was already created by wizard),
        // 'in_progress' (if job was retried), or 'retry_scheduled' (set by handleFailure for retry)
        $validStatuses = ['ready', 'validated', 'completed', 'in_progress', 'retry_scheduled'];
        if (! in_array($this->queueItem->processing_status, $validStatuses)) {
            throw new \Exception(
                "Queue item not in valid state for listing. Current status: {$this->queueItem->processing_status}"
            );
        }

        // Check eBay rate limits
        $rateLimitStatus = $rateLimiter->getStatus();

        if ($rateLimitStatus['daily']['remaining'] < 10) {
            throw new \Exception(
                "eBay daily rate limit nearly exhausted. Remaining: {$rateLimitStatus['daily']['remaining']}"
            );
        }

        if ($rateLimitStatus['daily']['percentage_used'] > 95) {
            throw new \Exception('eBay rate limit above 95%, delaying processing');
        }

        // Validate enriched data quality
        $enrichedData = $this->queueItem->enriched_data;
        $mergedFields = $this->getMergedFields();
        if (empty($enrichedData) || empty($mergedFields)) {
            throw new \Exception('No enriched product data available for listing');
        }

        // Check validation status if available
        if (! empty($enrichedData['validation']) && ! $enrichedData['validation']['is_complete']) {
            $missingFields = $enrichedData['validation']['missing_required'] ?? [];
            Log::warning('Product data incomplete but proceeding', [
                'missing_fields' => $missingFields,
                'quality_score' => $enrichedData['quality_score'] ?? 0,
            ]);
        }
    }

    /**
     * Get existing product or create new one
     */
    protected function getOrCreateProduct(): Product
    {
        // Check if product already exists
        if ($this->queueItem->product_id) {
            $product = Product::find($this->queueItem->product_id);
            if ($product) {
                Log::info('Using existing product', ['product_id' => $product->id]);

                return $product;
            }
        }

        // Create new product
        return DB::transaction(function () {
            return $this->createProductFromQueue();
        });
    }

    /**
     * Create product from queue data with bulletproof error handling
     */
    protected function createProductFromQueue(): Product
    {
        $mergedFields = $this->getMergedFields();

        // Generate unique SKU
        $sku = $this->generateUniqueSku();

        // Extract product data with fallbacks
        $productData = [
            'organization_id' => $this->queueItem->organization_id,
            'team_id' => $this->queueItem->team_id,
            'name' => $this->extractProductName($mergedFields),
            'slug' => $this->generateSlug($mergedFields),
            'description' => $this->extractDescription($mergedFields),
            'sku' => $sku,
            'selling_price' => $this->extractPrice($mergedFields),
            'condition' => $this->mapCondition($this->queueItem->condition ?? 'NEW'),
            'condition_description' => $this->queueItem->condition_description,
            'stock_quantity' => max(1, intval($this->queueItem->quick_quantity ?? 1)),
            'status' => 'active',
            'gallery_images' => $this->queueItem->photos ?? [],
            'attributes' => $this->buildProductAttributes($mergedFields),
            'created_by' => $this->queueItem->captured_by,
            'source_queue_item_id' => $this->queueItem->id,
        ];

        $product = Product::create($productData);

        // Update queue item with product reference
        $this->queueItem->update(['product_id' => $product->id]);

        Log::info('Product created from queue data', [
            'product_id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
        ]);

        return $product;
    }

    /**
     * Prepare eBay listing data from product
     */
    protected function prepareEbayData(Product $product): array
    {
        $mergedFields = $this->getMergedFields();

        return [
            'sku' => $product->sku,
            'name' => $product->name,
            'title' => $product->name,  // Validator expects 'title'
            'description' => $product->description,
            'price' => $product->selling_price,  // User-edited price from Product
            'condition' => $product->condition,
            'condition_description' => $product->condition_description,
            'stock_quantity' => $product->stock_quantity,
            'images' => $product->gallery_images ?: ($this->queueItem->photos ?? []),

            // eBay-specific data from enriched fields
            'brand' => $mergedFields['product_identity']['brand'] ?? null,
            'model' => $mergedFields['product_identity']['model'] ?? null,
            'mpn' => $mergedFields['product_identity']['manufacturer_part_number'] ?? null,
            'upc' => $mergedFields['product_identity']['upc'] ?? null,
            'ean' => $mergedFields['product_identity']['ean'] ?? null,
            'category' => $mergedFields['product_identity']['category'] ?? null,
            'seo_title' => $mergedFields['listing_optimization']['suggested_title'] ?? null,
            'key_features' => $mergedFields['product_details']['key_features'] ?? [],
            'specifications' => $mergedFields['product_details']['specifications'] ?? [],
            'keywords' => $mergedFields['listing_optimization']['primary_keywords'] ?? [],
            'bullet_points' => $mergedFields['listing_optimization']['bullet_points'] ?? [],
            'attributes' => $product->attributes,
        ];
    }

    /**
     * Handle successful listing creation
     */
    protected function handleSuccess(Product $product, array $listingResult): void
    {
        DB::transaction(function () use ($product, $listingResult) {
            $this->queueItem->update([
                'processing_status' => 'listed_on_ebay',
                'ebay_listing_id' => $listingResult['sku'] ?? $product->sku,
                'last_processed_at' => now(),
                'product_id' => $product->id,
                'processing_metadata' => [
                    'ebay_result' => $listingResult,
                    'listed_at' => now()->toIso8601String(),
                    'attempts' => $this->attempts(),
                    'processing_time_seconds' => $this->getProcessingTime(),
                ],
            ]);

            // Update product with eBay info
            $product->update([
                'ebay_listing_id' => $listingResult['sku'] ?? $product->sku,
                'ebay_listed_at' => now(),
                'listing_status' => 'active',
            ]);
        });

        // 🆕 NEW: Send success notification
        try {
            $user = $this->queueItem->capturedBy ?? $this->queueItem->createdBy ?? User::find($this->queueItem->captured_by);
            if ($user) {
                $user->notify(
                    new EbayListingCompleted($this->queueItem, [
                        'success' => true,
                        'listing_id' => $listingResult['listing_id'] ?? $listingResult['sku'] ?? $product->sku,
                        'sku' => $product->sku,
                        'attempt' => $this->attempts(),
                        'processing_time' => $this->getProcessingTime(),
                    ])
                );
            }

            Log::info('eBay listing notification sent', [
                'queue_item_id' => $this->queueItem->id,
                'product_id' => $product->id,
                'listing_id' => $listingResult['listing_id'] ?? $product->sku,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send eBay success notification', [
                'queue_item_id' => $this->queueItem->id,
                'error' => $e->getMessage(),
            ]);
            // Don't re-throw - the listing was successful
        }
    }

    /**
     * Handle job failures with intelligent retry logic
     */
    protected function handleFailure(
        \Exception $e,
        int $attemptNumber,
        EbayRateLimiter $rateLimiter,
        NotificationService $notificationService
    ): void {
        $errorMessage = $e->getMessage();
        $errorType = $this->categorizeError($e);

        Log::error('eBay listing job failed', [
            'queue_item_id' => $this->queueItem->id,
            'attempt' => $attemptNumber,
            'error_type' => $errorType,
            'error' => $errorMessage,
            'trace' => $e->getTraceAsString(),
        ]);

        // Record attempt
        $this->recordAttempt($errorType, $errorMessage);

        // Check if should retry based on error type and attempts
        if ($this->shouldRetry($errorType, $attemptNumber)) {
            Log::info('Will retry eBay listing job', [
                'queue_item_id' => $this->queueItem->id,
                'next_attempt' => $attemptNumber + 1,
                'error_type' => $errorType,
            ]);

            $this->updateStatus('retry_scheduled', [
                'last_error' => $errorMessage,
                'last_error_type' => $errorType,
                'retry_attempt' => $attemptNumber + 1,
                'retry_scheduled_for' => now()->addSeconds($this->backoff[$attemptNumber - 1] ?? 1800),
            ]);

            // Re-throw to trigger retry
            throw $e;
        }

        // No more retries - handle permanent failure
        $this->handlePermanentFailure($errorType, $errorMessage, $notificationService);
    }

    /**
     * Categorize errors for intelligent retry decisions
     */
    protected function categorizeError(\Exception $e): string
    {
        if ($e instanceof \App\Services\EbayApiException) {
            return match (true) {
                $e->getHttpStatusCode() === 400 => 'validation_error',
                $e->getHttpStatusCode() === 401 => 'authentication',
                $e->getHttpStatusCode() === 409 => 'conflict',
                $e->getHttpStatusCode() === 429 => 'rate_limit',
                $e->getHttpStatusCode() >= 500 => 'server_error',
                default => 'unknown_error',
            };
        }

        // Fall back to message text for non-eBay exceptions
        $message = strtolower($e->getMessage());
        if (str_contains($message, 'rate limit') || str_contains($message, '429')) {
            return 'rate_limit';
        }
        if (str_contains($message, 'authentication') || str_contains($message, '401')) {
            return 'authentication';
        }
        if (str_contains($message, 'server error') || str_contains($message, '500')
            || str_contains($message, '502') || str_contains($message, '503')) {
            return 'server_error';
        }
        if (str_contains($message, 'condition mismatch')) {
            return 'condition_mismatch';
        }
        if (str_contains($message, 'validation') || str_contains($message, 'missing')
            || str_contains($message, '400')) {
            return 'validation_error';
        }
        if (str_contains($message, 'conflict') || str_contains($message, '409')) {
            return 'conflict';
        }
        if (str_contains($message, 'timeout') || str_contains($message, 'connection')) {
            return 'network_error';
        }

        return 'unknown_error';
    }

    /**
     * Determine if should retry based on error type and attempt
     */
    protected function shouldRetry(string $errorType, int $attemptNumber): bool
    {
        // Never retry validation errors, condition mismatches, or conflicts
        if (in_array($errorType, ['validation_error', 'condition_mismatch', 'conflict'])) {
            return false;
        }

        // Don't exceed max attempts
        if ($attemptNumber >= $this->tries) {
            return false;
        }

        // Retry these error types
        return in_array($errorType, [
            'rate_limit',
            'authentication',
            'server_error',
            'network_error',
            'unknown_error',
        ]);
    }

    /**
     * Handle permanent failure
     */
    protected function handlePermanentFailure(
        string $errorType,
        string $errorMessage,
        NotificationService $notificationService
    ): void {
        $finalStatus = match ($errorType) {
            'validation_error', 'condition_mismatch' => 'needs_review',
            'conflict' => 'already_exists',
            default => 'listing_failed'
        };

        DB::transaction(function () use ($finalStatus, $errorType, $errorMessage) {
            $this->queueItem->update([
                'processing_status' => $finalStatus,
                'validation_errors' => [
                    'ebay_error' => $errorMessage,
                    'error_type' => $errorType,
                    'final_attempt' => $this->attempts(),
                    'failed_permanently_at' => now()->toIso8601String(),
                ],
                'last_processed_at' => now(),
                'processing_metadata' => [
                    'all_attempts' => $this->processingAttempts,
                    'total_attempts' => $this->attempts(),
                    'total_processing_time' => $this->getProcessingTime(),
                ],
            ]);

            // Detect condition-related eBay rejections and surface to wizard
            $lowerMsg = strtolower($errorMessage);
            $isConditionError = $errorType === 'condition_mismatch'
                || (str_contains($lowerMsg, 'condition')
                    && (str_contains($lowerMsg, 'invalid')
                        || str_contains($lowerMsg, 'not valid')
                        || str_contains($lowerMsg, 'mismatch')));

            if ($isConditionError) {
                $this->storeConditionValidationError($errorMessage);
            }
        });

        // Send notification for failed listing
        try {
            $this->notifyFailure($notificationService, $errorType, $errorMessage);
        } catch (\Exception $e) {
            Log::error('Failed to send failure notification', ['error' => $e->getMessage()]);
        }

        Log::error('eBay listing failed permanently', [
            'queue_item_id' => $this->queueItem->id,
            'final_status' => $finalStatus,
            'total_attempts' => $this->attempts(),
        ]);
    }

    // Helper methods...

    /**
     * Get merged product fields from enriched data.
     * Supports both 'merged_product_fields' (ProductEnrichmentService) and
     * 'research_data' (browser extension flow) as data sources.
     */
    protected function getMergedFields(): array
    {
        $enrichedData = $this->queueItem->enriched_data ?? [];

        return $enrichedData['merged_product_fields']
            ?? $enrichedData['research_data']
            ?? [];
    }

    /**
     * Extract item specifics from enriched data with priority:
     * 1. User-edited values from wizard
     * 2. AI-generated eBay-specific item specifics
     * 3. Empty (buildAspects will still map from specifications/attributes)
     */
    protected function extractItemSpecifics(array $mergedFields): array
    {
        $enrichedData = $this->queueItem->enriched_data ?? [];

        // Priority 1: User-edited item specifics from the wizard
        if (! empty($enrichedData['user_item_specifics'])) {
            return $enrichedData['user_item_specifics'];
        }

        // Priority 2: AI-generated eBay-specific item specifics (exact aspect names)
        $ebaySpecs = $mergedFields['listing_optimization']['platform_specific_tips']['ebay']['item_specifics'] ?? [];
        if (! empty($ebaySpecs)) {
            return $ebaySpecs;
        }

        // Priority 3: Empty -- buildAspects() will still map from specifications/attributes
        return [];
    }

    protected function generateUniqueSku(): string
    {
        $base = $this->queueItem->product_number ?? $this->queueItem->manufacturer_part_number ?? 'ITEM';
        $base = preg_replace('/[^a-zA-Z0-9\-]/', '', $base);

        $counter = 0;
        do {
            $sku = $counter > 0 ? "{$base}-{$counter}" : $base;
            $counter++;
        } while (Product::where('sku', $sku)->exists() && $counter < 100);

        return $sku;
    }

    protected function extractProductName(array $mergedFields): string
    {
        return $mergedFields['listing_optimization']['suggested_title']
            ?? $mergedFields['product_identity']['title']
            ?? $this->queueItem->manufacturer_part_number
            ?? 'Unknown Product';
    }

    protected function generateSlug(array $mergedFields): string
    {
        $name = $this->extractProductName($mergedFields);
        $slug = \Illuminate\Support\Str::slug($name);

        $counter = 0;
        do {
            $finalSlug = $counter > 0 ? "{$slug}-{$counter}" : $slug;
            $counter++;
        } while (Product::where('slug', $finalSlug)->exists() && $counter < 100);

        return $finalSlug;
    }

    protected function extractDescription(array $mergedFields): string
    {
        return $mergedFields['product_details']['description']
            ?? 'Product information available upon request.';
    }

    protected function extractPrice(array $mergedFields): float
    {
        return floatval(
            $mergedFields['market_pricing']['recommended_list_price']
            ?? $mergedFields['suggested_price']
            ?? 0
        );
    }

    protected function mapCondition(string $condition): string
    {
        $conditionMap = [
            'NEW' => 'new',
            'USED_EXCELLENT' => 'excellent',
            'USED_VERY_GOOD' => 'very_good',
            'USED_GOOD' => 'good',
            'USED_ACCEPTABLE' => 'acceptable',
            'FOR_PARTS_OR_NOT_WORKING' => 'parts',
        ];

        return $conditionMap[$condition] ?? 'new';
    }

    protected function buildProductAttributes(array $mergedFields): array
    {
        $attributes = [];

        if (! empty($mergedFields['product_identity'])) {
            $identity = $mergedFields['product_identity'];
            $attributes['brand'] = $identity['brand'] ?? null;
            $attributes['model'] = $identity['model'] ?? null;
            $attributes['mpn'] = $identity['manufacturer_part_number'] ?? null;
            $attributes['upc'] = $identity['upc'] ?? null;
            $attributes['category'] = $identity['category'] ?? null;
        }

        if (! empty($mergedFields['product_details']['specifications'])) {
            $attributes['specifications'] = $mergedFields['product_details']['specifications'];
        }

        return $attributes;
    }

    protected function updateStatus(string $status, array $metadata = []): void
    {
        $this->queueItem->update([
            'processing_status' => $status,
            'last_processed_at' => now(),
            'processing_metadata' => array_merge(
                $this->queueItem->processing_metadata ?? [],
                $metadata
            ),
        ]);
    }

    protected function recordAttempt(string $errorType, string $errorMessage): void
    {
        $this->processingAttempts[] = [
            'attempt' => $this->attempts(),
            'error_type' => $errorType,
            'error_message' => $errorMessage,
            'attempted_at' => now()->toIso8601String(),
        ];
    }

    protected function getProcessingTime(): int
    {
        $startTime = $this->queueItem->ai_started_at ?? $this->queueItem->created_at;

        return $startTime ? now()->diffInSeconds($startTime) : 0;
    }

    /**
     * Send failure notification to user
     */
    protected function notifyFailure(
        NotificationService $notificationService,
        string $errorType,
        string $errorMessage
    ): void {
        try {
            // 🆕 NEW: Send failure notification using EbayListingCompleted
            $user = $this->queueItem->capturedBy ?? $this->queueItem->createdBy ?? User::find($this->queueItem->captured_by);
            if ($user) {
                $user->notify(
                    new EbayListingCompleted($this->queueItem, [
                        'success' => false,
                        'error' => $errorMessage,
                        'error_type' => $errorType,
                        'attempt' => $this->attempts(),
                        'processing_time' => $this->getProcessingTime(),
                    ])
                );
            }

            Log::info('eBay failure notification sent', [
                'queue_item_id' => $this->queueItem->id,
                'error_type' => $errorType,
                'attempt' => $this->attempts(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send eBay failure notification', [
                'queue_item_id' => $this->queueItem->id,
                'notification_error' => $e->getMessage(),
                'original_error' => $errorMessage,
            ]);
        }
    }

    /**
     * Handle job failure (called by Laravel)
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('eBay listing job failed permanently (Laravel handler)', [
            'queue_item_id' => $this->queueItem->id,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);
    }
}
