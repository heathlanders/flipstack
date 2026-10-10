<?php

namespace App\Services;

use App\Models\Amazon\AmazonListing;
use App\Models\Amazon\AmazonSyncLog;
use App\Models\Ebay\EbayListing;
use App\Models\Ebay\EbaySyncLog;
use App\Models\Integration\IntegrationCredential;
use App\Models\Shopify\ShopifyListing;
use App\Models\Shopify\ShopifySyncLog;
use Illuminate\Support\Facades\DB;

class MarketplaceStatsService
{
    /**
     * Get aggregated stats across all marketplaces for an organization.
     */
    public function getOverviewStats(int $organizationId): array
    {
        $credentials = IntegrationCredential::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->get();

        $ebayCredentialIds = $credentials->where('platform', 'ebay')->pluck('id')->toArray();
        $shopifyCredentialIds = $credentials->where('platform', 'shopify')->pluck('id')->toArray();
        $amazonCredentialIds = $credentials->where('platform', 'amazon')->pluck('id')->toArray();

        return [
            'platforms' => $this->getPlatformSummaries($ebayCredentialIds, $shopifyCredentialIds, $amazonCredentialIds),
            'totals' => $this->getTotals($ebayCredentialIds, $shopifyCredentialIds, $amazonCredentialIds),
            'sync_health' => $this->getSyncHealth($ebayCredentialIds, $shopifyCredentialIds, $amazonCredentialIds),
            'recent_errors' => $this->getRecentErrors($ebayCredentialIds, $shopifyCredentialIds, $amazonCredentialIds),
            'connections' => $this->getConnectionStatus($credentials),
        ];
    }

    protected function getPlatformSummaries(array $ebayIds, array $shopifyIds, array $amazonIds): array
    {
        $platforms = [];

        if (! empty($ebayIds)) {
            $ebayStats = DB::table('ebay_listings')
                ->whereIn('integration_credential_id', $ebayIds)
                ->select([
                    DB::raw('COUNT(*) as total'),
                    DB::raw("SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as active"),
                    DB::raw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending"),
                    DB::raw("SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as errors"),
                    DB::raw("SUM(CASE WHEN status = 'ended' THEN 1 ELSE 0 END) as ended"),
                    DB::raw("SUM(CASE WHEN status = 'published' THEN listed_price ELSE 0 END) as total_listed_value"),
                    DB::raw("SUM(CASE WHEN status = 'published' THEN sold_quantity ELSE 0 END) as total_sold"),
                ])
                ->first();

            $platforms['ebay'] = [
                'name' => 'eBay',
                'total' => (int) $ebayStats->total,
                'active' => (int) $ebayStats->active,
                'pending' => (int) $ebayStats->pending,
                'errors' => (int) $ebayStats->errors,
                'ended' => (int) $ebayStats->ended,
                'total_listed_value' => round((float) $ebayStats->total_listed_value, 2),
                'total_sold' => (int) $ebayStats->total_sold,
            ];
        }

        if (! empty($shopifyIds)) {
            $shopifyStats = DB::table('shopify_listings')
                ->whereIn('integration_credential_id', $shopifyIds)
                ->select([
                    DB::raw('COUNT(*) as total'),
                    DB::raw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active"),
                    DB::raw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending"),
                    DB::raw("SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as errors"),
                    DB::raw("SUM(CASE WHEN status = 'archived' THEN 1 ELSE 0 END) as archived"),
                    DB::raw("SUM(CASE WHEN status = 'active' THEN listed_price ELSE 0 END) as total_listed_value"),
                ])
                ->first();

            $platforms['shopify'] = [
                'name' => 'Shopify',
                'total' => (int) $shopifyStats->total,
                'active' => (int) $shopifyStats->active,
                'pending' => (int) $shopifyStats->pending,
                'errors' => (int) $shopifyStats->errors,
                'archived' => (int) $shopifyStats->archived,
                'total_listed_value' => round((float) $shopifyStats->total_listed_value, 2),
            ];
        }

        if (! empty($amazonIds)) {
            $amazonStats = DB::table('amazon_listings')
                ->whereIn('integration_credential_id', $amazonIds)
                ->whereNull('deleted_at')
                ->select([
                    DB::raw('COUNT(*) as total'),
                    DB::raw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active"),
                    DB::raw("SUM(CASE WHEN status IN ('pending','created') THEN 1 ELSE 0 END) as pending"),
                    DB::raw("SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as errors"),
                    DB::raw("SUM(CASE WHEN status = 'incomplete' THEN 1 ELSE 0 END) as incomplete"),
                    DB::raw("SUM(CASE WHEN status = 'active' THEN listed_price ELSE 0 END) as total_listed_value"),
                ])
                ->first();

            $platforms['amazon'] = [
                'name' => 'Amazon',
                'total' => (int) $amazonStats->total,
                'active' => (int) $amazonStats->active,
                'pending' => (int) $amazonStats->pending,
                'errors' => (int) $amazonStats->errors,
                'incomplete' => (int) $amazonStats->incomplete,
                'total_listed_value' => round((float) $amazonStats->total_listed_value, 2),
            ];
        }

        return $platforms;
    }

    protected function getTotals(array $ebayIds, array $shopifyIds, array $amazonIds): array
    {
        $ebayTotal = ! empty($ebayIds)
            ? EbayListing::whereIn('integration_credential_id', $ebayIds)->count()
            : 0;

        $ebayActive = ! empty($ebayIds)
            ? EbayListing::whereIn('integration_credential_id', $ebayIds)->where('status', 'published')->count()
            : 0;

        $shopifyTotal = ! empty($shopifyIds)
            ? ShopifyListing::whereIn('integration_credential_id', $shopifyIds)->count()
            : 0;

        $shopifyActive = ! empty($shopifyIds)
            ? ShopifyListing::whereIn('integration_credential_id', $shopifyIds)->where('status', 'active')->count()
            : 0;

        $amazonTotal = ! empty($amazonIds)
            ? AmazonListing::whereIn('integration_credential_id', $amazonIds)->count()
            : 0;

        $amazonActive = ! empty($amazonIds)
            ? AmazonListing::whereIn('integration_credential_id', $amazonIds)->where('status', AmazonListing::STATUS_ACTIVE)->count()
            : 0;

        $totalErrors = 0;
        if (! empty($ebayIds)) {
            $totalErrors += EbayListing::whereIn('integration_credential_id', $ebayIds)->where('status', 'error')->count();
        }
        if (! empty($shopifyIds)) {
            $totalErrors += ShopifyListing::whereIn('integration_credential_id', $shopifyIds)->where('status', 'error')->count();
        }
        if (! empty($amazonIds)) {
            $totalErrors += AmazonListing::whereIn('integration_credential_id', $amazonIds)->where('status', AmazonListing::STATUS_ERROR)->count();
        }

        return [
            'total_listings' => $ebayTotal + $shopifyTotal + $amazonTotal,
            'active_listings' => $ebayActive + $shopifyActive + $amazonActive,
            'total_errors' => $totalErrors,
            'platforms_connected' => (! empty($ebayIds) ? 1 : 0)
                + (! empty($shopifyIds) ? 1 : 0)
                + (! empty($amazonIds) ? 1 : 0),
        ];
    }

    protected function getSyncHealth(array $ebayIds, array $shopifyIds, array $amazonIds): array
    {
        $last24h = now()->subHours(24);

        $ebayOps = ! empty($ebayIds)
            ? EbaySyncLog::whereIn('integration_credential_id', $ebayIds)
                ->where('created_at', '>=', $last24h)
                ->select([
                    DB::raw('COUNT(*) as total'),
                    DB::raw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success"),
                    DB::raw("SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as failed"),
                ])
                ->first()
            : null;

        $shopifyOps = ! empty($shopifyIds)
            ? ShopifySyncLog::whereIn('integration_credential_id', $shopifyIds)
                ->where('created_at', '>=', $last24h)
                ->select([
                    DB::raw('COUNT(*) as total'),
                    DB::raw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success"),
                    DB::raw("SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as failed"),
                ])
                ->first()
            : null;

        $amazonOps = ! empty($amazonIds)
            ? AmazonSyncLog::whereIn('integration_credential_id', $amazonIds)
                ->where('created_at', '>=', $last24h)
                ->select([
                    DB::raw('COUNT(*) as total'),
                    DB::raw("SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success"),
                    DB::raw("SUM(CASE WHEN status IN ('error','failed') THEN 1 ELSE 0 END) as failed"),
                ])
                ->first()
            : null;

        $totalOps = (int) ($ebayOps?->total ?? 0) + (int) ($shopifyOps?->total ?? 0) + (int) ($amazonOps?->total ?? 0);
        $totalSuccess = (int) ($ebayOps?->success ?? 0) + (int) ($shopifyOps?->success ?? 0) + (int) ($amazonOps?->success ?? 0);
        $totalFailed = (int) ($ebayOps?->failed ?? 0) + (int) ($shopifyOps?->failed ?? 0) + (int) ($amazonOps?->failed ?? 0);

        return [
            'last_24h_operations' => $totalOps,
            'last_24h_success' => $totalSuccess,
            'last_24h_failed' => $totalFailed,
            'success_rate' => $totalOps > 0 ? round(($totalSuccess / $totalOps) * 100, 1) : null,
        ];
    }

    protected function getRecentErrors(array $ebayIds, array $shopifyIds, array $amazonIds, int $limit = 10): array
    {
        $errors = collect();

        if (! empty($ebayIds)) {
            $ebayErrors = EbaySyncLog::whereIn('integration_credential_id', $ebayIds)
                ->where('status', 'error')
                ->with('product:id,name,sku')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(fn ($log) => [
                    'id' => $log->id,
                    'platform' => 'ebay',
                    'operation' => $log->operation,
                    'operation_label' => $log->operation_label,
                    'error_message' => $log->error_message,
                    'product_name' => $log->product?->name,
                    'product_sku' => $log->product?->sku,
                    'created_at' => $log->created_at->toISOString(),
                    'created_at_human' => $log->created_at->diffForHumans(),
                ]);

            $errors = $errors->merge($ebayErrors);
        }

        if (! empty($shopifyIds)) {
            $shopifyErrors = ShopifySyncLog::whereIn('integration_credential_id', $shopifyIds)
                ->where('status', 'error')
                ->with('product:id,name,sku')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(fn ($log) => [
                    'id' => $log->id,
                    'platform' => 'shopify',
                    'operation' => $log->operation,
                    'operation_label' => $log->operation_label,
                    'error_message' => $log->error_message,
                    'product_name' => $log->product?->name,
                    'product_sku' => $log->product?->sku,
                    'created_at' => $log->created_at->toISOString(),
                    'created_at_human' => $log->created_at->diffForHumans(),
                ]);

            $errors = $errors->merge($shopifyErrors);
        }

        if (! empty($amazonIds)) {
            $amazonErrors = AmazonSyncLog::whereIn('integration_credential_id', $amazonIds)
                ->whereIn('status', ['error', 'failed'])
                ->with('product:id,name,sku')
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(fn ($log) => [
                    'id' => $log->id,
                    'platform' => 'amazon',
                    'operation' => $log->operation,
                    'operation_label' => $log->operation_label,
                    'error_message' => $log->error_message,
                    'product_name' => $log->product?->name,
                    'product_sku' => $log->product?->sku,
                    'created_at' => $log->created_at->toISOString(),
                    'created_at_human' => $log->created_at->diffForHumans(),
                ]);

            $errors = $errors->merge($amazonErrors);
        }

        return $errors->sortByDesc('created_at')->take($limit)->values()->toArray();
    }

    protected function getConnectionStatus($credentials): array
    {
        $connections = [];

        foreach ($credentials as $credential) {
            $connected = false;
            $username = null;

            if ($credential->platform === 'ebay') {
                $token = $credential->getActiveEbayToken();
                $connected = $token !== null;
                $username = $credential->seller_username ?? ($token?->metadata ?? [])['username'] ?? null;
            } elseif ($credential->platform === 'shopify') {
                $connected = $credential->isShopifyReady();
                $username = $credential->config['shop_domain'] ?? null;
            } elseif ($credential->platform === 'amazon') {
                $connected = $credential->isAmazonReady();
                $username = $credential->config['amazon_seller_id'] ?? null;
            }

            $connections[] = [
                'platform' => $credential->platform,
                'connected' => $connected,
                'username' => $username,
                'environment' => $credential->environment,
            ];
        }

        return $connections;
    }

    /**
     * Get cross-platform listing data for products.
     */
    public function getCrossplatformListings(int $organizationId, array $filters = [], int $perPage = 20)
    {
        $credentials = IntegrationCredential::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->get();

        $ebayCredentialIds = $credentials->where('platform', 'ebay')->pluck('id')->toArray();
        $shopifyCredentialIds = $credentials->where('platform', 'shopify')->pluck('id')->toArray();
        $amazonCredentialIds = $credentials->where('platform', 'amazon')->pluck('id')->toArray();

        $query = DB::table('products')
            ->where('products.organization_id', $organizationId)
            ->whereNull('products.deleted_at')
            ->where(function ($q) use ($ebayCredentialIds, $shopifyCredentialIds, $amazonCredentialIds) {
                if (! empty($ebayCredentialIds)) {
                    $q->orWhereExists(function ($sub) use ($ebayCredentialIds) {
                        $sub->select(DB::raw(1))
                            ->from('ebay_listings')
                            ->whereColumn('ebay_listings.product_id', 'products.id')
                            ->whereIn('ebay_listings.integration_credential_id', $ebayCredentialIds);
                    });
                }
                if (! empty($shopifyCredentialIds)) {
                    $q->orWhereExists(function ($sub) use ($shopifyCredentialIds) {
                        $sub->select(DB::raw(1))
                            ->from('shopify_listings')
                            ->whereColumn('shopify_listings.product_id', 'products.id')
                            ->whereIn('shopify_listings.integration_credential_id', $shopifyCredentialIds);
                    });
                }
                if (! empty($amazonCredentialIds)) {
                    $q->orWhereExists(function ($sub) use ($amazonCredentialIds) {
                        $sub->select(DB::raw(1))
                            ->from('amazon_listings')
                            ->whereColumn('amazon_listings.product_id', 'products.id')
                            ->whereIn('amazon_listings.integration_credential_id', $amazonCredentialIds)
                            ->whereNull('amazon_listings.deleted_at');
                    });
                }
            })
            ->select([
                'products.id',
                'products.name',
                'products.sku',
                'products.selling_price',
                'products.stock_quantity',
                'products.featured_image',
                'products.status',
            ]);

        // Apply search filter
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('products.name', 'ilike', "%{$search}%")
                    ->orWhere('products.sku', 'ilike', "%{$search}%");
            });
        }

        // Apply platform filter
        if (! empty($filters['platform'])) {
            if ($filters['platform'] === 'ebay' && ! empty($ebayCredentialIds)) {
                $query->whereExists(function ($sub) use ($ebayCredentialIds) {
                    $sub->select(DB::raw(1))
                        ->from('ebay_listings')
                        ->whereColumn('ebay_listings.product_id', 'products.id')
                        ->whereIn('ebay_listings.integration_credential_id', $ebayCredentialIds);
                });
            } elseif ($filters['platform'] === 'shopify' && ! empty($shopifyCredentialIds)) {
                $query->whereExists(function ($sub) use ($shopifyCredentialIds) {
                    $sub->select(DB::raw(1))
                        ->from('shopify_listings')
                        ->whereColumn('shopify_listings.product_id', 'products.id')
                        ->whereIn('shopify_listings.integration_credential_id', $shopifyCredentialIds);
                });
            } elseif ($filters['platform'] === 'amazon' && ! empty($amazonCredentialIds)) {
                $query->whereExists(function ($sub) use ($amazonCredentialIds) {
                    $sub->select(DB::raw(1))
                        ->from('amazon_listings')
                        ->whereColumn('amazon_listings.product_id', 'products.id')
                        ->whereIn('amazon_listings.integration_credential_id', $amazonCredentialIds)
                        ->whereNull('amazon_listings.deleted_at');
                });
            }
        }

        $query->orderBy('products.updated_at', 'desc');

        $paginated = $query->paginate($perPage)->withQueryString();

        // Hydrate marketplace data for each product
        $productIds = collect($paginated->items())->pluck('id')->toArray();

        $ebayListings = ! empty($ebayCredentialIds)
            ? EbayListing::whereIn('product_id', $productIds)
                ->whereIn('integration_credential_id', $ebayCredentialIds)
                ->get()
                ->groupBy('product_id')
            : collect();

        $shopifyListings = ! empty($shopifyCredentialIds)
            ? ShopifyListing::whereIn('product_id', $productIds)
                ->whereIn('integration_credential_id', $shopifyCredentialIds)
                ->get()
                ->groupBy('product_id')
            : collect();

        $amazonListings = ! empty($amazonCredentialIds)
            ? AmazonListing::whereIn('product_id', $productIds)
                ->whereIn('integration_credential_id', $amazonCredentialIds)
                ->get()
                ->groupBy('product_id')
            : collect();

        $paginated->getCollection()->transform(function ($product) use ($ebayListings, $shopifyListings, $amazonListings) {
            $product->ebay_listings = ($ebayListings[$product->id] ?? collect())->map(fn ($l) => [
                'id' => $l->id,
                'status' => $l->status,
                'status_label' => $l->status_label,
                'status_color' => $l->status_color,
                'listed_price' => $l->listed_price,
                'ebay_listing_id' => $l->ebay_listing_id,
                'ebay_url' => $l->ebay_url,
                'last_synced_at_human' => $l->last_synced_at?->diffForHumans(),
            ])->values();

            $product->shopify_listings = ($shopifyListings[$product->id] ?? collect())->map(fn ($l) => [
                'id' => $l->id,
                'status' => $l->status,
                'status_label' => $l->status_label,
                'status_color' => $l->status_color,
                'listed_price' => $l->listed_price,
                'shopify_product_id' => $l->shopify_product_id,
                'last_synced_at_human' => $l->last_synced_at?->diffForHumans(),
            ])->values();

            $product->amazon_listings = ($amazonListings[$product->id] ?? collect())->map(fn ($l) => [
                'id' => $l->id,
                'status' => $l->status,
                'asin' => $l->asin,
                'sku' => $l->amazon_sku,
                'fulfillment_channel' => $l->fulfillment_channel,
                'listed_price' => $l->listed_price,
                'buy_box_price' => $l->buy_box_price,
                'last_synced_at_human' => $l->last_synced_at?->diffForHumans(),
            ])->values();

            return $product;
        });

        return $paginated;
    }
}
