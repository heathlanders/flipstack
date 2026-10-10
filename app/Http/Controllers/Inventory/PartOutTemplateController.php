<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Product;
use App\Models\Inventory\PartOutTemplate;
use App\Services\Inventory\PartOutService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartOutTemplateController extends Controller
{
    public function __construct(
        protected PartOutService $partOutService
    ) {
        $this->middleware(['auth']);
    }

    private function getOrganizationId(Request $request): int
    {
        $user = $request->user();

        return $user->currentTeam?->organization_id ?? $user->current_organization_id ?? $user->organization_id;
    }

    public function index(Request $request): Response
    {
        $organizationId = $this->getOrganizationId($request);

        $query = PartOutTemplate::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->with(['sourceProduct:id,name,sku'])
            ->withCount(['items', 'workOrders']);

        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhereHas('sourceProduct', fn ($pq) => $pq->where('name', 'ILIKE', "%{$search}%"));
            });
        }

        if ($request->filled('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $templates = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $stats = [
            'total' => PartOutTemplate::withoutGlobalScopes()->where('organization_id', $organizationId)->count(),
            'active' => PartOutTemplate::withoutGlobalScopes()->where('organization_id', $organizationId)->where('is_active', true)->count(),
        ];

        return Inertia::render('Inventory/PartOut/Templates/Index', [
            'templates' => $templates,
            'stats' => $stats,
            'filters' => $request->only('search', 'active'),
        ]);
    }

    public function create(Request $request): Response
    {
        $organizationId = $this->getOrganizationId($request);

        $products = Product::where('organization_id', $organizationId)
            ->select('id', 'name', 'sku', 'cost_price', 'selling_price', 'available_quantity', 'component_breakdown')
            ->orderBy('name')
            ->limit(500)
            ->get();

        $sourceProduct = null;
        if ($request->filled('product_id')) {
            $sourceProduct = Product::where('organization_id', $organizationId)
                ->where('id', $request->get('product_id'))
                ->select('id', 'name', 'sku', 'cost_price', 'selling_price', 'available_quantity', 'component_breakdown')
                ->first();
        }

        return Inertia::render('Inventory/PartOut/Templates/Create', [
            'products' => $products,
            'sourceProduct' => $sourceProduct,
        ]);
    }

    public function store(Request $request)
    {
        $organizationId = $this->getOrganizationId($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'source_product_id' => 'nullable|uuid|exists:products,id',
            'cost_allocation_method' => 'required|string|in:market_value,percentage,even',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string|max:255',
            'items.*.expected_quantity' => 'required|integer|min:1',
            'items.*.cost_allocation_pct' => 'nullable|numeric|min:0|max:100',
            'items.*.is_optional' => 'boolean',
            'items.*.default_condition' => 'nullable|string|max:30',
            'items.*.part_number' => 'nullable|string|max:100',
            'items.*.manufacturer' => 'nullable|string|max:100',
            'items.*.category' => 'nullable|string|max:50',
            'items.*.component_product_id' => 'nullable|uuid|exists:products,id',
            'items.*.suggested_selling_price' => 'nullable|numeric|min:0',
        ]);

        $validated['created_by'] = $request->user()->id;
        $template = $this->partOutService->createTemplate($validated, $organizationId);

        return redirect()->route('inventory.part-out.templates.show', $template)
            ->with('success', 'Part-out template created.');
    }

    public function show(PartOutTemplate $template): Response
    {
        $template->load([
            'sourceProduct:id,name,sku,cost_price,selling_price,available_quantity',
            'items.componentProduct:id,name,sku,available_quantity,cost_price,selling_price',
            'creator:id,name',
        ]);

        $recentWorkOrders = $template->workOrders()
            ->with('sourceProduct:id,name,sku')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return Inertia::render('Inventory/PartOut/Templates/Show', [
            'template' => $template,
            'recentWorkOrders' => $recentWorkOrders,
        ]);
    }

    public function edit(PartOutTemplate $template): Response
    {
        $template->load(['items.componentProduct:id,name,sku,available_quantity,cost_price,selling_price']);

        $organizationId = $template->organization_id;

        $products = Product::where('organization_id', $organizationId)
            ->select('id', 'name', 'sku', 'cost_price', 'selling_price', 'available_quantity', 'component_breakdown')
            ->orderBy('name')
            ->limit(500)
            ->get();

        return Inertia::render('Inventory/PartOut/Templates/Edit', [
            'template' => $template,
            'products' => $products,
        ]);
    }

    public function update(Request $request, PartOutTemplate $template)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'source_product_id' => 'nullable|uuid|exists:products,id',
            'cost_allocation_method' => 'required|string|in:market_value,percentage,even',
            'is_active' => 'boolean',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string|max:255',
            'items.*.expected_quantity' => 'required|integer|min:1',
            'items.*.cost_allocation_pct' => 'nullable|numeric|min:0|max:100',
            'items.*.is_optional' => 'boolean',
            'items.*.default_condition' => 'nullable|string|max:30',
            'items.*.part_number' => 'nullable|string|max:100',
            'items.*.manufacturer' => 'nullable|string|max:100',
            'items.*.category' => 'nullable|string|max:50',
            'items.*.component_product_id' => 'nullable|uuid|exists:products,id',
            'items.*.suggested_selling_price' => 'nullable|numeric|min:0',
        ]);

        $this->partOutService->updateTemplate($template, $validated);

        return redirect()->route('inventory.part-out.templates.show', $template)
            ->with('success', 'Template updated.');
    }

    public function destroy(PartOutTemplate $template)
    {
        $template->items()->delete();
        $template->delete();

        return redirect()->route('inventory.part-out.templates.index')
            ->with('success', 'Template deleted.');
    }

    public function duplicate(PartOutTemplate $template)
    {
        $template->load('items');

        $newTemplate = $template->replicate(['times_used']);
        $newTemplate->name = $template->name.' (Copy)';
        $newTemplate->times_used = 0;
        $newTemplate->save();

        foreach ($template->items as $item) {
            $newItem = $item->replicate();
            $newItem->template_id = $newTemplate->id;
            $newItem->save();
        }

        return redirect()->route('inventory.part-out.templates.show', $newTemplate)
            ->with('success', 'Template duplicated.');
    }

    public function createFromProduct(Request $request, Product $product)
    {
        $organizationId = $this->getOrganizationId($request);

        if (empty($product->component_breakdown)) {
            return redirect()->back()
                ->with('error', 'Product has no component breakdown data from AI analysis.');
        }

        $template = $this->partOutService->createTemplateFromProduct($product, $organizationId, $request->user()->id);

        return redirect()->route('inventory.part-out.templates.show', $template)
            ->with('success', 'Template created from product component data.');
    }
}
