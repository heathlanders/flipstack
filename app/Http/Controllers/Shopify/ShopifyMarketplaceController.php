<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Jobs\Shopify\BulkSyncToShopify;
use App\Jobs\Shopify\ImportShopifyProducts;
use App\Jobs\Shopify\SyncProductToShopify;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Integration\IntegrationCredential;
use App\Models\Inventory\InventoryLocation;
use App\Models\Shopify\ShopifyCollectionMapping;
use App\Models\Shopify\ShopifyListing;
use App\Models\Shopify\ShopifySyncLog;
use App\Models\System\BulkOperationJob;
use App\Services\Shopify\ShopifyCollectionService;
use App\Services\Shopify\ShopifyConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class ShopifyMarketplaceController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);
        $integrationStatus = $this->getIntegrationStatus($credential);
        $stats = $this->getListingStats($credential);

        $locations = InventoryLocation::where('organization_id', $user->organization_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return Inertia::render('Shopify/Settings/Index', [
            'integrationStatus' => $integrationStatus,
            'stats' => $stats,
            'shopifyConfig' => [
                'configured' => app(ShopifyConfigService::class)->isConfigured(),
            ],
            'locations' => $locations,
        ]);
    }

    public function products(Request $request): Response
    {
        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);

        $query = Product::where('organization_id', $user->organization_id)
            ->where('status', 'active')
            ->where('stock_quantity', '>', 0);

        if ($request->filled('search')) {
            $query->where('name', 'ilike', '%'.$request->search.'%');
        }

        $products = $query->with(['brand', 'category'])
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        // Attach Shopify listing status
        if ($credential) {
            $listingStatuses = ShopifyListing::where('integration_credential_id', $credential->id)
                ->whereIn('product_id', $products->pluck('id'))
                ->pluck('status', 'product_id');

            $products->through(function ($product) use ($listingStatuses) {
                $product->shopify_status = $listingStatuses[$product->id] ?? null;

                return $product;
            });
        }

        return Inertia::render('Shopify/Products/Index', [
            'products' => $products,
            'filters' => $request->only('search'),
            'integrationStatus' => $this->getIntegrationStatus($credential),
        ]);
    }

    public function syncProduct(Request $request, Product $product)
    {
        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);

        if (! $credential || ! $credential->is_active) {
            return back()->withErrors(['shopify' => 'Shopify is not connected']);
        }

        SyncProductToShopify::dispatch($product, $credential);

        return back()->with('success', 'Product sync queued');
    }

    public function syncBulk(Request $request)
    {
        $request->validate([
            'product_ids' => 'required|array',
            'product_ids.*' => 'string',
        ]);

        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);

        if (! $credential || ! $credential->is_active) {
            return back()->withErrors(['shopify' => 'Shopify is not connected']);
        }

        BulkSyncToShopify::dispatch(
            $request->product_ids,
            $credential->id,
            $user->currentTeam->id
        );

        return back()->with('success', count($request->product_ids).' products queued for sync');
    }

    public function startImport(Request $request)
    {
        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);

        if (! $credential || ! $credential->is_active) {
            return back()->withErrors(['shopify' => 'Shopify is not connected']);
        }

        $request->validate([
            'location_id' => [
                'nullable',
                'uuid',
                \Illuminate\Validation\Rule::exists('inventory_locations', 'id')
                    ->where('organization_id', $user->organization_id),
            ],
        ]);

        $locationId = $request->input('location_id');

        $bulkJob = BulkOperationJob::create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'operation_type' => 'shopify_import',
            'status' => 'pending',
            'total_items' => 0,
            'processed_items' => 0,
            'failed_items' => 0,
            'parameters' => [
                'credential_id' => $credential->id,
                'skip_existing_skus' => $request->boolean('skip_existing_skus', true),
                'location_id' => $locationId,
            ],
        ]);

        ImportShopifyProducts::dispatch(
            $credential->id,
            $user->organization_id,
            $user->id,
            $bulkJob->id,
            [
                'skip_existing_skus' => $request->boolean('skip_existing_skus', true),
                'location_id' => $locationId,
            ]
        );

        return back()->with('success', 'Shopify import started');
    }

    public function logs(Request $request): Response
    {
        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);

        $query = ShopifySyncLog::query()
            ->with(['product:id,name,sku', 'initiator:id,name'])
            ->orderBy('created_at', 'desc');

        if ($credential) {
            $query->where('integration_credential_id', $credential->id);
        }

        if ($request->filled('operation')) {
            $query->where('operation', $request->operation);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $logs = $query->paginate(50)->withQueryString();

        return Inertia::render('Shopify/SyncLogs/Index', [
            'logs' => $logs,
            'filters' => $request->only('operation', 'status'),
        ]);
    }

    public function categorySettings(Request $request): Response
    {
        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);

        $config = $credential?->config ?? [];
        $categories = Category::forOrganization($user->organization_id)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name']);

        $mappings = $credential
            ? ShopifyCollectionMapping::forCredential($credential->id)
                ->with('category:id,name')
                ->get()
            : collect();

        return Inertia::render('Shopify/Settings/CategorySettings', [
            'integrationStatus' => $this->getIntegrationStatus($credential),
            'settings' => [
                'product_type_source' => $config['shopify_product_type_source'] ?? 'internal_category',
                'collection_mapping_enabled' => (bool) ($config['shopify_collection_mapping_enabled'] ?? false),
            ],
            'mappings' => $mappings,
            'categories' => $categories,
        ]);
    }

    public function updateCategorySettings(Request $request)
    {
        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);

        if (! $credential) {
            return back()->withErrors(['shopify' => 'Shopify is not connected']);
        }

        $validated = $request->validate([
            'product_type_source' => 'required|in:internal_category,ebay_category,none',
            'collection_mapping_enabled' => 'required|boolean',
            'mappings' => 'array',
            'mappings.*.category_id' => 'nullable|string',
            'mappings.*.shopify_collection_id' => 'required|integer',
            'mappings.*.shopify_collection_title' => 'required|string|max:255',
            'mappings.*.collection_type' => 'required|in:custom,smart',
            'mappings.*.is_default' => 'required|boolean',
        ]);

        // Update config keys
        $config = $credential->config ?? [];
        $config['shopify_product_type_source'] = $validated['product_type_source'];
        $config['shopify_collection_mapping_enabled'] = $validated['collection_mapping_enabled'];
        $credential->update(['config' => $config]);

        // Sync mappings - delete all, re-insert
        ShopifyCollectionMapping::forCredential($credential->id)->delete();

        foreach ($validated['mappings'] ?? [] as $row) {
            ShopifyCollectionMapping::create([
                'integration_credential_id' => $credential->id,
                'category_id' => $row['category_id'] ?: null,
                'shopify_collection_id' => $row['shopify_collection_id'],
                'shopify_collection_title' => $row['shopify_collection_title'],
                'collection_type' => $row['collection_type'],
                'is_default' => $row['is_default'],
            ]);
        }

        return back()->with('success', 'Category settings saved');
    }

    public function fetchCollections(Request $request)
    {
        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);

        if (! $credential) {
            return response()->json(['error' => 'Shopify is not connected'], 422);
        }

        try {
            $service = new ShopifyCollectionService($credential);
            $bustCache = $request->boolean('refresh', false);
            $collections = $service->getAllCollections($bustCache);

            return response()->json(['collections' => $collections]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch Shopify collections', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Failed to fetch collections'], 500);
        }
    }

    public function toggleAutoSync(Request $request)
    {
        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);

        if (! $credential) {
            return back()->withErrors(['shopify' => 'Shopify is not connected']);
        }

        $credential->update([
            'auto_sync_enabled' => ! $credential->auto_sync_enabled,
        ]);

        $status = $credential->auto_sync_enabled ? 'enabled' : 'disabled';

        return back()->with('success', "Auto-sync {$status}.");
    }

    // Helper methods

    protected function getCredential(int $organizationId): ?IntegrationCredential
    {
        return IntegrationCredential::where('organization_id', $organizationId)
            ->where('platform', 'shopify')
            ->where('is_active', true)
            ->first();
    }

    protected function getIntegrationStatus(?IntegrationCredential $credential): array
    {
        if (! $credential) {
            return [
                'connected' => false,
                'shop_name' => null,
                'shop_domain' => null,
            ];
        }

        $config = $credential->config ?? [];
        $token = $credential->getActiveShopifyToken();

        return [
            'connected' => $token !== null,
            'shop_name' => $config['shop_name'] ?? null,
            'shop_domain' => $config['shop_domain'] ?? null,
            'primary_location_id' => $config['primary_location_id'] ?? null,
            'auto_sync_enabled' => $credential->auto_sync_enabled,
            'credential_id' => $credential->id,
        ];
    }

    protected function getListingStats(?IntegrationCredential $credential): array
    {
        if (! $credential) {
            return [
                'total' => 0,
                'active' => 0,
                'draft' => 0,
                'error' => 0,
                'pending' => 0,
            ];
        }

        $listings = ShopifyListing::where('integration_credential_id', $credential->id);

        return [
            'total' => (clone $listings)->count(),
            'active' => (clone $listings)->where('status', ShopifyListing::STATUS_ACTIVE)->count(),
            'draft' => (clone $listings)->where('status', ShopifyListing::STATUS_DRAFT)->count(),
            'error' => (clone $listings)->where('status', ShopifyListing::STATUS_ERROR)->count(),
            'pending' => (clone $listings)->where('status', ShopifyListing::STATUS_PENDING)->count(),
        ];
    }
}
