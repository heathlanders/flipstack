<?php

namespace App\Http\Controllers\Amazon;

use App\Http\Controllers\Controller;
use App\Jobs\Amazon\CreateAmazonListingJob;
use App\Models\Amazon\AmazonListing;
use App\Models\Amazon\AmazonSyncLog;
use App\Models\Catalog\Product;
use App\Models\Integration\IntegrationCredential;
use App\Services\Amazon\AmazonConfigService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AmazonMarketplaceController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);

        return Inertia::render('Marketplaces/Amazon/Index', [
            'integrationStatus' => $this->getIntegrationStatus($credential),
            'stats' => $this->getListingStats($credential),
            'amazonConfig' => [
                'configured' => app(AmazonConfigService::class)->isConfigured(),
                'environment' => app(AmazonConfigService::class)->getEnvironment(),
            ],
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

        if ($credential) {
            $listingStatuses = AmazonListing::where('integration_credential_id', $credential->id)
                ->whereIn('product_id', $products->pluck('id'))
                ->get(['product_id', 'status', 'asin', 'amazon_sku', 'listed_price', 'buy_box_price', 'fulfillment_channel'])
                ->keyBy('product_id');

            $products->through(function ($product) use ($listingStatuses) {
                $row = $listingStatuses[$product->id] ?? null;
                $product->amazon_status = $row?->status;
                $product->amazon_asin = $row?->asin;
                $product->amazon_sku = $row?->amazon_sku;
                $product->amazon_listed_price = $row?->listed_price;
                $product->amazon_buy_box_price = $row?->buy_box_price;
                $product->amazon_fulfillment_channel = $row?->fulfillment_channel;

                return $product;
            });
        }

        return Inertia::render('Marketplaces/Amazon/Products', [
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
            return back()->withErrors(['amazon' => 'Amazon is not connected']);
        }

        if ($product->organization_id !== $user->organization_id) {
            abort(403);
        }

        $validated = $request->validate([
            'asin' => 'nullable|string|max:20',
            'fulfillment_channel' => 'nullable|in:FBM,FBA',
        ]);

        CreateAmazonListingJob::dispatch(
            (string) $product->id,
            $credential->id,
            $validated['asin'] ?? null,
            $validated['fulfillment_channel'] ?? AmazonListing::FULFILLMENT_FBM,
        );

        return back()->with('success', 'Amazon listing queued for creation');
    }

    public function startImport(Request $request)
    {
        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);

        if (! $credential || ! $credential->is_active) {
            return back()->withErrors(['amazon' => 'Amazon is not connected']);
        }

        // TODO Phase 4: queue an ImportAmazonProducts job that calls
        // AmazonStoreImportService::importAllProducts.
        return back()->withErrors(['amazon' => 'Amazon import job is being built (Phase 4).']);
    }

    public function logs(Request $request): Response
    {
        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);

        $query = AmazonSyncLog::query()
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

        return Inertia::render('Marketplaces/Amazon/Logs', [
            'logs' => $logs,
            'filters' => $request->only('operation', 'status'),
        ]);
    }

    public function toggleAutoSync(Request $request)
    {
        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);

        if (! $credential) {
            return back()->withErrors(['amazon' => 'Amazon is not connected']);
        }

        $credential->update([
            'auto_sync_enabled' => ! $credential->auto_sync_enabled,
        ]);

        $status = $credential->auto_sync_enabled ? 'enabled' : 'disabled';

        return back()->with('success', "Auto-sync {$status}.");
    }

    protected function getCredential(int $organizationId): ?IntegrationCredential
    {
        return IntegrationCredential::where('organization_id', $organizationId)
            ->where('platform', 'amazon')
            ->where('is_active', true)
            ->first();
    }

    protected function getIntegrationStatus(?IntegrationCredential $credential): array
    {
        if (! $credential) {
            return [
                'connected' => false,
                'seller_id' => null,
                'marketplace_id' => null,
            ];
        }

        $config = $credential->config ?? [];
        $token = $credential->getActiveAmazonToken();

        return [
            'connected' => $token !== null,
            'seller_id' => $config['amazon_seller_id'] ?? null,
            'marketplace_id' => $config['amazon_marketplace_id'] ?? null,
            'marketplace_name' => $config['amazon_marketplace_name'] ?? null,
            'country_code' => $config['amazon_country_code'] ?? null,
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
                'pending' => 0,
                'error' => 0,
            ];
        }

        $listings = AmazonListing::where('integration_credential_id', $credential->id);

        return [
            'total' => (clone $listings)->count(),
            'active' => (clone $listings)->where('status', AmazonListing::STATUS_ACTIVE)->count(),
            'pending' => (clone $listings)->whereIn('status', [
                AmazonListing::STATUS_PENDING,
                AmazonListing::STATUS_CREATED,
            ])->count(),
            'error' => (clone $listings)->where('status', AmazonListing::STATUS_ERROR)->count(),
        ];
    }
}
