<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use App\Models\Inventory\PartOutTemplate;
use App\Models\Inventory\PartOutWorkOrder;
use App\Models\Inventory\PartOutWorkOrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PartOutDashboardController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $organizationId = $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;

        // Template stats
        $templateTotal = PartOutTemplate::withoutGlobalScopes()
            ->where('organization_id', $organizationId)->count();
        $templateActive = PartOutTemplate::withoutGlobalScopes()
            ->where('organization_id', $organizationId)->where('is_active', true)->count();

        // Work order stats
        $woStats = PartOutWorkOrder::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->select(
                DB::raw('COUNT(*) as total'),
                DB::raw("COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft"),
                DB::raw("COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress"),
                DB::raw("COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed"),
                DB::raw("COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled")
            )
            ->first();

        // Components created (products with parted_from_product_id)
        $componentsCreated = Product::where('organization_id', $organizationId)
            ->whereNotNull('parted_from_product_id')
            ->count();

        // ROI calculation from completed work orders
        $completedWOs = PartOutWorkOrder::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('status', 'completed')
            ->get();

        $totalParentCost = $completedWOs->sum('source_total_cost');
        $totalComponentValue = PartOutWorkOrderItem::withoutGlobalScopes()
            ->whereIn('work_order_id', $completedWOs->pluck('id'))
            ->whereNotNull('component_product_id')
            ->sum(DB::raw('suggested_selling_price * quantity'));

        $roiPercent = $totalParentCost > 0
            ? round((($totalComponentValue - $totalParentCost) / $totalParentCost) * 100, 1)
            : 0;

        // Recent work orders
        $recentWorkOrders = PartOutWorkOrder::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->with([
                'sourceProduct:id,name,sku',
                'template:id,name',
                'assignedTo:id,name',
            ])
            ->withCount('items')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(function ($wo) {
                $progress = $wo->getExtractionProgress();

                return [
                    'id' => $wo->id,
                    'work_order_number' => $wo->work_order_number,
                    'status' => $wo->status,
                    'source_product' => $wo->sourceProduct ? [
                        'id' => $wo->sourceProduct->id,
                        'name' => $wo->sourceProduct->name,
                        'sku' => $wo->sourceProduct->sku,
                    ] : null,
                    'template_name' => $wo->template?->name,
                    'assigned_to' => $wo->assignedTo?->name,
                    'items_count' => $wo->items_count,
                    'source_total_cost' => $wo->source_total_cost,
                    'extraction_progress' => $progress,
                    'parent_continues' => $wo->parent_continues,
                    'created_at' => $wo->created_at->toIso8601String(),
                    'completed_at' => $wo->completed_at?->toIso8601String(),
                ];
            });

        // Top templates by usage
        $topTemplates = PartOutTemplate::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->withCount('items')
            ->orderByDesc('times_used')
            ->limit(5)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'times_used' => $t->times_used,
                'items_count' => $t->items_count,
                'cost_allocation_method' => $t->cost_allocation_method,
            ]);

        // Recently created components (parted-out products)
        $recentComponents = Product::where('organization_id', $organizationId)
            ->whereNotNull('parted_from_product_id')
            ->with('partedFromProduct:id,name,sku')
            ->select('id', 'name', 'sku', 'cost_price', 'selling_price', 'available_quantity', 'parted_from_product_id', 'created_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'cost_price' => $p->cost_price,
                'selling_price' => $p->selling_price,
                'available_quantity' => $p->available_quantity,
                'parent_name' => $p->partedFromProduct?->name,
                'parent_sku' => $p->partedFromProduct?->sku,
                'parent_id' => $p->parted_from_product_id,
                'created_at' => $p->created_at->toIso8601String(),
            ]);

        // Products with component_breakdown (candidates for part-out)
        $productsWithComponents = Product::where('organization_id', $organizationId)
            ->whereNotNull('component_breakdown')
            ->where('available_quantity', '>', 0)
            ->count();

        return Inertia::render('Inventory/PartOut/Index', [
            'stats' => [
                'templates_total' => $templateTotal,
                'templates_active' => $templateActive,
                'work_orders_total' => (int) $woStats->total,
                'work_orders_draft' => (int) $woStats->draft,
                'work_orders_in_progress' => (int) $woStats->in_progress,
                'work_orders_completed' => (int) $woStats->completed,
                'work_orders_cancelled' => (int) $woStats->cancelled,
                'components_created' => $componentsCreated,
                'total_parent_cost' => round((float) $totalParentCost, 2),
                'total_component_value' => round((float) $totalComponentValue, 2),
                'roi_percent' => $roiPercent,
                'products_with_components' => $productsWithComponents,
            ],
            'recentWorkOrders' => $recentWorkOrders,
            'topTemplates' => $topTemplates,
            'recentComponents' => $recentComponents,
        ]);
    }
}
