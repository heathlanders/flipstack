<?php

namespace App\Http\Controllers\Api\Ebay;

use App\Http\Controllers\Controller;
use App\Jobs\Ebay\BulkSyncToEbay;
use App\Jobs\Ebay\RetryFailedEbaySync;
use App\Jobs\Ebay\SyncProductToEbay;
use App\Jobs\Ebay\UpdateEbayInventory;
use App\Models\Catalog\Product;
use App\Models\Ebay\EbayListing;
use App\Models\Ebay\EbaySyncLog;
use App\Models\Integration\IntegrationCredential;
use App\Services\EbayApiException;
use App\Services\EbayInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EbayApiController extends Controller
{
    /**
     * Get eBay integration status
     */
    public function status(Request $request): JsonResponse
    {
        $team = $request->user()->currentTeam;
        $credential = $this->getCredential($team->organization_id);

        if (! $credential) {
            return response()->json([
                'configured' => false,
                'message' => 'No eBay integration configured',
            ]);
        }

        $token = $credential->getActiveEbayToken();

        return response()->json([
            'configured' => true,
            'ready' => $credential->isEbayReady(),
            'environment' => $credential->environment,
            'marketplace' => $credential->marketplace_id ?? 'EBAY_US',
            'has_token' => $token !== null,
            'token_expires_at' => $token?->expires_at?->toISOString(),
            'auto_sync_enabled' => $credential->auto_sync_enabled,
            'last_full_sync_at' => $credential->last_full_sync_at?->toISOString(),
            'config' => [
                'has_location' => ! empty($credential->merchant_location_key),
                'has_fulfillment_policy' => ! empty($credential->fulfillment_policy_id),
                'has_payment_policy' => ! empty($credential->payment_policy_id),
                'has_return_policy' => ! empty($credential->return_policy_id),
            ],
        ]);
    }

    /**
     * Get listing statistics
     */
    public function stats(Request $request): JsonResponse
    {
        $team = $request->user()->currentTeam;
        $credential = $this->getCredential($team->organization_id);

        if (! $credential) {
            return response()->json(['error' => 'No eBay integration configured'], 404);
        }

        $listingStats = EbayListing::forCredential($credential->id)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'inventory_created' THEN 1 ELSE 0 END) as inventory_created,
                SUM(CASE WHEN status = 'offer_created' THEN 1 ELSE 0 END) as offer_created,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as errors,
                SUM(CASE WHEN status = 'ended' THEN 1 ELSE 0 END) as ended,
                SUM(sold_quantity) as total_sold
            ")
            ->first();

        $syncStats = EbaySyncLog::forCredential($credential->id)
            ->where('created_at', '>=', now()->subHours(24))
            ->selectRaw("
                COUNT(*) as total_operations,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as failed,
                AVG(response_time_ms) as avg_response_time
            ")
            ->first();

        $readyToSync = Product::readyForEbay()
            ->notOnEbay($credential->id)
            ->count();

        return response()->json([
            'listings' => [
                'total' => $listingStats->total ?? 0,
                'published' => $listingStats->published ?? 0,
                'pending' => $listingStats->pending ?? 0,
                'in_progress' => ($listingStats->inventory_created ?? 0) + ($listingStats->offer_created ?? 0),
                'errors' => $listingStats->errors ?? 0,
                'ended' => $listingStats->ended ?? 0,
                'total_sold' => $listingStats->total_sold ?? 0,
            ],
            'sync_24h' => [
                'total_operations' => $syncStats->total_operations ?? 0,
                'successful' => $syncStats->successful ?? 0,
                'failed' => $syncStats->failed ?? 0,
                'success_rate' => $syncStats->total_operations > 0
                    ? round(($syncStats->successful / $syncStats->total_operations) * 100, 1)
                    : 0,
                'avg_response_time_ms' => $syncStats->avg_response_time
                    ? round($syncStats->avg_response_time)
                    : null,
            ],
            'products' => [
                'ready_to_sync' => $readyToSync,
            ],
        ]);
    }

    /**
     * List eBay listings
     */
    public function listings(Request $request): JsonResponse
    {
        $team = $request->user()->currentTeam;
        $credential = $this->getCredential($team->organization_id);

        if (! $credential) {
            return response()->json(['error' => 'No eBay integration configured'], 404);
        }

        $query = EbayListing::forCredential($credential->id)
            ->with('product:id,name,sku,selling_price,stock_quantity');

        // Filters
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ebay_sku', 'ilike', "%{$search}%")
                    ->orWhere('ebay_listing_id', 'ilike', "%{$search}%")
                    ->orWhereHas('product', function ($pq) use ($search) {
                        $pq->where('name', 'ilike', "%{$search}%")
                            ->orWhere('sku', 'ilike', "%{$search}%");
                    });
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'updated_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $perPage = min($request->get('per_page', 20), 100);
        $listings = $query->paginate($perPage);

        return response()->json($listings);
    }

    /**
     * Get a single listing
     */
    public function listing(Request $request, EbayListing $listing): JsonResponse
    {
        $team = $request->user()->currentTeam;

        if ($listing->team_id !== $team->id) {
            return response()->json(['error' => 'Listing not found'], 404);
        }

        $listing->load(['product', 'integrationCredential:id,name,environment']);

        return response()->json([
            'listing' => $listing,
            'recent_logs' => EbaySyncLog::where('ebay_listing_id', $listing->id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(),
        ]);
    }

    /**
     * Sync a single product
     */
    public function syncProduct(Request $request): JsonResponse
    {
        $team = $request->user()->currentTeam;
        $credential = $this->getCredential($team->organization_id);

        if (! $credential) {
            return response()->json(['error' => 'No eBay integration configured'], 404);
        }

        if (! $credential->isEbayReady()) {
            return response()->json(['error' => 'eBay integration not fully configured'], 400);
        }

        $validated = $request->validate([
            'product_id' => 'required|uuid|exists:products,id',
            'category_id' => 'nullable|string',
        ]);

        $product = Product::findOrFail($validated['product_id']);

        // Verify product belongs to team's organization
        if ($product->organization_id !== $team->organization_id) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        SyncProductToEbay::dispatch(
            $product,
            $credential,
            $validated['category_id'] ?? null,
            false,
            $request->user()->id
        );

        return response()->json([
            'message' => 'Sync job queued',
            'product_id' => $product->id,
            'sku' => $product->sku,
        ]);
    }

    /**
     * Sync multiple products
     */
    public function syncBulk(Request $request): JsonResponse
    {
        $team = $request->user()->currentTeam;
        $credential = $this->getCredential($team->organization_id);

        if (! $credential) {
            return response()->json(['error' => 'No eBay integration configured'], 404);
        }

        if (! $credential->isEbayReady()) {
            return response()->json(['error' => 'eBay integration not fully configured'], 400);
        }

        $validated = $request->validate([
            'product_ids' => 'required|array|min:1|max:100',
            'product_ids.*' => 'uuid|exists:products,id',
            'category_id' => 'nullable|string',
        ]);

        $batchId = BulkSyncToEbay::syncProducts(
            $credential,
            $validated['product_ids'],
            $validated['category_id'] ?? null,
            $request->user()->id
        );

        return response()->json([
            'message' => 'Bulk sync job queued',
            'batch_id' => $batchId,
            'product_count' => count($validated['product_ids']),
        ]);
    }

    /**
     * Sync all pending products
     */
    public function syncAll(Request $request): JsonResponse
    {
        $team = $request->user()->currentTeam;
        $credential = $this->getCredential($team->organization_id);

        if (! $credential) {
            return response()->json(['error' => 'No eBay integration configured'], 404);
        }

        if (! $credential->isEbayReady()) {
            return response()->json(['error' => 'eBay integration not fully configured'], 400);
        }

        $pendingCount = Product::readyForEbay()
            ->notOnEbay($credential->id)
            ->count();

        if ($pendingCount === 0) {
            return response()->json([
                'message' => 'No products pending sync',
                'pending_count' => 0,
            ]);
        }

        $batchId = BulkSyncToEbay::syncAllPending($credential, $request->user()->id);

        $credential->update(['last_full_sync_at' => now()]);

        return response()->json([
            'message' => 'Full sync job queued',
            'batch_id' => $batchId,
            'pending_count' => $pendingCount,
        ]);
    }

    /**
     * Update a listing
     */
    public function updateListing(Request $request, EbayListing $listing): JsonResponse
    {
        $team = $request->user()->currentTeam;

        if ($listing->team_id !== $team->id) {
            return response()->json(['error' => 'Listing not found'], 404);
        }

        $validated = $request->validate([
            'update_type' => 'required|in:quantity,price,both',
        ]);

        UpdateEbayInventory::dispatch($listing, $validated['update_type']);

        return response()->json([
            'message' => 'Update job queued',
            'listing_id' => $listing->id,
            'update_type' => $validated['update_type'],
        ]);
    }

    /**
     * Retry a failed listing
     */
    public function retryListing(Request $request, EbayListing $listing): JsonResponse
    {
        $team = $request->user()->currentTeam;

        if ($listing->team_id !== $team->id) {
            return response()->json(['error' => 'Listing not found'], 404);
        }

        $listing->update([
            'status' => EbayListing::STATUS_PENDING,
            'consecutive_errors' => 0,
        ]);

        SyncProductToEbay::dispatch(
            $listing->product,
            $listing->integrationCredential,
            $listing->ebay_category_id,
            false,
            $request->user()->id
        );

        return response()->json([
            'message' => 'Retry job queued',
            'listing_id' => $listing->id,
        ]);
    }

    /**
     * Retry all failed listings
     */
    public function retryAll(Request $request): JsonResponse
    {
        $team = $request->user()->currentTeam;
        $credential = $this->getCredential($team->organization_id);

        if (! $credential) {
            return response()->json(['error' => 'No eBay integration configured'], 404);
        }

        $failedCount = EbayListing::forCredential($credential->id)
            ->where('status', EbayListing::STATUS_ERROR)
            ->where('consecutive_errors', '<', 5)
            ->count();

        if ($failedCount === 0) {
            return response()->json([
                'message' => 'No failed listings to retry',
                'failed_count' => 0,
            ]);
        }

        RetryFailedEbaySync::retryForCredential($credential);

        return response()->json([
            'message' => 'Retry job queued',
            'failed_count' => $failedCount,
        ]);
    }

    /**
     * Get sync logs
     */
    public function logs(Request $request): JsonResponse
    {
        $team = $request->user()->currentTeam;
        $credential = $this->getCredential($team->organization_id);

        if (! $credential) {
            return response()->json(['error' => 'No eBay integration configured'], 404);
        }

        $query = EbaySyncLog::forCredential($credential->id);

        // Filters
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($operation = $request->get('operation')) {
            $query->where('operation', $operation);
        }

        if ($listingId = $request->get('listing_id')) {
            $query->where('ebay_listing_id', $listingId);
        }

        if ($sku = $request->get('sku')) {
            $query->where('ebay_sku', $sku);
        }

        if ($since = $request->get('since')) {
            $query->where('created_at', '>=', $since);
        }

        $query->orderBy('created_at', 'desc');

        $perPage = min($request->get('per_page', 50), 200);
        $logs = $query->paginate($perPage);

        return response()->json($logs);
    }

    /**
     * Get category suggestions
     */
    public function categorySuggestions(Request $request): JsonResponse
    {
        $team = $request->user()->currentTeam;
        $credential = $this->getCredential($team->organization_id);

        if (! $credential) {
            return response()->json(['error' => 'No eBay integration configured'], 404);
        }

        $validated = $request->validate([
            'query' => 'required|string|min:2|max:100',
        ]);

        try {
            $service = new EbayInventoryService($credential);
            $suggestions = $service->getCategorySuggestions($validated['query']);

            return response()->json([
                'suggestions' => collect($suggestions)->take(15)->map(fn ($s) => [
                    'category_id' => $s['category']['categoryId'] ?? null,
                    'category_name' => $s['category']['categoryName'] ?? null,
                    'ancestors' => $s['categoryTreeNodeAncestors'] ?? [],
                ])->filter(fn ($s) => $s['category_id'] !== null)->values(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch categories',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get business policies
     */
    public function policies(Request $request): JsonResponse
    {
        $team = $request->user()->currentTeam;
        $credential = $this->getCredential($team->organization_id);

        if (! $credential) {
            return response()->json(['error' => 'No eBay integration configured'], 404);
        }

        if (! $credential->getActiveEbayToken()) {
            return response()->json(['error' => 'No active eBay token'], 401);
        }

        try {
            $service = new EbayInventoryService($credential);
            $policies = $service->getBusinessPolicies();

            return response()->json([
                'fulfillment' => $policies['fulfillment'] ?? [],
                'payment' => $policies['payment'] ?? [],
                'return' => $policies['return'] ?? [],
            ]);

        } catch (EbayApiException $e) {
            return response()->json([
                'error' => $e->getUserMessage(),
                'code' => $e->getEbayErrorCode(),
            ], $e->getStatusCode() ?? 500);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch policies',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update configuration
     */
    public function updateConfig(Request $request): JsonResponse
    {
        $team = $request->user()->currentTeam;
        $credential = $this->getCredential($team->organization_id);

        if (! $credential) {
            return response()->json(['error' => 'No eBay integration configured'], 404);
        }

        $validated = $request->validate([
            'marketplace_id' => 'sometimes|string|max:20',
            'fulfillment_policy_id' => 'sometimes|nullable|string|max:50',
            'payment_policy_id' => 'sometimes|nullable|string|max:50',
            'return_policy_id' => 'sometimes|nullable|string|max:50',
            'merchant_location_key' => 'sometimes|nullable|string|max:50',
            'auto_sync_enabled' => 'sometimes|boolean',
            'sync_interval_minutes' => 'sometimes|integer|min:15|max:1440',
        ]);

        $credential->update($validated);

        return response()->json([
            'message' => 'Configuration updated',
            'is_ready' => $credential->fresh()->isEbayReady(),
        ]);
    }

    /**
     * Helper: Get credential for organization
     */
    protected function getCredential(int $organizationId): ?IntegrationCredential
    {
        return IntegrationCredential::where('organization_id', $organizationId)
            ->where('platform', 'ebay')
            ->where('is_active', true)
            ->first();
    }
}
