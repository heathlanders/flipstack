<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\Catalog\Product;
use App\Models\Catalog\ProductIntakeQueue;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ListerDashboardController extends BaseDashboardController
{
    public function show(Request $request)
    {
        $user = $request->user();
        $organization = $user->organization;

        return Inertia::render('Dashboard/Lister', [
            'dashboardType' => 'lister',
            'user' => [
                'name' => $user->name,
                'role' => $user->getPrimaryRole(),
                'permissions' => $user->getEffectivePermissions(),
            ],
            'metrics' => $this->getListerMetrics($user),
            'recentActivity' => $this->getRecentActivity($user, 'lister'),
            'alerts' => $this->getAlerts($user),
            'quickActions' => $user->getQuickActions(),
            'listingPipeline' => $this->getListingPipeline($organization),
            'performanceMetrics' => $this->getPerformanceMetrics($organization),
            'platformBreakdown' => $this->getPlatformBreakdown($organization),
            'optimizationSuggestions' => $this->getOptimizationSuggestions($organization),
        ]);
    }

    protected function getListerMetrics($user)
    {
        $organization = $user->organization;
        $startOfWeek = now()->startOfWeek();
        $startOfMonth = now()->startOfMonth();

        // Items in intake queue
        $totalPending = ProductIntakeQueue::where('organization_id', $organization->id)
            ->where('processing_status', 'pending')
            ->count();

        $completedThisWeek = ProductIntakeQueue::where('organization_id', $organization->id)
            ->where('processing_status', 'completed')
            ->where('updated_at', '>=', $startOfWeek)
            ->count();

        // Products created/listed this month
        $listedThisMonth = Product::where('organization_id', $organization->id)
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        // Items needing photos (products with no product_images records)
        $needsPhotos = Product::where('organization_id', $organization->id)
            ->whereDoesntHave('images')
            ->count();

        $avgProcessingTime = ProductIntakeQueue::where('organization_id', $organization->id)
            ->where('processing_status', 'completed')
            ->whereNotNull('updated_at')
            ->selectRaw('AVG(EXTRACT(EPOCH FROM (updated_at - created_at)) / 3600) as avg_hours')
            ->value('avg_hours');

        $totalProducts = Product::where('organization_id', $organization->id)->count();
        $productsWithPhotos = $totalProducts > 0
            ? Product::where('organization_id', $organization->id)->whereHas('images')->count()
            : 0;
        $productsWithDescription = $totalProducts > 0
            ? Product::where('organization_id', $organization->id)->whereNotNull('description')->where('description', '!=', '')->count()
            : 0;

        $photoCompletion = $totalProducts > 0 ? round(($productsWithPhotos / $totalProducts) * 100, 1) : 0;
        $descriptionCompletion = $totalProducts > 0 ? round(($productsWithDescription / $totalProducts) * 100, 1) : 0;

        return [
            'pipeline' => [
                'pending_intake' => $totalPending,
                'completed_this_week' => $completedThisWeek,
                'avg_processing_time' => round((float) ($avgProcessingTime ?? 0), 1),
            ],
            'listings' => [
                'this_month' => $listedThisMonth,
                'needs_photos' => $needsPhotos,
            ],
            'quality' => [
                'photo_completion' => $photoCompletion,
                'description_completion' => $descriptionCompletion,
            ],
        ];
    }

    protected function getListingPipeline($organization)
    {
        return [
            'intake' => ProductIntakeQueue::where('organization_id', $organization->id)
                ->where('processing_status', 'pending')
                ->orderBy('created_at', 'asc')
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'title' => $item->product_number ?? $item->manufacturer_part_number ?? $item->barcode ?? 'Untitled Item',
                        'type' => $item->capture_method ?? 'manual',
                        'identifier' => $item->barcode ?? $item->product_number,
                        'created_at' => $item->created_at,
                        'url' => route('intake-queue.show', $item),
                    ];
                }),

            'drafts' => Product::where('organization_id', $organization->id)
                ->where('status', 'draft')
                ->orderBy('created_at', 'asc')
                ->limit(10)
                ->get()
                ->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'title' => $product->name,
                        'category' => $product->category?->name ?? 'Uncategorized',
                        'completion' => $this->calculateProductCompletion($product),
                        'url' => route('inventory.products.show', $product),
                    ];
                }),
        ];
    }

    protected function calculateProductCompletion($product)
    {
        $completionScore = 0;
        $totalFields = 6;

        if ($product->name) {
            $completionScore++;
        }
        if ($product->description) {
            $completionScore++;
        }
        if ($product->category_id) {
            $completionScore++;
        }
        if ($product->cost_price > 0) {
            $completionScore++;
        }
        if ($product->selling_price > 0) {
            $completionScore++;
        }
        if ($product->images()->exists()) {
            $completionScore++;
        }

        return round(($completionScore / $totalFields) * 100);
    }

    protected function getPerformanceMetrics($organization)
    {
        $startOfMonth = now()->startOfMonth();

        $itemsThisMonth = ProductIntakeQueue::where('organization_id', $organization->id)
            ->where('processing_status', 'completed')
            ->where('updated_at', '>=', $startOfMonth)
            ->count();

        $totalProducts = Product::where('organization_id', $organization->id)->count();
        $productsWithPhotos = Product::where('organization_id', $organization->id)
            ->whereHas('images')
            ->count();

        $photoCompletionRate = $totalProducts > 0 ? ($productsWithPhotos / $totalProducts) * 100 : 0;

        return [
            'velocity' => [
                'items_per_day' => round($itemsThisMonth / max(1, now()->diffInDays($startOfMonth) + 1), 1),
                'monthly_total' => $itemsThisMonth,
            ],
            'quality' => [
                'photo_completion' => round($photoCompletionRate, 1),
                'description_completion' => $totalProducts > 0
                    ? round((Product::where('organization_id', $organization->id)->whereNotNull('description')->where('description', '!=', '')->count() / $totalProducts) * 100, 1)
                    : 0,
            ],
        ];
    }

    protected function getPlatformBreakdown($organization)
    {
        return [
            'ebay' => [
                'total_listings' => Product::where('organization_id', $organization->id)
                    ->where('status', 'active')
                    ->count(),
                'avg_price' => (float) (Product::where('organization_id', $organization->id)
                    ->where('status', 'active')
                    ->avg('selling_price') ?? 0),
                'status' => 'connected',
            ],
            'amazon' => [
                'total_listings' => 0,
                'avg_price' => 0,
                'status' => 'not_connected',
            ],
        ];
    }

    protected function getOptimizationSuggestions($organization)
    {
        $suggestions = [];

        // Check for incomplete items (missing description or photos)
        $incompleteCount = Product::where('organization_id', $organization->id)
            ->where(function ($query) {
                $query->whereNull('description')
                    ->orWhere('description', '')
                    ->orWhereDoesntHave('images');
            })
            ->count();

        if ($incompleteCount > 5) {
            $suggestions[] = [
                'type' => 'quality',
                'title' => 'Complete Product Information',
                'message' => "{$incompleteCount} products are missing descriptions or photos",
                'action' => 'Review incomplete items',
            ];
        }

        return $suggestions;
    }
}
