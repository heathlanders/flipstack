<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Jobs\Inventory\PushInventoryToMarketplaces;
use App\Jobs\Shopify\MatchShopifyProducts;
use App\Models\Catalog\Product;
use App\Models\Integration\IntegrationCredential;
use App\Models\Marketplace\MarketplaceProductMatch;
use App\Models\Shopify\ShopifyListing;
use App\Models\System\BulkOperationJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShopifyProductMatchController extends Controller
{
    public function startMatch(Request $request)
    {
        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);

        if (! $credential || ! $credential->is_active) {
            return back()->withErrors(['shopify' => 'Shopify is not connected']);
        }

        $bulkJob = BulkOperationJob::create([
            'organization_id' => $user->organization_id,
            'user_id' => $user->id,
            'operation_type' => BulkOperationJob::TYPE_SHOPIFY_MATCHING,
            'status' => BulkOperationJob::STATUS_PENDING,
            'total_items' => 0,
            'processed_items' => 0,
            'failed_items' => 0,
            'parameters' => [
                'credential_id' => $credential->id,
            ],
        ]);

        MatchShopifyProducts::dispatch(
            $credential->id,
            $user->organization_id,
            $user->id,
            $bulkJob->id
        );

        return redirect()->route('marketplaces.shopify.match.review')
            ->with('success', 'Product matching started');
    }

    public function review(Request $request): Response
    {
        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);

        $bulkJob = BulkOperationJob::where('organization_id', $user->organization_id)
            ->where('operation_type', BulkOperationJob::TYPE_SHOPIFY_MATCHING)
            ->orderByDesc('created_at')
            ->first();

        $matches = collect();
        $summary = [
            'total' => 0,
            'matched_high' => 0,
            'matched_low' => 0,
            'unmatched' => 0,
            'linked' => 0,
            'ignored' => 0,
        ];

        if ($bulkJob) {
            $query = MarketplaceProductMatch::forSession($bulkJob->id)
                ->with('product:id,name,sku,upc,ean,barcode,selling_price,available_quantity,featured_image');

            $matches = $query->orderByRaw("
                CASE
                    WHEN status = 'pending' AND product_id IS NOT NULL AND confidence >= 85 THEN 1
                    WHEN status = 'pending' AND product_id IS NOT NULL AND confidence < 85 THEN 2
                    WHEN status = 'pending' AND product_id IS NULL THEN 3
                    WHEN status = 'linked' THEN 4
                    WHEN status = 'ignored' THEN 5
                    WHEN status = 'rejected' THEN 6
                    ELSE 7
                END
            ")->paginate(50)->withQueryString();

            $summary = [
                'total' => MarketplaceProductMatch::forSession($bulkJob->id)->count(),
                'matched_high' => MarketplaceProductMatch::forSession($bulkJob->id)->highConfidence()->count(),
                'matched_low' => MarketplaceProductMatch::forSession($bulkJob->id)->lowConfidence()->count(),
                'unmatched' => MarketplaceProductMatch::forSession($bulkJob->id)->unmatched()->count(),
                'linked' => MarketplaceProductMatch::forSession($bulkJob->id)->linked()->count(),
                'ignored' => MarketplaceProductMatch::forSession($bulkJob->id)->where('status', 'ignored')->count(),
            ];
        }

        return Inertia::render('Shopify/Match/Review', [
            'matches' => $matches,
            'summary' => $summary,
            'bulkJob' => $bulkJob ? [
                'id' => $bulkJob->id,
                'status' => $bulkJob->status,
                'total_items' => $bulkJob->total_items,
                'processed_items' => $bulkJob->processed_items,
                'failed_items' => $bulkJob->failed_items,
                'progress_percent' => $bulkJob->progress_percent,
                'parameters' => $bulkJob->parameters,
            ] : null,
            'integrationStatus' => $credential ? $this->getIntegrationStatus($credential) : ['connected' => false],
        ]);
    }

    public function status(Request $request, BulkOperationJob $bulkJob): JsonResponse
    {
        $user = $request->user();

        if ($bulkJob->organization_id !== $user->organization_id) {
            abort(403);
        }

        return response()->json([
            'id' => $bulkJob->id,
            'status' => $bulkJob->status,
            'total_items' => $bulkJob->total_items,
            'processed_items' => $bulkJob->processed_items,
            'failed_items' => $bulkJob->failed_items,
            'progress_percent' => $bulkJob->progress_percent,
            'parameters' => $bulkJob->parameters,
        ]);
    }

    public function confirm(Request $request)
    {
        $request->validate([
            'match_ids' => 'required|array',
            'match_ids.*' => 'integer|exists:marketplace_product_matches,id',
        ]);

        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);

        if (! $credential) {
            return back()->withErrors(['shopify' => 'Shopify is not connected']);
        }

        $matches = MarketplaceProductMatch::whereIn('id', $request->match_ids)
            ->where('organization_id', $user->organization_id)
            ->where('status', MarketplaceProductMatch::STATUS_PENDING)
            ->whereNotNull('product_id')
            ->get();

        $linked = 0;

        foreach ($matches as $match) {
            $listing = $this->createShopifyListing($match, $credential);

            if ($listing) {
                $match->update([
                    'status' => MarketplaceProductMatch::STATUS_LINKED,
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                ]);

                PushInventoryToMarketplaces::dispatch($match->product_id);
                $linked++;
            }
        }

        return back()->with('success', "{$linked} products linked to Shopify");
    }

    public function confirmAll(Request $request)
    {
        $user = $request->user();
        $credential = $this->getCredential($user->organization_id);

        if (! $credential) {
            return back()->withErrors(['shopify' => 'Shopify is not connected']);
        }

        $bulkJob = BulkOperationJob::where('organization_id', $user->organization_id)
            ->where('operation_type', BulkOperationJob::TYPE_SHOPIFY_MATCHING)
            ->orderByDesc('created_at')
            ->first();

        if (! $bulkJob) {
            return back()->withErrors(['match' => 'No matching session found']);
        }

        $matches = MarketplaceProductMatch::forSession($bulkJob->id)
            ->highConfidence()
            ->get();

        $linked = 0;

        foreach ($matches as $match) {
            $listing = $this->createShopifyListing($match, $credential);

            if ($listing) {
                $match->update([
                    'status' => MarketplaceProductMatch::STATUS_LINKED,
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now(),
                ]);

                PushInventoryToMarketplaces::dispatch($match->product_id);
                $linked++;
            }
        }

        return back()->with('success', "{$linked} high-confidence matches linked to Shopify");
    }

    public function reject(Request $request, MarketplaceProductMatch $match)
    {
        $user = $request->user();

        if ($match->organization_id !== $user->organization_id) {
            abort(403);
        }

        $match->update([
            'status' => MarketplaceProductMatch::STATUS_REJECTED,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Match rejected');
    }

    public function pair(Request $request, MarketplaceProductMatch $match)
    {
        $request->validate([
            'product_id' => 'required|string|exists:products,id',
        ]);

        $user = $request->user();

        if ($match->organization_id !== $user->organization_id) {
            abort(403);
        }

        $product = Product::where('id', $request->product_id)
            ->where('organization_id', $user->organization_id)
            ->firstOrFail();

        $match->update([
            'product_id' => $product->id,
            'match_type' => MarketplaceProductMatch::MATCH_MANUAL,
            'confidence' => 100,
            'status' => MarketplaceProductMatch::STATUS_PENDING,
        ]);

        return back()->with('success', 'Product paired - confirm to link');
    }

    public function ignore(Request $request, MarketplaceProductMatch $match)
    {
        $user = $request->user();

        if ($match->organization_id !== $user->organization_id) {
            abort(403);
        }

        $match->update([
            'status' => MarketplaceProductMatch::STATUS_IGNORED,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Item ignored');
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2',
        ]);

        $user = $request->user();

        $products = Product::where('organization_id', $user->organization_id)
            ->where(function ($query) use ($request) {
                $query->where('name', 'ilike', '%'.$request->q.'%')
                    ->orWhere('sku', 'ilike', '%'.$request->q.'%')
                    ->orWhere('upc', 'ilike', '%'.$request->q.'%');
            })
            ->select('id', 'name', 'sku', 'upc', 'selling_price', 'available_quantity', 'featured_image')
            ->limit(20)
            ->get();

        return response()->json(['products' => $products]);
    }

    protected function createShopifyListing(MarketplaceProductMatch $match, IntegrationCredential $credential): ?ShopifyListing
    {
        $sourceData = $match->source_data;

        if (! $sourceData) {
            return null;
        }

        // Check if listing already exists
        if (ShopifyListing::where('shopify_product_id', $match->source_listing_id)
            ->where('integration_credential_id', $credential->id)
            ->exists()) {
            return null;
        }

        $variant = $sourceData['variants'][0] ?? [];

        return ShopifyListing::create([
            'product_id' => $match->product_id,
            'integration_credential_id' => $credential->id,
            'team_id' => $credential->team_id,
            'shopify_product_id' => $match->source_listing_id,
            'shopify_variant_id' => $match->source_variant_id,
            'shopify_inventory_item_id' => $match->source_inventory_item_id,
            'shopify_sku' => $match->source_sku ?? 'SHOPIFY-'.$match->source_listing_id,
            'shopify_handle' => $sourceData['handle'] ?? null,
            'status' => ($sourceData['status'] ?? 'active') === 'active'
                ? ShopifyListing::STATUS_ACTIVE
                : ShopifyListing::STATUS_DRAFT,
            'listed_price' => $match->source_price,
            'compare_at_price' => $variant['compare_at_price'] ?? null,
            'currency' => 'USD',
            'inventory_quantity' => $match->source_quantity ?? 0,
            'last_synced_at' => now(),
            'last_shopify_response' => $sourceData,
        ]);
    }

    protected function getCredential(int $organizationId): ?IntegrationCredential
    {
        return IntegrationCredential::where('organization_id', $organizationId)
            ->where('platform', 'shopify')
            ->where('is_active', true)
            ->first();
    }

    protected function getIntegrationStatus(IntegrationCredential $credential): array
    {
        $config = $credential->config ?? [];
        $token = $credential->getActiveShopifyToken();

        return [
            'connected' => $token !== null,
            'shop_name' => $config['shop_name'] ?? null,
            'shop_domain' => $config['shop_domain'] ?? null,
            'auto_sync_enabled' => $credential->auto_sync_enabled,
            'credential_id' => $credential->id,
        ];
    }
}
