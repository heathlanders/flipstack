<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use App\Models\Catalog\ProductIntakeQueue;
use App\Models\Fulfillment\Order;
use App\Models\Fulfillment\ReturnRequest;
use App\Models\Fulfillment\Shipment;
use Illuminate\Http\Request;

class DashboardLiveDataController extends Controller
{
    /**
     * Get live data for specific dashboard type
     */
    public function getLiveData(Request $request, $dashboardType)
    {
        $user = $request->user();
        $organization = $user->organization;

        switch ($dashboardType) {
            case 'owner':
                return $this->getOwnerLiveData($organization);
            case 'buyer':
                return $this->getBuyerLiveData($organization);
            case 'lister':
                return $this->getListerLiveData($organization, $user);
            case 'fulfillment':
                return $this->getFulfillmentLiveData($organization);
            default:
                return response()->json(['error' => 'Invalid dashboard type'], 400);
        }
    }

    /**
     * Owner Dashboard Live Data
     */
    private function getOwnerLiveData($organization)
    {
        // Critical business metrics
        $pendingIntake = ProductIntakeQueue::whereHas('team', function ($query) use ($organization) {
            $query->where('organization_id', $organization->id);
        })
            ->where('processing_status', 'pending')
            ->count();

        $lowStockCount = Product::where('organization_id', $organization->id)
            ->where('manage_stock', true)
            ->where('stock_quantity', '<=', 5)
            ->count();

        $totalProducts = Product::where('organization_id', $organization->id)->count();

        // Recent activity (last 10 items)
        $recentActivity = $this->getRecentActivity($organization, 'owner');

        return response()->json([
            'businessMetrics' => [
                'pendingOrders' => Order::where('organization_id', $organization->id)
                    ->where('status', Order::STATUS_PENDING)->count(),
                'lowStockAlerts' => $lowStockCount,
                'activeListings' => Product::where('organization_id', $organization->id)
                    ->where('status', 'active')
                    ->count(),
                'pendingIntake' => $pendingIntake,
                'totalProducts' => $totalProducts,
            ],
            'alerts' => [
                'critical' => $lowStockCount > 10 ? ['Low stock critical level reached'] : [],
                'warning' => $pendingIntake > 20 ? ['Large intake queue backlog'] : [],
                'info' => [],
            ],
            'platformHealth' => [
                'ebay' => ['status' => 'connected', 'health' => 'good', 'last_sync' => now()->subMinutes(5)],
                'amazon' => ['status' => 'disconnected', 'health' => 'warning', 'last_sync' => null],
                'mercari' => ['status' => 'connected', 'health' => 'good', 'last_sync' => now()->subMinutes(3)],
            ],
            'recentActivity' => $recentActivity,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Buyer Dashboard Live Data
     */
    private function getBuyerLiveData($organization)
    {
        // ROI opportunities and sourcing metrics
        $highROIProducts = Product::where('organization_id', $organization->id)
            ->whereNotNull('selling_price')
            ->whereNotNull('cost_price')
            ->whereRaw('selling_price > cost_price * 1.5') // 50%+ margin
            ->count();

        return response()->json([
            'businessMetrics' => [
                'highROIOpportunities' => $highROIProducts,
                'averageROI' => $this->calculateAverageROI($organization),
                'suppliersActive' => Product::where('organization_id', $organization->id)
                    ->distinct('brand')
                    ->count('brand'),
                'sourcingAlerts' => 0, // Implement based on your sourcing logic
            ],
            'alerts' => [
                'critical' => [],
                'warning' => $highROIProducts < 10 ? ['Low ROI opportunities available'] : [],
                'info' => [],
            ],
            'recentActivity' => $this->getRecentActivity($organization, 'buyer'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Lister Dashboard Live Data
     */
    private function getListerLiveData($organization, $user)
    {
        // Intake queue and listing status
        $myPendingItems = ProductIntakeQueue::whereHas('team', function ($query) use ($organization) {
            $query->where('organization_id', $organization->id);
        })
            ->where('processing_status', 'pending')
            ->count();

        $readyForListing = Product::where('organization_id', $organization->id)
            ->where('ready_for_listing', true)
            ->count();

        $completedToday = ProductIntakeQueue::whereHas('team', function ($query) use ($organization) {
            $query->where('organization_id', $organization->id);
        })
            ->where('processing_status', 'completed')
            ->whereDate('processed_at', today())
            ->count();

        return response()->json([
            'businessMetrics' => [
                'pendingItems' => $myPendingItems,
                'readyForListing' => $readyForListing,
                'completedToday' => $completedToday,
                'averageProcessingTime' => '12 minutes', // Calculate based on your data
            ],
            'alerts' => [
                'critical' => $myPendingItems > 50 ? ['High intake queue backlog'] : [],
                'warning' => $readyForListing > 100 ? ['Many products ready for listing'] : [],
                'info' => [],
            ],
            'queueStats' => [
                'pending' => $myPendingItems,
                'in_progress' => ProductIntakeQueue::whereHas('team', function ($query) use ($organization) {
                    $query->where('organization_id', $organization->id);
                })
                    ->where('processing_status', 'in_progress')
                    ->count(),
                'completed_today' => $completedToday,
            ],
            'recentActivity' => $this->getRecentActivity($organization, 'lister'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Fulfillment Dashboard Live Data
     */
    private function getFulfillmentLiveData($organization)
    {
        $pendingOrders = Order::where('organization_id', $organization->id)
            ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_ASSIGNED])->count();
        $pickingQueue = Order::where('organization_id', $organization->id)
            ->whereIn('status', [Order::STATUS_ASSIGNED, Order::STATUS_PICKING])->count();
        $packingQueue = Order::where('organization_id', $organization->id)
            ->whereIn('status', [Order::STATUS_PICKED, Order::STATUS_PACKING])->count();
        $shippedToday = Shipment::whereDate('shipped_at', today())
            ->whereHas('order', fn ($q) => $q->where('organization_id', $organization->id))->count();
        $returnsProcessing = ReturnRequest::whereIn('status', [
            ReturnRequest::STATUS_OPEN, ReturnRequest::STATUS_APPROVED,
            ReturnRequest::STATUS_SHIPPED_BACK, ReturnRequest::STATUS_RECEIVED,
        ])->whereHas('order', fn ($q) => $q->where('organization_id', $organization->id))->count();
        $overdueOrders = Order::where('organization_id', $organization->id)->overdue()->count();

        $alerts = ['critical' => [], 'warning' => [], 'info' => []];
        if ($overdueOrders > 0) {
            $alerts['critical'][] = "$overdueOrders orders are overdue for shipping";
        }
        if ($packingQueue > 10) {
            $alerts['warning'][] = "$packingQueue orders waiting to be packed";
        }

        return response()->json([
            'businessMetrics' => [
                'pendingOrders' => $pendingOrders,
                'pickingQueue' => $pickingQueue,
                'packingQueue' => $packingQueue,
                'shippedToday' => $shippedToday,
                'returnsProcessing' => $returnsProcessing,
                'overdueOrders' => $overdueOrders,
            ],
            'alerts' => $alerts,
            'shippingStats' => [
                'ready_to_ship' => Shipment::whereIn('status', [Shipment::STATUS_LABEL_PURCHASED, Shipment::STATUS_PACKED])
                    ->whereHas('order', fn ($q) => $q->where('organization_id', $organization->id))->count(),
                'in_transit' => Shipment::where('status', Shipment::STATUS_IN_TRANSIT)
                    ->whereHas('order', fn ($q) => $q->where('organization_id', $organization->id))->count(),
                'delivered_today' => Shipment::whereDate('actual_delivery_date', today())
                    ->whereHas('order', fn ($q) => $q->where('organization_id', $organization->id))->count(),
            ],
            'recentActivity' => $this->getRecentActivity($organization, 'fulfillment'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Calculate average ROI for buyer dashboard
     */
    private function calculateAverageROI($organization)
    {
        $products = Product::where('organization_id', $organization->id)
            ->whereNotNull('selling_price')
            ->whereNotNull('cost_price')
            ->where('cost_price', '>', 0)
            ->get();

        if ($products->isEmpty()) {
            return 0;
        }

        $totalROI = $products->sum(function ($product) {
            return (($product->selling_price - $product->cost_price) / $product->cost_price) * 100;
        });

        return round($totalROI / $products->count(), 1);
    }

    /**
     * Get recent activity based on role
     */
    private function getRecentActivity($organization, $role)
    {
        $activities = [];
        $now = now();

        switch ($role) {
            case 'owner':
                // Mix of all activities for executive view
                $activities = [
                    [
                        'type' => 'inventory',
                        'title' => 'Low Stock Alert',
                        'description' => 'Nike Air Max running low (3 units)',
                        'user' => 'System',
                        'timestamp' => $now->subMinutes(5)->diffForHumans(),
                    ],
                    [
                        'type' => 'team',
                        'title' => 'Team Performance',
                        'description' => 'Listing team completed 47 items today',
                        'user' => 'System',
                        'timestamp' => $now->subHour()->diffForHumans(),
                    ],
                ];
                break;

            case 'buyer':
                $activities = [
                    [
                        'type' => 'inventory',
                        'title' => 'High ROI Product Added',
                        'description' => 'Canon Camera - 85% margin potential',
                        'user' => 'John Buyer',
                        'timestamp' => $now->subMinutes(15)->diffForHumans(),
                    ],
                ];
                break;

            case 'lister':
                $activities = [
                    [
                        'type' => 'intake',
                        'title' => 'Items Added to Queue',
                        'description' => '12 new products ready for processing',
                        'user' => 'Intake Team',
                        'timestamp' => $now->subMinutes(30)->diffForHumans(),
                    ],
                ];
                break;

            case 'fulfillment':
                $activities = [
                    [
                        'type' => 'platform',
                        'title' => 'Shipping Label Generated',
                        'description' => 'Order #12345 ready for pickup',
                        'user' => 'Fulfillment',
                        'timestamp' => $now->subMinutes(45)->diffForHumans(),
                    ],
                ];
                break;
        }

        return array_slice($activities, 0, 5); // Return max 5 activities
    }

    /**
     * Get alerts for specific dashboard type
     */
    public function getAlerts(Request $request, $dashboardType)
    {
        $user = $request->user();
        $organization = $user->organization;

        $pendingIntake = ProductIntakeQueue::whereHas('team', function ($query) use ($organization) {
            $query->where('organization_id', $organization->id);
        })
            ->where('processing_status', 'pending')
            ->count();

        $lowStockCount = Product::where('organization_id', $organization->id)
            ->where('manage_stock', true)
            ->where('stock_quantity', '<=', 5)
            ->count();

        $alerts = [
            'critical' => [],
            'warning' => [],
            'info' => [],
        ];

        switch ($dashboardType) {
            case 'owner':
                if ($lowStockCount > 10) {
                    $alerts['critical'][] = 'Low stock critical level reached';
                }
                if ($pendingIntake > 20) {
                    $alerts['warning'][] = 'Large intake queue backlog';
                }
                break;

            case 'buyer':
                $highROIProducts = Product::where('organization_id', $organization->id)
                    ->whereNotNull('selling_price')
                    ->whereNotNull('cost_price')
                    ->whereRaw('selling_price > cost_price * 1.5')
                    ->count();
                if ($highROIProducts < 10) {
                    $alerts['warning'][] = 'Low ROI opportunities available';
                }
                break;

            case 'lister':
                if ($pendingIntake > 50) {
                    $alerts['critical'][] = 'High intake queue backlog';
                }
                $readyForListing = Product::where('organization_id', $organization->id)
                    ->where('ready_for_listing', true)
                    ->count();
                if ($readyForListing > 100) {
                    $alerts['warning'][] = 'Many products ready for listing';
                }
                break;

            case 'fulfillment':
                $overdueCount = Order::where('organization_id', $organization->id)->overdue()->count();
                if ($overdueCount > 0) {
                    $alerts['critical'][] = "$overdueCount orders are overdue";
                }
                break;
        }

        return response()->json([
            'alerts' => $alerts,
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get platform health status
     */
    public function getPlatformHealth(Request $request)
    {
        return response()->json([
            'platformHealth' => [
                'ebay' => ['status' => 'connected', 'health' => 'good', 'last_sync' => now()->subMinutes(5)],
                'amazon' => ['status' => 'disconnected', 'health' => 'warning', 'last_sync' => null],
                'mercari' => ['status' => 'connected', 'health' => 'good', 'last_sync' => now()->subMinutes(3)],
            ],
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Get business metrics for specific dashboard type
     */
    public function getBusinessMetrics(Request $request, $dashboardType)
    {
        $user = $request->user();
        $organization = $user->organization;

        $businessMetrics = [];

        switch ($dashboardType) {
            case 'owner':
                $pendingIntake = ProductIntakeQueue::whereHas('team', function ($query) use ($organization) {
                    $query->where('organization_id', $organization->id);
                })
                    ->where('processing_status', 'pending')
                    ->count();

                $lowStockCount = Product::where('organization_id', $organization->id)
                    ->where('manage_stock', true)
                    ->where('stock_quantity', '<=', 5)
                    ->count();

                $businessMetrics = [
                    'pendingOrders' => Order::where('organization_id', $organization->id)
                        ->where('status', Order::STATUS_PENDING)->count(),
                    'lowStockAlerts' => $lowStockCount,
                    'activeListings' => Product::where('organization_id', $organization->id)
                        ->where('status', 'active')
                        ->count(),
                    'pendingIntake' => $pendingIntake,
                    'totalProducts' => Product::where('organization_id', $organization->id)->count(),
                ];
                break;

            case 'buyer':
                $highROIProducts = Product::where('organization_id', $organization->id)
                    ->whereNotNull('selling_price')
                    ->whereNotNull('cost_price')
                    ->whereRaw('selling_price > cost_price * 1.5')
                    ->count();

                $businessMetrics = [
                    'highROIOpportunities' => $highROIProducts,
                    'averageROI' => $this->calculateAverageROI($organization),
                    'suppliersActive' => Product::where('organization_id', $organization->id)
                        ->distinct('brand')
                        ->count('brand'),
                    'sourcingAlerts' => 0,
                ];
                break;

            case 'lister':
                $pendingItems = ProductIntakeQueue::whereHas('team', function ($query) use ($organization) {
                    $query->where('organization_id', $organization->id);
                })
                    ->where('processing_status', 'pending')
                    ->count();

                $businessMetrics = [
                    'pendingItems' => $pendingItems,
                    'readyForListing' => Product::where('organization_id', $organization->id)
                        ->where('ready_for_listing', true)
                        ->count(),
                    'completedToday' => ProductIntakeQueue::whereHas('team', function ($query) use ($organization) {
                        $query->where('organization_id', $organization->id);
                    })
                        ->where('processing_status', 'completed')
                        ->whereDate('processed_at', today())
                        ->count(),
                    'averageProcessingTime' => '12 minutes',
                ];
                break;

            case 'fulfillment':
                $businessMetrics = [
                    'pendingOrders' => Order::where('organization_id', $organization->id)
                        ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_ASSIGNED])->count(),
                    'pickingQueue' => Order::where('organization_id', $organization->id)
                        ->whereIn('status', [Order::STATUS_ASSIGNED, Order::STATUS_PICKING])->count(),
                    'packingQueue' => Order::where('organization_id', $organization->id)
                        ->whereIn('status', [Order::STATUS_PICKED, Order::STATUS_PACKING])->count(),
                    'shippedToday' => Shipment::whereDate('shipped_at', today())
                        ->whereHas('order', fn ($q) => $q->where('organization_id', $organization->id))->count(),
                    'returnsProcessing' => ReturnRequest::whereIn('status', [
                        ReturnRequest::STATUS_OPEN, ReturnRequest::STATUS_APPROVED,
                        ReturnRequest::STATUS_SHIPPED_BACK, ReturnRequest::STATUS_RECEIVED,
                    ])->whereHas('order', fn ($q) => $q->where('organization_id', $organization->id))->count(),
                ];
                break;

            default:
                return response()->json(['error' => 'Invalid dashboard type'], 400);
        }

        return response()->json([
            'businessMetrics' => $businessMetrics,
            'timestamp' => now()->toISOString(),
        ]);
    }
}
