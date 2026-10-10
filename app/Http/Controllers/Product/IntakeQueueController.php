<?php

namespace App\Http\Controllers\Product;

use App\Events\ExtensionResearchReceived;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessQueueItem;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductImage;
use App\Models\Catalog\ProductIntakeQueue;
use App\Models\Integration\IntegrationCredential;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\Supplier;
use App\Models\User\UserActivityLog;
use App\Services\EbayCategoryService;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\LabelingWorkflowService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class IntakeQueueController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private EbayCategoryService $categoryService) {}

    /**
     * Display the queue dashboard
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Handle users without a team (e.g., super_admin)
        if (! $user->currentTeam) {
            $teamId = null;
        } else {
            $teamId = $user->currentTeam->id;
        }

        $query = ProductIntakeQueue::with(['capturedBy', 'processedBy', 'aiAnalysis']);

        // Only filter by team if user has one
        if ($teamId) {
            $query->forTeam($teamId);
        }

        // Apply filters
        $status = $request->get('status', 'all');
        if ($status !== 'all') {
            match ($status) {
                'pending' => $query->pending(),
                'in_progress' => $query->inProgress(),
                'needs_review' => $query->where('processing_status', 'needs_review'),
                'needs_minor_review' => $query->where('processing_status', 'needs_minor_review'),
                'needs_major_review' => $query->where('processing_status', 'needs_major_review'),
                'ready' => $query->where('processing_status', 'ready'),
                'validated' => $query->where('processing_status', 'validated'),
                'completed' => $query->completed(),
                'archived' => $query->archived(),
                'failed' => $query->whereIn('processing_status', ['failed', 'listing_failed']),
                default => null,
            };
        }

        // Apply confidence filter
        $confidence = $request->get('confidence', 'all');
        if ($confidence !== 'all') {
            match ($confidence) {
                'high' => $query->highConfidence(),
                'medium' => $query->mediumConfidence(),
                'low' => $query->lowConfidence(),
                default => null,
            };
        }

        // Apply search
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('barcode', 'like', "%{$search}%")
                    ->orWhere('user_notes', 'like', "%{$search}%")
                    ->orWhereRaw('enriched_data::text LIKE ?', ["%{$search}%"]);
            });
        }

        // Ordering: explicit clickable-column sort, else priority default
        $sortable = [
            'captured' => 'captured_at',
            'status' => 'processing_status',
            'confidence' => 'ai_confidence_score',
            'barcode' => 'barcode',
        ];
        $sort = $request->get('sort');
        $direction = strtolower($request->get('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        if ($sort && isset($sortable[$sort])) {
            $query->orderBy($sortable[$sort], $direction);
        } else {
            $sort = null;
            $query->orderByPriority();
        }

        $items = $query
            ->paginate(20)
            ->withQueryString()
            ->through(function ($item) {
                return [
                    'id' => $item->id,
                    'captured_at' => $item->captured_at,
                    'captured_by' => $item->capturedBy->name,
                    'barcode' => $item->barcode,
                    'primary_photo' => $item->primary_photo,
                    'processing_status' => $item->processing_status,
                    'ai_analysis_status' => $item->ai_analysis_status,
                    'confidence_score' => $item->ai_confidence_score,
                    'confidence_level' => $item->confidence_level,
                    'confidence_color' => $item->confidence_color,
                    'status_badge' => $item->status_badge,
                    'preview_name' => ($item->enriched_data['merged_product_fields']['product_identity']['title'] ?? null)
                        ?: ($item->enriched_data['merged_product_fields']['name'] ?? null)
                        ?: (trim($item->brand.' '.$item->model_number) ?: 'Unnamed Product'),
                    'quick_quantity' => $item->quick_quantity,
                    'quick_location' => $item->quick_location,
                    'quick_location_id' => $item->quick_location_id,
                    'quick_bin_id' => $item->quick_bin_id,
                    'product_category' => $item->product_category,
                    'category_label' => $item->category_label,
                    'product_type' => $item->product_type,
                    'type_label' => $item->type_label,
                    'classification_context' => $item->classification_context,
                ];
            });

        // Get statistics (handle null teamId for super_admin)
        $statsQuery = ProductIntakeQueue::query();
        if ($teamId) {
            $statsQuery = ProductIntakeQueue::forTeam($teamId);
        }

        $stats = [
            'total' => (clone $statsQuery)->count(),
            'pending' => (clone $statsQuery)->pending()->count(),
            'in_progress' => (clone $statsQuery)->inProgress()->count(),
            'completed_today' => (clone $statsQuery)
                ->completed()
                ->whereDate('processed_at', today())
                ->count(),
            'high_confidence' => (clone $statsQuery)
                ->pending()
                ->highConfidence()
                ->count(),
        ];

        return Inertia::render('IntakeQueue/Index', [
            'items' => $items,
            'stats' => $stats,
            'filters' => [
                'status' => $status,
                'confidence' => $confidence,
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    /**
     * Show the create form
     */
    public function create(Request $request)
    {
        $orgId = auth()->user()->currentTeam?->organization_id ?? auth()->user()->current_organization_id ?? auth()->user()->organization_id;

        $locations = InventoryLocation::where('organization_id', $orgId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(function ($loc) {
                $loc->bins = \DB::table('warehouse_bins as wb')
                    ->join('warehouse_zones as wz', 'wz.id', '=', 'wb.zone_id')
                    ->where('wz.location_id', $loc->id)
                    ->where('wb.is_active', true)
                    ->orderBy('wz.code')->orderBy('wb.code')
                    ->select('wb.id', 'wb.code', 'wb.label', 'wz.code as zone_code', 'wz.name as zone_name')
                    ->get();

                return $loc;
            });

        return Inertia::render('IntakeQueue/Create', [
            'suppliers' => \App\Models\Inventory\Supplier::where('organization_id', $orgId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'locations' => $locations,
        ]);
    }

    /**
     * Show queue item for processing
     */
    public function show(Request $request, ProductIntakeQueue $queueItem)
    {
        // Ensure user has access to this team's data
        $this->authorize('view', $queueItem);

        $queueItem->load(['capturedBy', 'processedBy', 'aiAnalysis']);

        // Mark as in progress if pending
        if ($queueItem->processing_status === 'pending') {
            $queueItem->markAsInProgress($request->user());
        }

        // Get next queue item for "Save & Next" functionality
        $nextItem = ProductIntakeQueue::forTeam($queueItem->team_id)
            ->pending()
            ->where('id', '!=', $queueItem->id)
            ->orderByPriority()
            ->first();

        $orgId = $queueItem->organization_id;
        $locations = InventoryLocation::where('organization_id', $orgId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(function ($loc) {
                $loc->bins = \DB::table('warehouse_bins as wb')
                    ->join('warehouse_zones as wz', 'wz.id', '=', 'wb.zone_id')
                    ->where('wz.location_id', $loc->id)
                    ->where('wb.is_active', true)
                    ->orderBy('wz.code')->orderBy('wb.code')
                    ->select('wb.id', 'wb.code', 'wb.label', 'wz.code as zone_code')
                    ->get();

                return $loc;
            });

        return Inertia::render('IntakeQueue/Show', [
            'locations' => $locations,
            'queueItem' => [
                'id' => $queueItem->id,
                'captured_at' => $queueItem->captured_at,
                'captured_by' => $queueItem->capturedBy->name,
                'capture_method' => $queueItem->capture_method,
                'barcode' => $queueItem->barcode,
                'photos' => $queueItem->photo_urls,
                'user_notes' => $queueItem->user_notes,
                'quick_quantity' => $queueItem->quick_quantity,
                'quick_location' => $queueItem->quick_location,
                'quick_location_id' => $queueItem->quick_location_id,
                'quick_bin_id' => $queueItem->quick_bin_id,
                'quick_location_name' => $queueItem->quickLocation?->name,
                'quick_bin_code' => $queueItem->quickBin?->code,
                'ai_analysis_status' => $queueItem->ai_analysis_status,
                'ai_confidence_score' => $queueItem->ai_confidence_score,
                'confidence_level' => $queueItem->confidence_level,
                'enriched_data' => $queueItem->enriched_data,
                'processing_status' => $queueItem->processing_status,
                'product_category' => $queueItem->product_category,
                'category_label' => $queueItem->category_label,
                'product_type' => $queueItem->product_type,
                'type_label' => $queueItem->type_label,
                'classification_context' => $queueItem->classification_context,
                'condition' => $queueItem->condition,
                'condition_description' => $queueItem->condition_description,
            ],
            'aiAnalysis' => $queueItem->aiAnalysis ? [
                'extracted_text' => $queueItem->aiAnalysis->extracted_text,
                'detected_category' => $queueItem->aiAnalysis->detected_category,
                'detected_brand' => $queueItem->aiAnalysis->detected_brand,
                'detected_model' => $queueItem->aiAnalysis->detected_model,
                'ai_description' => $queueItem->aiAnalysis->ai_description,
                'ai_specifications' => $queueItem->aiAnalysis->ai_specifications,
                'confidence_score' => $queueItem->aiAnalysis->confidence_score,
            ] : null,
            'nextItemId' => $nextItem?->id,
        ]);
    }

    /**
     * Return extension data status as plain JSON (no Inertia, no 409).
     */
    public function extensionStatus(ProductIntakeQueue $queueItem): JsonResponse
    {
        $this->authorize('view', $queueItem);

        $marketPricing = $queueItem->enriched_data['research_data']['market_pricing'] ?? null;

        return response()->json([
            'has_extension_data' => ($marketPricing['source'] ?? null) === 'chrome_extension',
            'market_pricing' => $marketPricing,
        ]);
    }

    /**
     * Return valid eBay conditions for the queue item's preliminary category.
     * Uses the stored preliminary_ebay_category when available; falls back to a
     * live category lookup. Also surfaces any condition_validation_error stored
     * by a previous failed CreateEbayListingJob attempt.
     */
    public function getConditions(Request $request, ProductIntakeQueue $queueItem): JsonResponse
    {
        $this->authorize('view', $queueItem);

        $enrichedData = $queueItem->enriched_data ?? [];
        $categoryId = $enrichedData['preliminary_ebay_category']['category_id'] ?? null;

        // Re-resolve if AI identified a different category than the preliminary lookup
        $prelimName = $enrichedData['preliminary_ebay_category']['category_name'] ?? '';
        $aiCategory = $enrichedData['merged_product_fields']['product_identity']['category'] ?? '';
        if ($categoryId && $aiCategory && $prelimName && ! str_contains(strtolower($prelimName), strtolower($aiCategory))) {
            $categoryId = null;
        }

        if (! $categoryId) {
            $merged = $enrichedData['merged_product_fields'] ?? [];
            $identity = $merged['product_identity'] ?? [];

            // Broaden lookup: merged_product_fields first, then research_data / gemini paths
            $title = $merged['listing_optimization']['suggested_title']
                ?? $enrichedData['research_data']['product_identity']['title']
                ?? $enrichedData['product_data']['gemini_research']['product_identity']['title']
                ?? $request->query('title', '');

            $brand = $identity['brand']
                ?? $enrichedData['research_data']['product_identity']['brand']
                ?? $enrichedData['product_data']['gemini_research']['product_identity']['brand']
                ?? $request->query('brand', '');

            $model = $identity['model']
                ?? $enrichedData['research_data']['product_identity']['model']
                ?? $enrichedData['product_data']['gemini_research']['product_identity']['model']
                ?? $request->query('model', '');

            $categoryHint = $request->query('category_name', '');

            if ($title || $brand || $categoryHint) {
                try {
                    $result = $this->categoryService->findBestCategory([
                        'title' => $title ?: $categoryHint,
                        'brand' => $brand,
                        'model' => $model,
                        'category_name' => $categoryHint,
                    ]);
                    $categoryId = $result['category_id'] ?? null;

                    // Store the resolved category so CreateEbayListingJob can reuse it
                    if ($categoryId) {
                        $updated = $queueItem->enriched_data ?? [];
                        $updated['preliminary_ebay_category'] = [
                            'category_id' => $categoryId,
                            'category_name' => $result['name'] ?? $result['path'] ?? '',
                        ];
                        $queueItem->update(['enriched_data' => $updated]);
                    }
                } catch (\Exception $e) {
                    // non-blocking
                }
            }
        }

        if (! $categoryId) {
            return response()->json([
                'valid_conditions' => [],
                'category_id' => null,
                'condition_validation_error' => $enrichedData['condition_validation_error'] ?? null,
            ]);
        }

        $credential = IntegrationCredential::where('organization_id', $queueItem->organization_id)
            ->where('platform', 'ebay')
            ->where('is_active', true)
            ->first();

        $validConditions = $this->categoryService->getValidConditionsForCategory(
            (string) $categoryId,
            'EBAY_US',
            $credential
        );

        return response()->json([
            'category_id' => $categoryId,
            'valid_conditions' => $validConditions,
            'condition_validation_error' => $enrichedData['condition_validation_error'] ?? null,
        ]);
    }

    /**
     * Validate that the given condition is acceptable for the queue item's eBay category.
     * Returns null when valid (or when category info is unavailable).
     * Returns an error array when the condition is invalid with no within-family fallback.
     */
    private function validateConditionForDispatch(string $condition, ProductIntakeQueue $queueItem): ?array
    {
        $categoryId = ($queueItem->enriched_data ?? [])['preliminary_ebay_category']['category_id'] ?? null;

        if (! $categoryId) {
            return null;
        }

        $credential = IntegrationCredential::where('organization_id', $queueItem->organization_id)
            ->where('platform', 'ebay')
            ->where('is_active', true)
            ->first();

        $validConditions = $this->categoryService->getValidConditionsForCategory(
            (string) $categoryId,
            'EBAY_US',
            $credential
        );

        if (empty($validConditions) || in_array($condition, $validConditions)) {
            return null;
        }

        if ($this->categoryService->findClosestValidCondition($condition, $validConditions) !== null) {
            return null; // within-family auto-mapping will occur in the job
        }

        return [
            'message' => "The selected condition is not valid for this product's eBay category.",
            'valid_conditions' => $validConditions,
            'category_id' => $categoryId,
        ];
    }

    /**
     * Store new queue item (temporary web form for Phase 1)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            // Enhanced validation rules
            'supplier_id' => 'required|exists:suppliers,id',
            'product_category' => 'required|string|in:'.implode(',', array_keys(ProductIntakeQueue::PRODUCT_CATEGORIES)),
            'product_type' => 'required|string|in:'.implode(',', array_keys(ProductIntakeQueue::PRODUCT_TYPES)),
            'photos' => 'required|array|min:1|max:5',
            'photos.*' => 'image|max:10240',

            // Smart identifier lookup
            'primary_identifier' => 'required|string|max:255',
            'lookup_attempted' => 'nullable|in:true,false,1,0,""',
            'existing_product_data' => 'nullable|json',

            // Traditional fields (enhanced)
            'barcode' => 'nullable|string|max:13',
            'brand' => 'nullable|string|max:100',
            'manufacturer_part_number' => 'nullable|string|max:255',
            'product_number' => 'nullable|string|max:255',
            'model_number' => 'nullable|string|max:255',
            'series' => 'nullable|string|max:255',
            'keywords_text' => 'nullable|string|max:500',

            // Condition (NEW)
            'condition' => 'required|string|in:NEW,NEW_OTHER,NEW_WITH_DEFECTS,MANUFACTURER_REFURBISHED,CERTIFIED_REFURBISHED,USED_EXCELLENT,USED_VERY_GOOD,USED_GOOD,USED_ACCEPTABLE,FOR_PARTS_OR_NOT_WORKING',
            'condition_description' => 'nullable|string|max:2000',

            // Physical measurements (NEW)
            'weight_lbs' => 'nullable|numeric|between:0,999.99',
            'length_inches' => 'nullable|numeric|between:0,999.99',
            'width_inches' => 'nullable|numeric|between:0,999.99',
            'height_inches' => 'nullable|numeric|between:0,999.99',

            // Completeness (NEW)
            'completeness_data' => 'nullable|json',
            'missing_items_notes' => 'nullable|string|max:1000',

            // Enhanced quick fields
            'user_notes' => 'nullable|string|max:1000',
            'quick_quantity' => 'required|integer|min:1',
            'quick_location' => 'nullable|string|max:255',
            'quick_location_id' => 'nullable|uuid|exists:inventory_locations,id',
            'quick_bin_id' => 'nullable|uuid|exists:warehouse_bins,id',
            // Remove estimated_value since you don't want it
        ]);
        $validated['lookup_attempted'] = in_array($validated['lookup_attempted'], ['true', '1', true, 1]);

        // Upload photos (your existing logic)
        $photoPaths = [];
        foreach ($request->file('photos') as $photo) {
            $path = $photo->store('queue-photos', 'public');
            $photoPaths[] = $path;
        }

        // Enhanced capture method detection
        $captureMethod = $this->determineCaptureMethod($validated, $photoPaths);

        // Process keywords (your existing logic)
        $keywords = null;
        if (! empty($validated['keywords_text'])) {
            $keywords = array_map('trim', explode(',', $validated['keywords_text']));
            $keywords = array_filter($keywords);
        }

        // Create enhanced queue item
        $queueItem = ProductIntakeQueue::create([
            'team_id' => $request->user()->currentTeam?->id,
            'organization_id' => $request->user()->currentTeam?->organization_id ?? $request->user()->current_organization_id ?? $request->user()->organization_id,
            'captured_by' => $request->user()->id,
            'captured_at' => now(),
            'capture_method' => $captureMethod,
            'supplier_id' => $validated['supplier_id'],
            'product_category' => $validated['product_category'],
            'product_type' => $validated['product_type'],
            // Identifiers - enhanced logic with null safety
            'barcode' => $this->extractPrimaryIdentifier($validated['primary_identifier'], 'barcode') ?? ($validated['barcode'] ?? null),
            'manufacturer_part_number' => $this->extractPrimaryIdentifier($validated['primary_identifier'], 'manufacturer_part_number') ?? ($validated['manufacturer_part_number'] ?? null),
            'product_number' => $this->extractPrimaryIdentifier($validated['primary_identifier'], 'product_number') ?? ($validated['product_number'] ?? null),
            'brand' => $validated['brand'] ?? null,
            'model_number' => $validated['model_number'] ?? null,
            'series' => $validated['series'] ?? null,
            'keywords' => $keywords,

            // Condition (NEW)
            'condition' => $validated['condition'],
            'condition_description' => $validated['condition_description'] ?? null,

            // Physical measurements (NEW) - all optional
            'weight_lbs' => $validated['weight_lbs'] ?? null,
            'length_inches' => $validated['length_inches'] ?? null,
            'width_inches' => $validated['width_inches'] ?? null,
            'height_inches' => $validated['height_inches'] ?? null,

            // Completeness (NEW)
            'completeness_data' => ($validated['completeness_data'] ?? null) ? json_decode($validated['completeness_data'], true) : null,
            'missing_items_notes' => $validated['missing_items_notes'] ?? null,

            // Enhanced quick data
            'photos' => $photoPaths,
            'user_notes' => $validated['user_notes'] ?? null,
            'quick_quantity' => $validated['quick_quantity'],
            'quick_location' => $validated['quick_location'] ?? null,
            'quick_location_id' => $validated['quick_location_id'] ?? null,
            'quick_bin_id' => $validated['quick_bin_id'] ?? null,

            // Smart lookup data (NEW)
            'lookup_attempted' => $validated['lookup_attempted'] ?? false,
            'existing_product_data' => ($validated['existing_product_data'] ?? null) ? json_decode($validated['existing_product_data'], true) : null,

            // Enhanced priority calculation
            'priority' => $this->calculateEnhancedPriority($validated),

            // Status
            'processing_status' => 'pending',
            'ai_analysis_status' => 'pending',
        ]);

        // Dispatch your existing job
        ProcessQueueItem::dispatch($queueItem);

        return redirect()->route('intake-queue.show', $queueItem)
            ->with('success', 'Product added to queue for processing');
    }

    /**
     * Update queue item (save draft)
     */
    public function update(Request $request, ProductIntakeQueue $queueItem)
    {
        $this->authorize('update', $queueItem);

        $validated = $request->validate([
            'user_notes' => 'nullable|string',
            'quick_quantity' => 'nullable|integer|min:1',
            'quick_location' => 'nullable|string|max:255',
            'quick_location_id' => 'nullable|uuid|exists:inventory_locations,id',
            'quick_bin_id' => 'nullable|uuid|exists:warehouse_bins,id',
            'priority' => 'nullable|integer',
            'weight_lbs' => 'nullable|numeric|min:0|max:9999',
            'length_inches' => 'nullable|numeric|min:0|max:9999',
            'width_inches' => 'nullable|numeric|min:0|max:9999',
            'height_inches' => 'nullable|numeric|min:0|max:9999',
        ]);

        $queueItem->update($validated);

        UserActivityLog::log(
            'intake.updated',
            "Updated intake queue item #{$queueItem->id}",
            $request->user(),
            ['fields' => array_keys($validated), 'source' => 'web'],
            'info',
            'inventory',
        );

        return back()->with('success', 'Changes saved');
    }

    /**
     * Complete processing and create product
     */
    public function complete(Request $request, ProductIntakeQueue $queueItem)
    {
        $this->authorize('update', $queueItem);

        $validated = $request->validate([
            // Basic info
            'name' => 'required|string|max:500',
            'description' => 'nullable|string',
            'brand_id' => 'nullable|exists:brands,id',
            'category_id' => 'required|exists:categories,id',

            // Identifiers
            'sku' => 'required|string|unique:products,sku',
            'upc' => 'nullable|string|max:14',
            'ean' => 'nullable|string|max:14',
            'mpn' => 'nullable|string|max:100',

            // Inventory
            'quantity' => 'required|integer|min:0',

            // Pricing
            'cost_basis' => 'nullable|numeric|min:0',
            'list_price' => 'nullable|numeric|min:0',

            // Condition (accepts both simplified and eBay-style formats, plus underscore variants)
            'condition' => 'nullable|string|in:new,like_new,very_good,good,acceptable,used_good,used_very_good,used_excellent,used_acceptable,for_parts,NEW,NEW_OTHER,NEW_WITH_DEFECTS,MANUFACTURER_REFURBISHED,CERTIFIED_REFURBISHED,USED_EXCELLENT,USED_VERY_GOOD,USED_GOOD,USED_ACCEPTABLE,FOR_PARTS_OR_NOT_WORKING',
            'condition_notes' => 'nullable|string',
        ]);

        // Map condition to eBay format before creating product
        if (! empty($validated['condition'])) {
            $validated['condition'] = $this->mapConditionToEbayFormat($validated['condition']);
        }

        // Extract component_breakdown from AI research if available (for part-out feature)
        $componentBreakdown = $queueItem->enriched_data['component_breakdown']
            ?? $queueItem->enriched_data['research_data']['component_breakdown']
            ?? null;

        // Create product
        $product = Product::create(array_merge($validated, [
            'team_id' => $queueItem->team_id,
            'created_by' => $request->user()->id,
            'status' => $request->has('publish') ? 'active' : 'draft',
            'component_breakdown' => $componentBreakdown,
            'attributes' => $this->buildIntakeAttributes($queueItem, $validated['item_specifics'] ?? []),
        ]));

        $this->seedProductInventory(
            $product,
            (int) ($validated['quantity'] ?? 0),
            $queueItem->quick_location_id,
            $queueItem->quick_bin_id,
            $queueItem->quick_location
        );

        // Create product images from queue photos. Track the pristine
        // pre-background-removal original so the product hub can revert.
        if ($queueItem->hasPhotos()) {
            $originalPhotos = $queueItem->enriched_data['original_photos'] ?? [];
            foreach ($queueItem->photos as $index => $photoPath) {
                $originalUrl = $originalPhotos[$index] ?? $photoPath;
                ProductImage::create([
                    'product_id' => $product->id,
                    'url' => $photoPath,
                    'is_primary' => $index === 0,
                    'sort_order' => $index,
                    'metadata' => [
                        'original_url' => $originalUrl,
                        'bg_removed' => $originalUrl !== $photoPath,
                    ],
                ]);
            }
        }

        // Link AI analysis to product if exists
        if ($queueItem->aiAnalysis) {
            $queueItem->aiAnalysis->update(['product_id' => $product->id]);
        }

        // Mark queue item as completed
        $queueItem->markAsCompleted($product, $request->user());

        // Trigger labeling work order (uses 30-min batching)
        try {
            app(LabelingWorkflowService::class)->createFromIntakeComplete($product, $queueItem);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to create labeling WO from intake', [
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Redirect to next item or product
        if ($request->has('save_and_next') && $nextItem = $this->getNextQueueItem($queueItem)) {
            return redirect()->route('intake-queue.show', $nextItem)
                ->with('success', 'Product created successfully');
        }

        return redirect()->route('products.show', $product)
            ->with('success', 'Product created and published successfully');
    }

    /**
     * Save draft data from wizard
     */
    public function saveDraft(Request $request, ProductIntakeQueue $queueItem)
    {
        $this->authorize('update', $queueItem);

        // Save all wizard data to enriched_data
        $draftData = $request->all();

        $queueItem->update([
            'enriched_data' => array_merge(
                $queueItem->enriched_data ?? [],
                ['wizard_draft' => $draftData]
            ),
        ]);

        return back()->with('success', 'Draft saved successfully');
    }

    /**
     * Build durable Product.attributes from a queue item's AI research +
     * user-edited item specifics, so eBay/Shopify/Amazon listing CREATE and
     * UPDATE both read structured specifics from the Product (single source of
     * truth) rather than the transient queue enriched_data. Mirrors the shape
     * EbayInventoryService::buildAspects() consumes (top-level identifiers +
     * a nested specifications map).
     */
    protected function buildIntakeAttributes(ProductIntakeQueue $queueItem, array $userItemSpecifics = []): array
    {
        $enriched = $queueItem->enriched_data ?? [];
        $merged = $enriched['merged_product_fields'] ?? [];
        $identity = $merged['product_identity'] ?? [];

        $specifications = $merged['product_details']['specifications'] ?? [];
        $ebaySpecs = $merged['listing_optimization']['platform_specific_tips']['ebay']['item_specifics'] ?? [];
        $userSpecs = ! empty($userItemSpecifics)
            ? $userItemSpecifics
            : ($enriched['user_item_specifics'] ?? []);

        // Precedence: AI specifications < AI eBay specifics < user-edited specifics.
        $itemSpecifics = array_merge(
            is_array($specifications) ? $specifications : [],
            is_array($ebaySpecs) ? $ebaySpecs : [],
            is_array($userSpecs) ? $userSpecs : [],
        );

        return array_filter([
            'brand' => $identity['brand'] ?? null,
            'model' => $identity['model'] ?? null,
            'mpn' => $identity['manufacturer_part_number'] ?? ($identity['mpn'] ?? null),
            'upc' => $identity['upc'] ?? null,
            'ean' => $identity['ean'] ?? null,
            'category' => $identity['category'] ?? null,
            'specifications' => $itemSpecifics ?: null,
        ], fn ($v) => $v !== null && $v !== []);
    }

    /**
     * Publish listing from wizard
     */
    public function publish(Request $request, ProductIntakeQueue $queueItem)
    {
        $this->authorize('update', $queueItem);

        $validated = $request->validate([
            // Product Identity
            'name' => 'required|string|max:500',
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'mpn' => 'nullable|string|max:100',
            'upc' => 'nullable|string|max:14',
            'ean' => 'nullable|string|max:14',
            'category_id' => 'nullable|exists:categories,id',

            // Inventory
            'quantity' => 'required|integer|min:0',
            'quick_location_id' => 'nullable|uuid|exists:inventory_locations,id',
            'quick_bin_id' => 'nullable|uuid|exists:warehouse_bins,id',

            // Pricing
            'cost_basis' => 'nullable|numeric|min:0',
            'list_price' => 'required|numeric|min:0',

            // Listing Content
            'listing_title' => 'required|string|max:500',
            'listing_description' => 'nullable|string',
            'bullet_points' => 'nullable|array',
            'keywords' => 'nullable|array',
            'item_specifics' => 'nullable|array',

            // Condition (accepts both simplified and eBay-style formats, plus underscore variants)
            'condition' => 'required|string|in:new,like_new,very_good,good,acceptable,used_good,used_very_good,used_excellent,used_acceptable,for_parts,NEW,NEW_OTHER,NEW_WITH_DEFECTS,MANUFACTURER_REFURBISHED,CERTIFIED_REFURBISHED,USED_EXCELLENT,USED_VERY_GOOD,USED_GOOD,USED_ACCEPTABLE,FOR_PARTS_OR_NOT_WORKING',
            'condition_notes' => 'nullable|string',

            // Publishing
            'publish_to' => 'required|string|in:ebay,amazon,newegg',
            'selected_photos' => 'nullable|array',
        ]);

        // Map condition to eBay format
        $validated['condition'] = $this->mapConditionToEbayFormat($validated['condition']);

        // Sanitize barcodes — strip any non-numeric placeholder text
        $validated['upc'] = $this->sanitizeBarcode($validated['upc'] ?? null);
        $validated['ean'] = $this->sanitizeBarcode($validated['ean'] ?? null);

        // Extract component_breakdown from AI research if available (for part-out feature)
        $componentBreakdown = $queueItem->enriched_data['component_breakdown']
            ?? $queueItem->enriched_data['research_data']['component_breakdown']
            ?? null;

        // Allow publish-time override of intake-captured location/bin
        $resolvedLocationId = $validated['quick_location_id'] ?? $queueItem->quick_location_id;
        $resolvedBinId = $validated['quick_bin_id'] ?? $queueItem->quick_bin_id;

        if ($resolvedLocationId !== $queueItem->quick_location_id || $resolvedBinId !== $queueItem->quick_bin_id) {
            $queueItem->update([
                'quick_location_id' => $resolvedLocationId,
                'quick_bin_id' => $resolvedBinId,
            ]);
        }

        // Create product record (Product has no `location` column — see InventoryLevel for location/bin)
        $product = Product::create([
            'team_id' => $queueItem->team_id,
            'sku' => $this->generateSku($validated, $queueItem->organization_id),
            'upc' => $validated['upc'],
            'ean' => $validated['ean'],
            'mpn' => $validated['mpn'] ?? null,
            'name' => $validated['name'],
            'description' => $validated['listing_description'] ?? null,
            'quantity' => $validated['quantity'],
            'cost_basis' => $validated['cost_basis'] ?? null,
            'list_price' => $validated['list_price'],
            'condition' => $validated['condition'],
            'condition_notes' => $validated['condition_notes'] ?? null,
            'created_by' => $request->user()->id,
            'status' => 'active',
            'component_breakdown' => $componentBreakdown,
            'attributes' => $this->buildIntakeAttributes($queueItem, $validated['item_specifics'] ?? []),
        ]);

        $this->seedProductInventory(
            $product,
            (int) ($validated['quantity'] ?? 0),
            $resolvedLocationId,
            $resolvedBinId,
            $queueItem->quick_location
        );

        // Add product images from selected photos
        if (! empty($validated['selected_photos'])) {
            foreach ($validated['selected_photos'] as $index => $photoUrl) {
                // Check if it's a queue photo (storage path)
                $photoPath = $this->resolvePhotoPath($photoUrl, $queueItem);

                ProductImage::create([
                    'product_id' => $product->id,
                    'url' => $photoPath,
                    'is_primary' => $index === 0,
                    'sort_order' => $index,
                ]);
            }
        }

        // Store user-edited listing data in enriched_data (Product has no metadata column)
        $enrichedData = $queueItem->enriched_data ?? [];
        $enrichedData['user_item_specifics'] = $validated['item_specifics'] ?? [];
        $enrichedData['user_listing_data'] = [
            'listing_title' => $validated['listing_title'],
            'bullet_points' => $validated['bullet_points'] ?? [],
            'keywords' => $validated['keywords'] ?? [],
            'target_platform' => $validated['publish_to'],
        ];
        $queueItem->update(['enriched_data' => $enrichedData]);

        // Link AI analysis to product if exists
        if ($queueItem->aiAnalysis) {
            $queueItem->aiAnalysis->update(['product_id' => $product->id]);
        }

        // Mark queue item as completed
        $queueItem->markAsCompleted($product, $request->user());

        // Dispatch platform-specific listing creation
        $publishMessage = 'Product created successfully!';
        if ($validated['publish_to'] === 'ebay') {
            \App\Jobs\CreateEbayListingJob::dispatch($queueItem);
            $publishMessage = 'Product created and eBay listing queued for creation!';
        } elseif ($validated['publish_to'] === 'shopify') {
            $credential = IntegrationCredential::where('organization_id', $queueItem->organization_id)
                ->where('marketplace', 'shopify')
                ->where('is_active', true)
                ->first();

            if ($credential) {
                \App\Jobs\Shopify\SyncProductToShopify::dispatch($product, $credential);
                $publishMessage = 'Product created and Shopify listing queued for creation!';
            }
        }

        return redirect()->route('inventory.products.show', $product)
            ->with('success', $publishMessage);
    }

    /**
     * Generate SKU for new product, ensuring uniqueness within the organization.
     */
    private function sanitizeBarcode(?string $value): ?string
    {
        $cleaned = preg_replace('/[^0-9]/', '', (string) $value);

        return ! empty($cleaned) ? $cleaned : null;
    }

    private function generateSku(array $data, ?int $organizationId): string
    {
        $baseSku = $this->generateBaseSku($data);

        // If no organization_id, skip uniqueness check (legacy data)
        if ($organizationId === null) {
            return $baseSku;
        }

        // Check if SKU already exists for this organization
        if (! Product::where('organization_id', $organizationId)->where('sku', $baseSku)->exists()) {
            return $baseSku;
        }

        // SKU exists - append incrementing suffix until unique
        $counter = 2;
        do {
            $sku = $baseSku.'-'.$counter;
            $counter++;
        } while (Product::where('organization_id', $organizationId)->where('sku', $sku)->exists() && $counter < 100);

        return $sku;
    }

    /**
     * Generate base SKU from product data (without uniqueness check).
     */
    private function generateBaseSku(array $data): string
    {
        // Use UPC if available
        if (! empty($data['upc'])) {
            return 'UPC-'.$data['upc'];
        }

        // Use model if available
        if (! empty($data['model'])) {
            return strtoupper(Str::slug($data['model'], '-'));
        }

        // Generate random SKU
        return 'AUTO-'.Str::upper(Str::random(8));
    }

    /**
     * Resolve photo path from queue item photos
     */
    private function resolvePhotoPath(string $photoUrl, ProductIntakeQueue $queueItem): string
    {
        // If it's already a storage path, return it
        if (Str::startsWith($photoUrl, 'queue-photos/')) {
            return $photoUrl;
        }

        // If it's a full URL, try to find matching photo in queue
        foreach ($queueItem->photos as $queuePhoto) {
            if (Str::contains($photoUrl, basename($queuePhoto))) {
                return $queuePhoto;
            }
        }

        // Default: return original URL
        return $photoUrl;
    }

    /**
     * Retry a failed queue item
     */
    public function retry(Request $request, ProductIntakeQueue $queueItem)
    {
        $this->authorize('update', $queueItem);

        if ($queueItem->processing_status !== 'failed') {
            return back()->with('error', 'Only failed items can be retried.');
        }

        $enrichedData = $queueItem->enriched_data ?? [];
        unset($enrichedData['error'], $enrichedData['error_message'], $enrichedData['failure_reason']);

        $queueItem->update([
            'processing_status' => 'pending',
            'ai_analysis_status' => 'pending',
            'enriched_data' => $enrichedData,
        ]);

        ProcessQueueItem::dispatch($queueItem);

        return back()->with('success', 'Item has been queued for reprocessing.');
    }

    /**
     * Re-run AI research with user-provided corrections
     */
    public function reresearch(Request $request, ProductIntakeQueue $queueItem)
    {
        $this->authorize('update', $queueItem);

        $validated = $request->validate([
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'upc' => 'nullable|string|max:14',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Update queue item fields with corrections
        $updates = [
            'processing_status' => 'pending',
            'ai_analysis_status' => 'pending',
        ];

        if (! empty($validated['brand'])) {
            $updates['brand'] = $validated['brand'];
        }
        if (! empty($validated['model'])) {
            $updates['model_number'] = $validated['model'];
        }
        if (! empty($validated['upc'])) {
            $updates['barcode'] = $validated['upc'];
        }
        if (! empty($validated['notes'])) {
            $updates['user_notes'] = $validated['notes'];
        }

        // Clear stale research data but keep photos and capture metadata
        $enrichedData = $queueItem->enriched_data ?? [];
        unset(
            $enrichedData['research_data'],
            $enrichedData['product_data'],
            $enrichedData['confidence_breakdown'],
            $enrichedData['sources_used'],
            $enrichedData['warnings'],
            $enrichedData['merged_product_fields'],
            $enrichedData['error'],
            $enrichedData['error_message'],
            $enrichedData['failure_reason']
        );
        $enrichedData['correction_history'][] = [
            'corrected_at' => now()->toISOString(),
            'corrected_by' => $request->user()->id,
            'corrections' => $validated,
        ];
        $updates['enriched_data'] = $enrichedData;

        $queueItem->update($updates);

        // Dispatch the processing job
        ProcessQueueItem::dispatch($queueItem);

        return response()->json([
            'success' => true,
            'message' => 'Re-research started with corrections.',
        ]);
    }

    /**
     * Bulk approve high-confidence items
     */
    public function bulkApprove(Request $request)
    {
        $validated = $request->validate([
            'item_ids' => 'required|array',
            'item_ids.*' => 'exists:product_intake_queue,id',
        ]);

        $teamId = $request->user()->currentTeam->id;
        $processed = 0;
        $errors = [];

        foreach ($validated['item_ids'] as $itemId) {
            $queueItem = ProductIntakeQueue::find($itemId);

            // Verify team access
            if ($queueItem->team_id !== $teamId) {
                continue;
            }

            // Only approve high-confidence items
            if ($queueItem->confidence_level !== 'high') {
                $errors[] = "Item #{$itemId} has low confidence and was skipped";

                continue;
            }

            try {
                $this->autoCreateProduct($queueItem, $request->user());
                $processed++;
            } catch (\Exception $e) {
                $errors[] = "Item #{$itemId} failed: ".$e->getMessage();
            }
        }

        $message = "Processed {$processed} items";
        if (! empty($errors)) {
            $message .= '. Errors: '.implode(', ', $errors);
        }

        return redirect()->route('intake-queue.index')
            ->with('success', $message);
    }

    /**
     * Auto-create product from high-confidence queue item
     */
    private function autoCreateProduct(ProductIntakeQueue $queueItem, $user): Product
    {
        $enriched = $queueItem->enriched_data['merged_product_fields'] ?? [];

        // Product has no `location` column; physical placement lives on InventoryLevel.location_id/bin_id.
        $product = Product::create([
            'team_id' => $queueItem->team_id,
            'sku' => 'AUTO-'.Str::upper(Str::random(8)),
            'upc' => $enriched['upc'] ?? null,
            'ean' => $enriched['ean'] ?? null,
            'name' => $enriched['name'] ?? 'Imported Product',
            'description' => $enriched['description'] ?? null,
            'quantity' => $queueItem->quick_quantity ?? 1,
            'created_by' => $user->id,
            'status' => 'active',
            'attributes' => $this->buildIntakeAttributes($queueItem),
        ]);

        $this->seedProductInventory(
            $product,
            (int) ($queueItem->quick_quantity ?? 1),
            $queueItem->quick_location_id,
            $queueItem->quick_bin_id,
            $queueItem->quick_location
        );

        // Add images
        if ($queueItem->hasPhotos()) {
            foreach ($queueItem->photos as $index => $photoPath) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'url' => $photoPath,
                    'is_primary' => $index === 0,
                    'sort_order' => $index,
                ]);
            }
        }

        $queueItem->markAsCompleted($product, $user);

        return $product;
    }

    /**
     * Get next pending queue item
     */
    private function getNextQueueItem(ProductIntakeQueue $currentItem): ?ProductIntakeQueue
    {
        return ProductIntakeQueue::forTeam($currentItem->team_id)
            ->pending()
            ->where('id', '!=', $currentItem->id)
            ->orderByPriority()
            ->first();
    }

    /**
     * Seed an inventory_levels row for a freshly intaken product so the order
     * router (FulfillmentRouter + AutoAssignOrders) and the pick path have
     * something to bind to. Resolution order for location:
     *   1. explicit $locationId (intake FK)
     *   2. resolve $freetextLocation against InventoryLocation.code/name within org
     *   3. derive from $binId -> bin.zone.location_id
     *   4. fall back to org's first active InventoryLocation (logs a warning)
     *
     * $binId is persisted on inventory_levels.bin_id so pickers see exact bin.
     */
    private function seedProductInventory(
        Product $product,
        int $quantity,
        ?string $locationId = null,
        ?string $binId = null,
        ?string $freetextLocation = null
    ): void {
        if (! $locationId && $freetextLocation) {
            $locationId = InventoryLocation::where('organization_id', $product->organization_id)
                ->where('is_active', true)
                ->where(function ($q) use ($freetextLocation) {
                    $q->whereRaw('LOWER(code) = ?', [mb_strtolower($freetextLocation)])
                        ->orWhereRaw('LOWER(name) = ?', [mb_strtolower($freetextLocation)]);
                })
                ->value('id');
        }

        if (! $locationId && $binId) {
            $locationId = \DB::table('warehouse_bins as wb')
                ->join('warehouse_zones as wz', 'wz.id', '=', 'wb.zone_id')
                ->where('wb.id', $binId)
                ->value('wz.location_id');
        }

        if (! $locationId) {
            $locationId = InventoryLocation::where('organization_id', $product->organization_id)
                ->where('is_active', true)
                ->orderBy('created_at')
                ->value('id');

            if ($locationId) {
                Log::info('Intake: location FK absent — defaulted to first active inventory location', [
                    'product_id' => $product->id,
                    'organization_id' => $product->organization_id,
                    'freetext_quick_location' => $freetextLocation,
                ]);
            }
        }

        if (! $locationId) {
            Log::warning('Intake: no active inventory location to seed; product has no location', [
                'product_id' => $product->id,
                'organization_id' => $product->organization_id,
            ]);

            return;
        }

        try {
            $reason = $quantity > 0 ? 'Initial intake' : 'Initial intake (zero qty placeholder)';
            app(InventoryService::class)->adjustStock(
                $product->id,
                $locationId,
                $quantity,
                $reason
            );

            if ($binId) {
                \App\Models\Inventory\InventoryLevel::where('product_id', $product->id)
                    ->where('location_id', $locationId)
                    ->update(['bin_id' => $binId]);
            }
        } catch (\Throwable $e) {
            Log::warning('Intake: failed to seed inventory level', [
                'product_id' => $product->id,
                'location_id' => $locationId,
                'bin_id' => $binId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Detect product type from user notes
     */
    protected function detectProductType(?string $userNotes): string
    {
        if (! $userNotes) {
            return 'complete_unit';
        }

        $notes = strtolower($userNotes);

        // Check for COMPLETE UNIT FIRST (highest priority)
        if (preg_match('/\b(complete|full|entire|whole|unit|system|terminal|assembled)\b/', $notes)) {
            return 'complete_unit';
        }

        // Then check for parts (only if not complete unit)
        if (preg_match('/\b(part|component|board|motherboard|replacement|spare|module|card)\b/', $notes)) {
            return 'replacement_part';
        }

        // Finally check for accessories
        if (preg_match('/\b(accessory|cable|adapter|mount|stand|bracket|cover)\b/', $notes)) {
            return 'accessory';
        }

        return 'complete_unit'; // Default
    }

    // =============================================
    // MOBILE API METHODS - ADD THESE TO YOUR EXISTING IntakeQueueController.php
    // Add before the closing } of the class
    // =============================================

    /**
     * Mobile-optimized quick product capture
     */
    public function mobileStore(Request $request)
    {
        $validated = $request->validate([
            'identifiers' => 'required|array|min:1',
            'identifiers.*.type' => 'required|string|max:100',
            'identifiers.*.value' => 'required|string|max:500',
            'identifiers.*.source' => 'nullable|string|max:100',
            'identifiers.*.is_primary' => 'nullable|boolean',
            'product_category' => 'required|string|in:'.implode(',', array_keys(ProductIntakeQueue::PRODUCT_CATEGORIES)),
            'product_type' => 'required|string|in:'.implode(',', array_keys(ProductIntakeQueue::PRODUCT_TYPES)),
            'supplier_id' => 'required|exists:suppliers,id',
            'condition' => 'required|string|in:'.implode(',', array_keys(ProductIntakeQueue::CONDITIONS)),
            'condition_description' => 'nullable|string|max:2000',
            'brand' => 'nullable|string|max:100',
            'model_number' => 'nullable|string|max:255',
            'series' => 'nullable|string|max:255',
            'keywords_text' => 'nullable|string|max:500',
            'user_notes' => 'required|string|max:1000',
            'quick_quantity' => 'nullable|integer|min:1|max:10000',
            'quick_location' => 'nullable|string|max:255',
            'quick_location_id' => 'nullable|uuid|exists:inventory_locations,id',
            'quick_bin_id' => 'nullable|uuid|exists:warehouse_bins,id',

            'photos' => 'nullable|array|max:24',
            'photos.*' => 'file|image|max:10240', // 10MB max per photo
        ]);

        try {
            // Process identifiers to match your current structure
            $primaryIdentifier = collect($validated['identifiers'])->firstWhere('is_primary', true)
                               ?? $validated['identifiers'][0];

            $identifierData = [];
            foreach ($validated['identifiers'] as $identifier) {
                if ($identifier['type'] === 'barcode' || $identifier['type'] === 'upc' || $identifier['type'] === 'ean') {
                    $identifierData['barcode'] = $identifier['value'];
                } elseif ($identifier['type'] === 'manufacturer_part_number') {
                    $identifierData['manufacturer_part_number'] = $identifier['value'];
                } elseif ($identifier['type'] === 'product_number' || $identifier['type'] === 'mpn') {
                    $identifierData['product_number'] = $identifier['value'];
                }
            }

            // Handle photo uploads first
            $photoPaths = [];
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $path = $photo->store('queue-photos', 'public');
                    $photoPaths[] = $path;
                }
            }

            // Determine capture method
            $captureMethod = match (true) {
                ! empty($identifierData['barcode']) && count($photoPaths) > 0 => 'barcode_and_photos',
                ! empty($identifierData['manufacturer_part_number']) && count($photoPaths) > 0 => 'manufacturer_part_number_and_photos',
                ! empty($identifierData['product_number']) && count($photoPaths) > 0 => 'product_number_and_photos',
                ! empty($identifierData['barcode']) => 'barcode_only',
                ! empty($identifierData['manufacturer_part_number']) => 'manufacturer_part_number_only',
                ! empty($identifierData['product_number']) => 'product_number_only',
                count($photoPaths) > 0 => 'photos_only',
                default => 'manual_entry',
            };

            // Process keywords (comma-separated) into an array
            $keywords = null;
            if (! empty($validated['keywords_text'])) {
                $keywords = array_values(array_filter(array_map('trim', explode(',', $validated['keywords_text']))));
            }

            // Create intake queue item using your existing structure
            $queueItem = ProductIntakeQueue::create([
                'team_id' => $request->user()->currentTeam?->id,
                'organization_id' => $request->user()->currentTeam?->organization_id
                    ?? $request->user()->current_organization_id
                    ?? $request->user()->organization_id,
                'captured_by' => $request->user()->id,
                'captured_at' => now(),
                'capture_method' => $captureMethod,
                'supplier_id' => $validated['supplier_id'],
                'barcode' => $identifierData['barcode'] ?? null,
                'manufacturer_part_number' => $identifierData['manufacturer_part_number'] ?? null,
                'product_number' => $identifierData['product_number'] ?? null,
                'brand' => $validated['brand'] ?? null,
                'model_number' => $validated['model_number'] ?? null,
                'series' => $validated['series'] ?? null,
                'keywords' => $keywords,
                'condition' => $validated['condition'],
                'condition_description' => $validated['condition_description'] ?? null,
                'photos' => $photoPaths,
                'user_notes' => $validated['user_notes'],
                'quick_quantity' => $validated['quick_quantity'] ?? 1,
                'quick_location' => $validated['quick_location'] ?? null,
                'quick_location_id' => $validated['quick_location_id'] ?? null,
                'quick_bin_id' => $validated['quick_bin_id'] ?? null,
                'processing_status' => 'pending',
                'ai_analysis_status' => 'pending',
                'product_category' => $validated['product_category'],
                'product_type' => $validated['product_type'],
            ]);

            // Store additional identifiers in enriched_data for future processing
            $additionalIdentifiers = [];
            foreach ($validated['identifiers'] as $identifier) {
                $additionalIdentifiers[] = [
                    'type' => $identifier['type'],
                    'value' => $identifier['value'],
                    'source' => $identifier['source'] ?? 'mobile_app',
                    'is_primary' => $identifier['is_primary'] ?? false,
                ];
            }

            $queueItem->update([
                'enriched_data' => [
                    'mobile_identifiers' => $additionalIdentifiers,
                    'mobile_metadata' => [
                        'device_info' => $request->header('User-Agent'),
                        'capture_ip' => $request->ip(),
                        'app_version' => $request->header('X-App-Version'),
                    ],
                ],
            ]);

            UserActivityLog::log(
                'intake.created',
                "Captured intake queue item #{$queueItem->id} via mobile",
                $request->user(),
                [
                    'source' => 'mobile',
                    'product_category' => $queueItem->product_category,
                    'product_type' => $queueItem->product_type,
                    'condition' => $queueItem->condition,
                    'supplier_id' => $queueItem->supplier_id,
                    'photo_count' => count($photoPaths),
                ],
                'info',
                'inventory',
            );

            // Dispatch your existing background processing job
            ProcessQueueItem::dispatch($queueItem);

            return response()->json([
                'success' => true,
                'message' => 'Product captured successfully',
                'data' => [
                    'id' => $queueItem->id,
                    'processing_status' => $queueItem->processing_status,
                    'queue_position' => ProductIntakeQueue::forTeam($request->user()->currentTeam->id)
                        ->pending()
                        ->where('created_at', '<', $queueItem->created_at)
                        ->count() + 1,
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to capture product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Barcode scanning with identifier detection
     */
    public function scanBarcode(Request $request)
    {
        $validated = $request->validate([
            'barcode' => 'required|string|max:100',
        ]);

        try {
            $barcode = $validated['barcode'];

            // Detect identifier type using our ProductIdentifier class
            $detectedType = \App\Models\Catalog\ProductIdentifier::detectIdentifierType($barcode);

            // Check if this identifier already exists in your system
            // Check against all three identifier fields in your queue
            $existingInQueue = ProductIntakeQueue::forTeam(auth()->user()->currentTeam->id)
                ->where(function ($query) use ($barcode) {
                    $query->where('barcode', $barcode)
                        ->orWhere('manufacturer_part_number', $barcode)
                        ->orWhere('product_number', $barcode);
                })->first();

            // Also check if product already exists with this identifier
            $existingProduct = Product::where('team_id', auth()->user()->currentTeam->id)
                ->where(function ($query) use ($barcode) {
                    $query->where('sku', $barcode)
                        ->orWhere('barcode', $barcode); // if you have barcode field on products
                })->first();

            $response = [
                'success' => true,
                'barcode' => $barcode,
                'detected_type' => $detectedType,
                'formatted_type' => \App\Models\Catalog\ProductIdentifier::getValidIdentifierTypes()[$detectedType] ?? 'Unknown',
                'exists_in_queue' => $existingInQueue ? true : false,
                'exists_as_product' => $existingProduct ? true : false,
            ];

            if ($existingInQueue) {
                $response['queue_item'] = [
                    'id' => $existingInQueue->id,
                    'status' => $existingInQueue->processing_status,
                    'captured_at' => $existingInQueue->captured_at->format('M j, Y g:i A'),
                    'captured_by' => $existingInQueue->capturedBy->name,
                ];
            }

            if ($existingProduct) {
                $response['existing_product'] = [
                    'id' => $existingProduct->id,
                    'name' => $existingProduct->name,
                    'sku' => $existingProduct->sku,
                    'stock_quantity' => $existingProduct->stock_quantity,
                ];
            }

            return response()->json($response);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Barcode scan failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get mobile-optimized queue stats for dashboard
     */
    public function getMobileQueueStats()
    {
        $teamId = auth()->user()->currentTeam->id;
        $userId = auth()->id();

        $stats = [
            'my_captures_today' => ProductIntakeQueue::forTeam($teamId)
                ->where('captured_by', $userId)
                ->whereDate('captured_at', today())
                ->count(),

            'my_pending' => ProductIntakeQueue::forTeam($teamId)
                ->where('captured_by', $userId)
                ->pending()
                ->count(),

            'team_pending_total' => ProductIntakeQueue::forTeam($teamId)
                ->pending()
                ->count(),

            'team_high_confidence' => ProductIntakeQueue::forTeam($teamId)
                ->pending()
                ->highConfidence()
                ->count(),

            'processed_today' => ProductIntakeQueue::forTeam($teamId)
                ->completed()
                ->whereDate('processed_at', today())
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'message' => 'Queue stats retrieved successfully',
        ]);
    }

    /**
     * Get user's recent captures for mobile queue view
     */
    public function getMyCaptures(Request $request)
    {
        $teamId = auth()->user()->currentTeam->id;
        $userId = auth()->id();

        $limit = $request->input('limit', 20);

        $captures = ProductIntakeQueue::forTeam($teamId)
            ->where('captured_by', $userId)
            ->with(['capturedBy'])
            ->orderBy('captured_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'captured_at' => $item->captured_at->format('M j, g:i A'),
                    'capture_method' => $item->capture_method,
                    'primary_identifier' => $item->barcode ?? $item->manufacturer_part_number ?? $item->product_number ?? 'No ID',
                    'primary_photo' => $item->primary_photo,
                    'processing_status' => $item->processing_status,
                    'status_badge' => $item->status_badge,
                    'confidence_level' => $item->confidence_level,
                    'confidence_color' => $item->confidence_color,
                    'ai_confidence_score' => $item->ai_confidence_score,
                    'quick_quantity' => $item->quick_quantity,
                    'user_notes' => Str::limit($item->user_notes, 100),
                ];
            });

        return response()->json([
            'success' => true,
            'captures' => $captures,
            'total_today' => ProductIntakeQueue::forTeam($teamId)
                ->where('captured_by', $userId)
                ->whereDate('captured_at', today())
                ->count(),
        ]);
    }

    /**
     * Get available identifier types for mobile form
     */
    public function getIdentifierTypes()
    {
        return response()->json([
            'success' => true,
            'identifier_types' => \App\Models\Catalog\ProductIdentifier::getValidIdentifierTypes(),
            'scannable_types' => [
                'upc' => 'UPC Barcode',
                'ean' => 'EAN Barcode',
                'isbn' => 'ISBN Barcode',
                'serial' => 'Serial Number',
                'qr_code' => 'QR Code',
            ],
        ]);
    }

    /**
     * Everything the mobile intake form needs in a single call:
     * categories, types, conditions, identifier types, suppliers, and
     * locations with their bins (mirrors the web create() payload).
     */
    public function getFormOptions(Request $request): JsonResponse
    {
        $orgId = $request->user()->currentTeam?->organization_id
            ?? $request->user()->current_organization_id
            ?? $request->user()->organization_id;

        $locations = InventoryLocation::where('organization_id', $orgId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(function ($loc) {
                $loc->bins = \DB::table('warehouse_bins as wb')
                    ->join('warehouse_zones as wz', 'wz.id', '=', 'wb.zone_id')
                    ->where('wz.location_id', $loc->id)
                    ->where('wb.is_active', true)
                    ->orderBy('wz.code')->orderBy('wb.code')
                    ->select('wb.id', 'wb.code', 'wb.label', 'wz.code as zone_code', 'wz.name as zone_name')
                    ->get();

                return $loc;
            });

        $suppliers = Supplier::where('organization_id', $orgId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'company_name', 'code']);

        return response()->json([
            'success' => true,
            'categories' => ProductIntakeQueue::PRODUCT_CATEGORIES,
            'types' => ProductIntakeQueue::PRODUCT_TYPES,
            'conditions' => ProductIntakeQueue::CONDITIONS,
            'identifier_types' => \App\Models\Catalog\ProductIdentifier::getValidIdentifierTypes(),
            'scannable_types' => [
                'upc' => 'UPC Barcode',
                'ean' => 'EAN Barcode',
                'isbn' => 'ISBN Barcode',
                'serial' => 'Serial Number',
                'qr_code' => 'QR Code',
            ],
            'suppliers' => $suppliers,
            'locations' => $locations,
        ]);
    }

    /**
     * Add photos to existing queue item (for retaking/additional photos)
     */
    public function addPhotos(Request $request, ProductIntakeQueue $queueItem)
    {
        $this->authorize('update', $queueItem);

        $validated = $request->validate([
            'photos' => 'required|array|max:24',
            'photos.*' => 'file|image|max:10240',
            'replace_existing' => 'nullable|boolean',
        ]);

        try {
            $newPhotoPaths = [];
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('queue-photos', 'public');
                $newPhotoPaths[] = $path;
            }

            if ($validated['replace_existing'] ?? false) {
                // Replace all existing photos
                $queueItem->update(['photos' => $newPhotoPaths]);
            } else {
                // Add to existing photos
                $existingPhotos = $queueItem->photos ?? [];
                $allPhotos = array_merge($existingPhotos, $newPhotoPaths);
                $queueItem->update(['photos' => array_slice($allPhotos, 0, 24)]); // Max 24 photos
            }

            $fresh = $queueItem->fresh();

            UserActivityLog::log(
                'intake.photos_updated',
                "Updated photos on intake queue item #{$queueItem->id}",
                $request->user(),
                [
                    'source' => 'mobile',
                    'queue_item_id' => $queueItem->id,
                    'new_photo_count' => count($newPhotoPaths),
                    'replace_existing' => $validated['replace_existing'] ?? false,
                    'total_photos' => count($fresh->photos ?? []),
                ],
                'info',
                'inventory',
            );

            return response()->json([
                'success' => true,
                'message' => 'Photos updated successfully',
                'photo_count' => count($fresh->photos),
                'photos' => $fresh->photo_urls,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update photos',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update queue item from mobile (notes, quantity, location)
     */
    public function mobileUpdate(Request $request, ProductIntakeQueue $queueItem)
    {
        $this->authorize('update', $queueItem);

        $validated = $request->validate([
            'user_notes' => 'nullable|string|max:1000',
            'quick_quantity' => 'nullable|integer|min:1|max:10000',
            'quick_location' => 'nullable|string|max:255',
            'quick_location_id' => 'nullable|uuid|exists:inventory_locations,id',
            'quick_bin_id' => 'nullable|uuid|exists:warehouse_bins,id',
            'weight_lbs' => 'nullable|numeric|min:0|max:9999',
            'length_inches' => 'nullable|numeric|min:0|max:9999',
            'width_inches' => 'nullable|numeric|min:0|max:9999',
            'height_inches' => 'nullable|numeric|min:0|max:9999',
        ]);

        try {
            $queueItem->update($validated);

            UserActivityLog::log(
                'intake.updated',
                "Updated intake queue item #{$queueItem->id}",
                $request->user(),
                ['fields' => array_keys($validated), 'source' => 'mobile'],
                'info',
                'inventory',
            );

            return response()->json([
                'success' => true,
                'message' => 'Item updated successfully',
                'data' => [
                    'id' => $queueItem->id,
                    'user_notes' => $queueItem->user_notes,
                    'quick_quantity' => $queueItem->quick_quantity,
                    'quick_location' => $queueItem->quick_location,
                    'quick_location_id' => $queueItem->quick_location_id,
                    'quick_bin_id' => $queueItem->quick_bin_id,
                    'weight_lbs' => $queueItem->weight_lbs,
                    'length_inches' => $queueItem->length_inches,
                    'width_inches' => $queueItem->width_inches,
                    'height_inches' => $queueItem->height_inches,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create eBay listing directly from queue item
     */
    public function createEbayListing(ProductIntakeQueue $queueItem)
    {
        $this->authorize('update', $queueItem);

        if (! $queueItem->isReadyForListing()) {
            return response()->json([
                'success' => false,
                'message' => 'Item not ready for eBay listing',
                'current_status' => $queueItem->processing_status,
                'missing_requirements' => $this->getMissingRequirements($queueItem),
            ], 422);
        }

        // Check if already has an eBay listing
        if ($queueItem->ebay_listing_id) {
            return response()->json([
                'success' => false,
                'message' => 'Item already has an eBay listing',
                'listing_id' => $queueItem->ebay_listing_id,
            ], 400);
        }

        $conditionError = $this->validateConditionForDispatch($queueItem->condition ?? 'NEW', $queueItem);
        if ($conditionError) {
            return response()->json(['success' => false, ...$conditionError], 422);
        }

        try {
            // Dispatch your existing bulletproof job
            \App\Jobs\CreateEbayListingJob::dispatch($queueItem);

            // Update status to indicate eBay processing started
            $queueItem->update([
                'processing_status' => 'in_progress',
                'last_processed_at' => now(),
                'processing_attempts' => ($queueItem->processing_attempts ?? 0) + 1,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'eBay listing creation started',
                'queue_item_id' => $queueItem->id,
                'estimated_completion' => now()->addMinutes(2)->toISOString(),
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to dispatch eBay listing job', [
                'queue_item_id' => $queueItem->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to start eBay listing creation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk create eBay listings
     */
    public function bulkCreateEbayListings(Request $request)
    {
        $validated = $request->validate([
            'queue_item_ids' => 'required|array|min:1|max:50', // Limit bulk operations
            'queue_item_ids.*' => 'exists:product_intake_queue,id',
        ]);

        $teamId = $request->user()->currentTeam->id;

        $queueItems = ProductIntakeQueue::whereIn('id', $validated['queue_item_ids'])
            ->forTeam($teamId)
            ->get();

        $results = [
            'queued' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        foreach ($queueItems as $queueItem) {
            // Check authorization
            if ($queueItem->team_id !== $teamId) {
                $results['errors'][] = [
                    'id' => $queueItem->id,
                    'reason' => 'Access denied',
                ];

                continue;
            }

            // Check if ready
            if (! $queueItem->isReadyForListing()) {
                $results['skipped']++;
                $results['errors'][] = [
                    'id' => $queueItem->id,
                    'reason' => 'Not ready for listing',
                    'status' => $queueItem->processing_status,
                ];

                continue;
            }

            // Check if already listed
            if ($queueItem->ebay_listing_id) {
                $results['skipped']++;
                $results['errors'][] = [
                    'id' => $queueItem->id,
                    'reason' => 'Already has eBay listing',
                ];

                continue;
            }

            try {
                \App\Jobs\CreateEbayListingJob::dispatch($queueItem);

                $queueItem->update([
                    'processing_status' => 'in_progress',
                    'last_processed_at' => now(),
                    'processing_attempts' => ($queueItem->processing_attempts ?? 0) + 1,
                ]);

                $results['queued']++;

            } catch (\Exception $e) {
                $results['errors'][] = [
                    'id' => $queueItem->id,
                    'reason' => 'Dispatch failed: '.$e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Queued {$results['queued']} listings for eBay creation",
            'results' => $results,
        ]);
    }

    /**
     * Update the existing publish method to integrate eBay
     */
    public function publishWithEbayIntegration(Request $request, ProductIntakeQueue $queueItem)
    {
        $this->authorize('update', $queueItem);

        $validated = $request->validate([
            // Product Identity
            'name' => 'required|string|max:500',
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'mpn' => 'nullable|string|max:100',
            'upc' => 'nullable|string|max:14',
            'ean' => 'nullable|string|max:14',
            'category_id' => 'nullable|exists:categories,id',

            // Inventory
            'quantity' => 'required|integer|min:0',
            'quick_location_id' => 'nullable|uuid|exists:inventory_locations,id',
            'quick_bin_id' => 'nullable|uuid|exists:warehouse_bins,id',

            // Pricing
            'cost_basis' => 'nullable|numeric|min:0',
            'list_price' => 'required|numeric|min:0',

            // Listing Content
            'listing_title' => 'required|string|max:500',
            'listing_description' => 'nullable|string',
            'bullet_points' => 'nullable|array',
            'keywords' => 'nullable|array',
            'item_specifics' => 'nullable|array',

            // Condition (accepts both simplified and eBay-style formats, plus underscore variants)
            'condition' => 'required|string|in:new,like_new,very_good,good,acceptable,used_good,used_very_good,used_excellent,used_acceptable,for_parts,NEW,NEW_OTHER,NEW_WITH_DEFECTS,MANUFACTURER_REFURBISHED,CERTIFIED_REFURBISHED,USED_EXCELLENT,USED_VERY_GOOD,USED_GOOD,USED_ACCEPTABLE,FOR_PARTS_OR_NOT_WORKING',
            'condition_notes' => 'nullable|string',

            // Publishing
            'publish_to' => 'required|string|in:ebay,amazon,newegg',
            'selected_photos' => 'nullable|array',

            // eBay specific options (optional)
            'ebay_auto_publish' => 'nullable|boolean',
            'ebay_options' => 'nullable|array',
            'ebay_options.policies' => 'nullable|array',
            'ebay_options.policies.fulfillment' => 'nullable|string|max:50',
            'ebay_options.policies.payment' => 'nullable|string|max:50',
            'ebay_options.policies.return' => 'nullable|string|max:50',
            'ebay_options.shipping_rate' => 'nullable|array',
        ]);

        try {
            // Map condition to eBay format before creating product
            $validated['condition'] = $this->mapConditionToEbayFormat($validated['condition']);

            // Create product record (your existing logic)
            $product = Product::create([
                'organization_id' => $queueItem->organization_id,
                'sku' => $this->generateSku($validated, $queueItem->organization_id),
                'upc' => $validated['upc'] ?? null,
                'ean' => $validated['ean'] ?? null,
                'mpn' => $validated['mpn'] ?? null,
                'name' => $validated['name'],
                'description' => $validated['listing_description'] ?? null,
                'stock_quantity' => $validated['quantity'] ?? 0,
                'quantity' => $validated['quantity'] ?? 0,
                'available_quantity' => $validated['quantity'] ?? 0,
                'cost_price' => $validated['cost_basis'] ?? 0,
                'selling_price' => $validated['list_price'] ?? 0,
                'condition' => $validated['condition'],
                'condition_description' => $validated['condition_notes'] ?? null,
                'created_by' => $request->user()->id,
                'status' => 'active',
                'attributes' => $this->buildIntakeAttributes($queueItem, $validated['item_specifics'] ?? []),
            ]);

            $this->seedProductInventory(
                $product,
                (int) ($validated['quantity'] ?? 0),
                $validated['quick_location_id'] ?? $queueItem->quick_location_id,
                $validated['quick_bin_id'] ?? $queueItem->quick_bin_id,
                $queueItem->quick_location
            );

            // Add product images from selected photos (your existing logic)
            if (! empty($validated['selected_photos'])) {
                foreach ($validated['selected_photos'] as $index => $photoUrl) {
                    $photoPath = $this->resolvePhotoPath($photoUrl, $queueItem);

                    ProductImage::create([
                        'product_id' => $product->id,
                        'url' => $photoPath,
                        'is_primary' => $index === 0,
                        'sort_order' => $index,
                    ]);
                }
            }

            // Link AI analysis to product if exists
            if ($queueItem->aiAnalysis) {
                $queueItem->aiAnalysis->update(['product_id' => $product->id]);
            }

            // Mark queue item as completed
            $queueItem->markAsCompleted($product, $request->user());

            // PLATFORM-SPECIFIC PUBLISHING
            if ($validated['publish_to'] === 'ebay') {
                // Store user-edited item specifics and eBay options in enriched_data
                $enrichedData = $queueItem->enriched_data ?? [];
                $enrichedData['user_item_specifics'] = $validated['item_specifics'] ?? [];
                $enrichedData['user_listing_data'] = [
                    'listing_title' => $validated['listing_title'],
                    'bullet_points' => $validated['bullet_points'] ?? [],
                    'keywords' => $validated['keywords'] ?? [],
                    'target_platform' => $validated['publish_to'],
                ];

                $ebayOptions = $validated['ebay_options'] ?? [];
                if (! empty($ebayOptions['policies'])) {
                    $enrichedData['selected_policies'] = $ebayOptions['policies'];
                }
                if (! empty($ebayOptions['shipping_rate'])) {
                    $enrichedData['selected_shipping_rate'] = $ebayOptions['shipping_rate'];
                }
                if (! empty($ebayOptions['additional_item_cost'])) {
                    $enrichedData['additional_item_shipping_cost'] = (float) $ebayOptions['additional_item_cost'];
                }
                $enrichedData['shipping_insurance'] = ! empty($ebayOptions['insurance_enabled']);
                $enrichedData['shipping_confirmation'] = $ebayOptions['confirmation_type'] ?? 'none';
                $queueItem->update(['enriched_data' => $enrichedData]);

                // Validate condition before dispatching
                $conditionError = $this->validateConditionForDispatch($validated['condition'], $queueItem);
                if ($conditionError) {
                    return response()->json(['success' => false, ...$conditionError], 422);
                }

                // Dispatch eBay listing job
                \App\Jobs\CreateEbayListingJob::dispatch($queueItem);

                $message = 'Product created and eBay listing queued for creation!';
                $redirectRoute = 'intake-queue.show';
                $redirectParam = $queueItem;

            } elseif ($validated['publish_to'] === 'shopify') {
                $credential = IntegrationCredential::where('organization_id', $queueItem->organization_id)
                    ->where('marketplace', 'shopify')
                    ->where('is_active', true)
                    ->first();

                if ($credential) {
                    \App\Jobs\Shopify\SyncProductToShopify::dispatch($product, $credential);
                    $message = 'Product created and Shopify listing queued for creation!';
                } else {
                    $message = 'Product created but no active Shopify integration found.';
                }
                $redirectRoute = 'inventory.products.show';
                $redirectParam = $product;
            } else {
                $message = 'Product created successfully!';
                $redirectRoute = 'inventory.products.show';
                $redirectParam = $product;
            }

            return redirect()->route($redirectRoute, $redirectParam)
                ->with('success', $message);

        } catch (\Exception $e) {
            \Log::error('Product publish with eBay integration failed', [
                'queue_item_id' => $queueItem->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withInput()->withErrors([
                'general' => 'Failed to create product and listing: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Helper method to check what requirements are missing for eBay listing
     */
    private function getMissingRequirements(ProductIntakeQueue $queueItem): array
    {
        $missing = [];

        // Check photos
        if (! $queueItem->hasPhotos()) {
            $missing[] = 'At least one product photo required';
        }

        // Check condition
        if (empty($queueItem->condition)) {
            $missing[] = 'Product condition must be specified';
        }

        // Check AI analysis
        if (! $queueItem->hasAiAnalysis()) {
            $missing[] = 'AI analysis must be completed';
        }

        // Check enriched data
        $enrichedData = $queueItem->enriched_data ?? [];
        if (empty($enrichedData['merged_product_fields']['product_identity']['title'] ?? '')) {
            $missing[] = 'Product title required (from AI analysis)';
        }

        if (empty($enrichedData['merged_product_fields']['product_details']['description'] ?? '')) {
            $missing[] = 'Product description required (from AI analysis)';
        }

        // Check if has valid identifiers for eBay
        if (empty($queueItem->manufacturer_part_number) && empty($queueItem->product_number) && empty($queueItem->barcode)) {
            $missing[] = 'At least one product identifier required (NCR ID, Product Number, or Barcode)';
        }

        return $missing;
    }

    private function determineCaptureMethod($validated, $photoPaths): string
    {
        $hasPhotos = count($photoPaths) > 0;
        $hasLookup = $validated['lookup_attempted'] ?? false;

        return match (true) {
            $hasLookup && $hasPhotos => 'smart_lookup_with_photos',
            ! empty($validated['barcode']) && $hasPhotos => 'barcode_and_photos',
            ! empty($validated['manufacturer_part_number']) && $hasPhotos => 'manufacturer_part_number_and_photos',
            ! empty($validated['primary_identifier']) && $hasPhotos => 'identifier_and_photos',
            $hasLookup => 'smart_lookup_only',
            default => 'manual_entry_with_photos',
        };
    }

    private function extractPrimaryIdentifier($primaryId, $type): ?string
    {
        if (! $primaryId) {
            return null;
        }

        return match ($type) {
            'barcode' => $this->isPrimaryBarcode($primaryId) ? $primaryId : null,
            'manufacturer_part_number' => $this->isPrimaryNcrId($primaryId) ? $primaryId : null,
            'product_number' => $this->isPrimaryModelNumber($primaryId) ? $primaryId : null,
            default => null,
        };
    }

    private function calculateEnhancedPriority($data): int
    {
        $priority = 1; // Default normal priority

        // High value items get higher priority
        if (isset($data['estimated_value']) && $data['estimated_value'] > 500) {
            $priority = 3;
        } elseif (isset($data['estimated_value']) && $data['estimated_value'] > 100) {
            $priority = 2;
        }

        // NEW condition gets higher priority - ADD NULL CHECK
        if (($data['condition'] ?? null) === 'NEW') {
            $priority = max($priority, 2);
        }

        // Known products get higher priority - ADD NULL CHECKS
        if (($data['lookup_attempted'] ?? false) && ($data['existing_product_data'] ?? null)) {
            $priority = max($priority, 2);
        }

        // Supplier priority (if preferred supplier)
        // You can enhance this when you add supplier preferences

        return $priority;
    }

    public function productLookup(Request $request)
    {
        $request->validate(['identifier' => 'required|string']);

        $lookupService = new \App\Services\ProductLookupService;
        $result = $lookupService->lookupByIdentifier(
            $request->identifier,
            auth()->user()->currentTeam?->organization_id ?? auth()->user()->current_organization_id ?? auth()->user()->organization_id
        );

        return response()->json($result);
    }

    /**
     * Map simplified condition values to eBay-style database ENUM values.
     *
     * Frontend sends simplified values (good, like_new, etc.) but the database
     * ENUM requires eBay-style values (USED_GOOD, NEW_OTHER, etc.).
     */
    protected function mapConditionToEbayFormat(string $condition): string
    {
        $map = [
            // Simple names
            'new' => 'NEW',
            'like_new' => 'NEW_OTHER',
            'very_good' => 'USED_VERY_GOOD',
            'good' => 'USED_GOOD',
            'acceptable' => 'USED_ACCEPTABLE',
            // Underscore variants (from AI research or other sources)
            'used_good' => 'USED_GOOD',
            'used_very_good' => 'USED_VERY_GOOD',
            'used_excellent' => 'USED_EXCELLENT',
            'used_acceptable' => 'USED_ACCEPTABLE',
            'for_parts' => 'FOR_PARTS_OR_NOT_WORKING',
        ];

        // If already in eBay format (uppercase with underscores), return as-is
        if (ctype_upper(str_replace('_', '', $condition))) {
            return $condition;
        }

        // Normalize to lowercase for mapping
        $normalizedCondition = strtolower($condition);

        return $map[$normalizedCondition] ?? 'NEW';
    }

    /**
     * Check if identifier looks like a barcode (UPC/EAN pattern)
     */
    private function isPrimaryBarcode($identifier): bool
    {
        // Simple check for barcode patterns (8-14 digits)
        return preg_match('/^\d{8,14}$/', $identifier);
    }

    /**
     * Check if identifier looks like NCR/manufacturer part number pattern
     */
    private function isPrimaryNcrId($identifier): bool
    {
        // NCR pattern: numbers with dashes, or alphanumeric with dashes
        return preg_match('/^\d{4}-\d{4}-\d{4}$/', $identifier) ||
            preg_match('/^[A-Z0-9]+-[A-Z0-9]+-[A-Z0-9]+$/i', $identifier);
    }

    /**
     * Check if identifier looks like a model number (contains both letters and numbers)
     */
    private function isPrimaryModelNumber($identifier): bool
    {
        // Contains letters and numbers, not just numbers
        return preg_match('/[A-Za-z]/', $identifier) && preg_match('/\d/', $identifier);
    }

    /**
     * Store research data submitted by the Chrome extension.
     */
    public function storeExtensionResearch(Request $request, ProductIntakeQueue $queueItem)
    {
        $validated = $request->validate([
            'listings' => 'required|array',
            'listings.*.title' => 'nullable|string',
            'listings.*.avg_price_numeric' => 'nullable|numeric',
            'listings.*.price_numeric' => 'nullable|numeric',
            'listings.*.total_sold' => 'nullable|integer',
            'listings.*.last_sold_date' => 'nullable|string',
            'listings.*.url' => 'nullable|string',
            'listings.*.shipping_numeric' => 'nullable|numeric',
            'listings.*.condition' => 'nullable|string',
            'price_analysis' => 'nullable|array',
            'sales_analysis' => 'nullable|array',
            'market_insights' => 'nullable|array',
            'page_type' => 'nullable|string',
            'url' => 'nullable|string',
            'extraction_time' => 'nullable|string',
            'total_listings' => 'nullable|integer',
        ]);

        // Transform listings into market_pricing format
        $soldListings = collect($validated['listings'])->map(function ($listing) {
            return [
                'title' => $listing['title'] ?? '',
                'price' => number_format($listing['avg_price_numeric'] ?? $listing['price_numeric'] ?? 0, 2, '.', ''),
                'sold_date' => $listing['last_sold_date'] ?? null,
                'listing_url' => $listing['url'] ?? null,
                'shipping_cost' => isset($listing['shipping_numeric']) ? number_format($listing['shipping_numeric'], 2, '.', '') : null,
                'condition' => $listing['condition'] ?? null,
                'total_sold' => $listing['total_sold'] ?? null,
            ];
        })->sortByDesc('sold_date')->values()->toArray();

        $pa = $validated['price_analysis'] ?? [];

        $soldSummary = [
            'average_sold' => isset($pa['average']) ? number_format($pa['average'], 2, '.', '') : null,
            'median_sold' => isset($pa['median']) ? number_format($pa['median'], 2, '.', '') : null,
            'highest_sold' => isset($pa['max']) ? number_format($pa['max'], 2, '.', '') : null,
            'lowest_sold' => isset($pa['min']) ? number_format($pa['min'], 2, '.', '') : null,
            'total_sold' => $validated['sales_analysis']['total_units_sold'] ?? count($soldListings),
            'search_url' => $validated['url'] ?? null,
        ];

        $recommendedPrice = isset($pa['median']) ? number_format($pa['median'], 2, '.', '') : null;

        $marketPricing = [
            'ebay_sold_listings' => $soldListings,
            'ebay_sold_summary' => $soldSummary,
            'recommended_list_price' => $recommendedPrice,
            'source' => 'chrome_extension',
            'extracted_at' => $validated['extraction_time'] ?? now()->toIso8601String(),
        ];

        // Merge into enriched_data (preserve existing market_pricing keys like ebay_active_listings, pricing_psychology)
        $enrichedData = $queueItem->enriched_data ?? [];
        $existingMarketPricing = $enrichedData['research_data']['market_pricing'] ?? [];
        $enrichedData['research_data'] = array_merge(
            $enrichedData['research_data'] ?? [],
            ['market_pricing' => array_merge($existingMarketPricing, $marketPricing)]
        );

        // Append audit log
        $enrichedData['extension_research_audit'] = $enrichedData['extension_research_audit'] ?? [];
        $enrichedData['extension_research_audit'][] = [
            'received_at' => now()->toIso8601String(),
            'user_id' => $request->user()->id,
            'source_url' => $validated['url'] ?? null,
            'extraction_time' => $validated['extraction_time'] ?? null,
            'page_type' => $validated['page_type'] ?? null,
            'listing_count' => count($soldListings),
            'raw_price_analysis' => $pa,
            'raw_sales_analysis' => $validated['sales_analysis'] ?? null,
            'raw_market_insights' => $validated['market_insights'] ?? null,
        ];

        $queueItem->update(['enriched_data' => $enrichedData]);

        // Broadcast event for real-time UI update
        broadcast(new ExtensionResearchReceived($queueItem, $marketPricing))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Extension research data received',
            'market_pricing' => $marketPricing,
        ]);
    }

    public function getProductCategories()
    {
        return response()->json([
            'success' => true,
            'categories' => ProductIntakeQueue::PRODUCT_CATEGORIES,
        ]);
    }

    /**
     * Get product types for form dropdown
     */
    public function getProductTypes()
    {
        return response()->json([
            'success' => true,
            'types' => ProductIntakeQueue::PRODUCT_TYPES,
        ]);
    }

    /**
     * Get classification stats for dashboard
     */
    public function getClassificationStats(Request $request)
    {
        $teamId = $request->user()->currentTeam->id;

        $stats = [
            'total_items' => ProductIntakeQueue::forTeam($teamId)->count(),
            'classified_items' => ProductIntakeQueue::forTeam($teamId)->classified()->count(),
            'unclassified_items' => ProductIntakeQueue::forTeam($teamId)->unclassified()->count(),

            'by_category' => ProductIntakeQueue::forTeam($teamId)
                ->select('product_category')
                ->selectRaw('count(*) as count')
                ->whereNotNull('product_category')
                ->groupBy('product_category')
                ->get()
                ->pluck('count', 'product_category')
                ->toArray(),

            'by_type' => ProductIntakeQueue::forTeam($teamId)
                ->select('product_type')
                ->selectRaw('count(*) as count')
                ->whereNotNull('product_type')
                ->groupBy('product_type')
                ->get()
                ->pluck('count', 'product_type')
                ->toArray(),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats,
        ]);
    }

    /**
     * Filter items by classification
     */
    public function filterByClassification(Request $request)
    {
        $request->validate([
            'category' => 'nullable|string|in:'.implode(',', array_keys(ProductIntakeQueue::PRODUCT_CATEGORIES)),
            'type' => 'nullable|string|in:'.implode(',', array_keys(ProductIntakeQueue::PRODUCT_TYPES)),
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $teamId = $request->user()->currentTeam->id;
        $limit = $request->input('limit', 20);

        $query = ProductIntakeQueue::forTeam($teamId);

        if ($request->filled('category')) {
            $query->category($request->category);
        }

        if ($request->filled('type')) {
            $query->type($request->type);
        }

        $items = $query->orderByPriority()
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'captured_at' => $item->captured_at,
                    'primary_identifier' => $item->manufacturer_part_number ?? $item->product_number ?? $item->barcode ?? 'No ID',
                    'product_category' => $item->product_category,
                    'category_label' => $item->category_label,
                    'product_type' => $item->product_type,
                    'type_label' => $item->type_label,
                    'processing_status' => $item->processing_status,
                    'confidence_level' => $item->confidence_level,
                ];
            });

        return response()->json([
            'success' => true,
            'items' => $items,
            'total' => $query->count(),
        ]);
    }
}
