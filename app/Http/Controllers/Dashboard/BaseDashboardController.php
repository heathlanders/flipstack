<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\User\User;

class BaseDashboardController extends Controller
{
    protected function getRecentActivity(User $user, string $dashboardType): array
    {
        // This will integrate with your existing systems
        $activities = [];

        // Get recent intake queue activity
        if ($user->hasPermission('intake.view', $user->organization_id)) {
            $recentIntake = \App\Models\Catalog\ProductIntakeQueue::whereHas('team', function ($query) use ($user) {
                $query->where('organization_id', $user->organization_id);
            })
                ->latest()
                ->limit(5)
                ->get()
                ->map(fn ($item) => [
                    'type' => 'intake',
                    'title' => 'New item added to intake queue',
                    'description' => $item->product_number ?? $item->manufacturer_part_number ?? 'Product intake',
                    'timestamp' => $item->created_at,
                    'user' => $item->capturedBy->name ?? 'System',
                    'url' => route('intake-queue.show', $item),
                ]);

            $activities = array_merge($activities, $recentIntake->toArray());
        }

        // Get recent inventory changes based on role
        if ($user->can('inventory.view')) {
            // This would connect to your existing ProductController data
            $recentProducts = \App\Models\Catalog\Product::where('organization_id', $user->organization_id)
                ->latest()
                ->limit(3)
                ->get()
                ->map(fn ($product) => [
                    'type' => 'inventory',
                    'title' => 'Product updated',
                    'description' => $product->name,
                    'timestamp' => $product->updated_at,
                    'user' => 'System',
                    'url' => route('inventory.products.show', $product),
                ]);

            $activities = array_merge($activities, $recentProducts->toArray());
        }

        // Sort by timestamp and return latest 10
        usort($activities, fn ($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return array_slice($activities, 0, 10);
    }

    protected function getAlerts(User $user): array
    {
        $alerts = [];
        $organization = $user->organization;

        // Low stock alerts (using your existing inventory system)
        if ($user->can('inventory.view')) {
            $lowStockCount = \App\Models\Catalog\Product::where('organization_id', $organization->id)
                ->where('quantity', '<=', 5) // or use your low stock threshold
                ->count();

            if ($lowStockCount > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'title' => 'Low Stock Alert',
                    'message' => "{$lowStockCount} items are running low on stock",
                    'action' => route('inventory.operations.low-stock'),
                    'action_label' => 'View Items',
                ];
            }
        }

        // Pending orders alert (if fulfillment access)
        if ($user->can('orders.view')) {
            // This would integrate with your order system when you build it
            $pendingOrders = 0; // Placeholder for now

            if ($pendingOrders > 0) {
                $alerts[] = [
                    'type' => 'info',
                    'title' => 'Pending Orders',
                    'message' => "{$pendingOrders} orders need processing",
                    'action' => route('orders.index'),
                    'action_label' => 'Process Orders',
                ];
            }
        }

        return $alerts;
    }

    protected function formatCurrency($amount): string
    {
        return '$'.number_format($amount, 2);
    }

    protected function calculatePercentageChange($current, $previous): float
    {
        if ($previous == 0) {
            return 0;
        }

        return (($current - $previous) / $previous) * 100;
    }
}
