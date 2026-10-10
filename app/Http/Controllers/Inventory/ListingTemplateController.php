<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Listing\ListingTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ListingTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $organizationId = $user->organization_id ?? $user->current_organization_id ?? $user->currentTeam?->id ?? 1;

        $query = ListingTemplate::forOrganization($organizationId)
            ->with('creator:id,name')
            ->orderBy('times_used', 'desc')
            ->orderBy('name');

        if ($platform = $request->get('platform')) {
            $query->where('platform', $platform);
        }

        if ($search = $request->get('search')) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        $templates = $query->paginate(20)->withQueryString();

        return Inertia::render('Inventory/Templates/Index', [
            'templates' => $templates,
            'filters' => $request->only(['platform', 'search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Inventory/Templates/Create');
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $organizationId = $user->organization_id ?? $user->current_organization_id ?? $user->currentTeam?->id ?? 1;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'platform' => 'required|string|in:ebay,amazon,all',
            'description_template' => 'nullable|string',
            'default_item_specifics' => 'nullable|array',
            'default_categories' => 'nullable|array',
            'shipping_template' => 'nullable|array',
            'return_policy_template' => 'nullable|array',
            'payment_template' => 'nullable|array',
            'pricing_strategy' => 'nullable|string|in:fixed_markup,percentage,competitive',
            'markup_percentage' => 'nullable|numeric|min:0|max:999',
            'fixed_markup_amount' => 'nullable|numeric|min:0',
        ]);

        $template = ListingTemplate::create(array_merge($validated, [
            'organization_id' => $organizationId,
            'created_by' => $user->id,
        ]));

        return redirect()
            ->route('inventory.templates.index')
            ->with('success', 'Template created successfully.');
    }

    public function show(ListingTemplate $template): Response
    {
        $template->load('creator:id,name');
        $template->loadCount('productListings');

        return Inertia::render('Inventory/Templates/Show', [
            'template' => $template,
        ]);
    }

    public function edit(ListingTemplate $template): Response
    {
        return Inertia::render('Inventory/Templates/Edit', [
            'template' => $template,
        ]);
    }

    public function update(Request $request, ListingTemplate $template)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'platform' => 'required|string|in:ebay,amazon,all',
            'description_template' => 'nullable|string',
            'default_item_specifics' => 'nullable|array',
            'default_categories' => 'nullable|array',
            'shipping_template' => 'nullable|array',
            'return_policy_template' => 'nullable|array',
            'payment_template' => 'nullable|array',
            'pricing_strategy' => 'nullable|string|in:fixed_markup,percentage,competitive',
            'markup_percentage' => 'nullable|numeric|min:0|max:999',
            'fixed_markup_amount' => 'nullable|numeric|min:0',
        ]);

        $template->update($validated);

        return redirect()
            ->route('inventory.templates.index')
            ->with('success', 'Template updated successfully.');
    }

    public function destroy(ListingTemplate $template)
    {
        $template->delete();

        return redirect()
            ->route('inventory.templates.index')
            ->with('success', 'Template deleted successfully.');
    }
}
